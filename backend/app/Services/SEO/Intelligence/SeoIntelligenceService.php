<?php

namespace App\Services\SEO\Intelligence;

use App\Models\SeoIntelligenceInsight;
use App\Models\Website;
use App\Services\SEO\Intelligence\Evaluators\CannibalizationEvaluator;
use App\Services\SEO\Intelligence\Evaluators\ClicksDropEvaluator;
use App\Services\SEO\Intelligence\Evaluators\HighImpLowCtrEvaluator;
use App\Services\SEO\Intelligence\Evaluators\HighTrafficLowEngagementEvaluator;
use App\Services\SEO\Intelligence\Evaluators\InsightEvaluatorInterface;
use App\Services\SEO\Intelligence\Evaluators\RankingDropEvaluator;
use App\Services\SEO\Intelligence\Evaluators\StrikingDistanceEvaluator;
use App\Services\SEO\Intelligence\Evaluators\TrafficDecayEvaluator;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SeoIntelligenceService
{
    /**
     * The list of registered evaluator classes.
     *
     * @var array<int, class-string<InsightEvaluatorInterface>>
     */
    protected array $evaluators = [
        RankingDropEvaluator::class,
        ClicksDropEvaluator::class,
        HighImpLowCtrEvaluator::class,
        TrafficDecayEvaluator::class,
        StrikingDistanceEvaluator::class,
        CannibalizationEvaluator::class,
        HighTrafficLowEngagementEvaluator::class,
    ];

    /**
     * Run all heuristic evaluations on a website and persist non-duplicate insights.
     * Guaranteed 0 credit usage (pure local rule-based analytics).
     */
    public function analyzeWebsite(Website|int $website): Collection
    {
        if (is_int($website)) {
            $website = Website::findOrFail($website);
        }

        $allCandidates = [];

        foreach ($this->evaluators as $evaluatorClass) {
            /** @var InsightEvaluatorInterface $evaluator */
            $evaluator = app($evaluatorClass);
            $candidates = $evaluator->evaluate($website);
            foreach ($candidates as $candidate) {
                $allCandidates[] = $candidate;
            }
        }

        // Fetch all existing active insights for this website to prevent duplicate records
        $existingActive = SeoIntelligenceInsight::query()
            ->where('website_id', $website->id)
            ->whereIn('status', ['detected', 'in_progress'])
            ->get();

        $existingMap = [];
        foreach ($existingActive as $insight) {
            $targetKey = $insight->metrics_snapshot['target_key'] ?? (string) $insight->id;
            $mapKey = "{$insight->type}:{$targetKey}";
            $existingMap[$mapKey] = $insight;
        }

        $savedInsights = new Collection;

        DB::transaction(function () use ($allCandidates, &$existingMap, &$savedInsights): void {
            foreach ($allCandidates as $candidate) {
                $targetKey = $candidate['metrics_snapshot']['target_key'] ?? '';
                $mapKey = "{$candidate['type']}:{$targetKey}";

                if (isset($existingMap[$mapKey])) {
                    // Update existing active insight with fresh metrics without creating duplicates
                    /** @var SeoIntelligenceInsight $existing */
                    $existing = $existingMap[$mapKey];
                    $existing->update([
                        'severity' => $candidate['severity'],
                        'opportunity_score' => $candidate['opportunity_score'] ?? $existing->opportunity_score,
                        'title' => $candidate['title'],
                        'description' => $candidate['description'],
                        'metrics_snapshot' => $candidate['metrics_snapshot'],
                        'suggested_action' => $candidate['suggested_action'],
                    ]);
                    $savedInsights->push($existing);
                } else {
                    // Create new insight
                    $newInsight = SeoIntelligenceInsight::create([
                        'workspace_id' => $candidate['workspace_id'],
                        'website_id' => $candidate['website_id'],
                        'article_id' => $candidate['article_id'] ?? null,
                        'type' => $candidate['type'],
                        'severity' => $candidate['severity'] ?? 'info',
                        'opportunity_score' => $candidate['opportunity_score'] ?? 0,
                        'title' => $candidate['title'],
                        'description' => $candidate['description'],
                        'metrics_snapshot' => $candidate['metrics_snapshot'],
                        'suggested_action' => $candidate['suggested_action'] ?? 'investigate',
                        'status' => 'detected',
                    ]);
                    $existingMap[$mapKey] = $newInsight;
                    $savedInsights->push($newInsight);
                }
            }
        });

        return $savedInsights;
    }

    /**
     * Retrieve paginated SEO intelligence insights for a website with filters.
     */
    public function getInsights(int $websiteId, array $filters = []): LengthAwarePaginator
    {
        $query = SeoIntelligenceInsight::query()
            ->with(['article'])
            ->where('website_id', $websiteId);

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereIn('status', ['detected', 'in_progress']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['article_id'])) {
            $query->where('article_id', (int) $filters['article_id']);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->orderBy('opportunity_score', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get aggregate statistics and summary metrics for SEO insights.
     */
    public function getSummary(int $websiteId): array
    {
        $activeInsights = SeoIntelligenceInsight::query()
            ->where('website_id', $websiteId)
            ->whereIn('status', ['detected', 'in_progress'])
            ->get();

        $resolvedCount = SeoIntelligenceInsight::query()
            ->where('website_id', $websiteId)
            ->where('status', 'resolved')
            ->count();

        $dismissedCount = SeoIntelligenceInsight::query()
            ->where('website_id', $websiteId)
            ->where('status', 'dismissed')
            ->count();

        $criticalCount = $activeInsights->where('severity', 'critical')->count();
        $warningCount = $activeInsights->where('severity', 'warning')->count();
        $infoCount = $activeInsights->where('severity', 'info')->count();

        $opportunityCount = $activeInsights->filter(function ($item) {
            return in_array($item->type, ['striking_distance', 'high_imp_low_ctr'], true) || $item->opportunity_score >= 80;
        })->count();

        $avgScore = $activeInsights->isNotEmpty()
            ? round($activeInsights->avg('opportunity_score'), 1)
            : 0.0;

        $byType = [];
        foreach ($activeInsights as $insight) {
            $byType[$insight->type] = ($byType[$insight->type] ?? 0) + 1;
        }

        return [
            'total_active' => $activeInsights->count(),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'opportunity_count' => $opportunityCount,
            'resolved_count' => $resolvedCount,
            'dismissed_count' => $dismissedCount,
            'avg_opportunity_score' => $avgScore,
            'by_type' => $byType,
        ];
    }

    /**
     * Dismiss an insight.
     */
    public function dismiss(int $insightId, int $workspaceId): SeoIntelligenceInsight
    {
        $insight = SeoIntelligenceInsight::query()
            ->where('id', $insightId)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        $insight->update([
            'status' => 'dismissed',
        ]);

        return $insight;
    }

    /**
     * Execute or advance an insight to in_progress or resolved state.
     */
    public function takeAction(int $insightId, string $action, int $workspaceId): SeoIntelligenceInsight
    {
        $insight = SeoIntelligenceInsight::query()
            ->where('id', $insightId)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        $validStatuses = ['in_progress', 'resolved', 'dismissed', 'detected'];
        if (! in_array($action, $validStatuses, true)) {
            // If action is a specific task command like "start_refresh", mark as in_progress
            $action = 'in_progress';
        }

        $updateData = [
            'status' => $action,
        ];

        if ($action === 'resolved') {
            $updateData['resolved_at'] = Carbon::now();
        }

        $insight->update($updateData);

        return $insight;
    }

    /**
     * Calculate opportunity score (0 - 100) based on heuristic rules.
     */
    public function calculateOpportunityScore(string $type, array $metrics): int
    {
        return match ($type) {
            'striking_distance' => min(98, max(85, 85 + (int) min(13, (($metrics['impressions'] ?? 100) / 500)))),
            'high_imp_low_ctr' => min(98, max(75, 75 + (int) min(20, (($metrics['impressions'] ?? 1000) / 2000)))),
            'traffic_decay' => min(95, max(65, 60 + (int) (($metrics['decay_percent'] ?? 25) / 2))),
            'ranking_drop' => min(95, max(60, 60 + ((int) ($metrics['drop_amount'] ?? 3) * 4))),
            'cannibalization' => min(90, max(70, 70 + (((int) ($metrics['competing_urls_count'] ?? 2)) * 5))),
            'high_traffic_low_engagement' => min(90, max(65, 60 + (int) min(25, (($metrics['sessions'] ?? 100) / 200)))),
            'clicks_drop' => min(95, max(60, 50 + (int) (($metrics['drop_percent'] ?? 20) / 2))),
            default => 50,
        };
    }
}
