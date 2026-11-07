<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Jobs\SEO\SyncWebsiteGa4DataJob;
use App\Jobs\SEO\SyncWebsiteGscDataJob;
use App\Models\Website;
use App\Services\SEO\Analytics\SeoAnalyticsAggregatorService;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoPerformanceController extends Controller
{
    public function __construct(
        protected SeoAnalyticsAggregatorService $aggregator
    ) {}

    /**
     * Get aggregate overview performance metrics (GSC + GA4) with comparison.
     */
    public function performance(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Không gian làm việc hợp lệ.',
            ], 400);
        }

        $validated = $request->validate([
            'website_id' => 'nullable|integer',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'compare' => 'nullable|boolean',
            'device' => 'nullable|string|in:DESKTOP,MOBILE,TABLET,desktop,mobile,tablet',
            'country' => 'nullable|string|max:10',
        ]);

        $websiteId = isset($validated['website_id']) ? (int) $validated['website_id'] : null;
        if ($websiteId !== null) {
            $this->ensureWebsiteAccess($workspaceId, $websiteId);
        }

        $dateTo = $validated['date_to'] ?? Carbon::now()->toDateString();
        $dateFrom = $validated['date_from'] ?? Carbon::parse($dateTo)->subDays(27)->toDateString();
        $compare = $request->has('compare') ? $request->boolean('compare') : true;

        $filters = [];
        if (! empty($validated['device'])) {
            $filters['device'] = $validated['device'];
        }
        if (! empty($validated['country'])) {
            $filters['country'] = $validated['country'];
        }

        $overview = $this->aggregator->getPerformanceOverview(
            workspaceId: $workspaceId,
            websiteId: $websiteId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            compare: $compare,
            filters: $filters
        );

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * Get top search queries from GSC.
     */
    public function topQueries(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Không gian làm việc hợp lệ.',
            ], 400);
        }

        $validated = $request->validate([
            'website_id' => 'nullable|integer',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:200',
            'sort_by' => 'nullable|string|in:clicks,impressions,ctr,position',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string|max:255',
        ]);

        $websiteId = isset($validated['website_id']) ? (int) $validated['website_id'] : null;
        if ($websiteId !== null) {
            $this->ensureWebsiteAccess($workspaceId, $websiteId);
        }

        $dateTo = $validated['date_to'] ?? Carbon::now()->toDateString();
        $dateFrom = $validated['date_from'] ?? Carbon::parse($dateTo)->subDays(27)->toDateString();
        $limit = (int) ($validated['limit'] ?? 20);
        $sortBy = $validated['sort_by'] ?? 'clicks';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $search = $validated['search'] ?? null;

        $queries = $this->aggregator->getTopQueries(
            workspaceId: $workspaceId,
            websiteId: $websiteId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            limit: $limit,
            sortBy: $sortBy,
            sortDir: $sortDir,
            search: $search
        );

        return response()->json([
            'success' => true,
            'total' => count($queries),
            'data' => $queries,
        ]);
    }

    /**
     * Get top pages performance merged from GSC & GA4.
     */
    public function topPages(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Không gian làm việc hợp lệ.',
            ], 400);
        }

        $validated = $request->validate([
            'website_id' => 'nullable|integer',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:200',
            'sort_by' => 'nullable|string|in:clicks,impressions,ctr,position,sessions,active_users,engagement_rate,conversions',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string|max:255',
        ]);

        $websiteId = isset($validated['website_id']) ? (int) $validated['website_id'] : null;
        if ($websiteId !== null) {
            $this->ensureWebsiteAccess($workspaceId, $websiteId);
        }

        $dateTo = $validated['date_to'] ?? Carbon::now()->toDateString();
        $dateFrom = $validated['date_from'] ?? Carbon::parse($dateTo)->subDays(27)->toDateString();
        $limit = (int) ($validated['limit'] ?? 20);
        $sortBy = $validated['sort_by'] ?? 'clicks';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $search = $validated['search'] ?? null;

        $pages = $this->aggregator->getTopPages(
            workspaceId: $workspaceId,
            websiteId: $websiteId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            limit: $limit,
            sortBy: $sortBy,
            sortDir: $sortDir,
            search: $search
        );

        return response()->json([
            'success' => true,
            'total' => count($pages),
            'data' => $pages,
        ]);
    }

    /**
     * Trigger on-demand sync of GSC and GA4 daily data.
     */
    public function syncNow(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Không gian làm việc hợp lệ.',
            ], 400);
        }

        $validated = $request->validate([
            'website_id' => 'required|integer',
            'days' => 'nullable|integer|min:1|max:90',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $websiteId = (int) $validated['website_id'];
        $this->ensureWebsiteAccess($workspaceId, $websiteId);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        if (! $startDate && ! empty($validated['days'])) {
            $days = (int) $validated['days'];
            $endDate = Carbon::now()->toDateString();
            $startDate = Carbon::now()->subDays($days - 1)->toDateString();
        }

        SyncWebsiteGscDataJob::dispatch(
            workspaceId: $workspaceId,
            websiteId: $websiteId,
            startDate: $startDate,
            endDate: $endDate
        );

        SyncWebsiteGa4DataJob::dispatch(
            workspaceId: $workspaceId,
            websiteId: $websiteId,
            startDate: $startDate,
            endDate: $endDate
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã đưa tác vụ đồng bộ GSC & GA4 vào hàng đợi thành công.',
        ]);
    }

    /**
     * Resolve the current workspace id from context or user.
     */
    protected function resolveWorkspaceId(Request $request): ?int
    {
        $currentId = BelongsToWorkspace::resolveCurrentWorkspaceId();
        if ($currentId !== null) {
            return $currentId;
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        $headerId = $request->header('X-Workspace-Id') ?: $request->query('workspace_id');
        if ($headerId) {
            $candidateId = (int) $headerId;
            $exists = $user->workspaces()->where('workspaces.id', $candidateId)->exists();
            if ($exists) {
                return $candidateId;
            }
        }

        return $user->workspaces()->first()?->id ?? $user->default_workspace_id;
    }

    /**
     * Ensure the website belongs to the active workspace.
     */
    protected function ensureWebsiteAccess(int $workspaceId, int $websiteId): Website
    {
        return Website::withoutGlobalScopes()
            ->where('id', $websiteId)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();
    }
}
