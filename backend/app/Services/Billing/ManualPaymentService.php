<?php

namespace App\Services\Billing;

use App\Enums\PaymentDirection;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentOrder;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Security\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualPaymentService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected AuditLogService $auditLogService,
        protected CreditLedgerService $creditLedgerService
    ) {}

    /**
     * Cấu hình thông tin tài khoản ngân hàng nhận thanh toán.
     */
    public function getBankTransferConfig(): array
    {
        return [
            'bank_name' => config('services.bank_transfer.name', 'MB Bank (Ngan hang Quan Doi)'),
            'bank_code' => config('services.bank_transfer.code', 'MB'),
            'account_number' => config('services.bank_transfer.account_number', '0987654321'),
            'account_holder' => config('services.bank_transfer.account_holder', 'CONG TY CP CONG NGHE AUTO SEO'),
            'branch' => config('services.bank_transfer.branch', 'Hoi So Chinh - Ha Noi'),
            'instructions' => 'Vui lòng ghi đúng cú pháp chuyển khoản để hệ thống và Admin đối soát nhanh chóng nhất.',
        ];
    }

    /**
     * Tạo đơn hàng chuyển khoản thủ công cho Workspace.
     */
    public function createOrder(
        Workspace $workspace,
        Plan $plan,
        User $user,
        ?string $customerNote = null
    ): PaymentOrder {
        if (! $plan->is_active) {
            throw new DomainException('Gói dịch vụ đã ngừng hoạt động.');
        }

        if ($plan->price_monthly_vnd <= 0) {
            throw new DomainException('Gói miễn phí không cần tạo đơn chuyển khoản ngân hàng.');
        }

        // Tạo mã tham chiếu duy nhất (VD: SEOPRO_10293)
        $planCodePrefix = 'SEO'.strtoupper(substr($plan->code->value, 0, 3));
        do {
            $referenceCode = sprintf('%s_%d', $planCodePrefix, random_int(10000, 99999));
        } while (PaymentOrder::where('reference_code', $referenceCode)->exists());

        $bankConfig = $this->getBankTransferConfig();
        $bankConfig['transfer_syntax'] = $referenceCode;
        $bankConfig['amount_vnd'] = (int) $plan->price_monthly_vnd;
        $bankConfig['qr_payload'] = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
            $bankConfig['bank_code'],
            $bankConfig['account_number'],
            $plan->price_monthly_vnd,
            urlencode($referenceCode),
            urlencode($bankConfig['account_holder'])
        );

        $idempotencyKey = 'order_'.Str::uuid()->toString();

        return DB::transaction(function () use (
            $workspace,
            $plan,
            $user,
            $referenceCode,
            $bankConfig,
            $customerNote,
            $idempotencyKey
        ) {
            $order = PaymentOrder::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'reference_code' => $referenceCode,
                'amount_vnd' => (int) $plan->price_monthly_vnd,
                'currency' => 'VND',
                'payment_method' => 'bank_transfer',
                'status' => PaymentOrderStatus::PENDING_PAYMENT,
                'bank_info_snapshot' => $bankConfig,
                'proof_image_path' => null,
                'customer_note' => $customerNote,
                'proof_submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'admin_note' => null,
                'rejection_reason' => null,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addHours(72),
            ]);

            $this->auditLogService->log(
                action: 'billing.order_created',
                auditable: $order,
                oldValues: [],
                newValues: [
                    'reference_code' => $order->reference_code,
                    'amount_vnd' => $order->amount_vnd,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                ],
                notes: sprintf('Khách hàng tạo đơn chuyển khoản %s cho gói %s', $order->reference_code, $plan->name),
                workspaceId: $workspace->id,
                actor: $user
            );

            return $order->fresh(['plan', 'workspace', 'user']);
        });
    }

    /**
     * Khách hàng tải ảnh / gửi minh chứng thanh toán.
     */
    public function submitProof(
        PaymentOrder $order,
        string $proofImagePath,
        ?string $customerNote = null,
        ?User $actor = null
    ): PaymentOrder {
        return DB::transaction(function () use ($order, $proofImagePath, $customerNote, $actor) {
            /** @var PaymentOrder $order */
            $order = PaymentOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, [PaymentOrderStatus::PENDING_PAYMENT, PaymentOrderStatus::PENDING_VERIFICATION])) {
                throw new DomainException(sprintf('Không thể gửi minh chứng cho đơn hàng ở trạng thái %s.', $order->status->label()));
            }

            $oldValues = $order->only(['status', 'proof_image_path', 'customer_note', 'proof_submitted_at']);

            $order->update([
                'proof_image_path' => $proofImagePath,
                'customer_note' => $customerNote ?? $order->customer_note,
                'proof_submitted_at' => now(),
                'status' => PaymentOrderStatus::PENDING_VERIFICATION,
            ]);

            $this->auditLogService->log(
                action: 'billing.proof_submitted',
                auditable: $order,
                oldValues: $oldValues,
                newValues: $order->only(['status', 'proof_image_path', 'customer_note', 'proof_submitted_at']),
                notes: sprintf('Khách hàng gửi minh chứng thanh toán cho đơn %s', $order->reference_code),
                workspaceId: $order->workspace_id,
                actor: $actor
            );

            return $order->fresh(['plan', 'workspace', 'user']);
        });
    }

    /**
     * Khách hàng hủy đơn hàng đang chờ thanh toán.
     */
    public function cancelOrder(
        PaymentOrder $order,
        User $user,
        ?string $reason = null
    ): PaymentOrder {
        return DB::transaction(function () use ($order, $user, $reason) {
            /** @var PaymentOrder $order */
            $order = PaymentOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, [PaymentOrderStatus::PENDING_PAYMENT, PaymentOrderStatus::PENDING_VERIFICATION])) {
                throw new DomainException(sprintf('Chỉ có thể hủy đơn hàng đang chờ xử lý (trạng thái hiện tại: %s).', $order->status->label()));
            }

            $oldValues = $order->only(['status', 'customer_note']);

            $updatedNote = $order->customer_note;
            if ($reason) {
                $updatedNote = $updatedNote ? "{$updatedNote} | Lý do hủy: {$reason}" : "Lý do hủy: {$reason}";
            }

            $order->update([
                'status' => PaymentOrderStatus::CANCELLED,
                'customer_note' => $updatedNote,
            ]);

            $this->auditLogService->log(
                action: 'billing.order_cancelled',
                auditable: $order,
                oldValues: $oldValues,
                newValues: $order->only(['status', 'customer_note']),
                notes: sprintf('Người dùng %s hủy đơn hàng %s', $user->email, $order->reference_code),
                workspaceId: $order->workspace_id,
                actor: $user
            );

            return $order->fresh(['plan', 'workspace', 'user']);
        });
    }

    /**
     * Admin duyệt đơn thanh toán và tự động kích hoạt subscription + credit.
     * Chống trùng lặp (Idempotent lock).
     */
    public function approveOrder(
        PaymentOrder $order,
        User $admin,
        ?string $adminNote = null
    ): PaymentOrder {
        return DB::transaction(function () use ($order, $admin, $adminNote) {
            /** @var PaymentOrder $order */
            $order = PaymentOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // Idempotency: Nếu đã approved trước đó, trả về an toàn không cấp trùng credit
            if ($order->status === PaymentOrderStatus::APPROVED) {
                return $order->fresh(['plan', 'workspace', 'reviewer', 'transactions']);
            }

            if (! in_array($order->status, [PaymentOrderStatus::PENDING_PAYMENT, PaymentOrderStatus::PENDING_VERIFICATION])) {
                throw new DomainException(sprintf('Chỉ có thể duyệt đơn ở trạng thái Chờ thanh toán hoặc Chờ xác nhận (hiện tại: %s).', $order->status->label()));
            }

            $oldValues = $order->only(['status', 'reviewed_by', 'reviewed_at', 'admin_note']);

            // 1. Cập nhật trạng thái đơn
            $order->update([
                'status' => PaymentOrderStatus::APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $adminNote,
            ]);

            // 2. Tạo bản ghi giao dịch payment_transactions
            PaymentTransaction::create([
                'payment_order_id' => $order->id,
                'workspace_id' => $order->workspace_id,
                'transaction_code' => 'TXN_'.$order->reference_code,
                'amount_vnd' => (int) $order->amount_vnd,
                'direction' => PaymentDirection::INBOUND,
                'status' => PaymentTransactionStatus::VERIFIED,
                'verified_by' => $admin->id,
                'paid_at' => now(),
                'raw_payload' => [
                    'approved_by_admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'admin_note' => $adminNote,
                    'reference_code' => $order->reference_code,
                ],
            ]);

            // 3. Tự động kích hoạt Subscription và cấp Credit tương ứng cho Workspace
            $this->subscriptionService->subscribe(
                workspace: $order->workspace,
                plan: $order->plan,
                order: $order,
                actor: $admin
            );

            // 4. Ghi Audit Log có Admin ID, thời gian và số tiền
            $this->auditLogService->log(
                action: 'billing.order_approved',
                auditable: $order,
                oldValues: $oldValues,
                newValues: array_merge(
                    $order->only(['status', 'reviewed_by', 'reviewed_at', 'admin_note']),
                    [
                        'admin_id' => $admin->id,
                        'amount_vnd' => (int) $order->amount_vnd,
                        'reviewed_at' => $order->reviewed_at?->toIso8601String(),
                    ]
                ),
                notes: sprintf(
                    'Admin %s duyệt đơn hàng %s, số tiền %s VND, kích hoạt gói %s',
                    $admin->email,
                    $order->reference_code,
                    number_format($order->amount_vnd),
                    $order->plan->name
                ),
                workspaceId: $order->workspace_id,
                actor: $admin
            );

            return $order->fresh(['plan', 'workspace', 'reviewer', 'transactions']);
        });
    }

    /**
     * Admin từ chối đơn thanh toán kèm lý do.
     */
    public function rejectOrder(
        PaymentOrder $order,
        User $admin,
        string $reason,
        ?string $adminNote = null
    ): PaymentOrder {
        return DB::transaction(function () use ($order, $admin, $reason, $adminNote) {
            /** @var PaymentOrder $order */
            $order = PaymentOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === PaymentOrderStatus::REJECTED) {
                return $order->fresh(['plan', 'workspace', 'reviewer']);
            }

            if ($order->status === PaymentOrderStatus::APPROVED) {
                throw new DomainException('Không thể từ chối đơn hàng đã được duyệt thành công.');
            }

            $oldValues = $order->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'admin_note']);

            $order->update([
                'status' => PaymentOrderStatus::REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'admin_note' => $adminNote,
            ]);

            $this->auditLogService->log(
                action: 'billing.order_rejected',
                auditable: $order,
                oldValues: $oldValues,
                newValues: array_merge(
                    $order->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'admin_note']),
                    [
                        'admin_id' => $admin->id,
                        'reviewed_at' => $order->reviewed_at?->toIso8601String(),
                    ]
                ),
                notes: sprintf('Admin %s từ chối đơn hàng %s. Lý do: %s', $admin->email, $order->reference_code, $reason),
                workspaceId: $order->workspace_id,
                actor: $admin
            );

            return $order->fresh(['plan', 'workspace', 'reviewer']);
        });
    }

    /**
     * Admin cập nhật trạng thái workspace kèm ghi log kiểm toán nguyên tử.
     */
    public function updateWorkspaceStatus(
        Workspace $workspace,
        string $newStatus,
        User $admin,
        ?string $reason = null
    ): Workspace {
        return DB::transaction(function () use ($workspace, $newStatus, $admin, $reason) {
            /** @var Workspace $lockedWorkspace */
            $lockedWorkspace = Workspace::where('id', $workspace->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $lockedWorkspace->status?->value ?? (string) $lockedWorkspace->status;

            $lockedWorkspace->update([
                'status' => $newStatus,
            ]);

            $this->auditLogService->log(
                action: 'workspace.status_updated',
                auditable: $lockedWorkspace,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $newStatus,
                    'reason' => $reason,
                    'admin_id' => $admin->id,
                ],
                notes: sprintf(
                    'Admin %s cập nhật trạng thái workspace %s (%d) từ %s sang %s%s',
                    $admin->email,
                    $lockedWorkspace->name,
                    $lockedWorkspace->id,
                    $oldStatus,
                    $newStatus,
                    $reason ? " | Lý do: {$reason}" : ''
                ),
                workspaceId: $lockedWorkspace->id,
                actor: $admin
            );

            return $lockedWorkspace->fresh();
        });
    }
}
