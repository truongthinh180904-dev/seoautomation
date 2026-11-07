<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\GscSearchPerformanceDaily;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StrikingDistanceEvaluator implements InsightEvaluatorInterface
{
    /**
     * Minimum impressions to consider query significant.
     */
    protected const MIN_IMPRESSIONS = 100;

    /**
     * Position range for striking distance (Page 1 outside Top 3: 4.0 - 10.0).
     */
    protected const MIN_POSITION = 4.0;

    protected const MAX_POSITION = 10.0;

    public function evaluate(Website $website): array
    {
        $insights = [];
        $twentyEightDaysAgo = Carbon::today()->subDays(28)->toDateString();

        $candidates = GscSearchPerformanceDaily::query()
            ->select(
                'query',
                'query_hash',
                'page_url',
                DB::raw('SUM(clicks) as total_clicks'),
                DB::raw('SUM(impressions) as total_impressions'),
                DB::raw('AVG(position) as avg_position')
            )
            ->where('website_id', $website->id)
            ->where('date', '>=', $twentyEightDaysAgo)
            ->groupBy('query', 'query_hash', 'page_url')
            ->having('total_impressions', '>=', self::MIN_IMPRESSIONS)
            ->orderBy('total_impressions', 'desc')
            ->get();

        foreach ($candidates as $item) {
            $avgPos = round((float) $item->avg_position, 1);

            if ($avgPos < self::MIN_POSITION || $avgPos > self::MAX_POSITION) {
                continue;
            }

            $impressions = (int) $item->total_impressions;
            $clicks = (int) $item->total_clicks;

            $opportunityScore = min(98, max(85, 85 + (int) min(13, ($impressions / 500))));

            $insights[] = [
                'workspace_id' => $website->workspace_id,
                'website_id' => $website->id,
                'article_id' => null,
                'type' => 'striking_distance',
                'severity' => 'info',
                'opportunity_score' => $opportunityScore,
                'title' => "Cơ hội Top 3 (Striking Distance): Từ khóa '{$item->query}' đang ở hạng #{$avgPos}",
                'description' => "Từ khóa '{$item->query}' đang xếp hạng #{$avgPos} trên Google với ".number_format($impressions).' lượt hiển thị. Đang nằm ngay ngưỡng cửa Top 3! Tăng cường 2-3 liên kết nội bộ (internal links) từ các bài viết liên quan và tối ưu đoạn mở đầu sẽ giúp từ khóa này lọt Top 3 để thu hút 70% clicks.',
                'suggested_action' => 'add_internal_links',
                'metrics_snapshot' => [
                    'target_key' => 'striking_distance:query:'.$item->query_hash,
                    'query' => $item->query,
                    'query_hash' => $item->query_hash,
                    'page_url' => $item->page_url,
                    'avg_position' => $avgPos,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'opportunity_score' => $opportunityScore,
                ],
            ];
        }

        return $insights;
    }
}
