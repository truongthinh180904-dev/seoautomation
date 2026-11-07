<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\Article;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\Optimization\ContentRefreshService;
use InvalidArgumentException;

class ContentRefreshToolAdapter implements ChatToolInterface
{
    public function __construct(
        protected ContentRefreshService $contentRefreshService
    ) {}

    public function getName(): string
    {
        return 'content_refresh';
    }

    public function getTitle(): string
    {
        return 'Làm mới & Tối ưu Bài viết Cũ (AI Content Refresh)';
    }

    public function getDescription(): string
    {
        return 'Thu thập bối cảnh từ GSC, GA4, Rank Tracking để AI đề xuất làm mới bài viết cũ, tạo bản so sánh Diff Trước/Sau 2 cột, nâng điểm SEO và chuẩn bị phê duyệt xuất bản WordPress.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        $action = (string) ($params['action'] ?? 'plan');

        if ($action === 'plan') {
            return ContentRefreshService::PLAN_CREDIT_COST; // 2 credits theo quy định
        }

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
            'required' => ['article_id'],
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'ID bài viết trong hệ thống cần tối ưu và làm mới.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['plan', 'preview', 'apply', 'push_wordpress'],
                    'default' => 'plan',
                    'description' => 'Hành động: plan (sinh kế hoạch tối ưu 2 credits), preview (xem trước diff 0 credit), apply (áp dụng bản mới vào bài viết và lưu version), push_wordpress (cập nhật lên WordPress).',
                ],
                'insight_id' => [
                    'type' => 'integer',
                    'description' => 'ID cảnh báo bất thường liên quan (tùy chọn).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Xác nhận của người dùng (bắt buộc true khi push_wordpress).',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $articleId = ! empty($params['article_id']) ? (int) $params['article_id'] : 0;
        if ($articleId <= 0) {
            throw new InvalidArgumentException('Cần cung cấp article_id hợp lệ của bài viết cần tối ưu.');
        }

        // Đảm bảo cô lập Multi-Tenant 100%
        /** @var Article $article */
        $article = Article::where('workspace_id', $workspace->id)->findOrFail($articleId);

        $action = (string) ($params['action'] ?? 'plan');

        switch ($action) {
            case 'preview':
                $diff = $this->contentRefreshService->getDiffPreview($article, $workspace);

                return [
                    'tool' => $this->getName(),
                    'action' => 'preview',
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                    'diff' => $diff->toArray(),
                    'score_before' => $diff->scoreBefore,
                    'score_after' => $diff->scoreAfter,
                    'score_delta' => $diff->scoreDelta,
                    'summary_text' => sprintf(
                        'Bản xem trước Diff cho bài viết "%s": Điểm SEO dự kiến tăng từ %d lên %d (+%d điểm).',
                        $article->title,
                        $diff->scoreBefore,
                        $diff->scoreAfter,
                        $diff->scoreDelta
                    ),
                    'credits_deducted' => 0,
                ];

            case 'apply':
                $updatedArticle = $this->contentRefreshService->applyOptimization($article, $workspace, $params, $actor);

                return [
                    'tool' => $this->getName(),
                    'action' => 'apply',
                    'article_id' => $updatedArticle->id,
                    'article_title' => $updatedArticle->title,
                    'seo_score' => $updatedArticle->seo_score,
                    'word_count' => $updatedArticle->word_count,
                    'last_optimized_at' => $updatedArticle->last_optimized_at?->toIso8601String(),
                    'summary_text' => sprintf(
                        'Đã áp dụng bản làm mới nội dung vào bài viết "%s" và lưu trữ phiên bản lịch sử (HasVersions). Điểm SEO mới: %d/100.',
                        $updatedArticle->title,
                        $updatedArticle->seo_score
                    ),
                    'credits_deducted' => 0,
                ];

            case 'push_wordpress':
                $result = $this->contentRefreshService->pushUpdateToWordPress($article, $workspace, $params, $actor);

                return [
                    'tool' => $this->getName(),
                    'action' => 'push_wordpress',
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                    'wordpress_post_id' => $result['post_id'] ?? $article->wordpress_post_id,
                    'post_url' => $result['url'] ?? $article->wordpress_post_url,
                    'status' => 'published',
                    'summary_text' => sprintf(
                        'Đã cập nhật thành công bài viết "%s" lên website WordPress mục tiêu!',
                        $article->title
                    ),
                    'credits_deducted' => 0,
                ];

            case 'plan':
            default:
                $diff = $this->contentRefreshService->generateOptimizationPlan($article, $workspace, $params, $actor);

                $summaryText = sprintf(
                    'Đã khởi tạo kế hoạch làm mới nội dung bài viết "%s" thành công (tiêu hao 2 credits). Điểm SEO dự kiến tăng từ %d lên %d (+%d điểm), bổ sung %d từ và %d đề mục H2/H3 mới.',
                    $article->title,
                    $diff->scoreBefore,
                    $diff->scoreAfter,
                    $diff->scoreDelta,
                    $diff->wordCountDelta,
                    count($diff->addedHeadings)
                );

                return [
                    'tool' => $this->getName(),
                    'action' => 'plan',
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                    'diff' => $diff->toArray(),
                    'score_before' => $diff->scoreBefore,
                    'score_after' => $diff->scoreAfter,
                    'score_delta' => $diff->scoreDelta,
                    'word_count_delta' => $diff->wordCountDelta,
                    'added_headings' => $diff->addedHeadings,
                    'missing_intents_addressed' => $diff->missingIntentsAddressed,
                    'credits_deducted' => ContentRefreshService::PLAN_CREDIT_COST,
                    'summary_text' => $summaryText,
                ];
        }
    }
}
