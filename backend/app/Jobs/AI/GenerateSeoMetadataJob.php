<?php

namespace App\Jobs\AI;

use App\Agents\SEOOptimizationAgent;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Events\ArticleGenerated;
use App\Models\Article;
use App\Models\Task;
use App\Services\Article\ArticlePipelineService;
use App\Services\Queue\TaskService;
use App\Services\SEO\ArticleQualityReportService;
use App\Services\SEO\SEOScoreService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSeoMetadataJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $backoff = [60, 180];

    public $timeout = 180;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'seo_metadata_article_'.$this->articleId;
    }

    public function __construct(
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(
        SEOOptimizationAgent $agent,
        SEOScoreService $seoScoreService,
        ArticleQualityReportService $qualityReportService,
        ArticlePipelineService $pipeline,
        TaskService $taskService
    ): void {
        $article = Article::with('keyword')->find($this->articleId);

        if (! $article || ! $article->keyword) {
            return;
        }

        if ($article->tenant_id !== $article->keyword->tenant_id) {
            Log::error("GenerateSeoMetadataJob aborted: Tenant mismatch between article #{$article->id} and keyword #{$article->keyword->id}");

            return;
        }

        if (data_get($article->pipeline_status, 'current') === 'cancelled' || $article->status === ArticleStatus::FAILED) {
            Log::info("GenerateSeoMetadataJob aborted: Article #{$article->id} was cancelled or failed.");

            return;
        }

        try {
            BelongsToTenant::setExecutionContext($article->tenant_id);
            BelongsToWorkspace::setExecutionContext($article->workspace_id);

            $pipeline->start($article, 'seo_metadata', 'AI đang tối ưu tiêu đề, mô tả và FAQ.');

            $context = [
                'keyword' => $article->keyword->keyword,
                'article_content' => $article->content,
                'tenant_id' => $article->tenant_id,
                'workspace_id' => $article->workspace_id,
                'keyword_id' => $article->keyword_id,
                'article_id' => $this->articleId,
            ];

            $result = $agent->execute($context);

            if (! $result->success) {
                throw new \RuntimeException($result->error ?? 'SEO Metadata failed.');
            }

            $data = $result->data;

            $article->seo_title = $data['seo_title'];
            $article->seo_description = $data['seo_description'];

            if (! empty($data['faqs'])) {
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

            // Settle Task and reserved credits
            $task = Task::where('article_id', $this->articleId)
                ->where('status', '!=', TaskStatus::CANCELLED)
                ->latest('id')
                ->first();

            if ($task) {
                $taskService->completeTask($task, [
                    'article_id' => $article->id,
                    'title' => $article->title,
                    'status' => $article->status->value,
                ]);
            }

            ArticleGenerated::dispatch($article->fresh(['keyword']));
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    public function failed(Throwable $exception): void
    {
        $article = Article::find($this->articleId);

        if ($article) {
            app(ArticlePipelineService::class)->fail($article, 'seo_metadata', $exception->getMessage());
            $article->update([
                'status' => ArticleStatus::FAILED,
                'review_notes' => 'SEO Metadata failed: '.$exception->getMessage(),
            ]);
            $article->keyword?->update(['status' => KeywordStatus::FAILED]);
        }

        if ($task = Task::where('article_id', $this->articleId)->where('status', '!=', TaskStatus::CANCELLED)->latest('id')->first()) {
            app(TaskService::class)->failTask($task, $exception->getMessage());
        }
    }
}
