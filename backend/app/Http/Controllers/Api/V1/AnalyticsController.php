<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsService;
use App\Services\Cost\CostTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analytics,
        protected CostTrackingService $costTracking
    ) {}

    protected function resolveWorkspaceId(Request $request): ?int
    {
        $workspace = $request->attributes->get('current_workspace');
        if ($workspace instanceof Workspace) {
            return $workspace->id;
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        $rawId = $request->header('X-Workspace-Id')
            ?: $request->query('workspace_id')
            ?: $user->default_workspace_id;

        if ($rawId) {
            $workspaceId = (int) $rawId;
            if ($this->isSuperAdmin($request)) {
                return $workspaceId;
            }

            $isMember = $user->workspaces()
                ->where('workspaces.id', $workspaceId)
                ->wherePivot('is_active', true)
                ->exists();

            if ($isMember) {
                return $workspaceId;
            }
        }

        $fallback = $user->workspaces()->wherePivot('is_active', true)->first();

        return $fallback ? $fallback->id : null;
    }

    protected function isSuperAdmin(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return (bool) ($user->is_platform_admin ?? false) ||
            ($user->role?->value === 'super_admin' || $user->role === 'super_admin');
    }

    /**
     * Article counts per status + word count / SEO score averages + credit ledger and website costs.
     */
    public function summary(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 30), 90));

        if ($request->query('scope') === 'platform' && $this->isSuperAdmin($request)) {
            return response()->json($this->analytics->platformOverview($days));
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $workspaceId = $this->resolveWorkspaceId($request);

        return response()->json(
            $this->analytics->summary($tenantId, $workspaceId, $days)
        );
    }

    /**
     * AI cost per day for last N days.
     */
    public function aiCosts(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 30), 90));
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $workspaceId = $this->resolveWorkspaceId($request);

        return response()->json(
            $this->analytics->aiCosts($tenantId, $days, $workspaceId)
        );
    }

    /**
     * Keywords processed per day for last N days.
     */
    public function keywordsDaily(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 30), 90));
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $workspaceId = $this->resolveWorkspaceId($request);

        return response()->json(
            $this->analytics->keywordsDaily($tenantId, $days, $workspaceId)
        );
    }

    /**
     * Top failing agents by error count.
     */
    public function failingAgents(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 30), 90));
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $workspaceId = $this->resolveWorkspaceId($request);

        return response()->json(
            $this->analytics->failingAgents($tenantId, $days, $workspaceId)
        );
    }

    public function usage(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        return response()->json(
            $this->costTracking->getMonthlyUsage($tenantId)
        );
    }
}
