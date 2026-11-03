<?php

namespace App\Services\Billing;

use App\DTOs\CreditBalanceDTO;
use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditAccount;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreditLedgerService
{
    /**
     * Lấy hoặc khởi tạo tài khoản credit cho workspace.
     */
    public function getOrCreateAccount(Workspace|int $workspace): CreditAccount
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return CreditAccount::firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'available_credits' => 0,
                'reserved_credits' => 0,
                'cycle_granted_credits' => 0,
                'cycle_used_credits' => 0,
                'version' => 1,
            ]
        );
    }

    /**
     * Lấy DTO thông tin số dư credit của workspace.
     */
    public function getBalance(Workspace|int $workspace): CreditBalanceDTO
    {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);
        $account = $this->getOrCreateAccount($workspaceModel);
        $subscription = $workspaceModel->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->latest('id')
            ->with('plan')
            ->first();

        return new CreditBalanceDTO(
            workspaceId: $workspaceModel->id,
            availableCredits: (int) $account->available_credits,
            reservedCredits: (int) $account->reserved_credits,
            totalCredits: (int) ($account->available_credits + $account->reserved_credits),
            cycleGrantedCredits: (int) $account->cycle_granted_credits,
            cycleUsedCredits: (int) $account->cycle_used_credits,
            periodStart: $account->period_start,
            periodEnd: $account->period_end,
            planCode: $subscription?->plan?->code?->value,
            planName: $subscription?->plan?->name
        );
    }

    /**
     * Kiểm tra số dư khả dụng có đủ không.
     */
    public function hasSufficientCredits(Workspace|int $workspace, int $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $account = $this->getOrCreateAccount($workspaceId);

        return $account->available_credits >= $amount;
    }

    /**
     * Kiểm tra số dư và ném ngoại lệ nếu không đủ.
     *
     * @throws InsufficientCreditsException
     */
    public function checkCredits(Workspace|int $workspace, int $amount): void
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $account = $this->getOrCreateAccount($workspaceId);

        if ($account->available_credits < $amount) {
            throw new InsufficientCreditsException(
                requiredCredits: $amount,
                availableCredits: (int) $account->available_credits,
                workspaceId: $workspaceId
            );
        }
    }

    /**
     * Tạm giữ credit trước khi dispatch job AI/SERP (concurrency lock).
     *
     * @throws InsufficientCreditsException
     */
    public function reserveCredits(
        Workspace|int $workspace,
        int $amount,
        string $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
        ?array $metadata = null,
        ?User $actor = null
    ): CreditTransaction {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $key = $idempotencyKey ?? sprintf('reserve:%d:%s:%d', $workspaceId, $referenceType, $referenceId);

        return DB::transaction(function () use ($workspaceId, $amount, $referenceType, $referenceId, $key, $metadata, $actor) {
            // Idempotency check
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            /** @var CreditAccount $account */
            $account = CreditAccount::where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = $this->getOrCreateAccount($workspaceId);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            if ($account->available_credits < $amount) {
                throw new InsufficientCreditsException(
                    requiredCredits: $amount,
                    availableCredits: (int) $account->available_credits,
                    workspaceId: $workspaceId
                );
            }

            $balanceBefore = (int) $account->available_credits;
            $balanceAfter = $balanceBefore - $amount;

            $account->available_credits = $balanceAfter;
            $account->reserved_credits += $amount;
            $account->version += 1;
            $account->save();

            return CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'credit_grant_id' => null,
                'type' => CreditTransactionType::RESERVE,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_id' => $actor?->id,
                'idempotency_key' => $key,
                'description' => sprintf('Tạm giữ %d credit cho %s #%d', $amount, $referenceType, $referenceId),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Trừ credit khi hoàn tất tác vụ (có thể trừ từ phần đã reserve hoặc trừ trực tiếp).
     */
    public function deductCredits(
        Workspace|int $workspace,
        int $amount,
        string $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
        ?array $metadata = null,
        bool $fromReserved = false,
        ?User $actor = null
    ): CreditTransaction {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $key = $idempotencyKey ?? sprintf('deduct:%d:%s:%d:%s', $workspaceId, $referenceType, $referenceId, $fromReserved ? 'from_res' : 'direct');

        return DB::transaction(function () use ($workspaceId, $amount, $referenceType, $referenceId, $key, $metadata, $fromReserved, $actor) {
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            /** @var CreditAccount $account */
            $account = CreditAccount::where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = $this->getOrCreateAccount($workspaceId);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            $balanceBefore = (int) $account->available_credits;

            if ($fromReserved) {
                // Đã reserve trước đó: trừ từ reserved_credits, available_credits không đổi
                $actualFromReserved = min((int) $account->reserved_credits, $amount);
                $account->reserved_credits = max(0, (int) $account->reserved_credits - $amount);
                $account->cycle_used_credits += $amount;
                $balanceAfter = $balanceBefore;
            } else {
                // Trừ trực tiếp từ available_credits
                if ($account->available_credits < $amount) {
                    throw new InsufficientCreditsException(
                        requiredCredits: $amount,
                        availableCredits: (int) $account->available_credits,
                        workspaceId: $workspaceId
                    );
                }

                $balanceAfter = $balanceBefore - $amount;
                $account->available_credits = $balanceAfter;
                $account->cycle_used_credits += $amount;
            }

            $account->version += 1;
            $account->save();

            // Khấu trừ vào các grant còn hạn theo FIFO (FIFO credit consumption)
            $this->consumeGrantsFifo($account, $amount);

            return CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'credit_grant_id' => null,
                'type' => CreditTransactionType::DEDUCT,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_id' => $actor?->id,
                'idempotency_key' => $key,
                'description' => sprintf(
                    'Trừ %d credit cho %s #%d%s',
                    $amount,
                    $referenceType,
                    $referenceId,
                    $fromReserved ? ' (từ hạn mức tạm giữ)' : ''
                ),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Giải phóng credit đã tạm giữ trở lại số dư khả dụng (khi job hủy hoặc fail trước khi chạy).
     */
    public function releaseCredits(
        Workspace|int $workspace,
        int $amount,
        string $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
        ?string $reason = null,
        ?User $actor = null
    ): CreditTransaction {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $key = $idempotencyKey ?? sprintf('release:%d:%s:%d', $workspaceId, $referenceType, $referenceId);

        return DB::transaction(function () use ($workspaceId, $amount, $referenceType, $referenceId, $key, $reason, $actor) {
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            /** @var CreditAccount $account */
            $account = CreditAccount::where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = $this->getOrCreateAccount($workspaceId);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            $actualRelease = min((int) $account->reserved_credits, $amount);
            $balanceBefore = (int) $account->available_credits;
            $balanceAfter = $balanceBefore + $actualRelease;

            $account->reserved_credits = max(0, (int) $account->reserved_credits - $actualRelease);
            $account->available_credits = $balanceAfter;
            $account->version += 1;
            $account->save();

            return CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'credit_grant_id' => null,
                'type' => CreditTransactionType::RELEASE_REFUND,
                'amount' => $actualRelease,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_id' => $actor?->id,
                'idempotency_key' => $key,
                'description' => sprintf(
                    'Giải phóng %d credit tạm giữ cho %s #%d%s',
                    $actualRelease,
                    $referenceType,
                    $referenceId,
                    $reason ? " ({$reason})" : ''
                ),
                'metadata' => ['reason' => $reason],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Hoàn credit cho khách hàng khi job đã bị deduct nhưng gặp sự cố hợp lệ cần bồi hoàn.
     */
    public function refundCredits(
        Workspace|int $workspace,
        int $amount,
        string $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
        ?string $reason = null,
        ?User $actor = null
    ): CreditTransaction {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $key = $idempotencyKey ?? sprintf('refund:%d:%s:%d', $workspaceId, $referenceType, $referenceId);

        return DB::transaction(function () use ($workspaceId, $amount, $referenceType, $referenceId, $key, $reason, $actor) {
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            /** @var CreditAccount $account */
            $account = CreditAccount::where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = $this->getOrCreateAccount($workspaceId);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            $balanceBefore = (int) $account->available_credits;
            $balanceAfter = $balanceBefore + $amount;

            $account->available_credits = $balanceAfter;
            $account->cycle_used_credits = max(0, (int) $account->cycle_used_credits - $amount);
            $account->version += 1;
            $account->save();

            return CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'credit_grant_id' => null,
                'type' => CreditTransactionType::RELEASE_REFUND,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_id' => $actor?->id,
                'idempotency_key' => $key,
                'description' => sprintf(
                    'Hoàn trả %d credit cho %s #%d%s',
                    $amount,
                    $referenceType,
                    $referenceId,
                    $reason ? " ({$reason})" : ''
                ),
                'metadata' => ['reason' => $reason, 'action' => 'refund'],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Cấp credit theo chu kỳ hoặc khuyến mãi (Idempotent).
     * Nếu $resetPrevious = true, sẽ thu hồi/expire toàn bộ credit còn dư của chu kỳ trước (Carryover = false).
     */
    public function grantCredits(
        Workspace|int $workspace,
        int $amount,
        CreditGrantSource $source,
        CarbonInterface $expiresAt,
        ?Subscription $subscription = null,
        ?PaymentOrder $order = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $note = null,
        bool $resetPrevious = false
    ): CreditGrant {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $key = $idempotencyKey ?? sprintf(
            'grant:%d:%s:%s',
            $workspaceId,
            $source->value,
            now()->format('YmdHis')
        );

        return DB::transaction(function () use (
            $workspaceId,
            $amount,
            $source,
            $expiresAt,
            $subscription,
            $order,
            $actor,
            $key,
            $note,
            $resetPrevious
        ) {
            // Idempotency check trên CreditGrant
            $existing = CreditGrant::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            /** @var CreditAccount $account */
            $account = CreditAccount::where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = $this->getOrCreateAccount($workspaceId);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            // Nếu reset previous (không cộng dồn sang tháng mới / ngày mới):
            if ($resetPrevious) {
                $this->expireAllActiveGrantsInternal($account, 'Chu kỳ mới bắt đầu - hết hạn credit chu kỳ cũ (không cộng dồn)');
            }

            $balanceBefore = (int) $account->available_credits;
            $balanceAfter = $balanceBefore + $amount;

            $account->available_credits = $balanceAfter;
            $account->cycle_granted_credits = $amount;
            $account->cycle_used_credits = 0;
            $account->period_start = now();
            $account->period_end = $expiresAt;
            $account->version += 1;
            $account->save();

            $grant = CreditGrant::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'subscription_id' => $subscription?->id,
                'payment_order_id' => $order?->id,
                'source_type' => $source,
                'credits_granted' => $amount,
                'credits_remaining' => $amount,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'expired_processed_at' => null,
                'granted_by' => $actor?->id,
                'idempotency_key' => $key,
                'note' => $note,
            ]);

            CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'credit_grant_id' => $grant->id,
                'type' => CreditTransactionType::GRANT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $subscription ? 'subscription' : ($order ? 'payment_order' : null),
                'reference_id' => $subscription?->id ?? $order?->id,
                'actor_id' => $actor?->id,
                'idempotency_key' => 'tx:'.$key,
                'description' => sprintf('Cấp %d credit (%s)', $amount, $source->label()),
                'metadata' => [
                    'source' => $source->value,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'note' => $note,
                ],
                'created_at' => now(),
            ]);

            return $grant;
        });
    }

    /**
     * Admin điều chỉnh credit thủ công (có thể cộng hoặc trừ).
     */
    public function adminAdjustCredits(
        Workspace|int $workspace,
        int $amount,
        User $admin,
        string $reason,
        ?string $idempotencyKey = null
    ): CreditTransaction {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $key = $idempotencyKey ?? sprintf('admin_adj:%d:%s:%s', $workspaceId, now()->format('YmdHis'), uniqid());

        return DB::transaction(function () use ($workspaceId, $amount, $admin, $reason, $key) {
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            /** @var CreditAccount $account */
            $account = CreditAccount::where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = $this->getOrCreateAccount($workspaceId);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            $balanceBefore = (int) $account->available_credits;
            $balanceAfter = max(0, $balanceBefore + $amount);
            $actualDelta = $balanceAfter - $balanceBefore;

            $account->available_credits = $balanceAfter;
            $account->version += 1;
            $account->save();

            // Nếu là cộng credit, tạo thêm CreditGrant để theo dõi
            $grantId = null;
            if ($amount > 0) {
                $grant = CreditGrant::create([
                    'credit_account_id' => $account->id,
                    'workspace_id' => $workspaceId,
                    'source_type' => CreditGrantSource::MANUAL_ADMIN,
                    'credits_granted' => $amount,
                    'credits_remaining' => $amount,
                    'granted_at' => now(),
                    'expires_at' => now()->addDays(30),
                    'granted_by' => $admin->id,
                    'idempotency_key' => 'grant:'.$key,
                    'note' => $reason,
                ]);
                $grantId = $grant->id;
            }

            return CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $workspaceId,
                'credit_grant_id' => $grantId,
                'type' => CreditTransactionType::ADMIN_ADJUST,
                'amount' => $actualDelta,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'user',
                'reference_id' => $admin->id,
                'actor_id' => $admin->id,
                'idempotency_key' => $key,
                'description' => sprintf(
                    'Admin %s %d credit: %s',
                    $actualDelta >= 0 ? 'cộng' : 'trừ',
                    abs($actualDelta),
                    $reason
                ),
                'metadata' => [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Hết hạn một lô credit grant cụ thể (khi hết chu kỳ, không cộng dồn).
     */
    public function expireGrant(CreditGrant $grant): ?CreditTransaction
    {
        if ($grant->expired_processed_at !== null || $grant->credits_remaining <= 0) {
            $grant->update(['expired_processed_at' => now()]);

            return null;
        }

        return DB::transaction(function () use ($grant) {
            /** @var CreditAccount $account */
            $account = CreditAccount::where('id', $grant->credit_account_id)
                ->lockForUpdate()
                ->first();

            $remaining = (int) $grant->credits_remaining;
            if ($remaining <= 0) {
                $grant->update(['expired_processed_at' => now()]);

                return null;
            }

            $balanceBefore = (int) $account->available_credits;
            $expireAmount = min($balanceBefore, $remaining);
            $balanceAfter = max(0, $balanceBefore - $expireAmount);

            $account->available_credits = $balanceAfter;
            $account->version += 1;
            $account->save();

            $grant->credits_remaining = 0;
            $grant->expired_processed_at = now();
            $grant->save();

            return CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $grant->workspace_id,
                'credit_grant_id' => $grant->id,
                'type' => CreditTransactionType::EXPIRE,
                'amount' => -$expireAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'credit_grant',
                'reference_id' => $grant->id,
                'actor_id' => null,
                'idempotency_key' => sprintf('expire:grant:%d', $grant->id),
                'description' => sprintf('Hết hạn %d credit từ lô #%d (không cộng dồn sang chu kỳ mới)', $expireAmount, $grant->id),
                'metadata' => [
                    'grant_id' => $grant->id,
                    'source_type' => $grant->source_type->value,
                    'granted_at' => $grant->granted_at?->toIso8601String(),
                    'expires_at' => $grant->expires_at?->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Quét và xử lý hết hạn toàn bộ các grant đã quá hạn của workspace.
     */
    public function expireDueGrants(Workspace|int $workspace): int
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        $dueGrants = CreditGrant::where('workspace_id', $workspaceId)
            ->where('expires_at', '<=', now())
            ->whereNull('expired_processed_at')
            ->get();

        $count = 0;
        foreach ($dueGrants as $grant) {
            $tx = $this->expireGrant($grant);
            if ($tx) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Cấp 100 credit hàng ngày cho Gói Free (chu kỳ 24h từ lần cấp gần nhất).
     * Không cộng dồn: credit còn dư của 24h trước hết hạn.
     */
    public function ensureDailyFreeCredits(Workspace|int $workspace): ?CreditGrant
    {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);

        return DB::transaction(function () use ($workspaceModel) {
            // Kiểm tra gói active hiện tại: nếu đang có gói trả phí còn hạn thì không áp dụng Free hàng ngày
            $activeSub = $workspaceModel->subscriptions()
                ->where('status', SubscriptionStatus::ACTIVE)
                ->latest('id')
                ->with('plan')
                ->first();

            if ($activeSub && $activeSub->plan && $activeSub->plan->code !== PlanCode::FREE) {
                return null;
            }

            // Tìm plan Free
            $freePlan = Plan::where('code', PlanCode::FREE)->first();
            if (! $freePlan) {
                return null;
            }

            // Kiểm tra lần cấp Free gần nhất
            $lastFreeGrant = CreditGrant::where('workspace_id', $workspaceModel->id)
                ->where('source_type', CreditGrantSource::FREE_DAILY)
                ->orderByDesc('granted_at')
                ->orderByDesc('id')
                ->first();

            // Nếu đã cấp trong vòng 24h và chưa hết hạn, bỏ qua
            if ($lastFreeGrant && $lastFreeGrant->expires_at > now()) {
                return $lastFreeGrant;
            }

            // Đã quá 24h hoặc chưa từng được cấp: tạo subscription Free nếu chưa có
            if (! $activeSub) {
                $activeSub = Subscription::create([
                    'workspace_id' => $workspaceModel->id,
                    'plan_id' => $freePlan->id,
                    'status' => SubscriptionStatus::ACTIVE,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addDay(),
                    'last_credit_granted_at' => now(),
                    'next_credit_grant_at' => now()->addDay(),
                ]);
            } else {
                $activeSub->update([
                    'current_period_start' => now(),
                    'current_period_end' => now()->addDay(),
                    'last_credit_granted_at' => now(),
                    'next_credit_grant_at' => now()->addDay(),
                ]);
            }

            $idempotencyKey = $lastFreeGrant
                ? sprintf('free_daily:ws_%d:after_%d', $workspaceModel->id, $lastFreeGrant->id)
                : sprintf('free_daily:ws_%d:initial', $workspaceModel->id);

            return $this->grantCredits(
                workspace: $workspaceModel,
                amount: (int) $freePlan->credits_per_cycle,
                source: CreditGrantSource::FREE_DAILY,
                expiresAt: now()->addDay(),
                subscription: $activeSub,
                idempotencyKey: $idempotencyKey,
                note: 'Cấp 100 credit miễn phí hàng ngày (chu kỳ 24h, không cộng dồn)',
                resetPrevious: true
            );
        });
    }

    /**
     * Trừ dần hạn mức trên các CreditGrant theo FIFO.
     */
    protected function consumeGrantsFifo(CreditAccount $account, int $amountToConsume): void
    {
        if ($amountToConsume <= 0) {
            return;
        }

        $activeGrants = CreditGrant::where('credit_account_id', $account->id)
            ->where('credits_remaining', '>', 0)
            ->where('expires_at', '>', now())
            ->whereNull('expired_processed_at')
            ->orderBy('expires_at', 'asc')
            ->get();

        $remainingToDeduct = $amountToConsume;

        foreach ($activeGrants as $grant) {
            if ($remainingToDeduct <= 0) {
                break;
            }

            $availableInGrant = (int) $grant->credits_remaining;
            $deductFromThis = min($availableInGrant, $remainingToDeduct);

            $grant->credits_remaining = $availableInGrant - $deductFromThis;
            $grant->save();

            $remainingToDeduct -= $deductFromThis;
        }
    }

    /**
     * Thu hồi toàn bộ credit grant còn hạn bên trong transaction.
     */
    protected function expireAllActiveGrantsInternal(CreditAccount $account, string $reason): void
    {
        $activeGrants = CreditGrant::where('credit_account_id', $account->id)
            ->where('credits_remaining', '>', 0)
            ->whereNull('expired_processed_at')
            ->get();

        $totalRemaining = 0;
        foreach ($activeGrants as $grant) {
            $totalRemaining += (int) $grant->credits_remaining;
            $grant->credits_remaining = 0;
            $grant->expired_processed_at = now();
            $grant->save();
        }

        if ($totalRemaining > 0 && $account->available_credits > 0) {
            $balanceBefore = (int) $account->available_credits;
            $expireAmount = min($balanceBefore, $totalRemaining);
            $balanceAfter = max(0, $balanceBefore - $expireAmount);

            $account->available_credits = $balanceAfter;

            CreditTransaction::create([
                'credit_account_id' => $account->id,
                'workspace_id' => $account->workspace_id,
                'credit_grant_id' => null,
                'type' => CreditTransactionType::EXPIRE,
                'amount' => -$expireAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'cycle_reset',
                'reference_id' => null,
                'actor_id' => null,
                'idempotency_key' => sprintf('expire:reset:%d:%s_%s', $account->workspace_id, now()->format('YmdHis'), uniqid()),
                'description' => sprintf('Hết hạn %d credit còn dư: %s', $expireAmount, $reason),
                'metadata' => ['total_cleared' => $expireAmount],
                'created_at' => now(),
            ]);
        }
    }
}
