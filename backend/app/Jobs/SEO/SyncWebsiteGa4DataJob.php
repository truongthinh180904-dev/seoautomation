<?php

namespace App\Jobs\SEO;

use App\DTOs\SEO\Ga4PerformanceDTO;
use App\Jobs\BaseSyncJob;
use App\Models\Article;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\WebsiteGoogleAnalytics;
use App\Services\SEO\GA4\GoogleAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncWebsiteGa4DataJob extends BaseSyncJob
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

        return "sync:ga4:website:{$this->websiteId}:{$start}:{$end}";
    }

    /**
     * Concrete sync logic to execute within tenant context and distributed lock.
     *
     * @throws \Throwable
     */
    protected function executeSync(): void
    {
        $wGa4 = WebsiteGoogleAnalytics::where('website_id', $this->websiteId)
            ->where('workspace_id', $this->workspaceId)
            ->with(['googleAccount'])
            ->first();

        if (! $wGa4 || ! $wGa4->googleAccount || ! $wGa4->is_verified) {
            Log::warning("[SyncWebsiteGa4DataJob] GA4 property not found or not verified for website #{$this->websiteId}", [
                'workspace_id' => $this->workspaceId,
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        $wGa4->update(['sync_status' => 'syncing']);

        try {
            $endDate = $this->endDate ?? Carbon::now()->subDays(1)->toDateString();
            if ($this->startDate) {
                $startDate = $this->startDate;
            } elseif ($wGa4->last_synced_at === null) {
                $startDate = Carbon::now()->subDays(28)->toDateString();
            } else {
                $startDate = Carbon::now()->subDays(3)->toDateString();
            }

            /** @var GoogleAnalyticsService $ga4Service */
            $ga4Service = app(GoogleAnalyticsService::class);

            $performanceRows = $ga4Service->fetchPagePerformance(
                account: $wGa4->googleAccount,
                propertyId: $wGa4->property_id,
                startDate: $startDate,
                endDate: $endDate,
                dimensions: ['date', 'landingPagePlusQueryString'],
                metrics: ['sessions', 'totalUsers', 'screenPageViews', 'engagementRate', 'conversions']
            );

            if (! empty($performanceRows)) {
                // Pre-map articles for website to link article_id
                $articles = Article::where('website_id', $this->websiteId)
                    ->where('workspace_id', $this->workspaceId)
                    ->get(['id', 'slug', 'wordpress_post_url']);

                $articleMap = [];
                foreach ($articles as $art) {
                    if (! empty($art->slug)) {
                        $cleanSlug = trim($art->slug, '/');
                        $articleMap['/'.$cleanSlug] = $art->id;
                        $articleMap[$cleanSlug] = $art->id;
                    }
                    if (! empty($art->wordpress_post_url)) {
                        $parsedPath = parse_url($art->wordpress_post_url, PHP_URL_PATH);
                        if (is_string($parsedPath) && $parsedPath !== '') {
                            $norm = Ga4PerformanceDTO::normalizeLandingPage($parsedPath);
                            $articleMap[$norm] = $art->id;
                        }
                    }
                }

                $now = now();
                $dedupedRows = [];

                foreach ($performanceRows as $dto) {
                    $normalizedLandingPage = Ga4PerformanceDTO::normalizeLandingPage($dto->landingPage);
                    $pageHash = Ga4PagePerformanceDaily::generatePageHash($normalizedLandingPage);

                    $trimmedLanding = ltrim($normalizedLandingPage, '/');
                    $articleId = $articleMap[$normalizedLandingPage] ?? $articleMap[$trimmedLanding] ?? null;

                    $uniqueKey = "{$this->websiteId}_{$dto->date}_{$pageHash}";

                    $dedupedRows[$uniqueKey] = [
                        'workspace_id' => $this->workspaceId,
                        'website_id' => $this->websiteId,
                        'article_id' => $articleId,
                        'date' => $dto->date,
                        'landing_page' => mb_substr($normalizedLandingPage, 0, 1024),
                        'page_hash' => $pageHash,
                        'sessions' => $dto->sessions,
                        'active_users' => $dto->activeUsers,
                        'engagement_rate' => round($dto->engagementRate, 4),
                        'conversions' => (int) $dto->conversions,
                        'avg_session_duration' => round($dto->averageSessionDuration, 2),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk(array_values($dedupedRows), 500) as $chunk) {
                    Ga4PagePerformanceDaily::upsert(
                        $chunk,
                        ['website_id', 'date', 'page_hash'],
                        [
                            'workspace_id',
                            'article_id',
                            'landing_page',
                            'sessions',
                            'active_users',
                            'engagement_rate',
                            'conversions',
                            'avg_session_duration',
                            'updated_at',
                        ]
                    );
                }
            }

            $wGa4->update([
                'last_synced_at' => now(),
                'sync_status' => 'idle',
            ]);
        } catch (\Throwable $e) {
            $wGa4->update(['sync_status' => 'failed']);
            Log::error("[SyncWebsiteGa4DataJob] Failed syncing GA4 for website #{$this->websiteId}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
