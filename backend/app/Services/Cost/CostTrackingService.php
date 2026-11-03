<?php

namespace App\Services\Cost;

use App\DTOs\QuotaCheckResultDTO;
use App\Models\AiUsageLog;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\DB;

class CostTrackingService
{
    public function __construct(
        protected ?CreditLedgerService $creditLedgerService = null,
        protected ?SubscriptionService $subscriptionService = null
    ) {
        $this->creditLedgerService = $creditLedgerService ?? app(CreditLedgerService::class);
        $this->subscriptionService = $subscriptionService ?? app(SubscriptionService::class);
    }

    public function logUsage(array $data): AiUsageLog
    {
        $articleId = $data['article_id'] ?? null;
        $campaignId = $data['campaign_id'] ?? null;
        $workspaceId = $data['workspace_id'] ?? null;
        $websiteId = $data['website_id'] ?? null;

        if (! $campaignId && $articleId) {
            $campaignId = Article::query()->whereKey($articleId)->value('campaign_id');
        }

        if (! $workspaceId && $articleId) {
            $workspaceId = Article::query()->whereKey($articleId)->value('workspace_id');
        }

        $keywordId = $data['keyword_id'] ?? null;
        if (! $workspaceId && $keywordId) {
            $keyword = Keyword::find($keywordId);
            $workspaceId = $keyword?->workspace_id;
            if (! $campaignId && $keyword?->campaign_id) {
                $campaignId = $keyword->campaign_id;
            }
        }

        if (! $workspaceId && ! empty($data['tenant_id'])) {
            $workspaceId = Workspace::where('legacy_tenant_id', $data['tenant_id'])->value('id');
        }

        $log = AiUsageLog::create([
            'tenant_id' => $data['tenant_id'] ?? null,
            'workspace_id' => $workspaceId,
            'website_id' => $websiteId,
            'campaign_id' => $campaignId,
            'article_id' => $articleId,
            'keyword_id' => $data['keyword_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'job_type' => $data['job_type'],
            'provider' => $data['provider'],
            'model' => $data['model'] ?? null,
            'prompt_tokens' => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens' => $data['total_tokens'] ?? 0,
            'cost_usd' => $data['cost_usd'] ?? 0,
            'credits_charged' => $data['credits_charged'] ?? 0,
            'duration_ms' => $data['duration_ms'] ?? null,
            'status' => $data['status'] ?? 'success',
            'error_message' => $data['error_message'] ?? null,
            'request_hash' => $data['request_hash'] ?? null,
        ]);

        // Ghi song song vào usage_records cho SaaS Workspace nếu có workspace_id
        if ($workspaceId) {
            UsageRecord::create([
                'workspace_id' => $workspaceId,
                'website_id' => $websiteId,
                'campaign_id' => $campaignId,
                'article_id' => $articleId,
                'task_id' => $data['task_id'] ?? null,
                'credit_transaction_id' => $data['credit_transaction_id'] ?? null,
                'action_type' => $data['job_type'] ?? 'ai_generation',
                'credits_charged' => $data['credits_charged'] ?? 0,
                'tokens_used' => $data['total_tokens'] ?? 0,
                'provider_cost_usd' => $data['cost_usd'] ?? 0,
                'status' => ($data['status'] ?? 'success') === 'success' ? 'settled' : 'refunded',
                'usage_date' => now()->toDateString(),
            ]);
        }

        if (! empty($data['tenant_id']) && ($data['status'] ?? 'success') === 'success') {
            $this->incrementMonthlyCost((int) $data['tenant_id'], (float) ($data['cost_usd'] ?? 0));
        }

        return $log;
    }

    public function calculateCost(string $provider, ?string $model, int $promptTokens = 0, int $completionTokens = 0): float
    {
        if ($provider === 'serper') {
            return (float) config('ai_costs.serper_cost_per_call_usd', 0.001);
        }

        $rates = config('ai_costs.models', []);
        $rate = $rates[$model] ?? config('ai_costs.fallback_model_rate', ['input' => 5.0, 'output' => 15.0]);

        return round(
            (($promptTokens / 1000000) * $rate['input']) + (($completionTokens / 1000000) * $rate['output']),
            8
        );
    }

    /**
     * Kiểm tra Quota cho SaaS Workspace mới (dựa trên Credit và Giới hạn Plan).
     */
    public function checkWorkspaceQuota(Workspace|int $workspace, int $requiredCredits = 20): QuotaCheckResultDTO
    {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);
        $plan = $this->subscriptionService->getActivePlan($workspaceModel);
        $balance = $this->creditLedgerService->getBalance($workspaceModel);

        $creditShortage = $balance->availableCredits < $requiredCredits;

        // Đếm bài viết đã tạo trong chu kỳ hiện tại
        $periodStart = $balance->periodStart ?? now()->startOfMonth();
        $articlesUsed = Article::where('workspace_id', $workspaceModel->id)
            ->where('created_at', '>=', $periodStart)
            ->count();
        $articlesLimit = (int) $plan->articles_limit_per_cycle;
        $articlesExceeded = $articlesLimit > 0 && $articlesUsed >= $articlesLimit;

        $canProceed = ! $creditShortage && ! $articlesExceeded;

        $message = null;
        if ($creditShortage) {
            $message = sprintf('Không đủ credit (cần %d, hiện có %d).', $requiredCredits, $balance->availableCredits);
        } elseif ($articlesExceeded) {
            $message = sprintf('Đã đạt giới hạn bài viết của gói (%d bài).', $articlesLimit);
        }

        return new QuotaCheckResultDTO(
            canProceed: $canProceed,
            articlesExceeded: $articlesExceeded,
            costExceeded: $creditShortage,
            warningThreshold: ($articlesLimit > 0 && ($articlesUsed / $articlesLimit) >= 0.8) || ($balance->availableCredits <= 50),
            articlesLimit: $articlesLimit,
            articlesUsed: $articlesUsed,
            costLimitUsd: (float) $balance->cycleGrantedCredits,
            costUsedUsd: (float) $balance->cycleUsedCredits,
            message: $message
        );
    }

