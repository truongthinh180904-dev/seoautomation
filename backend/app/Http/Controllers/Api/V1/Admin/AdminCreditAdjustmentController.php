<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAdjustCreditRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminCreditAdjustmentController extends Controller
{
    public function __construct(
        protected CreditLedgerService $creditLedgerService
    ) {}

    public function adjust(AdminAdjustCreditRequest $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $amount = (int) $request->input('amount');
        $reason = $request->input('reason');
        $admin = $request->user();

        try {
            $transaction = $this->creditLedgerService->adminAdjustCredits(
                workspace: $workspace,
                amount: $amount,
                admin: $admin,
                reason: $reason
            );

            $newBalance = $this->creditLedgerService->getBalance($workspace);

            return response()->json([
                'success' => true,
                'message' => sprintf('Đã điều chỉnh %s%d credit thành công.', $amount > 0 ? '+' : '', $amount),
                'data' => [
                    'transaction' => new CreditTransactionResource($transaction),
                    'new_balance' => [
                        'available_credits' => $newBalance->availableCredits,
                        'reserved_credits' => $newBalance->reservedCredits,
                        'total_credits' => $newBalance->totalCredits,
                    ],
                ],
            ]);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi điều chỉnh credit thủ công: '.$e->getMessage(), [
                'workspace_id' => $workspaceId,
                'amount' => $amount,
                'admin_id' => $admin?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi điều chỉnh credit.',
            ], 500);
        }
    }
}
