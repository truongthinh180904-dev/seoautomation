<?php

namespace Tests\Feature;

use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Enums\BillingInterval;
use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\QuotaExceededException;
use App\Models\Article;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\Keyword;
use App\Models\Plan;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\SubscriptionService;
use App\Services\Cost\CostTrackingService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected CreditLedgerService $ledgerService;

    protected SubscriptionService $subscriptionService;

    protected CostTrackingService $costTrackingService;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Demo Tenant',
            'slug' => 'demo-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'customer@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Main Workspace',
            'slug' => 'main-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->user->update(['default_workspace_id' => $this->workspace->id]);

        $this->ledgerService = app(CreditLedgerService::class);
        $this->subscriptionService = app(SubscriptionService::class);
        $this->costTrackingService = app(CostTrackingService::class);
    }

    protected function tearDown(): void
    {
        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();
        parent::tearDown();
    }

    public function test_seeds_all_four_subscription_plans_with_exact_quotas(): void
    {
        $free = Plan::where('code', PlanCode::FREE)->first();
        $basic = Plan::where('code', PlanCode::BASIC)->first();
        $pro = Plan::where('code', PlanCode::PRO)->first();
        $business = Plan::where('code', PlanCode::BUSINESS)->first();

        $this->assertNotNull($free);
        $this->assertSame(0, $free->price_monthly_vnd);
        $this->assertSame(BillingInterval::DAILY, $free->billing_interval);
        $this->assertSame(100, $free->credits_per_cycle);
        $this->assertSame(5, $free->articles_limit_per_cycle);
        $this->assertFalse($free->carryover);

        $this->assertNotNull($basic);
        $this->assertSame(450000, $basic->price_monthly_vnd);
        $this->assertSame(BillingInterval::MONTHLY, $basic->billing_interval);
        $this->assertSame(2000, $basic->credits_per_cycle);
        $this->assertSame(100, $basic->articles_limit_per_cycle);
        $this->assertFalse($basic->carryover);

        $this->assertNotNull($pro);
        $this->assertSame(1250000, $pro->price_monthly_vnd);
        $this->assertSame(BillingInterval::MONTHLY, $pro->billing_interval);
        $this->assertSame(7000, $pro->credits_per_cycle);
        $this->assertSame(350, $pro->articles_limit_per_cycle);
        $this->assertFalse($pro->carryover);

        $this->assertNotNull($business);
        $this->assertSame(2500000, $business->price_monthly_vnd);
        $this->assertSame(BillingInterval::MONTHLY, $business->billing_interval);
        $this->assertSame(20000, $business->credits_per_cycle);
        $this->assertSame(1000, $business->articles_limit_per_cycle);
        $this->assertFalse($business->carryover);
    }

    public function test_ensures_daily_free_credits_for_workspace_every_24_hours(): void
    {
        // 1. Lần đầu cấp 100 credit
        $grant1 = $this->ledgerService->ensureDailyFreeCredits($this->workspace);

        $this->assertNotNull($grant1);
        $this->assertSame(100, $grant1->credits_granted);
        $this->assertSame(CreditGrantSource::FREE_DAILY, $grant1->source_type);

        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(100, $balance->availableCredits);

        // 2. Gọi lại ngay trong vòng 24h -> không cấp thêm, số dư vẫn là 100
        $grant2 = $this->ledgerService->ensureDailyFreeCredits($this->workspace);
        $this->assertSame($grant1->id, $grant2->id);

        $balanceAfter = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(100, $balanceAfter->availableCredits);

        // 3. Giả lập hết 24h: grant1 hết hạn, workspace còn dư 40 credit
        $this->ledgerService->deductCredits($this->workspace, 60, 'test', 1);
        $this->assertSame(40, $this->ledgerService->getBalance($this->workspace)->availableCredits);

        // Đẩy thời gian grant1 về 25 tiếng trước
        $grant1->update([
            'granted_at' => now()->subHours(25),
            'expires_at' => now()->subHours(1),
        ]);

        // Cấp lại sau 24h: 40 credit cũ hết hạn (reset không cộng dồn), cấp 100 credit mới
        $grant3 = $this->ledgerService->ensureDailyFreeCredits($this->workspace);
        $this->assertNotNull($grant3);
        $this->assertNotSame($grant1->id, $grant3->id);

        // Số dư phải được reset về đúng 100 credit (không thành 140 credit)
        $newBalance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(100, $newBalance->availableCredits);
    }

    public function test_grant_credits_is_strictly_idempotent(): void
    {
        $idempotencyKey = 'unique_grant_key_12345';
        $expiresAt = now()->addDays(30);

        $grant1 = $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 500,
            source: CreditGrantSource::PROMO,
            expiresAt: $expiresAt,
            idempotencyKey: $idempotencyKey
        );

        $grant2 = $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 500,
            source: CreditGrantSource::PROMO,
            expiresAt: $expiresAt,
            idempotencyKey: $idempotencyKey
        );

        $this->assertSame($grant1->id, $grant2->id);
        $this->assertSame(1, CreditGrant::where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(1, CreditTransaction::where('idempotency_key', 'tx:'.$idempotencyKey)->count());
        $this->assertSame(500, $this->ledgerService->getBalance($this->workspace)->availableCredits);
    }

    public function test_subscribe_activates_paid_plan_and_grants_monthly_credits(): void
    {
        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        $sub = $this->subscriptionService->subscribe($this->workspace, $proPlan);

        $this->assertSame(SubscriptionStatus::ACTIVE, $sub->status);
        $this->assertSame($proPlan->id, $sub->plan_id);

        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(7000, $balance->availableCredits);
        $this->assertSame('pro', $balance->planCode);
    }

    public function test_resets_credits_without_carryover_when_switching_plans_or_renewing(): void
    {
        // 1. Cấp gói Basic (2.000 credit)
        $basicPlan = Plan::where('code', PlanCode::BASIC)->firstOrFail();
        $this->subscriptionService->subscribe($this->workspace, $basicPlan);

        // Dùng 500 credit, còn dư 1.500 credit
        $this->ledgerService->deductCredits($this->workspace, 500, 'article', 101);
        $this->assertSame(1500, $this->ledgerService->getBalance($this->workspace)->availableCredits);

        // 2. Nâng cấp lên gói Pro (7.000 credit) không cộng dồn (carryover = false)
        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();
        $this->subscriptionService->subscribe($this->workspace, $proPlan);

        // Số dư phải là đúng 7.000 credit, không phải 8.500 credit
        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(7000, $balance->availableCredits);

        // Kiểm tra có transaction EXPIRE ghi nhận hết hạn số dư cũ
        $expireTx = CreditTransaction::where('workspace_id', $this->workspace->id)
            ->where('type', CreditTransactionType::EXPIRE)
            ->first();
        $this->assertNotNull($expireTx);
        $this->assertSame(-1500, $expireTx->amount);
    }

    public function test_reserve_credits_holds_balance_and_records_transaction(): void
    {
        $this->ledgerService->grantCredits($this->workspace, 100, CreditGrantSource::PROMO, now()->addDay());

        $tx = $this->ledgerService->reserveCredits(
            workspace: $this->workspace,
            amount: 20,
            referenceType: 'task',
            referenceId: 88,
            metadata: ['purpose' => 'generate_article']
        );

        $this->assertSame(CreditTransactionType::RESERVE, $tx->type);
        $this->assertSame(-20, $tx->amount);
        $this->assertSame(100, $tx->balance_before);
        $this->assertSame(80, $tx->balance_after);

        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(80, $balance->availableCredits);
        $this->assertSame(20, $balance->reservedCredits);
        $this->assertSame(100, $balance->totalCredits);
    }

    public function test_deduct_credits_from_reserved_settles_balance(): void
    {
        $this->ledgerService->grantCredits($this->workspace, 100, CreditGrantSource::PROMO, now()->addDay());
        $this->ledgerService->reserveCredits($this->workspace, 20, 'task', 88);

        $deductTx = $this->ledgerService->deductCredits(
            workspace: $this->workspace,
            amount: 20,
            referenceType: 'task',
            referenceId: 88,
            fromReserved: true
        );

        $this->assertSame(CreditTransactionType::DEDUCT, $deductTx->type);
        $this->assertSame(-20, $deductTx->amount);

        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(80, $balance->availableCredits);
        $this->assertSame(0, $balance->reservedCredits);
        $this->assertSame(20, $balance->cycleUsedCredits);
    }

    public function test_release_credits_restores_reserved_balance(): void
    {
        $this->ledgerService->grantCredits($this->workspace, 100, CreditGrantSource::PROMO, now()->addDay());
        $this->ledgerService->reserveCredits($this->workspace, 20, 'task', 88);

        $releaseTx = $this->ledgerService->releaseCredits(
            workspace: $this->workspace,
            amount: 20,
            referenceType: 'task',
            referenceId: 88,
            reason: 'Task cancelled by user'
        );

        $this->assertSame(CreditTransactionType::RELEASE_REFUND, $releaseTx->type);
        $this->assertSame(20, $releaseTx->amount);

        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(100, $balance->availableCredits);
        $this->assertSame(0, $balance->reservedCredits);
    }

    public function test_refund_credits_reimburses_deducted_balance(): void
    {
        $this->ledgerService->grantCredits($this->workspace, 100, CreditGrantSource::PROMO, now()->addDay());
        $this->ledgerService->deductCredits($this->workspace, 20, 'task', 88);

        $this->assertSame(80, $this->ledgerService->getBalance($this->workspace)->availableCredits);

        $refundTx = $this->ledgerService->refundCredits(
            workspace: $this->workspace,
            amount: 20,
            referenceType: 'task',
            referenceId: 88,
            reason: 'AI generation failed due to third-party timeout'
        );

        $this->assertSame(CreditTransactionType::RELEASE_REFUND, $refundTx->type);
        $this->assertSame(20, $refundTx->amount);

        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(100, $balance->availableCredits);
        $this->assertSame(0, $balance->cycleUsedCredits);
    }

    public function test_throws_insufficient_credits_exception_when_balance_too_low(): void
    {
        $this->ledgerService->grantCredits($this->workspace, 15, CreditGrantSource::PROMO, now()->addDay());

        $this->expectException(InsufficientCreditsException::class);
        $this->expectExceptionMessageMatches('/Số dư credit không đủ/i');

        $this->ledgerService->reserveCredits($this->workspace, 20, 'task', 99);
    }

    public function test_pessimistic_lock_prevents_negative_balance_on_concurrent_requests(): void
    {
        $this->ledgerService->grantCredits($this->workspace, 30, CreditGrantSource::PROMO, now()->addDay());

        // Lần 1: trừ 20 credit thành công
        $tx1 = $this->ledgerService->reserveCredits($this->workspace, 20, 'task', 1);
        $this->assertNotNull($tx1);

        // Lần 2: cố gắng trừ tiếp 20 credit khi chỉ còn 10 credit
        $failed = false;
        try {
            $this->ledgerService->reserveCredits($this->workspace, 20, 'task', 2);
        } catch (InsufficientCreditsException $e) {
            $failed = true;
        }

        $this->assertTrue($failed);
        $balance = $this->ledgerService->getBalance($this->workspace);
        $this->assertSame(10, $balance->availableCredits);
        $this->assertSame(20, $balance->reservedCredits);
        $this->assertGreaterThanOrEqual(0, $balance->availableCredits);
    }

    public function test_enforces_article_limit_per_cycle_for_plan(): void
    {
        // Gói Free có limit 5 bài/ngày
        $freePlan = Plan::where('code', PlanCode::FREE)->firstOrFail();
        $this->subscriptionService->subscribe($this->workspace, $freePlan);

        $keyword = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'SEO Automation Test',
        ]);

        // Tạo 5 bài trong ngày
        for ($i = 1; $i <= 5; $i++) {
            Article::create([
                'tenant_id' => $this->tenant->id,
                'workspace_id' => $this->workspace->id,
                'keyword_id' => $keyword->id,
                'user_id' => $this->user->id,
                'title' => "Bài viết {$i}",
                'content' => 'Nội dung bài viết',
                'status' => 'draft',
            ]);
        }

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessageMatches('/Đã đạt hạn mức tối đa 5 bài viết/i');

        $this->subscriptionService->validateCanGenerateArticle($this->workspace);
    }

    public function test_enforces_concurrent_tasks_limit_for_plan(): void
    {
        // Gói Free có max_concurrent_tasks = 1
        $freePlan = Plan::where('code', PlanCode::FREE)->firstOrFail();
        $this->subscriptionService->subscribe($this->workspace, $freePlan);

        // Tạo 1 task đang chạy
        Task::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'type' => 'full_article_pipeline',
            'status' => TaskStatus::RUNNING,
        ]);

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessageMatches('/Đã đạt giới hạn 1 tác vụ chạy đồng thời/i');

        $this->subscriptionService->validateCanGenerateArticle($this->workspace);
    }

    public function test_admin_can_adjust_credits_manually_with_audit_trail(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_platform_admin' => true,
        ]);

        // Admin cộng 500 credit
        $addTx = $this->ledgerService->adminAdjustCredits(
            workspace: $this->workspace,
            amount: 500,
            admin: $admin,
            reason: 'Bồi hoàn sự cố mạng cho khách hàng'
        );

        $this->assertSame(500, $addTx->amount);
        $this->assertSame(CreditTransactionType::ADMIN_ADJUST, $addTx->type);
        $this->assertSame($admin->id, $addTx->actor_id);
        $this->assertSame(500, $this->ledgerService->getBalance($this->workspace)->availableCredits);

        // Admin trừ 200 credit
        $subtractTx = $this->ledgerService->adminAdjustCredits(
            workspace: $this->workspace,
            amount: -200,
            admin: $admin,
            reason: 'Thu hồi credit cấp nhầm'
        );

        $this->assertSame(-200, $subtractTx->amount);
        $this->assertSame(300, $this->ledgerService->getBalance($this->workspace)->availableCredits);
    }

    public function test_process_credit_cycles_command_expires_overdue_grants(): void
    {
        // Tạo grant đã quá hạn
        $account = $this->ledgerService->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 150]);

        $overdueGrant = CreditGrant::create([
            'credit_account_id' => $account->id,
            'workspace_id' => $this->workspace->id,
            'source_type' => CreditGrantSource::PROMO,
            'credits_granted' => 100,
            'credits_remaining' => 100,
            'granted_at' => now()->subDays(35),
            'expires_at' => now()->subDays(5),
            'idempotency_key' => 'overdue_grant_test_999',
        ]);

        $exitCode = Artisan::call('credit:process-cycles');
        $this->assertSame(0, $exitCode);

        $overdueGrant->refresh();
        $this->assertNotNull($overdueGrant->expired_processed_at);
        $this->assertSame(0, $overdueGrant->credits_remaining);

        // 100 credit đã bị trừ khỏi số dư khả dụng
        $account->refresh();
        $this->assertSame(50, $account->available_credits);
    }

    public function test_ai_cost_calculation_differentiates_gpt_4o_mini_correctly(): void
    {
        // 1. Kiểm tra DTO
        $dtoMini = new AIResponseDTO(
            content: 'Test content',
            provider: AIProvider::OPENAI,
            model: 'gpt-4o-mini',
            promptTokens: 1000000,
            completionTokens: 1000000,
            totalTokens: 2000000,
            latencyMs: 500,
            rawResponse: []
        );

        // gpt-4o-mini: 0.15 + 0.60 = 0.75 USD
        $this->assertEqualsWithDelta(0.75, $dtoMini->totalCostUsd(), 0.0001);

        $dtoStandard = new AIResponseDTO(
            content: 'Test content',
            provider: AIProvider::OPENAI,
            model: 'gpt-4o',
            promptTokens: 1000000,
            completionTokens: 1000000,
            totalTokens: 2000000,
            latencyMs: 500,
            rawResponse: []
        );

        // gpt-4o chuẩn: 5.0 + 15.0 = 20.0 USD
        $this->assertEqualsWithDelta(20.0, $dtoStandard->totalCostUsd(), 0.0001);

        // 2. Kiểm tra CostTrackingService::calculateCost
        $serviceMiniCost = $this->costTrackingService->calculateCost('openai', 'gpt-4o-mini', 1000000, 1000000);
        $this->assertEqualsWithDelta(0.75, $serviceMiniCost, 0.0001);
    }

    public function test_insufficient_credits_exception_renders_402_status_code(): void
    {
        Route::get('/api/test-credit-check', function () {
            throw new InsufficientCreditsException(
                requiredCredits: 20,
                availableCredits: 5,
                workspaceId: 1
            );
        });

        $response = $this->getJson('/api/test-credit-check');

        $response->assertStatus(402);
        $response->assertJson([
            'error_code' => 'INSUFFICIENT_CREDITS',
            'required_credits' => 20,
            'available_credits' => 5,
            'workspace_id' => 1,
        ]);
    }
}
