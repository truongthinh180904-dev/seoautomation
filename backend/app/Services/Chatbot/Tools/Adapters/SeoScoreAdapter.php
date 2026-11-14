<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\Article;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\SeoScoringEngine;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SeoScoreAdapter implements ChatToolInterface
{
    public function __construct(
        protected SeoScoringEngine $scoringEngine
    ) {}

    public function getName(): string
    {
        return 'seo_score';
    }

    public function getTitle(): string
    {
        return 'Chấm điểm SEO 100 Điểm (SeoScoringEngine)';
    }

    public function getDescription(): string
    {
        return 'Đánh giá chất lượng toàn diện bài viết theo thang điểm 100 qua 14 tiêu chí kỹ thuật SEO thuần PHP (tức thì, 0 credit).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0; // Thuần PHP toán học, 0 credit
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
                    'description' => 'ID bài viết trong hệ thống cần chấm điểm SEO.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Nội dung văn bản thô hoặc HTML cần đánh giá nếu không truyền article_id.',
                ],
                'keyword' => [
                    'type' => 'string',
                    'description' => 'Từ khóa SEO trọng tâm để chấm điểm mật độ và vị trí xuất hiện.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Tiêu đề bài viết cần đối chiếu thẻ H1.',
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
            $audit = $this->scoringEngine->auditArticle($article, $params, true);

            return [
                'tool' => $this->getName(),
                'article_id' => $article->id,
                'title' => $article->title,
                'focus_keyword' => $article->focus_keyword,
                'total_score' => $audit['total_score'] ?? 0,
                'readability_score' => $audit['readability_score'] ?? 0,
                'passed' => ($audit['total_score'] ?? 0) >= 80,
                'criteria' => $audit['criteria'] ?? [],
                'recommendations' => $audit['recommendations'] ?? [],
                'credits_deducted' => 0,
            ];
        }

        $content = trim((string) ($params['content'] ?? ''));
        if ($content === '') {
            throw new InvalidArgumentException('Cần cung cấp article_id hoặc nội dung content để chấm điểm SEO.');
        }

        $keyword = trim((string) ($params['keyword'] ?? $params['focus_keyword'] ?? ''));
        $title = trim((string) ($params['title'] ?? 'Tiêu đề bài viết SEO'));

        $audit = $this->scoringEngine->audit(
            content: $content,
            title: $title,
            seoTitle: $title,
            seoDescription: Str::limit(strip_tags($content), 150),
            keyword: $keyword
        );

        return [
            'tool' => $this->getName(),
            'title' => $title,
            'focus_keyword' => $keyword,
            'total_score' => $audit['total_score'] ?? 0,
            'readability_score' => $audit['readability_score'] ?? 0,
            'passed' => ($audit['total_score'] ?? 0) >= 80,
            'criteria' => $audit['criteria'] ?? [],
            'recommendations' => $audit['recommendations'] ?? [],
            'credits_deducted' => 0,
        ];
    }
}
