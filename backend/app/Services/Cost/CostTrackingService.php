<?php

namespace App\Services\Cost;

use App\DTOs\QuotaCheckResultDTO;
use App\Models\AiUsageLog;
use App\Models\Article;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;

class CostTrackingService
{
    public function logUsage(array $data): AiUsageLog
    {
        $articleId = $data['article_id'] ?? null;
        $campaignId = $data['campaign_id'] ?? null;

        if (!$campaignId && $articleId) {
            $campaignId = Article::query()->whereKey($articleId)->value('campaign_id');
        }

        $log = AiUsageLog::create([
            'tenant_id' => $data['tenant_id'],
            'campaign_id' => $campaignId,
            'article_id' => $articleId,
            'keyword_id' => $data['keyword_id'] ?? null,
            'job_type' => $data['job_type'],
            'provider' => $data['provider'],
            'model' => $data['model'] ?? null,
            'prompt_tokens' => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens' => $data['total_tokens'] ?? 0,
            'cost_usd' => $data['cost_usd'] ?? 0,
            'duration_ms' => $data['duration_ms'] ?? null,
            'status' => $data['status'] ?? 'success',
            'error_message' => $data['error_message'] ?? null,
        ]);

        if (($data['status'] ?? 'success') === 'success') {
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

        return (($promptTokens / 1000000) * $rate['input'])
            + (($completionTokens / 1000000) * $rate['output']);
    }

    public function checkQuota(int $tenantId): QuotaCheckResultDTO
    {
        $subscription = $this->getOrCreateSubscription($tenantId);
        $plan = $subscription->plan;
        $articlesLimit = $plan->articles_per_month;
        $costLimit = $plan->ai_cost_budget_usd;
        $articlesUsed = $subscription->articles_used_this_month;
        $costUsed = (float) $subscription->ai_cost_used_this_month_usd;

        $articlesExceeded = $articlesLimit !== null && $articlesUsed >= $articlesLimit;
        $costExceeded = $costLimit !== null && $costUsed >= $costLimit;
        $articleWarning = $articlesLimit !== null && $articlesLimit > 0 && ($articlesUsed / $articlesLimit) >= 0.8;
        $costWarning = $costLimit !== null && $costLimit > 0 && ($costUsed / $costLimit) >= 0.8;

        return new QuotaCheckResultDTO(
            canProceed: !$articlesExceeded && !$costExceeded,
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
