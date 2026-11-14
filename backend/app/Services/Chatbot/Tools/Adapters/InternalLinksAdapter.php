<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\Article;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\InternalLinkingEngine;
use InvalidArgumentException;

class InternalLinksAdapter implements ChatToolInterface
{
    public function __construct(
        protected InternalLinkingEngine $internalLinkingEngine
    ) {}

    public function getName(): string
    {
        return 'internal_links';
    }

    public function getTitle(): string
    {
        return 'Đồ thị Liên kết Nội bộ (Internal Linking)';
    }

    public function getDescription(): string
    {
        return 'Quét kho bài viết của website và tự động gợi ý neo liên kết theo ngữ cảnh thực thể liên quan.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'ID bài viết nguồn cần tìm liên kết nội bộ hướng tới các bài khác.',
                ],
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID Website cần quét mạng lưới liên kết nội bộ toàn diện.',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $articleId = ! empty($params['article_id']) ? (int) $params['article_id'] : null;

        if ($articleId) {
            /** @var Article $article */
            $article = Article::where('workspace_id', $workspace->id)->findOrFail($articleId);
            $suggestions = $this->internalLinkingEngine->scanArticle($article);

            return [
                'tool' => $this->getName(),
                'mode' => 'single_article',
                'source_article_id' => $article->id,
                'source_article_title' => $article->title,
                'total_suggestions' => count($suggestions),
                'suggestions' => array_map(function ($s) {
                    return is_array($s) ? $s : [
                        'id' => $s->id ?? null,
                        'suggested_anchor_text' => $s->suggested_anchor_text ?? '',
                        'context_sentence' => $s->context_sentence ?? '',
                        'relevance_score' => $s->relevance_score ?? 80,
                        'target_article_id' => $s->target_article_id ?? null,
                    ];
                }, $suggestions),
                'credits_deducted' => 0,
            ];
        }

        $websiteId = ! empty($params['website_id']) ? (int) $params['website_id'] : null;
        $website = null;
        if ($websiteId) {
            $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
        }
        if (! $website) {
            $website = Website::where('workspace_id', $workspace->id)->first();
        }

        if (! $website) {
            throw new InvalidArgumentException('Workspace chưa có website nào để phân tích Internal Links.');
        }

        $result = $this->internalLinkingEngine->scanWebsite($website->id, $workspace->id);

        return [
            'tool' => $this->getName(),
            'mode' => 'website_scan',
            'website_id' => $website->id,
            'website_name' => $website->name,
            'total_articles' => $result['total_articles'] ?? 0,
            'total_suggestions' => $result['suggestions_found'] ?? count($result['suggestions'] ?? []),
            'suggestions' => array_slice($result['suggestions'] ?? [], 0, 15),
            'credits_deducted' => 0,
        ];
    }
}
