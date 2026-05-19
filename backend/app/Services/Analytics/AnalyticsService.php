<?php

namespace App\Services\Analytics;

use App\Models\AILog;
use App\Models\Article;
use App\Models\Keyword;

class AnalyticsService
{
    public function summary(int $tenantId): array
    {
        $byStatus = Article::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totals = Article::where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total, AVG(seo_score) as avg_seo, AVG(word_count) as avg_words, SUM(ai_cost_usd) as total_cost')
            ->first();

        return [
            'by_status' => $byStatus,
            'total' => $totals->total ?? 0,
            'avg_seo' => round($totals->avg_seo ?? 0, 1),
            'avg_words' => round($totals->avg_words ?? 0),
            'total_cost' => round($totals->total_cost ?? 0, 4),
        ];
    }

    public function aiCosts(int $tenantId, int $days): array
    {
        $data = AILog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(cost_usd) as total_cost, COUNT(*) as calls, SUM(total_tokens) as tokens')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return ['data' => $data, 'days' => $days];
    }

    public function keywordsDaily(int $tenantId, int $days): array
    {
        $data = Keyword::where('tenant_id', $tenantId)
            ->where('updated_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(updated_at) as date, status, COUNT(*) as count')
            ->groupByRaw('DATE(updated_at), status')
            ->orderBy('date')
            ->get();

        return ['data' => $data, 'days' => $days];
    }

    public function failingAgents(int $tenantId, int $days): array
    {
        $data = AILog::where('tenant_id', $tenantId)
            ->whereIn('status', ['failed', 'rate_limited'])
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('agent_type, status, COUNT(*) as count')
            ->groupByRaw('agent_type, status')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        return ['data' => $data];
    }
}
