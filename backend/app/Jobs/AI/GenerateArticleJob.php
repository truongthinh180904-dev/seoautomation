<?php

namespace App\Jobs\AI;

use App\Agents\WritingAgent;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Jobs\Media\GenerateArticleImagesJob;
use App\Models\Article;
use App\Models\CompetitorAnalysis;
use App\Models\Keyword;
use App\Models\Task;
use App\Models\Website;
use App\Services\Article\ArticleContentFormatter;
use App\Services\Article\ArticlePipelineService;
use App\Services\Article\ContentStrategyService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Cost\CostTrackingService;
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

class GenerateArticleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $backoff = [120, 300];

    public $timeout = 300;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'generate_article_'.$this->articleId;
    }

    public function __construct(
        public int $keywordId,
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(
        WritingAgent $agent,
        CostTrackingService $costTracking,
        ArticlePipelineService $pipeline,
        ArticleContentFormatter $formatter,
        CreditLedgerService $creditLedger,
        TaskService $taskService
    ): void {
        $article = Article::find($this->articleId);
        $keyword = Keyword::find($this->keywordId);

        if (! $article || ! $keyword) {
            return;
        }

        if ($article->tenant_id !== $keyword->tenant_id) {
            Log::error("GenerateArticleJob aborted: Tenant mismatch between article #{$article->id} and keyword #{$keyword->id}");

            return;
        }

        if (data_get($article->pipeline_status, 'current') === 'cancelled' || $article->status === ArticleStatus::FAILED) {
            Log::info("GenerateArticleJob aborted: Article #{$article->id} was cancelled or failed.");

            return;
        }

        try {
            BelongsToTenant::setExecutionContext($article->tenant_id);
            BelongsToWorkspace::setExecutionContext($article->workspace_id);

            // Check Task and reserved credits
            $task = Task::where('article_id', $this->articleId)
                ->where('status', '!=', TaskStatus::CANCELLED)
                ->latest('id')
                ->first();

            if (! $task) {
                $task = Task::where('keyword_id', $this->keywordId)
                    ->where('status', '!=', TaskStatus::CANCELLED)
                    ->latest('id')
                    ->first();
                if ($task && ! $task->article_id) {
                    $task->update(['article_id' => $this->articleId]);
                }
            }

            $creditCost = (int) config('ai_costs.credits.standard_article', 20);

            if ($task) {
                $taskService->startTask($task, 'writing');
            }

            // Only check available credits if not already reserved by a Task
            if ((! $task || $task->credits_reserved < $creditCost) && $article->workspace_id) {
                $creditLedger->checkCredits($article->workspace_id, $creditCost);
            }

            $pipeline->start($article, 'writing', 'AI đang viết nội dung chính.');

            $competitorAnalyses = CompetitorAnalysis::where('keyword_id', $this->keywordId)
                ->where('analysis_completed', true)
                ->get();

            $semanticKeywords = [];
            $entities = [];
            $faqs = [];

            foreach ($competitorAnalyses as $comp) {
                if (is_array($comp->semantic_keywords)) {
                    $semanticKeywords = array_merge($semanticKeywords, $comp->semantic_keywords);
                }
                if (is_array($comp->semantic_entities)) {
                    $entities = array_merge($entities, $comp->semantic_entities);
                }
                if (is_array($comp->faqs)) {
                    $faqs = array_merge($faqs, $comp->faqs);
                }
            }

            // Kết hợp dữ liệu từ 5-Step Strategy Analysis & Brand Knowledge
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

            $strategyAnalysis = (array) (data_get($article->outline, 'strategy_analysis') ?? []);
            if (empty($strategyAnalysis) && $website) {
                $strategyAnalysis = $strategyService->analyzeBeforeWriting($website, $keyword, $brief);
            }
            $strategyBlock = ! empty($strategyAnalysis)
                ? $strategyService->buildStrategyPromptBlock($strategyAnalysis)
                : '';

            $strategySemantic = (array) data_get($strategyAnalysis, 'step_2_keyword_analysis.semantic_keywords', []);
            $strategySecondary = (array) data_get($strategyAnalysis, 'step_2_keyword_analysis.secondary_keywords', []);
            $strategyEntities = (array) data_get($strategyAnalysis, 'step_2_keyword_analysis.related_entities', []);
            $outlineFaqs = (array) data_get($article->outline, 'faqs', []);

            $semanticKeywords = array_values(array_unique(array_merge($semanticKeywords, $strategySecondary, $strategySemantic)));
            $entities = array_values(array_unique(array_merge($entities, $strategyEntities)));
            $faqs = ! empty($outlineFaqs) ? $outlineFaqs : $faqs;

            $resolvedTargetWordCount = (int) (
                data_get($strategyAnalysis, 'step_4_content_strategy.target_word_count')
                ?: ($keyword->target_word_count ?: 1800)
            );
            $specialRequirements = (string) ($brief['special_requirements'] ?? $keyword->brief_notes ?? '');

            $context = [
                'keyword' => $keyword->keyword,
                'outline' => $article->outline,
                'semantic_keywords' => $semanticKeywords,
                'entities' => $entities,
                'faqs' => $faqs,
                'competitor_data' => $competitorAnalyses->toArray(),
                'brand_context' => $brandContext,
                'strategy_block' => $strategyBlock,
                'article_id' => $this->articleId,
                'keyword_id' => $this->keywordId,
                'tenant_id' => $article->tenant_id,
                'workspace_id' => $article->workspace_id,
                'target_word_count' => $resolvedTargetWordCount,
                'brief_notes' => $specialRequirements,
                'must_include_points' => $keyword->must_include_points ?? [],
                'avoid_topics' => $keyword->avoid_topics ?? [],
            ];

            $result = $agent->execute($context);

            if (! $result->success) {
                throw new \RuntimeException($result->error ?? 'WritingAgent failed');
            }

            $data = $result->data;
            $content = $formatter->normalize($data['content'], $data['title']);
            $rawInternalLinks = $keyword->meta['internal_links'] ?? null;
            if (empty($rawInternalLinks)) {
                $opps = (array) data_get($strategyAnalysis, 'step_4_content_strategy.internal_link_opportunities', []);
                if (! empty($opps)) {
                    $pairs = [];
                    foreach ($opps as $opp) {
                        if (! empty($opp['anchor']) && ! empty($opp['url'])) {
                            $pairs[] = $opp['anchor'].'|'.$opp['url'];
                        }
                    }
                    $rawInternalLinks = implode(', ', $pairs);
                }
            }
            [$content, $internalLinks] = $formatter->insertInternalLinks($content, $rawInternalLinks);

            $article->update([
                'title' => $data['title'],
                'content' => $content,
                'excerpt' => $data['excerpt'],
                'word_count' => $formatter->wordCount($content),
                'internal_links' => ! empty($internalLinks) ? $internalLinks : $article->internal_links,
                'ai_provider' => $data['ai_provider'] ?? 'openai',
                'ai_model' => $data['ai_model'] ?? null,
                'ai_tokens_used' => $result->tokensUsed,
                'ai_cost_usd' => $data['ai_cost_usd'] ?? 0,
                'duplicate_check_hash' => $data['duplicate_check_hash'] ?? null,
            ]);
            $pipeline->complete($article->fresh(), 'writing', 'Đã viết nội dung bài.');

            if ($task && $task->credits_reserved > 0) {
                $taskService->updateProgress($task, 70, 'writing_done');
            } elseif ($article->workspace_id) {
                // If not managed by a task with reserved credits, deduct directly
                $creditLedger->deductCredits(
                    $article->workspace_id,
                    $creditCost,
                    'article',
                    $article->id,
                    sprintf('deduct:%d:article:%d:generation', $article->workspace_id, $article->id),
                    ['job' => 'GenerateArticleJob', 'keyword_id' => $this->keywordId],
                    false
                );
            }

            $costTracking->incrementGeneratedArticle($article->tenant_id);
            GenerateArticleImagesJob::dispatch($this->articleId)->onQueue('imports');

            if (class_exists('App\Jobs\AI\OptimizeInternalLinksJob')) {
                OptimizeInternalLinksJob::dispatch($this->articleId)->onQueue('ai-writing');
            } elseif (class_exists('App\Jobs\AI\GenerateSeoMetadataJob')) {
                GenerateSeoMetadataJob::dispatch($this->articleId)->onQueue('ai-writing');
            } else {
                Log::info('SEO Metadata and Internal Linking jobs not found, skipping.');
            }
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    public function failed(Throwable $exception): void
    {
        $article = Article::find($this->articleId);
        if ($article) {
            app(ArticlePipelineService::class)->fail($article, 'writing', $exception->getMessage());

            $article->update([
                'status' => ArticleStatus::FAILED,
                'review_notes' => 'Writing generation failed: '.$exception->getMessage(),
            ]);

            $article->keyword?->update(['status' => KeywordStatus::FAILED]);
        }

        if ($task = Task::where('article_id', $this->articleId)->where('status', '!=', TaskStatus::CANCELLED)->latest('id')->first()
            ?: Task::where('keyword_id', $this->keywordId)->where('status', '!=', TaskStatus::CANCELLED)->latest('id')->first()) {
            app(TaskService::class)->failTask($task, $exception->getMessage());
        }
    }
}
