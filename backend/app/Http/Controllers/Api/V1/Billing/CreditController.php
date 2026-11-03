<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreditTransactionResource;
use App\Models\CreditTransaction;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreditController extends Controller
{
    public function __construct(
        protected CreditLedgerService $creditLedgerService
    ) {}

    protected function resolveWorkspace(Request $request): ?Workspace
    {
        $currentWorkspace = $request->attributes->get('current_workspace');
        $user = $request->user();

        $explicitWorkspaceId = $request->header('X-Workspace-Id')
            ?: $request->query('workspace_id')
            ?: $request->input('workspace_id');

        if ($explicitWorkspaceId && $currentWorkspace && (int) $currentWorkspace->id === (int) $explicitWorkspaceId) {
            return $currentWorkspace;
        }

        if ($explicitWorkspaceId && $user) {
            $isSuperAdmin = ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN);
            if ($isSuperAdmin) {
                return Workspace::find((int) $explicitWorkspaceId);
            }

            return $user->workspaces()
                ->where('workspaces.id', (int) $explicitWorkspaceId)
                ->wherePivot('is_active', true)
                ->first();
        }

        if ($currentWorkspace instanceof Workspace) {
            return $currentWorkspace;
        }

        if ($user?->default_workspace_id) {
            $workspace = $user->workspaces()
                ->where('workspaces.id', (int) $user->default_workspace_id)
                ->wherePivot('is_active', true)
                ->first();
            if ($workspace) {
                return $workspace;
            }
        }

        return $user?->workspaces()->wherePivot('is_active', true)->first();
    }

    public function history(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy không gian làm việc hoặc bạn không có quyền truy cập.',
            ], 404);
        }

        try {
            $transactions = CreditTransaction::where('workspace_id', $workspace->id)
                ->latest('id')
                ->paginate((int) $request->query('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => CreditTransactionResource::collection($transactions),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi tải lịch sử credit: '.$e->getMessage(), [
                'workspace_id' => $workspace->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi tải lịch sử giao dịch credit.',
            ], 500);
        }
    }

    public function balance(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy không gian làm việc hoặc bạn không có quyền truy cập.',
            ], 404);
        }

        try {
            $balance = $this->creditLedgerService->getBalance($workspace);

            return response()->json([
                'success' => true,
                'data' => [
                    'workspace_id' => $balance->workspaceId,
                    'available_credits' => $balance->availableCredits,
                    'reserved_credits' => $balance->reservedCredits,
                    'total_credits' => $balance->totalCredits,
                    'cycle_granted_credits' => $balance->cycleGrantedCredits,
                    'cycle_used_credits' => $balance->cycleUsedCredits,
                    'period_start' => $balance->periodStart?->toIso8601String(),
                    'period_end' => $balance->periodEnd?->toIso8601String(),
                    'plan_code' => $balance->planCode,
                    'plan_name' => $balance->planName,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi đọc số dư credit: '.$e->getMessage(), [
                'workspace_id' => $workspace->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi kiểm tra số dư credit.',
            ], 500);
        }
    }
}
