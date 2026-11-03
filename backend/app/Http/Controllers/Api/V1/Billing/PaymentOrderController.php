<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\UserRole;
use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelPaymentOrderRequest;
use App\Http\Requests\Billing\StorePaymentOrderRequest;
use App\Http\Requests\Billing\SubmitPaymentProofRequest;
use App\Http\Resources\PaymentOrderResource;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Billing\ManualPaymentService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PaymentOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ManualPaymentService $manualPaymentService
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

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy không gian làm việc hoặc bạn không có quyền truy cập.',
            ], 404);
        }

        $orders = PaymentOrder::where('workspace_id', $workspace->id)
            ->with(['plan', 'transactions'])
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => PaymentOrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(StorePaymentOrderRequest $request): JsonResponse
    {
        $user = $request->user();

        // Chống Cross-Workspace IDOR khi truyền workspace_id khác qua body/query/header
        if ($request->filled('workspace_id')) {
            $targetWorkspaceId = (int) $request->input('workspace_id');
            $isSuperAdmin = ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN);
            $hasAccess = $user->workspaces()
                ->where('workspaces.id', $targetWorkspaceId)
                ->wherePivot('is_active', true)
                ->exists();

            if (! $hasAccess && ! $isSuperAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thao tác trên Không gian làm việc được yêu cầu.',
                    'error_code' => 'FORBIDDEN_WORKSPACE_ACCESS',
                ], 403);
            }
        }

        $workspace = $this->resolveWorkspace($request);

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy không gian làm việc hoặc bạn không có quyền truy cập.',
            ], 404);
        }

        $plan = Plan::findOrFail((int) $request->input('plan_id'));

        try {
            $order = $this->manualPaymentService->createOrder(
                workspace: $workspace,
                plan: $plan,
                user: $user,
                customerNote: $request->input('customer_note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn hàng chuyển khoản thành công.',
                'data' => new PaymentOrderResource($order),
            ], 201);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi tạo đơn hàng thanh toán: '.$e->getMessage(), [
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi tạo đơn hàng.',
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = PaymentOrder::with(['plan', 'workspace', 'user', 'reviewer', 'transactions'])->findOrFail($id);

        Gate::authorize('view', $order);

        return response()->json([
            'success' => true,
            'data' => new PaymentOrderResource($order),
        ]);
    }

    public function submitProof(SubmitPaymentProofRequest $request, int $id): JsonResponse
    {
        $order = PaymentOrder::findOrFail($id);

        Gate::authorize('submitProof', $order);

        $proofPath = $request->input('proof_image_path');
        if ($request->hasFile('proof_image')) {
            $proofPath = $request->file('proof_image')->store('payment_proofs', 'public');
        }

        try {
            $updatedOrder = $this->manualPaymentService->submitProof(
                order: $order,
                proofImagePath: $proofPath,
                customerNote: $request->input('customer_note'),
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã gửi minh chứng thanh toán thành công. Đơn hàng đang chờ Admin xác nhận.',
                'data' => new PaymentOrderResource($updatedOrder),
            ]);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi gửi minh chứng thanh toán: '.$e->getMessage(), [
                'order_id' => $id,
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi gửi minh chứng thanh toán.',
            ], 500);
        }
    }

    public function cancel(CancelPaymentOrderRequest $request, int $id): JsonResponse
    {
        $order = PaymentOrder::findOrFail($id);

        Gate::authorize('cancel', $order);

        try {
            $updatedOrder = $this->manualPaymentService->cancelOrder(
                order: $order,
                user: $request->user(),
                reason: $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy đơn hàng thành công.',
                'data' => new PaymentOrderResource($updatedOrder),
            ]);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi hủy đơn hàng thanh toán: '.$e->getMessage(), [
                'order_id' => $id,
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi hủy đơn hàng.',
            ], 500);
        }
    }
}
