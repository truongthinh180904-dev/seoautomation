<?php

namespace App\Jobs\SERP;

use App\Agents\SerpResearchAgent;
use App\Enums\ArticleStatus;
use App\Enums\CampaignStatus;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\AI\GenerateOutlineJob;
use App\Jobs\Crawl\CrawlCompetitorJob;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\Article\ArticlePipelineService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Cost\CostTrackingService;
use App\Services\Queue\TaskService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SerpResearchJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 120, 300];

    public $timeout = 180;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'serp_keyword_'.$this->keyword->id;
    }

    public function __construct(
        public Keyword $keyword
    ) {
        $this->onQueue('ai-research');
    }

    public function handle(
        SerpResearchAgent $agent,
        ArticlePipelineService $pipeline,
        CostTrackingService $costTracking,
        TaskService $taskService,
        CreditLedgerService $creditLedger
    ): void {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $this->keyword->refresh();

        if ($this->keyword->campaign && $this->keyword->campaign->status === CampaignStatus::PAUSED) {
            Log::info("SerpResearchJob aborted: Campaign is PAUSED for keyword #{$this->keyword->id}");

            return;
        }

        // 1. Quota check
        $workspace = $this->keyword->workspace_id
            ? Workspace::find($this->keyword->workspace_id)
            : null;

        if ($workspace) {
            $quota = $costTracking->checkWorkspaceQuota($workspace);
            if (! $quota->canProceed) {
                Log::warning("SerpResearchJob aborted: Quota exceeded for workspace #{$workspace->id} on keyword #{$this->keyword->id}");
                $this->keyword->update(['status' => KeywordStatus::FAILED]);

                return;
            }
        } elseif ($this->keyword->tenant_id) {
            $quota = $costTracking->checkQuota($this->keyword->tenant_id);
            if (! $quota->canProceed) {
                Log::warning("SerpResearchJob aborted: Quota exceeded for tenant #{$this->keyword->tenant_id} on keyword #{$this->keyword->id}");
                $this->keyword->update(['status' => KeywordStatus::FAILED]);

                return;
            }
        }

        // 2. Task & Credit Reservation
        $task = Task::where('keyword_id', $this->keyword->id)
            ->where('status', '!=', TaskStatus::CANCELLED)
            ->latest('id')
            ->first();

        $creditCost = (int) config('ai_costs.credits.standard_article', 20);

        if (! $task && $this->keyword->workspace_id) {
            try {
                $task = $taskService->createTask([
                    'workspace_id' => $this->keyword->workspace_id,
                    'campaign_id' => $this->keyword->campaign_id,
                    'keyword_id' => $this->keyword->id,
                    'triggered_by' => $this->keyword->user_id,
                    'type' => 'full_article_pipeline',
                    'current_step' => 'serp_research',
                    'credits_reserved' => $creditCost,
                    'payload' => [
                        'keyword_id' => $this->keyword->id,
                        'keyword' => $this->keyword->keyword,
                    ],
                ]);
            } catch (InsufficientCreditsException $e) {
                Log::warning("SerpResearchJob aborted: Insufficient credits for keyword #{$this->keyword->id}: {$e->getMessage()}");
                $this->keyword->update(['status' => KeywordStatus::FAILED]);

                return;
            }
        }

        if ($task) {
            $taskService->startTask($task, 'serp_research');
        }

        try {
            BelongsToTenant::setExecutionContext($this->keyword->tenant_id);
            BelongsToWorkspace::setExecutionContext($this->keyword->workspace_id);

            $context = [
                'keyword' => $this->keyword->keyword,
                'keyword_id' => $this->keyword->id,
                'tenant_id' => $this->keyword->tenant_id,
                'workspace_id' => $this->keyword->workspace_id,
            ];

            $result = $agent->execute($context);

            if (! $result->success) {
                throw new \RuntimeException($result->error ?? 'SerpResearchAgent failed without specific error message.');
            }

            // Find or auto-provision an Article for this keyword
            $article = Article::where('keyword_id', $this->keyword->id)
                ->where('tenant_id', $this->keyword->tenant_id)
                ->first();

            if (! $article) {
                $keywordMeta = $this->keyword->meta ?? [];
                $article = Article::create([
                    'tenant_id' => $this->keyword->tenant_id,
                    'workspace_id' => $this->keyword->workspace_id,
                    'keyword_id' => $this->keyword->id,
                    'campaign_id' => $this->keyword->campaign_id,
                    'wordpress_site_id' => $this->keyword->wordpress_site_id,
                    'user_id' => $this->keyword->user_id,
                    'title' => 'Bài viết: '.$this->keyword->keyword,
                    'slug' => Str::slug($this->keyword->keyword),
                    'focus_keyword' => $this->keyword->keyword,
                    'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                    'media_plan' => array_filter([
                        'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                        'image_urls' => $keywordMeta['image_urls'] ?? null,
                        'internal_links' => $keywordMeta['internal_links'] ?? null,
                        'image_source' => $keywordMeta['image_source'] ?? config('ai.image_generation.source_strategy', 'hybrid'),
                        'inline_image_count' => $keywordMeta['inline_image_count'] ?? config('ai.image_generation.default_inline_count', 3),
                    ]),
                    'status' => ArticleStatus::DRAFT,
                ]);
            }

            if ($task && ! $task->article_id) {
                $task->update(['article_id' => $article->id]);
            }

            $pipeline->reset($article);
            $pipeline->start($article, 'outline', 'Bắt đầu lập dàn ý từ dữ liệu SERP.');

            // Collect competitor crawling jobs for top search results
            $serpResults = SerpResult::where('keyword_id', $this->keyword->id)
                ->orderBy('position')
                ->take(3)
                ->get();

            $crawlJobs = [];
            foreach ($serpResults as $serpResult) {
                if ($serpResult->url) {
                    $crawlJobs[] = new CrawlCompetitorJob(
                        $serpResult->id,
                        $this->keyword->id,
                        $this->keyword->tenant_id,
                        $serpResult->url,
                        $this->keyword->keyword,
                        $this->keyword->workspace_id
                    );
                }
            }

            $keywordId = $this->keyword->id;
            $articleId = $article->id;

            if (! empty($crawlJobs)) {
                try {
                    Bus::batch($crawlJobs)
                        ->name("crawl_competitors_kw_{$keywordId}")
                        ->onQueue('ai-research')
                        ->allowFailures()
                        ->finally(function () use ($keywordId, $articleId) {
                            GenerateOutlineJob::dispatch($keywordId, $articleId)->onQueue('ai-writing');
                        })
                        ->dispatch();
                } catch (Throwable $e) {
                    foreach ($crawlJobs as $job) {
                        dispatch($job);
                    }
                    GenerateOutlineJob::dispatch($keywordId, $articleId)->onQueue('ai-writing');
                }
            } else {
                GenerateOutlineJob::dispatch($keywordId, $articleId)->onQueue('ai-writing');
            }
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("SerpResearchJob permanently failed for keyword #{$this->keyword->id}: ".$exception->getMessage());
        $this->keyword->update(['status' => KeywordStatus::FAILED]);

        $task = Task::where('keyword_id', $this->keyword->id)
            ->where('status', '!=', TaskStatus::CANCELLED)
            ->latest('id')
            ->first();

        if ($task) {
            app(TaskService::class)->failTask($task, $exception->getMessage());
        }
    }
}
