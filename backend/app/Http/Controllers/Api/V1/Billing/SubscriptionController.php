<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected CreditLedgerService $creditLedgerService
    ) {}

    public function current(Request $request): JsonResponse
    {
        $currentWorkspace = $request->attributes->get('current_workspace');
        $user = $request->user();

        $explicitWorkspaceId = $request->header('X-Workspace-Id')
            ?: $request->query('workspace_id')
            ?: $request->user()?->default_workspace_id;

        $workspace = null;

        if ($explicitWorkspaceId && $currentWorkspace && (int) $currentWorkspace->id === (int) $explicitWorkspaceId) {
            $workspace = $currentWorkspace;
        } elseif ($explicitWorkspaceId && $user) {
            $isSuperAdmin = ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN);
            if ($isSuperAdmin) {
                $workspace = Workspace::find((int) $explicitWorkspaceId);
            } else {
                $workspace = $user->workspaces()
                    ->where('workspaces.id', (int) $explicitWorkspaceId)
                    ->wherePivot('is_active', true)
                    ->first();
            }
        }

        if (! $workspace && $currentWorkspace instanceof Workspace) {
            $workspace = $currentWorkspace;
        }

        if (! $workspace && $user) {
            $workspace = $user->workspaces()->wherePivot('is_active', true)->first();
        }

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy không gian làm việc hoặc bạn không có quyền truy cập.',
            ], 404);
        }

        try {
            $activePlan = $this->subscriptionService->getActivePlan($workspace);
            $balance = $this->creditLedgerService->getBalance($workspace);

            $subscription = Subscription::where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->latest('id')
                ->with('plan')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
                    'plan' => new PlanResource($activePlan),
                    'balance' => [
                        'available_credits' => $balance->availableCredits,
                        'reserved_credits' => $balance->reservedCredits,
                        'total_credits' => $balance->totalCredits,
                        'cycle_granted_credits' => $balance->cycleGrantedCredits,
                        'cycle_used_credits' => $balance->cycleUsedCredits,
                        'period_start' => $balance->periodStart?->toIso8601String(),
                        'period_end' => $balance->periodEnd?->toIso8601String(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi đọc thông tin subscription hiện tại: '.$e->getMessage(), [
                'workspace_id' => $workspace->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi kiểm tra thông tin gói dịch vụ.',
            ], 500);
        }
    }
}
