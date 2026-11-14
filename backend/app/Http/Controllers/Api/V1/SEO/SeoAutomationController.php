<?php

namespace App\Http\Controllers\Api\V1\SEO;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\SEO\Automation\SeoAutomationSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoAutomationController extends Controller
{
    public function __construct(
        protected SeoAutomationSchedulerService $schedulerService
    ) {}

    /**
     * Get automation rules for a specific website.
     */
    public function getRules(Request $request, int $id): JsonResponse
    {
        $website = Website::findOrFail($id);
        $this->authorizeWebsiteAccess($request, $website);

        $rules = $this->schedulerService->getRulesForWebsite($website->workspace_id, $website->id);

        return response()->json([
            'success' => true,
            'message' => 'Lấy quy tắc tự động hóa thành công',
            'data' => $rules,
        ]);
    }

    /**
     * Update automation rules for a specific website.
     */
    public function updateRules(Request $request, int $id): JsonResponse
    {
        $website = Website::findOrFail($id);
        $this->authorizeWebsiteAccess($request, $website);

        $validated = $request->validate([
            'sync_gsc_enabled' => 'sometimes|boolean',
            'sync_ga4_enabled' => 'sometimes|boolean',
            'rank_tracking_enabled' => 'sometimes|boolean',
            'anomaly_scan_enabled' => 'sometimes|boolean',
            'alert_ranking_drop_threshold' => 'sometimes|integer|min:1|max:50',
            'alert_traffic_drop_percentage' => 'sometimes|integer|min:1|max:100',
            'email_notifications_enabled' => 'sometimes|boolean',
        ]);

        $updatedRules = $this->schedulerService->updateRulesForWebsite(
            workspaceId: $website->workspace_id,
            websiteId: $website->id,
            data: $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật quy tắc tự động hóa thành công',
            'data' => $updatedRules,
        ]);
    }

    /**
     * Get overview of schedules and automation status across workspace.
     */
    public function indexSchedules(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);

        $overview = $this->schedulerService->getAutomationOverview($workspaceId);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách lịch trình tự động hóa thành công',
            'data' => $overview,
        ]);
    }

    /**
     * Bulk update automation settings or schedules for the workspace.
     */
    public function updateSchedules(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);

        $validated = $request->validate([
            'website_id' => 'sometimes|integer|exists:websites,id',
            'sync_gsc_enabled' => 'sometimes|boolean',
            'sync_ga4_enabled' => 'sometimes|boolean',
            'rank_tracking_enabled' => 'sometimes|boolean',
            'anomaly_scan_enabled' => 'sometimes|boolean',
            'alert_ranking_drop_threshold' => 'sometimes|integer|min:1|max:50',
            'alert_traffic_drop_percentage' => 'sometimes|integer|min:1|max:100',
            'email_notifications_enabled' => 'sometimes|boolean',
        ]);

        if (isset($validated['website_id'])) {
            $website = Website::where('workspace_id', $workspaceId)->findOrFail($validated['website_id']);
            $this->schedulerService->updateRulesForWebsite($workspaceId, $website->id, $validated);
        }

        $overview = $this->schedulerService->getAutomationOverview($workspaceId);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật cấu hình lịch trình tự động thành công',
            'data' => $overview,
        ]);
    }

    /**
     * Trigger immediate manual sync for a specific website.
     */
    public function triggerSync(Request $request, int $id): JsonResponse
    {
        $website = Website::findOrFail($id);
        $this->authorizeWebsiteAccess($request, $website);

        $service = $request->input('service', 'all');

        $results = [];
        if (in_array($service, ['gsc', 'all'], true)) {
            $results['gsc'] = $this->schedulerService->syncAllWebsitesGsc($website->id);
        }
        if (in_array($service, ['ga4', 'all'], true)) {
            $results['ga4'] = $this->schedulerService->syncAllWebsitesGa4($website->id);
        }
        if (in_array($service, ['rank', 'all'], true)) {
            $results['rank'] = $this->schedulerService->trackAllRankings($website->id);
        }
        if (in_array($service, ['anomaly', 'all'], true)) {
            $results['anomaly'] = $this->schedulerService->scanAllAnomalies($website->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã kích hoạt tác vụ đồng bộ SEO thành công',
            'data' => $results,
        ]);
    }

    /**
     * Resolve the active workspace ID.
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
            abort(403, 'Bạn không có quyền truy cập quy tắc tự động hóa của website này.');
        }
    }
}
