<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\Article;
use App\Models\GscSearchPerformanceDaily;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClicksDropEvaluator implements InsightEvaluatorInterface
{
    /**
     * Minimum drop percentage (20%).
     */
    protected const DROP_PERCENT_THRESHOLD = 20.0;

    /**
     * Minimum previous clicks required to avoid low-sample noise.
     */
    protected const MIN_PREVIOUS_CLICKS = 10;

    public function evaluate(Website $website): array
    {
        $insights = [];

        $today = Carbon::today();
        $recentStart = $today->copy()->subDays(6)->toDateString();
        $recentEnd = $today->toDateString();

        $prevStart = $today->copy()->subDays(13)->toDateString();
        $prevEnd = $today->copy()->subDays(7)->toDateString();

        // 1. Overall Website Clicks comparison
        $recentTotalClicks = (int) GscSearchPerformanceDaily::query()
            ->where('website_id', $website->id)
            ->whereBetween('date', [$recentStart, $recentEnd])
            ->sum('clicks');

        $prevTotalClicks = (int) GscSearchPerformanceDaily::query()
            ->where('website_id', $website->id)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->sum('clicks');

        if ($prevTotalClicks >= self::MIN_PREVIOUS_CLICKS && $recentTotalClicks < $prevTotalClicks) {
            $dropPercent = round((($prevTotalClicks - $recentTotalClicks) / $prevTotalClicks) * 100, 1);
            if ($dropPercent >= self::DROP_PERCENT_THRESHOLD) {
                $severity = $dropPercent >= 40.0 ? 'critical' : 'warning';
                $opportunityScore = min(95, max(60, 50 + (int) ($dropPercent / 2)));

                $insights[] = [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'article_id' => null,
                    'type' => 'clicks_drop',
                    'severity' => $severity,
                    'opportunity_score' => $opportunityScore,
                    'title' => "Lưu lượng tìm kiếm toàn website giảm {$dropPercent}% so với tuần trước",
                    'description' => "Tổng số clicks toàn website trong 7 ngày qua ({$recentTotalClicks} clicks) đã sụt giảm {$dropPercent}% so với 7 ngày trước đó ({$prevTotalClicks} clicks). Cần kiểm tra thứ hạng các trang chủ lực và tình trạng thu thập dữ liệu của Google.",
                    'suggested_action' => 'audit_traffic_drop',
                    'metrics_snapshot' => [
                        'target_key' => 'clicks_drop:website',
                        'scope' => 'website',
                        'recent_clicks' => $recentTotalClicks,
                        'previous_clicks' => $prevTotalClicks,
                        'drop_percent' => $dropPercent,
                        'recent_period' => "{$recentStart} to {$recentEnd}",
                        'previous_period' => "{$prevStart} to {$prevEnd}",
                        'opportunity_score' => $opportunityScore,
                    ],
                ];
            }
        }

        // 2. Specific Page URLs with significant drop
        $recentPages = GscSearchPerformanceDaily::query()
            ->select('page_url', 'page_hash', DB::raw('SUM(clicks) as total_clicks'))
            ->where('website_id', $website->id)
            ->whereBetween('date', [$recentStart, $recentEnd])
            ->groupBy('page_url', 'page_hash')
            ->pluck('total_clicks', 'page_hash')
            ->all();

        $prevPages = GscSearchPerformanceDaily::query()
            ->select('page_url', 'page_hash', DB::raw('SUM(clicks) as total_clicks'))
            ->where('website_id', $website->id)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->groupBy('page_url', 'page_hash')
            ->having('total_clicks', '>=', self::MIN_PREVIOUS_CLICKS)
            ->get();

        foreach ($prevPages as $prevPage) {
            $pageHash = $prevPage->page_hash;
            $prevClicks = (int) $prevPage->total_clicks;
            $recentClicks = isset($recentPages[$pageHash]) ? (int) $recentPages[$pageHash] : 0;

            if ($recentClicks < $prevClicks) {
                $pageDropPercent = round((($prevClicks - $recentClicks) / $prevClicks) * 100, 1);
                if ($pageDropPercent >= self::DROP_PERCENT_THRESHOLD) {
                    $severity = $pageDropPercent >= 40.0 ? 'critical' : 'warning';
                    $opportunityScore = min(95, max(60, 55 + (int) ($pageDropPercent / 2)));

                    // Match with Article if exists
                    $matchedArticle = Article::query()
                        ->where('website_id', $website->id)
                        ->where(function ($q) use ($prevPage) {
                            $q->where('wordpress_post_url', $prevPage->page_url)
                                ->orWhere('canonical_url', $prevPage->page_url);
                        })
                        ->first();

                    $pageTitle = $matchedArticle?->title ?? $prevPage->page_url;

                    $insights[] = [
                        'workspace_id' => $website->workspace_id,
                        'website_id' => $website->id,
                        'article_id' => $matchedArticle?->id,
                        'type' => 'clicks_drop',
                        'severity' => $severity,
                        'opportunity_score' => $opportunityScore,
                        'title' => "Trang '{$pageTitle}' giảm {$pageDropPercent}% lượt click tuần này",
                        'description' => "Trang {$prevPage->page_url} chỉ nhận được {$recentClicks} clicks trong 7 ngày qua so với {$prevClicks} clicks ở tuần trước (giảm {$pageDropPercent}%). Cần kiểm tra lại hiển thị tiêu đề và tình trạng index.",
                        'suggested_action' => 'refresh_content',
                        'metrics_snapshot' => [
                            'target_key' => 'clicks_drop:page:'.$pageHash,
                            'scope' => 'page',
                            'page_url' => $prevPage->page_url,
                            'page_hash' => $pageHash,
                            'recent_clicks' => $recentClicks,
                            'previous_clicks' => $prevClicks,
                            'drop_percent' => $pageDropPercent,
                            'article_id' => $matchedArticle?->id,
                            'opportunity_score' => $opportunityScore,
                        ],
                    ];
                }
            }
        }

        return $insights;
    }
}
