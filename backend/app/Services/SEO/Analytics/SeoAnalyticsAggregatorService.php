<?php

namespace App\Services\SEO\Analytics;

use App\Models\Ga4PagePerformanceDaily;
use App\Models\GscSearchPerformanceDaily;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class SeoAnalyticsAggregatorService
{
    /**
     * Get aggregate overview metrics and timeseries data with previous period comparison.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPerformanceOverview(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo,
        bool $compare = true,
        array $filters = []
    ): array {
        $cStart = Carbon::parse($dateFrom)->startOfDay();
        $cEnd = Carbon::parse($dateTo)->endOfDay();
        $daysCount = $cStart->diffInDays($cEnd) + 1;

        // Current period metrics
        $currentGsc = $this->queryGscSummary($workspaceId, $websiteId, $dateFrom, $dateTo, $filters);
        $currentGa4 = $this->queryGa4Summary($workspaceId, $websiteId, $dateFrom, $dateTo);

        // Previous period metrics
        $prevDateTo = $cStart->copy()->subDay()->toDateString();
        $prevDateFrom = Carbon::parse($prevDateTo)->subDays($daysCount - 1)->toDateString();

        $prevGsc = $compare
            ? $this->queryGscSummary($workspaceId, $websiteId, $prevDateFrom, $prevDateTo, $filters)
            : ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0];

        $prevGa4 = $compare
            ? $this->queryGa4Summary($workspaceId, $websiteId, $prevDateFrom, $prevDateTo)
            : ['sessions' => 0, 'active_users' => 0, 'engagement_rate' => 0.0, 'conversions' => 0];

        // Timeseries breakdown
        $timeseries = $this->buildTimeseries($workspaceId, $websiteId, $dateFrom, $dateTo, $filters);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'compare' => $compare,
            'compare_date_from' => $compare ? $prevDateFrom : null,
            'compare_date_to' => $compare ? $prevDateTo : null,
            'summary' => [
                'clicks' => [
                    'current' => (int) ($currentGsc['clicks'] ?? 0),
                    'previous' => (int) ($prevGsc['clicks'] ?? 0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGsc['clicks'] ?? 0), (float) ($prevGsc['clicks'] ?? 0)),
                ],
                'impressions' => [
                    'current' => (int) ($currentGsc['impressions'] ?? 0),
                    'previous' => (int) ($prevGsc['impressions'] ?? 0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGsc['impressions'] ?? 0), (float) ($prevGsc['impressions'] ?? 0)),
                ],
                'ctr' => [
                    'current' => (float) ($currentGsc['ctr'] ?? 0.0),
                    'previous' => (float) ($prevGsc['ctr'] ?? 0.0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGsc['ctr'] ?? 0.0), (float) ($prevGsc['ctr'] ?? 0.0)),
                ],
                'position' => [
                    'current' => (float) ($currentGsc['position'] ?? 0.0),
                    'previous' => (float) ($prevGsc['position'] ?? 0.0),
                    'delta_value' => round((float) (($currentGsc['position'] ?? 0.0) - ($prevGsc['position'] ?? 0.0)), 2),
                ],
                'sessions' => [
                    'current' => (int) ($currentGa4['sessions'] ?? 0),
                    'previous' => (int) ($prevGa4['sessions'] ?? 0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGa4['sessions'] ?? 0), (float) ($prevGa4['sessions'] ?? 0)),
                ],
                'active_users' => [
                    'current' => (int) ($currentGa4['active_users'] ?? 0),
                    'previous' => (int) ($prevGa4['active_users'] ?? 0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGa4['active_users'] ?? 0), (float) ($prevGa4['active_users'] ?? 0)),
                ],
                'engagement_rate' => [
                    'current' => (float) ($currentGa4['engagement_rate'] ?? 0.0),
                    'previous' => (float) ($prevGa4['engagement_rate'] ?? 0.0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGa4['engagement_rate'] ?? 0.0), (float) ($prevGa4['engagement_rate'] ?? 0.0)),
                ],
                'conversions' => [
                    'current' => (int) ($currentGa4['conversions'] ?? 0),
                    'previous' => (int) ($prevGa4['conversions'] ?? 0),
                    'delta_percent' => $this->calculateDelta((float) ($currentGa4['conversions'] ?? 0), (float) ($prevGa4['conversions'] ?? 0)),
                ],
            ],
            'timeseries' => $timeseries,
        ];
    }

    /**
     * Get top performing search queries from GSC.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopQueries(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo,
        int $limit = 20,
        string $sortBy = 'clicks',
        string $sortDir = 'desc',
        ?string $search = null
    ): array {
        $allowedSort = ['clicks', 'impressions', 'ctr', 'position'];
        $column = in_array(strtolower($sortBy), $allowedSort, true) ? strtolower($sortBy) : 'clicks';
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $query = GscSearchPerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $query->where('website_id', $websiteId);
        }

        if (! empty($search)) {
            $query->where('query', 'like', '%'.trim($search).'%');
        }

        $results = $query->select([
            'query',
            DB::raw('SUM(clicks) as clicks'),
            DB::raw('SUM(impressions) as impressions'),
            DB::raw('ROUND(AVG(ctr), 4) as ctr'),
            DB::raw('ROUND(AVG(position), 2) as position'),
        ])
            ->groupBy('query')
            ->orderBy($column, $direction)
            ->limit(min(max($limit, 1), 200))
            ->get();

        return $results->map(function ($row) {
            $impressions = (int) $row->impressions;
            $clicks = (int) $row->clicks;
            $ctr = $impressions > 0 ? round($clicks / $impressions, 4) : (float) $row->ctr;

            return [
                'query' => (string) $row->query,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'position' => (float) $row->position,
            ];
        })->toArray();
    }

    /**
     * Get top pages merged from GSC and GA4.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopPages(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo,
        int $limit = 20,
        string $sortBy = 'clicks',
        string $sortDir = 'desc',
        ?string $search = null
    ): array {
        $gscQuery = GscSearchPerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $gscQuery->where('website_id', $websiteId);
        }

        if (! empty($search)) {
            $gscQuery->where('page_url', 'like', '%'.trim($search).'%');
        }

        $gscPages = $gscQuery->select([
            'page_hash',
            'page_url',
            DB::raw('SUM(clicks) as clicks'),
            DB::raw('SUM(impressions) as impressions'),
            DB::raw('ROUND(AVG(ctr), 4) as ctr'),
            DB::raw('ROUND(AVG(position), 2) as position'),
        ])
            ->groupBy('page_hash', 'page_url')
            ->get()
            ->keyBy('page_hash');

        $ga4Query = Ga4PagePerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $ga4Query->where('website_id', $websiteId);
        }

        if (! empty($search)) {
            $ga4Query->where('landing_page', 'like', '%'.trim($search).'%');
        }

        $ga4Pages = $ga4Query->select([
            'page_hash',
            'landing_page',
            DB::raw('SUM(sessions) as sessions'),
            DB::raw('SUM(active_users) as active_users'),
            DB::raw('ROUND(AVG(engagement_rate), 4) as engagement_rate'),
            DB::raw('SUM(conversions) as conversions'),
        ])
            ->groupBy('page_hash', 'landing_page')
            ->get()
            ->keyBy('page_hash');

        // Merge pages by page_hash or url
        $allHashes = $gscPages->keys()->merge($ga4Pages->keys())->unique();
        $merged = [];

        foreach ($allHashes as $hash) {
            $gsc = $gscPages->get($hash);
            $ga4 = $ga4Pages->get($hash);

            $pageUrl = $gsc?->page_url ?? $ga4?->landing_page ?? '';
            $clicks = (int) ($gsc?->clicks ?? 0);
            $impressions = (int) ($gsc?->impressions ?? 0);
            $ctr = $impressions > 0 ? round($clicks / $impressions, 4) : (float) ($gsc?->ctr ?? 0.0);
            $position = (float) ($gsc?->position ?? 0.0);

            $sessions = (int) ($ga4?->sessions ?? 0);
            $activeUsers = (int) ($ga4?->active_users ?? 0);
            $engagementRate = (float) ($ga4?->engagement_rate ?? 0.0);
            $conversions = (int) ($ga4?->conversions ?? 0);

            $merged[] = [
                'page_hash' => $hash,
                'page_url' => $pageUrl,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'position' => $position,
                'sessions' => $sessions,
                'active_users' => $activeUsers,
                'engagement_rate' => $engagementRate,
                'conversions' => $conversions,
            ];
        }

        // Sorting
        $allowedSort = ['clicks', 'impressions', 'ctr', 'position', 'sessions', 'active_users', 'engagement_rate', 'conversions'];
        $sortCol = in_array(strtolower($sortBy), $allowedSort, true) ? strtolower($sortBy) : 'clicks';
        $direction = strtolower($sortDir) === 'asc' ? 1 : -1;

        usort($merged, function ($a, $b) use ($sortCol, $direction) {
            if ($a[$sortCol] == $b[$sortCol]) {
                return 0;
            }

            return ($a[$sortCol] < $b[$sortCol]) ? -$direction : $direction;
        });

        return array_slice($merged, 0, min(max($limit, 1), 200));
    }

    /**
     * Identify declining pages based on click drop between consecutive periods.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDecliningPages(
        int $workspaceId,
        ?int $websiteId,
        int $days = 28,
        int $limit = 10
    ): array {
        $curEnd = Carbon::now()->subDays(2)->toDateString();
        $curStart = Carbon::parse($curEnd)->subDays($days - 1)->toDateString();

        $prevEnd = Carbon::parse($curStart)->subDay()->toDateString();
        $prevStart = Carbon::parse($prevEnd)->subDays($days - 1)->toDateString();

        $currentList = $this->getTopPages($workspaceId, $websiteId, $curStart, $curEnd, 100, 'clicks', 'desc');
        $prevList = $this->getTopPages($workspaceId, $websiteId, $prevStart, $prevEnd, 100, 'clicks', 'desc');

        $prevMap = collect($prevList)->keyBy('page_hash');
        $declining = [];

        foreach ($currentList as $cur) {
            $prev = $prevMap->get($cur['page_hash']);
            $prevClicks = $prev['clicks'] ?? 0;
            $curClicks = $cur['clicks'];

            if ($prevClicks > 10 && $curClicks < $prevClicks) {
                $dropPercent = $this->calculateDelta($curClicks, $prevClicks);
                $declining[] = [
                    'page_url' => $cur['page_url'],
                    'current_clicks' => $curClicks,
                    'previous_clicks' => $prevClicks,
                    'drop_clicks' => $prevClicks - $curClicks,
                    'drop_percent' => $dropPercent,
                ];
            }
        }

        usort($declining, fn ($a, $b) => $a['drop_percent'] <=> $b['drop_percent']);

        return array_slice($declining, 0, $limit);
    }

    /**
     * Identify queries with high impressions but low CTR.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLowCtrOpportunities(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo,
        int $minImpressions = 500,
        float $maxCtr = 0.02,
        int $limit = 20
    ): array {
        $queries = $this->getTopQueries($workspaceId, $websiteId, $dateFrom, $dateTo, 100, 'impressions', 'desc');

        $opportunities = array_filter($queries, function ($q) use ($minImpressions, $maxCtr) {
            return $q['impressions'] >= $minImpressions && $q['ctr'] <= $maxCtr;
        });

        return array_slice(array_values($opportunities), 0, $limit);
    }

    /**
     * Query GSC totals.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    protected function queryGscSummary(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo,
        array $filters = []
    ): array {
        $query = GscSearchPerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $query->where('website_id', $websiteId);
        }

        if (! empty($filters['device'])) {
            $query->where('device', strtoupper($filters['device']));
        }

        if (! empty($filters['country'])) {
            $query->where('country', strtolower($filters['country']));
        }

        $res = $query->select([
            DB::raw('COALESCE(SUM(clicks), 0) as total_clicks'),
            DB::raw('COALESCE(SUM(impressions), 0) as total_impressions'),
            DB::raw('COALESCE(ROUND(AVG(ctr), 4), 0.0000) as avg_ctr'),
            DB::raw('COALESCE(ROUND(AVG(position), 2), 0.00) as avg_pos'),
        ])->first();

        $clicks = (int) ($res->total_clicks ?? 0);
        $impressions = (int) ($res->total_impressions ?? 0);
        $ctr = $impressions > 0 ? round($clicks / $impressions, 4) : (float) ($res->avg_ctr ?? 0.0);

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => (float) ($res->avg_pos ?? 0.0),
        ];
    }

    /**
     * Query GA4 totals.
     *
     * @return array<string, float|int>
     */
    protected function queryGa4Summary(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo
    ): array {
        $query = Ga4PagePerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $query->where('website_id', $websiteId);
        }

        $res = $query->select([
            DB::raw('COALESCE(SUM(sessions), 0) as total_sessions'),
            DB::raw('COALESCE(SUM(active_users), 0) as total_users'),
            DB::raw('COALESCE(ROUND(AVG(engagement_rate), 4), 0.0000) as avg_engagement'),
            DB::raw('COALESCE(SUM(conversions), 0) as total_conv'),
        ])->first();

        return [
            'sessions' => (int) ($res->total_sessions ?? 0),
            'active_users' => (int) ($res->total_users ?? 0),
            'engagement_rate' => (float) ($res->avg_engagement ?? 0.0),
            'conversions' => (int) ($res->total_conv ?? 0),
        ];
    }

    /**
     * Build continuous daily timeseries with zero-fill.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    protected function buildTimeseries(
        int $workspaceId,
        ?int $websiteId,
        string $dateFrom,
        string $dateTo,
        array $filters = []
    ): array {
        $gscQuery = GscSearchPerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $gscQuery->where('website_id', $websiteId);
        }

        if (! empty($filters['device'])) {
            $gscQuery->where('device', strtoupper($filters['device']));
        }

        if (! empty($filters['country'])) {
            $gscQuery->where('country', strtolower($filters['country']));
        }

        $gscDaily = $gscQuery->select([
            'date',
            DB::raw('COALESCE(SUM(clicks), 0) as clicks'),
            DB::raw('COALESCE(SUM(impressions), 0) as impressions'),
            DB::raw('COALESCE(ROUND(AVG(ctr), 4), 0.0000) as ctr'),
            DB::raw('COALESCE(ROUND(AVG(position), 2), 0.00) as position'),
        ])->groupBy('date')->get()->keyBy(fn ($item) => Carbon::parse($item->date)->toDateString());

        $ga4Query = Ga4PagePerformanceDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($websiteId !== null && $websiteId > 0) {
            $ga4Query->where('website_id', $websiteId);
        }

        $ga4Daily = $ga4Query->select([
            'date',
            DB::raw('COALESCE(SUM(sessions), 0) as sessions'),
            DB::raw('COALESCE(SUM(active_users), 0) as active_users'),
            DB::raw('COALESCE(ROUND(AVG(engagement_rate), 4), 0.0000) as engagement_rate'),
            DB::raw('COALESCE(SUM(conversions), 0) as conversions'),
        ])->groupBy('date')->get()->keyBy(fn ($item) => Carbon::parse($item->date)->toDateString());

        $period = CarbonPeriod::create($dateFrom, $dateTo);
        $timeseries = [];

        foreach ($period as $dt) {
            $dStr = $dt->toDateString();
            $gsc = $gscDaily->get($dStr);
            $ga4 = $ga4Daily->get($dStr);

            $clicks = (int) ($gsc?->clicks ?? 0);
            $impressions = (int) ($gsc?->impressions ?? 0);
            $ctr = $impressions > 0 ? round($clicks / $impressions, 4) : (float) ($gsc?->ctr ?? 0.0);

            $timeseries[] = [
                'date' => $dStr,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'position' => (float) ($gsc?->position ?? 0.0),
                'sessions' => (int) ($ga4?->sessions ?? 0),
                'active_users' => (int) ($ga4?->active_users ?? 0),
                'engagement_rate' => (float) ($ga4?->engagement_rate ?? 0.0),
                'conversions' => (int) ($ga4?->conversions ?? 0),
            ];
        }

        return $timeseries;
    }

    /**
     * Calculate delta percentage.
     */
    public function calculateDelta(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
