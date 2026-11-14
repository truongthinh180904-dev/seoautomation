<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\RankTrackedKeyword;
use App\Models\RankTrackingHistory;
use App\Models\Website;
use Carbon\Carbon;

class RankingDropEvaluator implements InsightEvaluatorInterface
{
    /**
     * Minimum drop in positions required to trigger an anomaly.
     */
    protected const MIN_DROP_THRESHOLD = 3;

    public function evaluate(Website $website): array
    {
        $insights = [];
        $sevenDaysAgo = Carbon::today()->subDays(7)->toDateString();

        $trackedKeywords = RankTrackedKeyword::query()
            ->where('website_id', $website->id)
            ->where('is_active', true)
            ->get();

        foreach ($trackedKeywords as $keyword) {
            $drop = 0;
            $previousPos = null;
            $currentPos = $keyword->current_position;

            // 1. Check history 7 days ago if available
            $history7Days = RankTrackingHistory::query()
                ->where('rank_tracked_keyword_id', $keyword->id)
                ->where('check_date', '<=', $sevenDaysAgo)
                ->orderBy('check_date', 'desc')
                ->first();

            if ($history7Days && $history7Days->position !== null && $currentPos !== null) {
                $pos7DaysAgo = (int) $history7Days->position;
                if ($currentPos - $pos7DaysAgo >= self::MIN_DROP_THRESHOLD) {
                    $drop = $currentPos - $pos7DaysAgo;
                    $previousPos = $pos7DaysAgo;
                }
            }

            // 2. Fallback to previous_position column if no 7-day history or greater drop
            if ($drop < self::MIN_DROP_THRESHOLD && $keyword->previous_position !== null && $currentPos !== null) {
                if ($currentPos - $keyword->previous_position >= self::MIN_DROP_THRESHOLD) {
                    $drop = $currentPos - $keyword->previous_position;
                    $previousPos = $keyword->previous_position;
                }
            }

            // 3. Check if dropped out of Top 100
            $droppedOutOfTop100 = false;
            if ($currentPos === null && $keyword->previous_position !== null) {
                $drop = 101 - $keyword->previous_position;
                $previousPos = $keyword->previous_position;
                $droppedOutOfTop100 = true;
            }

            if ($drop >= self::MIN_DROP_THRESHOLD || $droppedOutOfTop100) {
                $severity = 'warning';
                if ($droppedOutOfTop100 || $drop > 5 || ($previousPos !== null && $previousPos <= 10 && $currentPos !== null && $currentPos > 10)) {
                    $severity = 'critical';
                }

                $opportunityScore = min(95, max(60, 60 + ($drop * 4)));

                $title = $droppedOutOfTop100
                    ? "Từ khóa '{$keyword->keyword}' đã rớt khỏi Top 100 trên SERP"
                    : "Từ khóa '{$keyword->keyword}' tụt {$drop} bậc trên Google SERP";

                $descCurrentPos = $currentPos !== null ? "#{$currentPos}" : 'ngoài Top 100';
                $description = "Từ khóa '{$keyword->keyword}' đã tụt từ vị trí #{$previousPos} xuống {$descCurrentPos} (tụt {$drop} bậc). Cần rà soát lại search intent, đối thủ mới vượt mặt và cấu trúc liên kết nội bộ.";

                $insights[] = [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'article_id' => null,
                    'type' => 'ranking_drop',
                    'severity' => $severity,
                    'opportunity_score' => $opportunityScore,
                    'title' => $title,
                    'description' => $description,
                    'suggested_action' => 'investigate_drop',
                    'metrics_snapshot' => [
                        'target_key' => 'ranking_drop:'.$keyword->id,
                        'keyword_id' => $keyword->id,
                        'keyword' => $keyword->keyword,
                        'current_position' => $currentPos,
                        'previous_position' => $previousPos,
                        'drop_amount' => $drop,
                        'dropped_out_of_top_100' => $droppedOutOfTop100,
                        'target_url' => $keyword->target_url,
                        'country' => $keyword->country,
                        'device' => $keyword->device,
                        'opportunity_score' => $opportunityScore,
                    ],
                ];
            }
        }

        return $insights;
    }
}
