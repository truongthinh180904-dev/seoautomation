<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\GscSearchPerformanceDaily;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HighImpLowCtrEvaluator implements InsightEvaluatorInterface
{
    /**
     * Threshold for impressions (>= 1,000).
     */
    protected const IMPRESSIONS_THRESHOLD = 1000;

    /**
     * Threshold for CTR (< 2% / 0.02).
     */
    protected const CTR_THRESHOLD = 0.02;

    public function evaluate(Website $website): array
    {
        $insights = [];
        $twentyEightDaysAgo = Carbon::today()->subDays(28)->toDateString();

        // 1. Evaluate by Query
        $lowCtrQueries = GscSearchPerformanceDaily::query()
            ->select(
                'query',
                'query_hash',
                DB::raw('SUM(clicks) as total_clicks'),
                DB::raw('SUM(impressions) as total_impressions'),
                DB::raw('AVG(position) as avg_position')
            )
            ->where('website_id', $website->id)
            ->where('date', '>=', $twentyEightDaysAgo)
            ->groupBy('query', 'query_hash')
            ->having('total_impressions', '>=', self::IMPRESSIONS_THRESHOLD)
            ->get();

        foreach ($lowCtrQueries as $item) {
            $impressions = (int) $item->total_impressions;
            $clicks = (int) $item->total_clicks;
            $ctr = $impressions > 0 ? ($clicks / $impressions) : 0.0;

            if ($ctr < self::CTR_THRESHOLD) {
                $ctrPercent = round($ctr * 100, 2);
                $avgPos = round((float) $item->avg_position, 1);
                $opportunityScore = min(98, max(75, 75 + (int) min(20, ($impressions / 2000))));

                $insights[] = [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'article_id' => null,
                    'type' => 'high_imp_low_ctr',
                    'severity' => 'warning',
                    'opportunity_score' => $opportunityScore,
                    'title' => "Cơ hội CTR: Truy vấn '{$item->query}' có ".number_format($impressions)." hiển thị nhưng CTR chỉ {$ctrPercent}%",
                    'description' => "Truy vấn '{$item->query}' xếp hạng trung bình #{$avgPos} với ".number_format($impressions)." lượt hiển thị trên Google trong 28 ngày qua, nhưng tỷ lệ nhấp chỉ đạt {$ctrPercent}% (dưới ngưỡng 2%). Tối ưu thẻ Title và Meta Description sẽ giúp nhân đôi lượng click mà không cần đẩy thêm thứ hạng.",
                    'suggested_action' => 'optimize_title_meta',
                    'metrics_snapshot' => [
                        'target_key' => 'high_imp_low_ctr:query:'.$item->query_hash,
                        'scope' => 'query',
                        'query' => $item->query,
                        'query_hash' => $item->query_hash,
                        'impressions' => $impressions,
                        'clicks' => $clicks,
                        'ctr' => $ctr,
                        'ctr_percent' => $ctrPercent,
                        'avg_position' => $avgPos,
                        'opportunity_score' => $opportunityScore,
                    ],
                ];
            }
        }

        return $insights;
    }
}
