<?php

namespace App\Services\SEO\Automation;

use App\Enums\TaskStatus;
use App\Events\SeoAnomalyDetectedEvent;
use App\Jobs\SEO\SyncWebsiteGa4DataJob;
use App\Jobs\SEO\SyncWebsiteGscDataJob;
use App\Jobs\SEO\TrackKeywordRankingJob;
use App\Models\RankTrackedKeyword;
use App\Models\Task;
use App\Models\Website;
use App\Models\WebsiteAutomationRule;
use App\Models\WebsiteGoogleAnalytics;
use App\Models\WebsiteSearchConsole;
use App\Services\SEO\Intelligence\SeoIntelligenceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SeoAutomationSchedulerService
{
    public function __construct(
        protected SeoIntelligenceService $intelligenceService
    ) {}

    /**
     * Get or create automation rules for a website within tenant context.
     */
    public function getRulesForWebsite(int $workspaceId, int $websiteId): WebsiteAutomationRule
    {
        $website = Website::where('workspace_id', $workspaceId)->findOrFail($websiteId);

        return WebsiteAutomationRule::getOrCreateForWebsite($workspaceId, $website->id);
    }

    /**
     * Update automation rules for a website.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRulesForWebsite(int $workspaceId, int $websiteId, array $data): WebsiteAutomationRule
    {
        $rule = $this->getRulesForWebsite($workspaceId, $websiteId);

        $rule->update(array_filter([
            'sync_gsc_enabled' => array_key_exists('sync_gsc_enabled', $data) ? (bool) $data['sync_gsc_enabled'] : null,
            'sync_ga4_enabled' => array_key_exists('sync_ga4_enabled', $data) ? (bool) $data['sync_ga4_enabled'] : null,
            'rank_tracking_enabled' => array_key_exists('rank_tracking_enabled', $data) ? (bool) $data['rank_tracking_enabled'] : null,
            'anomaly_scan_enabled' => array_key_exists('anomaly_scan_enabled', $data) ? (bool) $data['anomaly_scan_enabled'] : null,
            'alert_ranking_drop_threshold' => array_key_exists('alert_ranking_drop_threshold', $data) ? (int) $data['alert_ranking_drop_threshold'] : null,
            'alert_traffic_drop_percentage' => array_key_exists('alert_traffic_drop_percentage', $data) ? (int) $data['alert_traffic_drop_percentage'] : null,
            'email_notifications_enabled' => array_key_exists('email_notifications_enabled', $data) ? (bool) $data['email_notifications_enabled'] : null,
        ], fn ($val) => ! is_null($val)));

        return $rule->fresh();
    }

    /**
     * Dispatch GSC synchronization for all active websites with GSC sync enabled.
     *
     * @return array<string, int>
     */
    public function syncAllWebsitesGsc(?int $specificWebsiteId = null): array
    {
        $query = Website::where('status', 'active')->with(['workspace']);

        if ($specificWebsiteId) {
            $query->where('id', $specificWebsiteId);
        }

        $websites = $query->get();
        $dispatched = 0;
        $skipped = 0;

        foreach ($websites as $website) {
            $rule = WebsiteAutomationRule::getOrCreateForWebsite($website->workspace_id, $website->id);

            if (! $rule->sync_gsc_enabled) {
                Log::info("[SeoAutomationScheduler] GSC sync disabled for website #{$website->id}, skipping.");
                $skipped++;

                continue;
            }

            $hasGsc = WebsiteSearchConsole::where('website_id', $website->id)
                ->where('is_verified', true)
                ->exists();

            if (! $hasGsc) {
                Log::info("[SeoAutomationScheduler] Website #{$website->id} has no verified GSC property, skipping.");
                $skipped++;

                continue;
            }

            // Record task for tracking
            $this->createTaskLog(
                workspaceId: $website->workspace_id,
                websiteId: $website->id,
                type: 'gsc_sync',
                details: ['source' => 'scheduler_auto_sync']
            );

            SyncWebsiteGscDataJob::dispatch($website->workspace_id, $website->id);
            $dispatched++;
        }

        return [
            'total_websites' => count($websites),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Dispatch GA4 synchronization for all active websites with GA4 sync enabled.
     *
     * @return array<string, int>
     */
    public function syncAllWebsitesGa4(?int $specificWebsiteId = null): array
    {
        $query = Website::where('status', 'active')->with(['workspace']);

        if ($specificWebsiteId) {
            $query->where('id', $specificWebsiteId);
        }

        $websites = $query->get();
        $dispatched = 0;
        $skipped = 0;

        foreach ($websites as $website) {
            $rule = WebsiteAutomationRule::getOrCreateForWebsite($website->workspace_id, $website->id);

            if (! $rule->sync_ga4_enabled) {
                Log::info("[SeoAutomationScheduler] GA4 sync disabled for website #{$website->id}, skipping.");
                $skipped++;

                continue;
            }

            $hasGa4 = WebsiteGoogleAnalytics::where('website_id', $website->id)
                ->where('is_verified', true)
                ->exists();

            if (! $hasGa4) {
                Log::info("[SeoAutomationScheduler] Website #{$website->id} has no verified GA4 property, skipping.");
                $skipped++;

                continue;
            }

            // Record task for tracking
            $this->createTaskLog(
                workspaceId: $website->workspace_id,
                websiteId: $website->id,
                type: 'ga4_sync',
                details: ['source' => 'scheduler_auto_sync']
            );

            SyncWebsiteGa4DataJob::dispatch($website->workspace_id, $website->id);
            $dispatched++;
        }

        return [
            'total_websites' => count($websites),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Dispatch rank tracking jobs for all active keywords on websites where rank tracking is enabled.
     *
     * @return array<string, int>
     */
    public function trackAllRankings(?int $specificWebsiteId = null): array
    {
        $query = Website::where('status', 'active');

        if ($specificWebsiteId) {
            $query->where('id', $specificWebsiteId);
        }

        $websites = $query->get();
        $dispatchedKeywords = 0;
        $skippedWebsites = 0;

        foreach ($websites as $website) {
            $rule = WebsiteAutomationRule::getOrCreateForWebsite($website->workspace_id, $website->id);

            if (! $rule->rank_tracking_enabled) {
                Log::info("[SeoAutomationScheduler] Rank tracking disabled for website #{$website->id}, skipping.");
                $skippedWebsites++;

                continue;
            }

            $keywords = RankTrackedKeyword::where('website_id', $website->id)
                ->where('is_active', true)
                ->get();

            if ($keywords->isEmpty()) {
                continue;
            }

            // Record task for rank tracking batch
            $this->createTaskLog(
                workspaceId: $website->workspace_id,
                websiteId: $website->id,
                type: 'rank_tracking_batch',
                details: ['keyword_count' => $keywords->count()]
            );

            foreach ($keywords as $keyword) {
                TrackKeywordRankingJob::dispatch($keyword->workspace_id, $keyword->id);
                $dispatchedKeywords++;
            }
        }

        return [
            'total_websites' => count($websites),
            'dispatched_keywords' => $dispatchedKeywords,
            'skipped_websites' => $skippedWebsites,
        ];
    }

    /**
     * Scan SEO Intelligence heuristics and trigger alert notifications for critical anomalies.
     *
     * @return array<string, int>
     */
    public function scanAllAnomalies(?int $specificWebsiteId = null): array
    {
        $query = Website::where('status', 'active');

        if ($specificWebsiteId) {
            $query->where('id', $specificWebsiteId);
        }

        $websites = $query->get();
        $totalInsights = 0;
        $criticalAlerts = 0;
        $skippedWebsites = 0;

        foreach ($websites as $website) {
            $rule = WebsiteAutomationRule::getOrCreateForWebsite($website->workspace_id, $website->id);

            if (! $rule->anomaly_scan_enabled) {
                Log::info("[SeoAutomationScheduler] Anomaly scan disabled for website #{$website->id}, skipping.");
                $skippedWebsites++;

                continue;
            }

            $newInsights = $this->intelligenceService->analyzeWebsite($website);
            $totalInsights += $newInsights->count();

            foreach ($newInsights as $insight) {
                $isCritical = ($insight->severity === 'critical');
                $thresholdBreached = null;

                // Check ranking drop threshold
                if ($insight->type === 'ranking_drop') {
                    $dropAmount = (int) ($insight->metrics_snapshot['drop_amount'] ?? 0);
                    if ($dropAmount >= $rule->alert_ranking_drop_threshold) {
                        $isCritical = true;
                        $thresholdBreached = "ranking_drop:{$dropAmount}>={$rule->alert_ranking_drop_threshold}";
                    }
                }

                // Check traffic decay or clicks drop threshold
                if (in_array($insight->type, ['clicks_drop', 'traffic_decay'], true)) {
                    $dropPct = (int) ($insight->metrics_snapshot['drop_percentage'] ?? 0);
                    if ($dropPct >= $rule->alert_traffic_drop_percentage) {
                        $isCritical = true;
                        $thresholdBreached = "traffic_decay:{$dropPct}%>={$rule->alert_traffic_drop_percentage}%";
                    }
                }

                if ($isCritical) {
                    $criticalAlerts++;
                    event(new SeoAnomalyDetectedEvent($insight, $rule, $thresholdBreached));
                }
            }
        }

        return [
            'websites_scanned' => count($websites),
            'insights_detected' => $totalInsights,
            'critical_alerts' => $criticalAlerts,
            'skipped_websites' => $skippedWebsites,
        ];
    }

    /**
     * Get overview of all websites and their automation status within a workspace.
     *
     * @return array<string, mixed>
     */
    public function getAutomationOverview(int $workspaceId): array
    {
        $websites = Website::where('workspace_id', $workspaceId)
            ->with(['workspace'])
            ->get();

        $items = $websites->map(function ($website) use ($workspaceId) {
            $rule = WebsiteAutomationRule::getOrCreateForWebsite($workspaceId, $website->id);
            $hasGsc = WebsiteSearchConsole::where('website_id', $website->id)->where('is_verified', true)->exists();
            $hasGa4 = WebsiteGoogleAnalytics::where('website_id', $website->id)->where('is_verified', true)->exists();
            $keywordCount = RankTrackedKeyword::where('website_id', $website->id)->where('is_active', true)->count();

            return [
                'website_id' => $website->id,
                'website_name' => $website->name,
                'domain' => $website->domain,
                'has_gsc' => $hasGsc,
                'has_ga4' => $hasGa4,
                'active_keywords_count' => $keywordCount,
                'rules' => [
                    'sync_gsc_enabled' => $rule->sync_gsc_enabled,
                    'sync_ga4_enabled' => $rule->sync_ga4_enabled,
                    'rank_tracking_enabled' => $rule->rank_tracking_enabled,
                    'anomaly_scan_enabled' => $rule->anomaly_scan_enabled,
                    'alert_ranking_drop_threshold' => $rule->alert_ranking_drop_threshold,
                    'alert_traffic_drop_percentage' => $rule->alert_traffic_drop_percentage,
                    'email_notifications_enabled' => $rule->email_notifications_enabled,
                    'updated_at' => $rule->updated_at?->toIso8601String(),
                ],
            ];
        });

        return [
            'workspace_id' => $workspaceId,
            'schedules' => [
                ['name' => 'GSC Performance Sync', 'cron' => '0 2 * * *', 'time' => '02:00', 'status' => 'active'],
                ['name' => 'GA4 Analytics Sync', 'cron' => '0 3 * * *', 'time' => '03:00', 'status' => 'active'],
                ['name' => 'Serper Rank Tracking', 'cron' => '0 4 * * *', 'time' => '04:00', 'status' => 'active'],
                ['name' => 'SEO Anomaly Scan', 'cron' => '0 5 * * *', 'time' => '05:00', 'status' => 'active'],
            ],
            'distributed_locking' => [
                'driver' => 'redis/cache',
                'lock_timeout_minutes' => 60,
                'prevent_overlapping' => true,
            ],
            'websites' => $items,
        ];
    }

    /**
     * Helper to create task audit log for automated runs.
     *
     * @param  array<string, mixed>  $details
     */
    protected function createTaskLog(int $workspaceId, int $websiteId, string $type, array $details = []): Task
    {
        return Task::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'website_id' => $websiteId,
            'type' => $type,
            'status' => TaskStatus::SUCCESS,
            'progress' => 100,
            'credits_reserved' => 0,
            'credits_settled' => 0,
            'attempts' => 1,
            'max_attempts' => 3,
            'unique_lock_key' => "auto_{$type}_{$websiteId}_".date('Ymd'),
            'payload' => $details,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
