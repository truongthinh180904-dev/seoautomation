<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Actions\Publishing\PushToWordPressAction;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use InvalidArgumentException;
use RuntimeException;

class WordPressPublishAdapter implements ChatToolInterface
{
    public function getName(): string
    {
        return 'wordpress_publish';
    }

    public function getTitle(): string
    {
        return 'Xuất bản Bài viết Lên WordPress';
    }

    public function getDescription(): string
    {
        return 'Đẩy bài viết và ảnh trực tiếp lên website WordPress mục tiêu qua REST API có xác thực (Hành động nhạy cảm xuất bản website thật).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0;
    }

    public function isSensitive(): bool
    {
        return true; // Bắt buộc phê duyệt Human-in-the-Loop
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['article_id'],
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'ID bài viết trong hệ thống cần xuất bản lên WordPress.',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'description' => 'ID của WordPress Site đã cấu hình kết nối (tùy chọn).',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish', 'pending'],
                    'default' => 'draft',
                    'description' => 'Trạng thái bài viết khi đăng lên WordPress (draft hoặc publish).',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $articleId = ! empty($params['article_id']) ? (int) $params['article_id'] : 0;
        if ($articleId <= 0) {
            throw new InvalidArgumentException('ID bài viết cần xuất bản không hợp lệ.');
        }

        /** @var Article $article */
        $article = Article::where('workspace_id', $workspace->id)->findOrFail($articleId);

        // Xác định site WordPress đích
        $siteId = ! empty($params['site_id']) ? (int) $params['site_id'] : null;
        $site = null;
        if ($siteId) {
            $site = WordPressSite::where('workspace_id', $workspace->id)->find($siteId);
        }

        if (! $site && $article->wordpress_site_id) {
            $site = WordPressSite::where('workspace_id', $workspace->id)->find($article->wordpress_site_id);
        }

        if (! $site && $article->website_id) {
            $site = WordPressSite::where('workspace_id', $workspace->id)
                ->where('website_id', $article->website_id)
                ->first();
        }

        if (! $site) {
            $site = WordPressSite::where('workspace_id', $workspace->id)->first();
        }

        if (! $site) {
            throw new RuntimeException('Chưa cấu hình tài khoản kết nối WordPress Site nào trong Workspace này.');
        }

        // Gán site id và duyệt trạng thái bài viết sang APPROVED để đủ điều kiện PushToWordPressAction
        $article->update([
            'wordpress_site_id' => $site->id,
            'status' => ArticleStatus::APPROVED,
        ]);

        // Đẩy tác vụ vào Queue xuất bản WordPress
        PushToWordPressAction::run($article);

        return [
            'tool' => $this->getName(),
            'article_id' => $article->id,
            'article_title' => $article->title,
            'wordpress_site_id' => $site->id,
            'site_name' => $site->name ?? $site->url ?? (string) ($site->site_url ?? ''),
            'site_url' => $site->url ?? (string) ($site->site_url ?? ''),
            'publish_status' => $params['status'] ?? 'draft',
            'action_status' => 'dispatched_to_queue',
            'is_sensitive' => true,
        ];
    }
}
