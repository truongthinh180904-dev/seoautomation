<?php

namespace App\Console\Commands;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\CreditGrant;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCreditCyclesCommand extends Command
{
    protected $signature = 'credit:process-cycles';

    protected $description = 'Quét chu kỳ credit, hết hạn credit quá hạn (không cộng dồn) và cấp credit chu kỳ mới/Free hàng ngày';

    public function handle(CreditLedgerService $ledgerService, SubscriptionService $subService): int
    {
        $this->info('Bắt đầu xử lý chu kỳ credit...');
        Log::info('[CreditScheduler] Bắt đầu quét chu kỳ credit.');

        // 1. Quét các CreditGrant đã hết hạn (expires_at <= now() và chưa đánh dấu expired_processed_at)
        $dueGrants = CreditGrant::where('expires_at', '<=', now())
            ->whereNull('expired_processed_at')
            ->get();

        $expiredCount = 0;
        foreach ($dueGrants as $grant) {
            $tx = $ledgerService->expireGrant($grant);
            if ($tx) {
                $expiredCount++;
            }
        }
        $this->line("Đã xử lý hết hạn {$expiredCount} lô credit quá hạn.");

        // 2. Quét các Subscription Active đã đến hạn gia hạn chu kỳ (next_credit_grant_at <= now() hoặc current_period_end <= now())
        $dueSubs = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('current_period_end', '<=', now())
            ->with(['plan', 'workspace'])
            ->get();

        $renewedCount = 0;
        foreach ($dueSubs as $sub) {
            try {
                $subService->renewCycle($sub);
                $renewedCount++;
            } catch (\Throwable $e) {
                Log::error("[CreditScheduler] Lỗi tái tục subscription #{$sub->id}: ".$e->getMessage());
                $this->error("Lỗi tái tục sub #{$sub->id}: ".$e->getMessage());
            }
        }
        $this->line("Đã gia hạn chu kỳ cho {$renewedCount} gói dịch vụ.");

        // 3. Quét các Workspace hoạt động đang dùng Free Plan (hoặc chưa có gói trả phí) để cấp 100 credit / 24h
        $workspaces = Workspace::where('status', 'active')->get();
        $freeGrantedCount = 0;

        foreach ($workspaces as $workspace) {
            try {
                $activeSub = $workspace->subscriptions()
                    ->where('status', SubscriptionStatus::ACTIVE)
                    ->where('current_period_end', '>', now())
                    ->latest('id')
                    ->with('plan')
                    ->first();

                // Chỉ cấp Free hàng ngày nếu đang là Free plan hoặc chưa có active subscription
                if (! $activeSub || ($activeSub->plan && $activeSub->plan->code === PlanCode::FREE)) {
                    $grant = $ledgerService->ensureDailyFreeCredits($workspace);
                    if ($grant && $grant->wasRecentlyCreated) {
                        $freeGrantedCount++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[CreditScheduler] Lỗi cấp Free credit cho workspace #{$workspace->id}: ".$e->getMessage());
            }
        }
        $this->line("Đã cấp Free credit hàng ngày cho {$freeGrantedCount} workspaces.");

        $this->info('Hoàn tất xử lý chu kỳ credit.');

        return self::SUCCESS;
    }
}
