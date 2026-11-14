<?php

namespace App\Services\Analytics;

use App\Models\AILog;
use App\Models\AiUsageLog;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\Keyword;
use App\Models\PaymentOrder;
use App\Models\Task;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;

class AnalyticsService
{
    /**
     * Article counts per status + averages + credit and website costs.
     */
    public function summary(int $tenantId, ?int $workspaceId = null, int $days = 30): array
    {
        $articleQuery = Article::query();
        if ($workspaceId) {
            $articleQuery->where('workspace_id', $workspaceId);
        } else {
            $articleQuery->where('tenant_id', $tenantId);
        }

        $byStatus = (clone $articleQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totals = (clone $articleQuery)
            ->selectRaw('COUNT(*) as total, AVG(seo_score) as avg_seo, AVG(word_count) as avg_words, SUM(ai_cost_usd) as total_cost')
            ->first();

        // Thống kê số lượng Từ khóa và Chiến dịch cho Dashboard KPI
        $keywordQuery = Keyword::query();
        if ($workspaceId) {
            $keywordQuery->where('workspace_id', $workspaceId);
        } else {
            $keywordQuery->where('tenant_id', $tenantId);
        }
        $totalKeywords = $keywordQuery->count();

        $campaignQuery = Campaign::query();
        if ($workspaceId) {
            $campaignQuery->where('workspace_id', $workspaceId);
        } else {
            $campaignQuery->where('tenant_id', $tenantId);
        }
        $totalCampaigns = $campaignQuery->count();

        // Tiêu thụ credit theo ngày gần đây từ credit_transactions
        $creditHistory = [];
        if ($workspaceId) {
            $creditQuery = CreditTransaction::where('workspace_id', $workspaceId)
                ->where('created_at', '>=', now()->subDays($days))
                ->where('amount', '<', 0)
                ->selectRaw('DATE(created_at) as date, ABS(SUM(amount)) as credits_used, COUNT(*) as tx_count')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->get();

            $creditHistory = $creditQuery->map(fn ($r) => [
                'date' => (string) $r->date,
                'credits_used' => (int) $r->credits_used,
                'tx_count' => (int) $r->tx_count,
            ])->all();
        }

        // Chi phí AI theo website
        $costsByWebsite = $this->aiCostsByWebsite($tenantId, $workspaceId, $days);

        return [
            'by_status' => $byStatus,
            'total' => (int) ($totals->total ?? 0),
            'total_keywords' => $totalKeywords,
            'total_campaigns' => $totalCampaigns,
            'avg_seo' => round((float) ($totals->avg_seo ?? 0), 1),
            'avg_words' => round((float) ($totals->avg_words ?? 0)),
            'total_cost' => round((float) ($totals->total_cost ?? 0), 4),
            'credit_history_daily' => $creditHistory,
            'ai_costs_by_website' => $costsByWebsite,
        ];
    }

    /**
     * AI cost per day for last N days.
     */
    public function aiCosts(int $tenantId, int $days = 30, ?int $workspaceId = null): array
    {
        $aiUsageQuery = AiUsageLog::query()->where('created_at', '>=', now()->subDays($days));
        if ($workspaceId) {
            $aiUsageQuery->where('workspace_id', $workspaceId);
        } else {
            $aiUsageQuery->where('tenant_id', $tenantId);
        }

        $data = $aiUsageQuery
            ->selectRaw('DATE(created_at) as date, SUM(cost_usd) as total_cost, COUNT(*) as calls, SUM(total_tokens) as tokens')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        if ($data->isEmpty()) {
            $data = AILog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('DATE(created_at) as date, SUM(cost_usd) as total_cost, COUNT(*) as calls, SUM(total_tokens) as tokens')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->get();
        }

        return ['data' => $data, 'days' => $days];
    }

    /**
     * Chi phí AI nhóm theo Website thực tế.
     */
    public function aiCostsByWebsite(int $tenantId, ?int $workspaceId = null, int $days = 30): array
    {
        $query = AiUsageLog::query()
            ->whereNotNull('website_id')
            ->where('created_at', '>=', now()->subDays($days));

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $rows = $query->selectRaw('website_id, SUM(cost_usd) as total_cost, SUM(credits_charged) as total_credits, COUNT(*) as total_calls')
            ->groupBy('website_id')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            $artQuery = Article::query()
                ->where('created_at', '>=', now()->subDays($days));

            if ($workspaceId) {
                $artQuery->where('workspace_id', $workspaceId);
            } else {
                $artQuery->where('tenant_id', $tenantId);
            }

            $rows = (clone $artQuery)
                ->whereNotNull('website_id')
                ->selectRaw('website_id, SUM(ai_cost_usd) as total_cost, COUNT(*) as total_calls')
                ->groupBy('website_id')
                ->orderByDesc('total_cost')
                ->limit(10)
                ->get();

            if ($rows->isEmpty()) {
                // Fallback theo wordpress_site_id trên Article khi website_id trống
                $wpRows = (clone $artQuery)
                    ->whereNotNull('wordpress_site_id')
                    ->selectRaw('wordpress_site_id, SUM(ai_cost_usd) as total_cost, COUNT(*) as total_calls')
                    ->groupBy('wordpress_site_id')
                    ->orderByDesc('total_cost')
                    ->limit(10)
                    ->get();

                if ($wpRows->isNotEmpty()) {
                    $wpSiteIds = $wpRows->pluck('wordpress_site_id')->all();
                    $wpSiteQuery = WordPressSite::whereIn('id', $wpSiteIds)
                        ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId));
                    $wpSites = $wpSiteQuery->get()->keyBy('id');

                    return $wpRows->map(function ($row) use ($wpSites) {
                        $site = $wpSites->get($row->wordpress_site_id);

                        return [
                            'website_id' => $row->wordpress_site_id,
                            'name' => $site?->name ?? "WordPress #{$row->wordpress_site_id}",
                            'domain' => $site?->url ? (parse_url($site->url, PHP_URL_HOST) ?: $site->url) : '',
                            'cost_usd' => round((float) ($row->total_cost ?? 0), 4),
                            'calls' => (int) ($row->total_calls ?? 0),
                            'credits' => 0,
                        ];
                    })->values()->all();
                }
            }
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $websiteIds = $rows->pluck('website_id')->all();
        $websites = Website::whereIn('id', $websiteIds)
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($websites) {
            $site = $websites->get($row->website_id);

            return [
                'website_id' => $row->website_id,
                'name' => $site?->name ?? "Website #{$row->website_id}",
                'domain' => $site?->domain ?? '',
                'cost_usd' => round((float) ($row->total_cost ?? 0), 4),
                'calls' => (int) ($row->total_calls ?? 0),
                'credits' => (int) ($row->total_credits ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Keywords processed per day for last N days.
     */
    public function keywordsDaily(int $tenantId, int $days = 30, ?int $workspaceId = null): array
    {
        $query = Keyword::query()->where('updated_at', '>=', now()->subDays($days));
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $data = $query
            ->selectRaw('DATE(updated_at) as date, status, COUNT(*) as count')
            ->groupByRaw('DATE(updated_at), status')
            ->orderBy('date')
            ->get();

        return ['data' => $data, 'days' => $days];
    }

    /**
     * Top failing agents by error count.
     */
    public function failingAgents(int $tenantId, int $days = 30, ?int $workspaceId = null): array
    {
        if ($workspaceId) {
            $rows = AiUsageLog::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', ['failed', 'rate_limited'])
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('job_type as agent_type, status, COUNT(*) as count')
                ->groupByRaw('job_type, status')
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            if ($rows->isNotEmpty()) {
                return ['data' => $rows];
            }
        }

        $query = AILog::query()
            ->whereIn('status', ['failed', 'rate_limited'])
            ->where('created_at', '>=', now()->subDays($days))
            ->where('tenant_id', $tenantId);

        $data = $query
            ->selectRaw('agent_type, status, COUNT(*) as count')
            ->groupByRaw('agent_type, status')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        return ['data' => $data];
    }

    /**
     * Tổng quan toàn hệ thống cho Super Admin.
     */
    public function platformOverview(int $days = 30): array
    {
        $totalWorkspaces = Workspace::count();
        $activeWorkspaces = Workspace::where('status', 'active')->count();
        $suspendedWorkspaces = Workspace::where('status', 'suspended')->count();

        $totalUsers = User::count();
        $totalWebsites = Website::count();
        $totalArticles = Article::withoutGlobalScopes()->count();
        $totalKeywords = Keyword::withoutGlobalScopes()->count();

        $aiCostSum = AiUsageLog::withoutGlobalScopes()->sum('cost_usd') ?: AILog::withoutGlobalScopes()->sum('cost_usd') ?: 0;
        $totalTokens = AiUsageLog::withoutGlobalScopes()->sum('total_tokens') ?: AILog::withoutGlobalScopes()->sum('total_tokens') ?: 0;

        $ordersQuery = PaymentOrder::withoutGlobalScopes();
        $totalOrders = (clone $ordersQuery)->count();
        $pendingOrders = (clone $ordersQuery)->whereIn('status', ['pending_payment', 'pending_verification'])->count();
        $approvedOrders = (clone $ordersQuery)->where('status', 'approved')->count();

        $providers = AiUsageLog::withoutGlobalScopes()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('provider, SUM(cost_usd) as cost, COUNT(*) as calls')
            ->groupBy('provider')
            ->get()
            ->map(fn ($p) => [
                'provider' => (string) ($p->provider ?: 'OpenAI'),
                'cost' => round((float) $p->cost, 4),
                'calls' => (int) $p->calls,
            ]);

        $failedTasksCount = Task::withoutGlobalScopes()->where('status', 'failed')->count();
        $recentFailedTasks = Task::withoutGlobalScopes()
            ->where('status', 'failed')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'workspace_id', 'type as task_type', 'error_message', 'created_at']);

        return [
            'workspaces' => [
                'total' => $totalWorkspaces,
                'active' => $activeWorkspaces,
                'suspended' => $suspendedWorkspaces,
            ],
            'users' => [
                'total' => $totalUsers,
            ],
            'websites' => [
                'total' => $totalWebsites,
            ],
            'articles' => [
                'total' => $totalArticles,
            ],
            'keywords' => [
                'total' => $totalKeywords,
            ],
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'approved' => $approvedOrders,
            ],
            'ai' => [
                'total_cost_usd' => round((float) $aiCostSum, 4),
                'total_tokens' => (int) $totalTokens,
                'providers' => $providers,
            ],
            'queue' => [
                'failed_tasks_count' => $failedTasksCount,
                'recent_failed_tasks' => $recentFailedTasks,
            ],
        ];
    }
}
