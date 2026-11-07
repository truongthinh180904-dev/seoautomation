<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\Article;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HighTrafficLowEngagementEvaluator implements InsightEvaluatorInterface
{
    /**
     * Minimum sessions in 28 days to consider traffic high (>= 100).
     */
    protected const MIN_SESSIONS_THRESHOLD = 100;

    /**
     * Maximum engagement rate threshold (< 30% / 0.30).
     */
    protected const MAX_ENGAGEMENT_RATE = 0.30;

    public function evaluate(Website $website): array
    {
        $insights = [];
        $twentyEightDaysAgo = Carbon::today()->subDays(28)->toDateString();

        $candidates = Ga4PagePerformanceDaily::query()
            ->select(
                'landing_page',
                'page_hash',
                'article_id',
                DB::raw('SUM(sessions) as total_sessions'),
                DB::raw('SUM(active_users) as total_users'),
                DB::raw('AVG(engagement_rate) as avg_engagement_rate'),
                DB::raw('AVG(avg_session_duration) as avg_duration')
            )
            ->where('website_id', $website->id)
            ->where('date', '>=', $twentyEightDaysAgo)
            ->groupBy('landing_page', 'page_hash', 'article_id')
            ->having('total_sessions', '>=', self::MIN_SESSIONS_THRESHOLD)
            ->having('avg_engagement_rate', '<', self::MAX_ENGAGEMENT_RATE)
            ->get();

        foreach ($candidates as $item) {
            $sessions = (int) $item->total_sessions;
            $users = (int) $item->total_users;
            $engagementRate = (float) $item->avg_engagement_rate;
            $engagementRatePercent = round($engagementRate * 100, 1);
            $avgDurationSec = round((float) $item->avg_duration, 0);

            $opportunityScore = min(90, max(65, 60 + (int) min(25, ($sessions / 200))));

            // Find matching article if not linked
            $matchedArticle = null;
            if ($item->article_id) {
                $matchedArticle = Article::find($item->article_id);
            } else {
                $matchedArticle = Article::query()
                    ->where('website_id', $website->id)
                    ->where(function ($q) use ($item) {
                        $q->where('wordpress_post_url', $item->landing_page)
                            ->orWhere('canonical_url', $item->landing_page)
                            ->orWhere('slug', trim($item->landing_page, '/'));
                    })
                    ->first();
            }

            $pageTitle = $matchedArticle?->title ?? $item->landing_page;

            $insights[] = [
                'workspace_id' => $website->workspace_id,
                'website_id' => $website->id,
                'article_id' => $matchedArticle?->id,
                'type' => 'high_traffic_low_engagement',
                'severity' => 'warning',
                'opportunity_score' => $opportunityScore,
                'title' => "Lưu lượng cao nhưng tương tác thấp: '{$pageTitle}'",
                'description' => "Trang {$item->landing_page} đạt ".number_format($sessions)." sessions nhưng tỷ lệ tương tác chỉ đạt {$engagementRatePercent}% (thời lượng trung bình {$avgDurationSec}s). Người dùng rời đi nhanh mà không cuộn xem nội dung. Khuyến nghị cải thiện dàn bài, chèn bảng tóm tắt, bổ sung hình ảnh và lời kêu gọi hành động (CTA).",
                'suggested_action' => 'improve_content_engagement',
                'metrics_snapshot' => [
                    'target_key' => 'high_traffic_low_engagement:page:'.$item->page_hash,
                    'landing_page' => $item->landing_page,
                    'page_hash' => $item->page_hash,
                    'sessions' => $sessions,
                    'active_users' => $users,
                    'engagement_rate' => $engagementRate,
                    'engagement_rate_percent' => $engagementRatePercent,
                    'avg_duration_seconds' => $avgDurationSec,
                    'article_id' => $matchedArticle?->id,
                    'opportunity_score' => $opportunityScore,
                ],
            ];
        }

        return $insights;
    }
}
