<?php

namespace App\Jobs\AI;

use App\Agents\SEOOptimizationAgent;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Events\ArticleGenerated;
use App\Models\Article;
use App\Services\Article\ArticlePipelineService;
use App\Services\SEO\ArticleQualityReportService;
use App\Services\SEO\SEOScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateSeoMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60, 180];

    public function __construct(
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(
        SEOOptimizationAgent $agent,
        SEOScoreService $seoScoreService,
        ArticleQualityReportService $qualityReportService,
        ArticlePipelineService $pipeline
    ): void
    {
        $article = Article::with('keyword')->find($this->articleId);

        if (!$article || !$article->keyword) {
            return;
        }

        $pipeline->start($article, 'seo_metadata', 'AI đang tối ưu tiêu đề, mô tả và FAQ.');

        $context = [
            'keyword' => $article->keyword->keyword,
            'article_content' => $article->content,
            'tenant_id' => $article->tenant_id,
            'keyword_id' => $article->keyword_id,
            'article_id' => $this->articleId,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $pipeline->fail($article, 'seo_metadata', $result->error ?? 'SEO Metadata failed.');
            $article->update(['status' => ArticleStatus::FAILED, 'review_notes' => 'SEO Metadata failed: ' . $result->error]);
            $this->fail(new \Exception($result->error));
            return;
        }

        $data = $result->data;

        $article->seo_title = $data['seo_title'];
        $article->seo_description = $data['seo_description'];
        
        if (!empty($data['faqs'])) {
            $article->faqs = $data['faqs'];
        }

        $article->seo_score = $seoScoreService->calculate($article);
        $article->status = ArticleStatus::REVIEW;
        $article->generateReviewToken();
        $article->save();
        $pipeline->complete($article->fresh(), 'seo_metadata', 'Đã tối ưu SEO metadata.');
        $article->keyword->update([
            'status' => KeywordStatus::COMPLETED,
            'processed_at' => now(),
        ]);

        $pipeline->start($article->fresh(), 'quality_check', 'Đang tạo báo cáo SEO QA.');
        $qualityReportService->generate($article->fresh(['keyword', 'wordpressSite']));
        $pipeline->complete($article->fresh(), 'quality_check', 'Đã tạo báo cáo SEO QA.');
        $pipeline->complete($article->fresh(), 'done', 'Bài viết đã sẵn sàng để duyệt.');
        ArticleGenerated::dispatch($article->fresh(['keyword']));
    }

    public function failed(Throwable $exception): void
    {
        $article = Article::find($this->articleId);

        if ($article) {
            app(ArticlePipelineService::class)->fail($article, 'seo_metadata', $exception->getMessage());
            $article->update([
                'status' => ArticleStatus::FAILED,
                'review_notes' => 'SEO Metadata failed: ' . $exception->getMessage(),
            ]);
            $article->keyword?->update(['status' => KeywordStatus::FAILED]);
        }
    }
}
