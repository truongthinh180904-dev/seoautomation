<?php

namespace App\Http\Controllers\Api\V1\SEO;

use App\Exceptions\QuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SEO\StoreTrackedKeywordRequest;
use App\Jobs\SEO\TrackKeywordRankingJob;
use App\Models\RankTrackedKeyword;
use App\Models\Website;
use App\Services\SEO\RankTracking\RankTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RankTrackerController extends Controller
{
    public function __construct(
        protected RankTrackingService $service
    ) {}

    /**
     * List tracked keywords with filters, pagination, and overview summary.
     */
    public function index(Request $request): JsonResponse
    {
        $websiteId = (int) $request->input('website_id');

        if (! $websiteId) {
            $defaultWebsite = Website::first();
            $websiteId = $defaultWebsite?->id ?? 0;
        }

        if (! $websiteId) {
            return response()->json([
                'success' => true,
                'data' => [],
                'summary' => [
                    'total_keywords' => 0,
                    'top_3' => 0,
                    'top_10' => 0,
                    'top_30' => 0,
                    'top_100' => 0,
                    'out_of_100' => 0,
                    'improved' => 0,
                    'declined' => 0,
                    'unchanged' => 0,
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                ],
            ]);
        }

        $filters = [
            'search' => $request->input('search'),
            'device' => $request->input('device'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'per_page' => (int) $request->input('per_page', 20),
        ];

        $paginator = $this->service->getTrackedKeywords($websiteId, $filters);
        $summary = $this->service->getWebsiteSummary($websiteId);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Add a keyword to rank tracking.
     */
    public function store(StoreTrackedKeywordRequest $request): JsonResponse
    {
        $website = Website::findOrFail($request->validated('website_id'));

        $keyword = trim($request->validated('keyword'));
        $country = strtolower($request->validated('country') ?? 'vn');
        $device = strtolower($request->validated('device') ?? 'desktop');

        $exists = RankTrackedKeyword::query()
            ->where('website_id', $website->id)
            ->where('keyword', $keyword)
            ->where('country', $country)
            ->where('device', $device)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Từ khóa này đã được thêm theo dõi trên thiết bị và vị trí địa lý này.',
            ], 422);
        }

        try {
            $tracked = $this->service->addTrackedKeyword($website, $request->validated());

            // Run ranking check asynchronously or dispatch job
            if (app()->environment('testing')) {
                // In testing, execute synchronously if not in queue-faked test
                try {
                    $this->service->trackKeyword($tracked);
                    $tracked->refresh();
                } catch (\Throwable $e) {
                    Log::warning("[RankTrackerController] Auto-track failed in test: {$e->getMessage()}");
                }
            } else {
                TrackKeywordRankingJob::dispatch($tracked->workspace_id, $tracked->id);
            }

            return response()->json([
                'success' => true,
                'data' => $tracked,
                'message' => 'Đã thêm từ khóa vào danh sách theo dõi thứ hạng thành công.',
            ], 201);
        } catch (QuotaExceededException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Delete a tracked keyword.
     */
    public function destroy(int $id): JsonResponse
    {
        $keyword = RankTrackedKeyword::findOrFail($id);
        $this->service->removeTrackedKeyword($keyword);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa từ khóa theo dõi thành công.',
        ]);
    }

    /**
     * Retrieve historical ranking data for chart plotting.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $keyword = RankTrackedKeyword::findOrFail($id);
        $days = min(max((int) $request->input('days', 30), 7), 180);

        $history = $this->service->getKeywordHistory($keyword, $days);

        return response()->json([
            'success' => true,
            'keyword' => $keyword,
            'data' => $history,
        ]);
    }

    /**
     * Trigger an on-demand rank refresh.
     */
    public function refresh(int $id): JsonResponse
    {
        $keyword = RankTrackedKeyword::findOrFail($id);

        try {
            $historyRecord = $this->service->trackKeyword($keyword);
            $keyword->refresh();

            return response()->json([
                'success' => true,
                'data' => $keyword,
                'history' => $historyRecord,
                'message' => 'Đã làm mới thứ hạng từ khóa thành công.',
            ]);
        } catch (\Throwable $e) {
            Log::error("[RankTrackerController] Manual refresh error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Không thể làm mới thứ hạng vào lúc này: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve aggregate summary metrics for a website.
     */
    public function summary(Request $request): JsonResponse
    {
        $websiteId = (int) $request->input('website_id');
        $website = Website::findOrFail($websiteId);

        $summary = $this->service->getWebsiteSummary($website->id);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
