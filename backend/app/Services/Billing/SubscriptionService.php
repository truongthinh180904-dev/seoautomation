<?php

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\CreditGrantSource;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\TaskStatus;
use App\Exceptions\QuotaExceededException;
use App\Exceptions\SubscriptionException;
use App\Models\Article;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        protected CreditLedgerService $creditLedgerService
    ) {}

    /**
     * Lấy gói dịch vụ đang hoạt động của Workspace (mặc định Free nếu chưa đăng ký gói nào).
     */
    public function getActivePlan(Workspace|int $workspace): Plan
    {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);

        $subscription = $workspaceModel->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('current_period_end', '>', now())
            ->latest('id')
            ->with('plan')
            ->first();

        if ($subscription && $subscription->plan) {
            return $subscription->plan;
        }

        // Fallback về Free plan
        return Plan::where('code', PlanCode::FREE)->firstOrFail();
    }

    /**
     * Kích hoạt hoặc nâng cấp gói dịch vụ cho Workspace (Idempotent qua DB transaction).
     */
    public function subscribe(
        Workspace|int $workspace,
        Plan $plan,
        ?PaymentOrder $order = null,
        ?User $actor = null
    ): Subscription {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);

        return DB::transaction(function () use ($workspaceModel, $plan, $order, $actor) {
            // Hủy/kết thúc các subscription active trước đó nếu có
            $activeSubs = $workspaceModel->subscriptions()
                ->where('status', SubscriptionStatus::ACTIVE)
                ->get();

            foreach ($activeSubs as $oldSub) {
                $oldSub->update([
                    'status' => SubscriptionStatus::CANCELLED,
                    'cancelled_at' => now(),
                    'metadata' => array_merge($oldSub->metadata ?? [], [
                        'cancelled_reason' => 'Nâng cấp sang gói '.$plan->name,
                    ]),
                ]);
            }

            $now = now();
            $periodEnd = $plan->billing_interval === BillingInterval::DAILY
                ? $now->copy()->addDay()
                : $now->copy()->addMonth();

            $subscription = Subscription::create([
                'workspace_id' => $workspaceModel->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::ACTIVE,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'last_credit_granted_at' => $now,
                'next_credit_grant_at' => $periodEnd,
                'activated_by_order_id' => $order?->id,
                'metadata' => [
                    'plan_code' => $plan->code->value,
                    'plan_name' => $plan->name,
                    'order_reference' => $order?->reference_code,
                ],
            ]);

            // Cấp credit chu kỳ mới
            $source = $plan->code === PlanCode::FREE
                ? CreditGrantSource::FREE_DAILY
                : CreditGrantSource::SUBSCRIPTION_CYCLE;

            $idempotencyKey = sprintf(
                'sub_grant:%d:%d:%s',
                $workspaceModel->id,
                $subscription->id,
                $now->format('YmdHis')
            );

            // Không cộng dồn credit còn dư từ gói cũ sang gói mới nếu carryover = false
            $resetPrevious = ! $plan->carryover;

            $this->creditLedgerService->grantCredits(
                workspace: $workspaceModel,
                amount: (int) $plan->credits_per_cycle,
                source: $source,
                expiresAt: $periodEnd,
                subscription: $subscription,
                order: $order,
                actor: $actor,
                idempotencyKey: $idempotencyKey,
                note: sprintf('Kích hoạt gói %s (%d credit)', $plan->name, $plan->credits_per_cycle),
                resetPrevious: $resetPrevious
            );

            return $subscription;
        });
    }

    /**
     * Tái tục chu kỳ mới cho gói (Reset credit cũ, cấp credit chu kỳ mới).
     */
    public function renewCycle(Subscription $subscription): void
    {
        if ($subscription->status !== SubscriptionStatus::ACTIVE) {
            throw new SubscriptionException('Subscription không ở trạng thái hoạt động để gia hạn.');
        }

        $plan = $subscription->plan;
        $workspace = $subscription->workspace;

        DB::transaction(function () use ($subscription, $plan, $workspace) {
            $now = now();
            $newEnd = $plan->billing_interval === BillingInterval::DAILY
                ? $now->copy()->addDay()
                : $now->copy()->addMonth();

            $subscription->update([
                'current_period_start' => $now,
                'current_period_end' => $newEnd,
                'last_credit_granted_at' => $now,
                'next_credit_grant_at' => $newEnd,
            ]);

            $source = $plan->code === PlanCode::FREE
                ? CreditGrantSource::FREE_DAILY
                : CreditGrantSource::SUBSCRIPTION_CYCLE;

            $idempotencyKey = sprintf(
                'renew_grant:%d:%d:%s',
                $workspace->id,
                $subscription->id,
                $now->format('YmdHis')
            );

            $this->creditLedgerService->grantCredits(
                workspace: $workspace,
                amount: (int) $plan->credits_per_cycle,
                source: $source,
                expiresAt: $newEnd,
                subscription: $subscription,
                idempotencyKey: $idempotencyKey,
                note: sprintf('Gia hạn chu kỳ gói %s (%d credit)', $plan->name, $plan->credits_per_cycle),
                resetPrevious: ! $plan->carryover
            );
        });
    }

    /**
     * Hủy gói dịch vụ.
     */
    public function cancel(Subscription $subscription, ?string $reason = null): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
            'metadata' => array_merge($subscription->metadata ?? [], [
                'cancel_reason' => $reason,
            ]),
        ]);
    }

    /**
     * Kiểm tra toàn diện các giới hạn trước khi thực hiện tác vụ (Bài viết, Concurrency, Credit).
     *
     * @throws QuotaExceededException
     */
    public function validateCanGenerateArticle(Workspace|int $workspace): void
    {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);
        $plan = $this->getActivePlan($workspaceModel);

        // 1. Kiểm tra hạn mức bài viết trong chu kỳ hiện tại
        $this->assertArticleLimitNotExceeded($workspaceModel, $plan);

        // 2. Kiểm tra giới hạn tác vụ song song (concurrency limit)
        $this->assertConcurrencyLimitNotExceeded($workspaceModel, $plan);

        // 3. Kiểm tra số dư credit cơ sở (20 credit / bài tiêu chuẩn)
        $requiredCredits = (int) config('ai_costs.credits.standard_article', 20);
        $this->creditLedgerService->checkCredits($workspaceModel, $requiredCredits);
    }

    /**
     * Kiểm tra giới hạn bài viết của gói.
     *
     * @throws QuotaExceededException
     */
    public function assertArticleLimitNotExceeded(Workspace $workspace, ?Plan $plan = null): void
    {
        $plan = $plan ?? $this->getActivePlan($workspace);
        $limit = (int) $plan->articles_limit_per_cycle;

        if ($limit <= 0) {
            return;
        }

        // Khoảng thời gian chu kỳ: nếu là daily thì trong ngày, nếu monthly thì trong tháng hiện tại
        $periodStart = $plan->billing_interval === BillingInterval::DAILY
            ? now()->startOfDay()
            : now()->startOfMonth();

        $articlesCount = Article::where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $periodStart)
            ->count();

        if ($articlesCount >= $limit) {
            throw new QuotaExceededException(
                message: sprintf(
                    'Đã đạt hạn mức tối đa %d bài viết của gói %s (%s). Vui lòng nâng cấp gói để viết thêm.',
                    $limit,
                    $plan->name,
                    $plan->billing_interval->label()
                ),
                quotaType: 'articles_limit',
                limit: $limit,
                used: $articlesCount,
                workspaceId: $workspace->id
            );
        }
    }

    /**
     * Kiểm tra giới hạn tác vụ đồng thời (Max Concurrent Tasks).
     *
     * @throws QuotaExceededException
     */
    public function assertConcurrencyLimitNotExceeded(Workspace $workspace, ?Plan $plan = null): void
    {
        $plan = $plan ?? $this->getActivePlan($workspace);
        $maxConcurrent = (int) $plan->max_concurrent_tasks;

        if ($maxConcurrent <= 0) {
            return;
        }

        // Đếm các task đang chờ hoặc đang chạy
        $runningTasks = Task::where('workspace_id', $workspace->id)
            ->whereIn('status', [TaskStatus::PENDING, TaskStatus::RUNNING])
            ->count();

        if ($runningTasks >= $maxConcurrent) {
            throw new QuotaExceededException(
                message: sprintf(
                    'Đã đạt giới hạn %d tác vụ chạy đồng thời của gói %s. Vui lòng chờ tác vụ hiện tại hoàn tất.',
                    $maxConcurrent,
                    $plan->name
                ),
                quotaType: 'max_concurrent_tasks',
                limit: $maxConcurrent,
                used: $runningTasks,
                workspaceId: $workspace->id
            );
        }
    }

    /**
     * Kiểm tra giới hạn số lượng website kết nối.
     *
     * @throws QuotaExceededException
     */
    public function assertWebsiteLimitNotExceeded(Workspace $workspace, ?Plan $plan = null): void
    {
        $plan = $plan ?? $this->getActivePlan($workspace);
        $limit = (int) $plan->websites_limit;

        if ($limit <= 0) {
            return;
        }

        $currentCount = Website::where('workspace_id', $workspace->id)->count();

        if ($currentCount >= $limit) {
            throw new QuotaExceededException(
                message: sprintf(
                    'Đã đạt giới hạn %d website kết nối của gói %s. Vui lòng nâng cấp gói.',
                    $limit,
                    $plan->name
                ),
                quotaType: 'websites_limit',
                limit: $limit,
                used: $currentCount,
                workspaceId: $workspace->id
            );
        }
    }
}
