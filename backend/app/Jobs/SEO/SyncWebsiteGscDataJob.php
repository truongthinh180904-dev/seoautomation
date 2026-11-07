<?php

namespace App\Jobs\SEO;

use App\Jobs\BaseSyncJob;
use App\Models\GscSearchPerformanceDaily;
use App\Models\WebsiteSearchConsole;
use App\Services\SEO\GSC\GoogleSearchConsoleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncWebsiteGscDataJob extends BaseSyncJob
{
    public function __construct(
        int $workspaceId,
        public int $websiteId,
        public ?string $startDate = null,
        public ?string $endDate = null
    ) {
        parent::__construct($workspaceId);
    }

    /**
     * Unique idempotency key identifying this sync run.
     */
    public function getIdempotencyKey(): string
    {
        $start = $this->startDate ?? 'default';
        $end = $this->endDate ?? 'default';

        return "sync:gsc:website:{$this->websiteId}:{$start}:{$end}";
    }

    /**
     * Concrete sync logic to execute within tenant context and distributed lock.
     *
     * @throws \Throwable
     */
    protected function executeSync(): void
    {
        $wGsc = WebsiteSearchConsole::where('website_id', $this->websiteId)
            ->where('workspace_id', $this->workspaceId)
            ->with(['googleAccount'])
            ->first();

        if (! $wGsc || ! $wGsc->googleAccount || ! $wGsc->is_verified) {
            Log::warning("[SyncWebsiteGscDataJob] GSC property not found or not verified for website #{$this->websiteId}", [
                'workspace_id' => $this->workspaceId,
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        $wGsc->update(['sync_status' => 'syncing']);

        try {
            $endDate = $this->endDate ?? Carbon::now()->subDays(2)->toDateString();
            if ($this->startDate) {
                $startDate = $this->startDate;
            } elseif ($wGsc->last_synced_at === null) {
                $startDate = Carbon::now()->subDays(28)->toDateString();
            } else {
                // Backfill buffer: GSC finalizes data with ~3 days latency
                $startDate = Carbon::now()->subDays(5)->toDateString();
            }

            /** @var GoogleSearchConsoleService $gscService */
            $gscService = app(GoogleSearchConsoleService::class);

            $metrics = $gscService->fetchAllSearchAnalytics(
                account: $wGsc->googleAccount,
                siteUrl: $wGsc->site_url,
                startDate: $startDate,
                endDate: $endDate,
                dimensions: ['query', 'page', 'date', 'device', 'country']
            );

            if (! empty($metrics)) {
                $now = now();
                $dedupedRows = [];

                foreach ($metrics as $metric) {
                    $pageUrl = $metric->pageUrl;
                    $query = $metric->query;
                    $pageHash = GscSearchPerformanceDaily::generatePageHash($pageUrl);
                    $queryHash = GscSearchPerformanceDaily::generateQueryHash($query);
                    $country = mb_substr(strtolower($metric->country ?: 'vnm'), 0, 3);
                    $device = in_array(strtoupper($metric->device), ['DESKTOP', 'MOBILE', 'TABLET'], true)
                        ? strtoupper($metric->device)
                        : 'DESKTOP';

                    $uniqueKey = "{$this->websiteId}_{$metric->date}_{$pageHash}_{$queryHash}_{$country}_{$device}";

                    $dedupedRows[$uniqueKey] = [
                        'workspace_id' => $this->workspaceId,
                        'website_id' => $this->websiteId,
                        'date' => $metric->date,
                        'query' => mb_substr($query, 0, 255),
                        'page_url' => mb_substr($pageUrl, 0, 1024),
                        'page_hash' => $pageHash,
                        'query_hash' => $queryHash,
                        'country' => $country,
                        'device' => $device,
                        'clicks' => $metric->clicks,
                        'impressions' => $metric->impressions,
                        'ctr' => round($metric->ctr, 4),
                        'position' => round($metric->position, 2),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk(array_values($dedupedRows), 500) as $chunk) {
                    GscSearchPerformanceDaily::upsert(
                        $chunk,
                        ['website_id', 'date', 'page_hash', 'query_hash', 'country', 'device'],
                        ['workspace_id', 'query', 'page_url', 'clicks', 'impressions', 'ctr', 'position', 'updated_at']
                    );
                }
            }

            $wGsc->update([
                'last_synced_at' => now(),
                'sync_status' => 'idle',
            ]);
        } catch (\Throwable $e) {
            $wGsc->update(['sync_status' => 'failed']);
            Log::error("[SyncWebsiteGscDataJob] Failed syncing GSC for website #{$this->websiteId}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
