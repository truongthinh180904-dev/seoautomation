<?php

namespace App\Jobs\AI;

use App\Agents\OutlineAgent;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Models\Article;
use App\Models\CompetitorAnalysis;
use App\Models\Keyword;
use App\Models\Task;
use App\Services\Article\ArticlePipelineService;
use App\Services\Queue\TaskService;
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

class GenerateOutlineJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $backoff = [60, 180];

    public $timeout = 180;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'outline_article_'.$this->articleId;
    }

    public function __construct(
        public int $keywordId,
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(OutlineAgent $agent, ArticlePipelineService $pipeline): void
    {
        $article = Article::find($this->articleId);
        $keyword = Keyword::find($this->keywordId);

        if (! $article || ! $keyword) {
            return;
        }

        if ($article->tenant_id !== $keyword->tenant_id) {
            Log::error("GenerateOutlineJob aborted: Tenant mismatch between article #{$article->id} and keyword #{$keyword->id}");

            return;
        }

        if (data_get($article->pipeline_status, 'current') === 'cancelled' || $article->status === ArticleStatus::FAILED) {
            Log::info("GenerateOutlineJob aborted: Article #{$article->id} was cancelled or failed.");

            return;
        }

        try {
            BelongsToTenant::setExecutionContext($article->tenant_id);
            BelongsToWorkspace::setExecutionContext($article->workspace_id);

            // Update Task running status
            if ($task = Task::where('article_id', $this->articleId)->where('status', '!=', TaskStatus::CANCELLED)->latest('id')->first()) {
                app(TaskService::class)->startTask($task, 'outline');
            }

            $pipeline->complete($article, 'queued', 'Queue worker đã bắt đầu xử lý.');
            $pipeline->start($article, 'outline', 'AI đang tạo dàn ý bài viết.');

            $competitorAnalyses = CompetitorAnalysis::where('keyword_id', $this->keywordId)
                ->where('analysis_completed', true)
                ->get();

            $context = [
                'keyword' => $keyword->keyword,
                'search_intent' => $keyword->search_intent ?: 'informational',
                'target_word_count' => $keyword->target_word_count ?: 3000,
                'competitor_data' => $competitorAnalyses->toArray(),
                'keyword_id' => $this->keywordId,
                'tenant_id' => $article->tenant_id,
                'workspace_id' => $article->workspace_id,
                'article_id' => $this->articleId,
            ];

            $result = $agent->execute($context);

            if (! $result->success) {
                throw new \RuntimeException($result->error ?? 'Outline generation failed.');
            }

            $article->update([
                'outline' => $result->data['outline'],
            ]);
            $pipeline->complete($article->fresh(), 'outline', 'Đã tạo dàn ý xong.');

            GenerateArticleJob::dispatch($this->keywordId, $this->articleId)->onQueue('ai-writing');
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($article = Article::find($this->articleId)) {
            app(ArticlePipelineService::class)->fail($article, 'outline', $exception->getMessage());
        }

        Article::whereKey($this->articleId)->update([
            'status' => ArticleStatus::FAILED,
            'review_notes' => 'Outline generation failed: '.$exception->getMessage(),
        ]);

        Keyword::whereKey($this->keywordId)->update([
            'status' => KeywordStatus::FAILED,
        ]);

        if ($task = Task::where('article_id', $this->articleId)->where('status', '!=', TaskStatus::CANCELLED)->latest('id')->first()
            ?: Task::where('keyword_id', $this->keywordId)->where('status', '!=', TaskStatus::CANCELLED)->latest('id')->first()) {
            app(TaskService::class)->failTask($task, $exception->getMessage());
        }
    }
}
