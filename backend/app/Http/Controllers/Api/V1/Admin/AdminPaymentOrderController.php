<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminApprovePaymentOrderRequest;
use App\Http\Requests\Admin\AdminRejectPaymentOrderRequest;
use App\Http\Resources\PaymentOrderResource;
use App\Models\PaymentOrder;
use App\Services\Billing\ManualPaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPaymentOrderController extends Controller
{
    public function __construct(
        protected ManualPaymentService $manualPaymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PaymentOrder::with(['workspace', 'user', 'plan', 'reviewer', 'transactions'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', (int) $request->query('workspace_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('workspace', function ($wq) use ($search) {
                        $wq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate((int) $request->query('per_page', 15));

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

    public function show(int $id): JsonResponse
    {
        $order = PaymentOrder::with(['workspace', 'user', 'plan', 'reviewer', 'transactions'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PaymentOrderResource($order),
        ]);
    }

    public function approve(AdminApprovePaymentOrderRequest $request, int $id): JsonResponse
    {
        $order = PaymentOrder::findOrFail($id);

        try {
            $approvedOrder = $this->manualPaymentService->approveOrder(
                order: $order,
                admin: $request->user(),
                adminNote: $request->input('admin_note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Duyệt đơn thanh toán và kích hoạt gói cước thành công.',
                'data' => new PaymentOrderResource($approvedOrder),
            ]);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi duyệt đơn thanh toán: '.$e->getMessage(), [
                'order_id' => $id,
                'admin_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi duyệt đơn thanh toán.',
            ], 500);
        }
    }

    public function reject(AdminRejectPaymentOrderRequest $request, int $id): JsonResponse
    {
        $order = PaymentOrder::findOrFail($id);

        try {
            $rejectedOrder = $this->manualPaymentService->rejectOrder(
                order: $order,
                admin: $request->user(),
                reason: $request->input('rejection_reason'),
                adminNote: $request->input('admin_note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã từ chối đơn thanh toán.',
                'data' => new PaymentOrderResource($rejectedOrder),
            ]);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi từ chối đơn thanh toán: '.$e->getMessage(), [
                'order_id' => $id,
                'admin_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi từ chối đơn thanh toán.',
            ], 500);
        }
    }
}