    /**
     * Tương thích ngược: kiểm tra quota theo tenant cũ.
     */
    public function checkQuota(int $tenantId): QuotaCheckResultDTO
    {
        // Thử tìm workspace tương ứng với legacy_tenant_id
        $workspace = Workspace::where('legacy_tenant_id', $tenantId)->first();
        if ($workspace) {
            return $this->checkWorkspaceQuota($workspace);
        }

        $subscription = $this->getOrCreateSubscription($tenantId);
        $plan = $subscription->plan;
        $articlesLimit = $plan->articles_per_month;
        $costLimit = $plan->ai_cost_budget_usd;
        $articlesUsed = (int) ($subscription->articles_used_this_month ?? 0);
        $costUsed = (float) ($subscription->ai_cost_used_this_month_usd ?? 0);

        $articlesExceeded = $articlesLimit !== null && $articlesUsed >= $articlesLimit;
        $costExceeded = $costLimit !== null && $costUsed >= $costLimit;
        $articleWarning = $articlesLimit !== null && $articlesLimit > 0 && ($articlesUsed / $articlesLimit) >= 0.8;
        $costWarning = $costLimit !== null && $costLimit > 0 && ($costUsed / $costLimit) >= 0.8;

        return new QuotaCheckResultDTO(
            canProceed: ! $articlesExceeded && ! $costExceeded,
            articlesExceeded: $articlesExceeded,
            costExceeded: $costExceeded,
            warningThreshold: $articleWarning || $costWarning,
            articlesLimit: $articlesLimit,
            articlesUsed: $articlesUsed,
            costLimitUsd: $costLimit,
            costUsedUsd: round($costUsed, 6),
            message: $articlesExceeded
                ? 'Monthly article limit reached.'
                : ($costExceeded ? 'Monthly AI cost budget exceeded.' : null)
        );
    }

    public function incrementGeneratedArticle(int $tenantId): void
    {
        $subscription = $this->getOrCreateSubscription($tenantId);
        $subscription->increment('articles_used_this_month');
    }

    public function getMonthlyUsage(int $tenantId): array
    {
        $subscription = $this->getOrCreateSubscription($tenantId)->load('plan');
        $costByJobType = AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->select('job_type', DB::raw('sum(cost_usd) as total_cost'))
            ->groupBy('job_type')
            ->pluck('total_cost', 'job_type')
            ->map(fn ($value) => round((float) $value, 6))
            ->all();

        return [
            'subscription' => $subscription,
            'quota' => $this->checkQuota($tenantId)->toArray(),
            'cost_by_job_type' => $costByJobType,
        ];
    }

    protected function getOrCreateSubscription(int $tenantId): TenantSubscription
    {
        $subscription = TenantSubscription::query()->with('plan')->where('tenant_id', $tenantId)->first();

        if ($subscription) {
            $dirty = false;

            if ($subscription->articles_used_this_month === null) {
                $subscription->articles_used_this_month = 0;
                $dirty = true;
            }

            if ($subscription->ai_cost_used_this_month_usd === null) {
                $subscription->ai_cost_used_this_month_usd = 0;
                $dirty = true;
            }

            if (! $subscription->billing_cycle_start) {
                $subscription->billing_cycle_start = now()->startOfMonth();
                $dirty = true;
            }

            if (! $subscription->billing_cycle_end) {
                $subscription->billing_cycle_end = now()->endOfMonth();
                $dirty = true;
            }

            if ($dirty) {
                $subscription->save();
            }

            return $subscription;
        }

        $plan = SubscriptionPlan::query()->where('name', 'starter')->first()
            ?? SubscriptionPlan::query()->create([
                'name' => 'starter',
                'articles_per_month' => 30,
                'ai_cost_budget_usd' => 10,
                'campaigns_limit' => 2,
                'wordpress_sites_limit' => 1,
                'team_members_limit' => 1,
                'serp_lookups_per_article' => 10,
                'is_active' => true,
            ]);

        return TenantSubscription::query()->create([
            'tenant_id' => $tenantId,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'billing_cycle_start' => now()->startOfMonth(),
            'billing_cycle_end' => now()->endOfMonth(),
            'trial_ends_at' => now()->addDays(14),
        ])->load('plan');
    }

    protected function incrementMonthlyCost(int $tenantId, float $costUsd): void
    {
        if ($costUsd <= 0) {
            return;
        }

        $subscription = $this->getOrCreateSubscription($tenantId);
        $subscription->increment('ai_cost_used_this_month_usd', $costUsd);
    }
}
