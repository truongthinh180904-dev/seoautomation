<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\Article;
use App\Models\GscSearchPerformanceDaily;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrafficDecayEvaluator implements InsightEvaluatorInterface
{
    /**
     * Minimum clicks in Week 1 to qualify for decay detection.
     */
    protected const MIN_WEEK1_CLICKS = 10;

    /**
     * Minimum drop percentage from W1 to W4 (25%).
     */
    protected const DECAY_PERCENT_THRESHOLD = 25.0;

    public function evaluate(Website $website): array
    {
        $insights = [];
        $today = Carbon::today();

        // Define 4 consecutive 7-day intervals
        $w4Start = $today->copy()->subDays(6)->toDateString();
        $w4End = $today->toDateString();

        $w3Start = $today->copy()->subDays(13)->toDateString();
        $w3End = $today->copy()->subDays(7)->toDateString();

        $w2Start = $today->copy()->subDays(20)->toDateString();
        $w2End = $today->copy()->subDays(14)->toDateString();

        $w1Start = $today->copy()->subDays(27)->toDateString();
        $w1End = $today->copy()->subDays(21)->toDateString();

        // Fetch weekly clicks grouped by page_url & page_hash
        $w1Records = GscSearchPerformanceDaily::query()
            ->select('page_url', 'page_hash', DB::raw('SUM(clicks) as clicks'))
            ->where('website_id', $website->id)
            ->whereBetween('date', [$w1Start, $w1End])
            ->groupBy('page_url', 'page_hash')
            ->having('clicks', '>=', self::MIN_WEEK1_CLICKS)
            ->pluck('clicks', 'page_hash')
            ->all();

        if (empty($w1Records)) {
            return $insights;
        }

        $w2Records = GscSearchPerformanceDaily::query()
            ->select('page_hash', DB::raw('SUM(clicks) as clicks'))
            ->where('website_id', $website->id)
            ->whereBetween('date', [$w2Start, $w2End])
            ->whereIn('page_hash', array_keys($w1Records))
            ->groupBy('page_hash')
            ->pluck('clicks', 'page_hash')
            ->all();

        $w3Records = GscSearchPerformanceDaily::query()
            ->select('page_hash', DB::raw('SUM(clicks) as clicks'))
            ->where('website_id', $website->id)
            ->whereBetween('date', [$w3Start, $w3End])
            ->whereIn('page_hash', array_keys($w1Records))
            ->groupBy('page_hash')
            ->pluck('clicks', 'page_hash')
            ->all();

        $w4Records = GscSearchPerformanceDaily::query()
            ->select('page_url', 'page_hash', DB::raw('SUM(clicks) as clicks'))
            ->where('website_id', $website->id)
            ->whereBetween('date', [$w4Start, $w4End])
            ->whereIn('page_hash', array_keys($w1Records))
            ->groupBy('page_url', 'page_hash')
            ->get();

        // Also get page_url lookup
        $pageUrlLookup = GscSearchPerformanceDaily::query()
            ->where('website_id', $website->id)
            ->whereIn('page_hash', array_keys($w1Records))
            ->pluck('page_url', 'page_hash')
            ->all();

        $w4Map = [];
        foreach ($w4Records as $row) {
            $w4Map[$row->page_hash] = (int) $row->clicks;
        }

        foreach ($w1Records as $pageHash => $w1Clicks) {
            $w1 = (int) $w1Clicks;
            $w2 = (int) ($w2Records[$pageHash] ?? 0);
            $w3 = (int) ($w3Records[$pageHash] ?? 0);
            $w4 = (int) ($w4Map[$pageHash] ?? 0);

            $pageUrl = $pageUrlLookup[$pageHash] ?? '';

            $isMonotonicDecay = ($w1 > $w2 && $w2 > $w3 && $w3 > $w4);
            $decayPercent = $w1 > 0 ? round((($w1 - $w4) / $w1) * 100, 1) : 0.0;

            if ($isMonotonicDecay || $decayPercent >= self::DECAY_PERCENT_THRESHOLD) {
                $severity = $decayPercent >= 50.0 ? 'critical' : 'warning';
                $opportunityScore = min(95, max(65, 60 + (int) ($decayPercent / 2)));

                // Try to find matching article
                $matchedArticle = Article::query()
                    ->where('website_id', $website->id)
                    ->where(function ($q) use ($pageUrl) {
                        $q->where('wordpress_post_url', $pageUrl)
                            ->orWhere('canonical_url', $pageUrl);
                    })
                    ->first();

                $pageTitle = $matchedArticle?->title ?? $pageUrl;

                $insights[] = [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'article_id' => $matchedArticle?->id,
                    'type' => 'traffic_decay',
                    'severity' => $severity,
                    'opportunity_score' => $opportunityScore,
                    'title' => "Suy giảm lưu lượng liên tục (Traffic Decay): '{$pageTitle}'",
                    'description' => "Trang {$pageUrl} suy giảm lưu lượng từ {$w1} clicks (Tuần 1) xuống {$w4} clicks (Tuần 4), sụt giảm {$decayPercent}%. Bài viết có dấu hiệu lỗi thời so với Search Intent mới. Khuyến nghị cập nhật và làm mới nội dung.",
                    'suggested_action' => 'refresh_content',
                    'metrics_snapshot' => [
                        'target_key' => 'traffic_decay:page:'.$pageHash,
                        'page_url' => $pageUrl,
                        'page_hash' => $pageHash,
                        'w1_clicks' => $w1,
                        'w2_clicks' => $w2,
                        'w3_clicks' => $w3,
                        'w4_clicks' => $w4,
                        'decay_percent' => $decayPercent,
                        'is_monotonic_decay' => $isMonotonicDecay,
                        'article_id' => $matchedArticle?->id,
                        'opportunity_score' => $opportunityScore,
                    ],
                ];
            }
        }

        return $insights;
    }
}
