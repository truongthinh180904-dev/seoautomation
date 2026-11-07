<?php

namespace App\Http\Controllers\Api\V1\SEO;

use App\Http\Controllers\Controller;
use App\Models\SeoIntelligenceInsight;
use App\Models\Website;
use App\Services\SEO\Intelligence\SeoIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoIntelligenceController extends Controller
{
    public function __construct(
        protected SeoIntelligenceService $service
    ) {}

    /**
     * List SEO intelligence insights with filters and pagination.
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
                    'total_active' => 0,
                    'critical_count' => 0,
                    'warning_count' => 0,
                    'info_count' => 0,
                    'opportunity_count' => 0,
                    'resolved_count' => 0,
                    'dismissed_count' => 0,
                    'avg_opportunity_score' => 0,
                    'by_type' => [],
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
            ]);
        }

        $website = Website::findOrFail($websiteId);
        $this->authorizeWebsiteAccess($request, $website);

        $filters = [
            'status' => $request->input('status', 'active'),
            'severity' => $request->input('severity'),
            'type' => $request->input('type'),
            'article_id' => $request->input('article_id'),
            'per_page' => (int) $request->input('per_page', 15),
        ];

        $paginator = $this->service->getInsights($websiteId, $filters);
        $summary = $this->service->getSummary($websiteId);

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
     * Get aggregate summary statistics for insights of a website.
     */
    public function summary(Request $request): JsonResponse
    {
        $websiteId = (int) $request->input('website_id');

        if (! $websiteId) {
            $defaultWebsite = Website::first();
            $websiteId = $defaultWebsite?->id ?? 0;
        }

        if (! $websiteId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_active' => 0,
                    'critical_count' => 0,
                    'warning_count' => 0,
                    'info_count' => 0,
                    'opportunity_count' => 0,
                    'resolved_count' => 0,
                    'dismissed_count' => 0,
                    'avg_opportunity_score' => 0,
                    'by_type' => [],
                ],
            ]);
        }

        $website = Website::findOrFail($websiteId);
        $this->authorizeWebsiteAccess($request, $website);

        $summary = $this->service->getSummary($websiteId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Trigger immediate heuristic scan for a website (0 credit).
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'website_id' => 'required|integer|exists:websites,id',
        ]);

        $website = Website::findOrFail((int) $request->input('website_id'));
        $this->authorizeWebsiteAccess($request, $website);

        $insights = $this->service->analyzeWebsite($website);

        return response()->json([
            'success' => true,
            'message' => 'Quét phân tích cơ hội & bất thường SEO thành công.',
            'total_detected' => $insights->count(),
            'data' => $insights,
        ]);
    }

    /**
     * Get details of a single insight.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $insight = SeoIntelligenceInsight::with(['article', 'website'])->findOrFail($id);
        $this->authorizeInsightAccess($request, $insight);

        return response()->json([
            'success' => true,
            'data' => $insight,
        ]);
    }

    /**
     * Dismiss an insight.
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        $insight = SeoIntelligenceInsight::findOrFail($id);
        $this->authorizeInsightAccess($request, $insight);

        $workspaceId = $this->resolveWorkspaceId($request);
        $updated = $this->service->dismiss($id, $workspaceId);

        return response()->json([
            'success' => true,
            'message' => 'Đã bỏ qua cảnh báo SEO thành công.',
            'data' => $updated,
        ]);
    }

    /**
     * Advance insight state or apply suggested action.
     */
    public function action(Request $request, int $id): JsonResponse
    {
        $insight = SeoIntelligenceInsight::findOrFail($id);
        $this->authorizeInsightAccess($request, $insight);

        $action = (string) $request->input('action', 'in_progress');
        $workspaceId = $this->resolveWorkspaceId($request);

        $updated = $this->service->takeAction($id, $action, $workspaceId);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật trạng thái xử lý cảnh báo thành công.',
            'data' => $updated,
        ]);
    }

    /**
     * Resolve the current workspace id from execution context or user.
     */
    protected function resolveWorkspaceId(Request $request): int
    {
        if (app()->bound('current_workspace_id')) {
            $wsId = (int) app('current_workspace_id');
            if ($wsId > 0) {
                return $wsId;
            }
        }

        $headerWs = $request->header('X-Workspace-Id') ?: $request->query('workspace_id');
        if ($headerWs) {
            return (int) $headerWs;
        }

        return (int) ($request->user()?->default_workspace_id ?? 0);
    }

    /**
     * Ensure the requesting user has access to the target website.
     */
    protected function authorizeWebsiteAccess(Request $request, Website $website): void
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        if ($workspaceId > 0 && (int) $website->workspace_id !== $workspaceId) {
            abort(403, 'Bạn không có quyền truy cập dữ liệu của website này.');
        }
    }

    /**
     * Ensure the requesting user has access to the target insight.
     */
    protected function authorizeInsightAccess(Request $request, SeoIntelligenceInsight $insight): void
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        if ($workspaceId > 0 && (int) $insight->workspace_id !== $workspaceId) {
            abort(403, 'Bạn không có quyền thao tác trên cảnh báo này.');
        }
    }
}
