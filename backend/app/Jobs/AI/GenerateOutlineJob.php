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
use App\Models\Website;
use App\Services\Article\ArticlePipelineService;
use App\Services\Article\ContentStrategyService;
use App\Services\Brand\BrandKnowledgeService;
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
use Illuminate\Support\Facades\Schema;
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
            $pipeline->start($article, 'outline', 'AI Content Strategy Engine đang phân tích 5 bước chiến lược & lập dàn ý.');

            $competitorAnalyses = CompetitorAnalysis::where('keyword_id', $this->keywordId)
                ->where('analysis_completed', true)
                ->get();

            // Lấy Content Brief đã lưu từ pipeline_status.content_brief hoặc outline.content_brief
            $brief = (array) (data_get($article->pipeline_status, 'content_brief')
                ?? data_get($article->outline, 'content_brief')
                ?? []);

            $brandKnowledgeId = isset($brief['brand_knowledge_id']) ? (int) $brief['brand_knowledge_id'] : null;
            $website = $article->website ?: Website::find($article->website_id);

            $brandKnowledgeService = app(BrandKnowledgeService::class);
            $strategyService = app(ContentStrategyService::class);

            $brandContext = $website
                ? $brandKnowledgeService->buildPromptContext($website, $brandKnowledgeId)
                : '';

            $strategyAnalysis = $website
                ? $strategyService->analyzeBeforeWriting($website, $keyword, $brief)
                : [];
            $strategyBlock = ! empty($strategyAnalysis)
                ? $strategyService->buildStrategyPromptBlock($strategyAnalysis)
                : '';

            $resolvedTargetWordCount = (int) (
                data_get($strategyAnalysis, 'step_4_content_strategy.target_word_count')
                ?: ($keyword->target_word_count ?: 1800)
            );
            $resolvedIntent = (string) (
                data_get($strategyAnalysis, 'step_2_keyword_analysis.primary_search_intent')
                ?: ($keyword->intent ?: 'informational')
            );

            $context = [
                'keyword' => $keyword->keyword,
                'search_intent' => $resolvedIntent,
                'target_word_count' => $resolvedTargetWordCount,
                'competitor_data' => $competitorAnalyses->toArray(),
                'brand_context' => $brandContext,
                'strategy_block' => $strategyBlock,
                'strategy_analysis' => $strategyAnalysis,
                'keyword_id' => $this->keywordId,
                'tenant_id' => $article->tenant_id,
                'workspace_id' => $article->workspace_id,
                'article_id' => $this->articleId,
            ];

            $result = $agent->execute($context);

            if (! $result->success) {
                throw new \RuntimeException($result->error ?? 'Outline generation failed.');
            }

            $outlinePayload = $result->data['outline'];
            if (is_array($outlinePayload) && ! empty($brief)) {
                $outlinePayload['content_brief'] = $brief;
            }
            if (is_array($outlinePayload) && ! empty($strategyAnalysis)) {
                $outlinePayload['strategy_analysis'] = $strategyAnalysis;
            }

            $updatePayload = [
                'outline' => $outlinePayload,
            ];

            if (! empty($strategyAnalysis['step_4_content_strategy']['recommended_cta']) && empty($article->primary_cta)) {
                $updatePayload['primary_cta'] = $strategyAnalysis['step_4_content_strategy']['recommended_cta'];
            }

            // Nếu CSDL đã có các cột mở rộng thì ghi đồng thời vào cột mở rộng
            try {
                if (Schema::hasColumn('articles', 'content_type')) {
                    $updatePayload['content_type'] = data_get($strategyAnalysis, 'step_4_content_strategy.content_type');
                    $updatePayload['search_intent'] = data_get($strategyAnalysis, 'step_2_keyword_analysis.primary_search_intent');
                    $updatePayload['secondary_intents'] = data_get($strategyAnalysis, 'step_2_keyword_analysis.secondary_search_intents', []);
                    $updatePayload['secondary_keywords'] = data_get($strategyAnalysis, 'step_2_keyword_analysis.secondary_keywords', []);
                    $updatePayload['content_brief'] = $brief;
                    $updatePayload['strategy_analysis'] = $strategyAnalysis;
                    if ($brandKnowledgeId) {
                        $updatePayload['brand_knowledge_id'] = $brandKnowledgeId;
                    }
                }
            } catch (Throwable) {
                // Bỏ qua nếu không kiểm tra được schema, dữ liệu đã an toàn trong cột outline JSON
            }

            $article->update($updatePayload);
            $pipeline->complete($article->fresh(), 'outline', 'Đã hoàn thành phân tích chiến lược 5 bước & lập dàn ý.');

            // Nếu người dùng yêu cầu dừng lại để duyệt dàn ý trước khi viết (require_outline_approval = true)
            if (! empty($brief['require_outline_approval'])) {
                $pipeline->start($article->fresh(), 'writing', 'Đang chờ duyệt dàn ý trước khi viết nội dung.');

                return;
            }

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
