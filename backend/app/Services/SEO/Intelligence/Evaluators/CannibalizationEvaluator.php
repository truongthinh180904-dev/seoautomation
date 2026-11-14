<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\GscSearchPerformanceDaily;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CannibalizationEvaluator implements InsightEvaluatorInterface
{
    /**
     * Maximum position to consider cannibalization significant (Top 20).
     */
    protected const MAX_POSITION_THRESHOLD = 20.0;

    /**
     * Minimum impressions per competing URL.
     */
    protected const MIN_IMPRESSIONS_THRESHOLD = 50;

    public function evaluate(Website $website): array
    {
        $insights = [];
        $twentyEightDaysAgo = Carbon::today()->subDays(28)->toDateString();

        // 1. Group by query and page_url
        $queryPageStats = GscSearchPerformanceDaily::query()
            ->select(
                'query',
                'query_hash',
                'page_url',
                'page_hash',
                DB::raw('SUM(clicks) as total_clicks'),
                DB::raw('SUM(impressions) as total_impressions'),
                DB::raw('AVG(position) as avg_position')
            )
            ->where('website_id', $website->id)
            ->where('date', '>=', $twentyEightDaysAgo)
            ->groupBy('query', 'query_hash', 'page_url', 'page_hash')
            ->having('avg_position', '<=', self::MAX_POSITION_THRESHOLD)
            ->having('total_impressions', '>=', self::MIN_IMPRESSIONS_THRESHOLD)
            ->get();

        // Group pages by query_hash
        $grouped = [];
        foreach ($queryPageStats as $row) {
            $grouped[$row->query_hash][] = $row;
        }

        foreach ($grouped as $queryHash => $rows) {
            // Check if 2 or more distinct pages are competing for this query
            if (count($rows) >= 2) {
                $queryName = $rows[0]->query;
                $competingPages = [];
                $totalClicks = 0;
                $totalImpressions = 0;

                foreach ($rows as $row) {
                    $totalClicks += (int) $row->total_clicks;
                    $totalImpressions += (int) $row->total_impressions;
                    $competingPages[] = [
                        'url' => $row->page_url,
                        'avg_position' => round((float) $row->avg_position, 1),
                        'clicks' => (int) $row->total_clicks,
                        'impressions' => (int) $row->total_impressions,
                    ];
                }

                $opportunityScore = min(90, max(70, 70 + (count($competingPages) * 5)));

                $urlsListText = collect($competingPages)
                    ->map(fn ($p) => "{$p['url']} (Hạng #{$p['avg_position']})")
                    ->implode(' và ');

                $insights[] = [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'article_id' => null,
                    'type' => 'cannibalization',
                    'severity' => 'warning',
                    'opportunity_score' => $opportunityScore,
                    'title' => 'Ăn thịt từ khóa (Cannibalization): '.count($competingPages)." trang cùng xếp hạng '{$queryName}'",
                    'description' => "Từ khóa '{$queryName}' đang bị cạnh tranh nội bộ giữa các trang: {$urlsListText}. Tình trạng phân mảnh này làm suy yếu thẩm quyền SEO tổng thể. Khuyến nghị hợp nhất nội dung hoặc chỉ định thẻ Canonical về trang chuyển đổi chính.",
                    'suggested_action' => 'canonicalize_or_merge',
                    'metrics_snapshot' => [
                        'target_key' => 'cannibalization:query:'.$queryHash,
                        'query' => $queryName,
                        'query_hash' => $queryHash,
                        'competing_urls_count' => count($competingPages),
                        'competing_pages' => $competingPages,
                        'total_clicks' => $totalClicks,
                        'total_impressions' => $totalImpressions,
                        'opportunity_score' => $opportunityScore,
                    ],
                ];
            }
        }

        return $insights;
    }
}
