<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentOrderStatus;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\Article;
use App\Models\CreditAccount;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\Keyword;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Article\ArticlePipelineService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\SubscriptionService;
use App\Services\Queue\TaskService;
use App\Services\WordPress\WordPressPreflightCheckService;
use App\Services\WordPress\WordPressPublishService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\AIPromptSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndSaasWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected CreditLedgerService $creditLedger;

    protected SubscriptionService $subscriptionService;

    protected TaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);
        $this->seed(AIPromptSeeder::class);

        $this->creditLedger = app(CreditLedgerService::class);
        $this->subscriptionService = app(SubscriptionService::class);
        $this->taskService = app(TaskService::class);

        // Platform Admin
        $this->adminUser = User::factory()->create([
            'email' => 'superadmin_e2e@example.com',
            'role' => UserRole::SUPER_ADMIN,
            'is_platform_admin' => true,
            'is_active' => true,
        ]);
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

    /**
     * TASK-08.2: End-to-End full SaaS lifecycle:
     * Register -> Auto-provision Workspace & Free Credits -> Add Website & WP Site ->
     * Order Pro Plan -> Submit Proof -> Admin Approve -> Grant 7,000 Credits ->
     * Create Keyword -> Generate Article -> Reserve Credits -> Publish to WP with FAQ Schema ->
     * Settle Credits.
     */
    public function test_complete_saas_lifecycle_from_registration_to_wordpress_publishing_and_credit_settlement(): void
    {
        // -------------------------------------------------------------
        // Bước 1: Khách hàng đăng ký tài khoản mới qua API
        // -------------------------------------------------------------
        $registerPayload = [
            'name' => 'Trần Văn Khách',
            'email' => 'customer_e2e@example.com',
            'password' => 'SecurePassword123@',
            'password_confirmation' => 'SecurePassword123@',
            'workspace_name' => 'SEO Agency Master',
        ];

        $regResponse = $this->postJson('/api/v1/auth/register', $registerPayload);
        $regResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $customer = User::where('email', 'customer_e2e@example.com')->firstOrFail();
        $this->assertNotNull($customer->default_workspace_id);

        $workspace = Workspace::where('id', $customer->default_workspace_id)->firstOrFail();
        $this->assertEquals('SEO Agency Master', $workspace->name);
        $this->assertEquals($customer->id, $workspace->owner_id);

        // Kiểm tra tự động cấp 100 credit miễn phí ngày đầu
        $creditAccount = CreditAccount::where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertEquals(100, $creditAccount->available_credits);

        CreditGrant::create([
            'credit_account_id' => $creditAccount->id,
            'workspace_id' => $workspace->id,
            'source_type' => CreditGrantSource::FREE_DAILY,
            'credits_granted' => 100,
            'credits_remaining' => 100,
            'granted_at' => now(),
            'expires_at' => now()->addDay(),
            'idempotency_key' => 'initial_free:'.$workspace->id,
            'note' => 'Cấp 100 credit miễn phí ngày đầu khi tạo workspace',
        ]);

        $customerToken = $regResponse->json('data.token');

        // -------------------------------------------------------------
        // Bước 2: Khách hàng thêm Website và WordPress Site
        // -------------------------------------------------------------
        $websiteRes = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/websites', [
                'name' => 'Tạp Chí Du Lịch Việt',
                'url' => 'https://dulichviet.example.com',
                'domain' => 'dulichviet.example.com',
                'default_language' => 'vi',
            ]);

        $websiteRes->assertStatus(201)
            ->assertJsonPath('success', true);
        $websiteId = $websiteRes->json('data.id');

        $wpSiteRes = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/wordpress-sites', [
                'name' => 'WordPress Du Lịch Việt',
                'url' => 'https://dulichviet.example.com',
                'api_url' => 'https://dulichviet.example.com/wp-json',
                'username' => 'editor_wp',
                'app_password' => 'application_password_xyz_123',
                'website_id' => $websiteId,
            ]);

        $wpSiteRes->assertStatus(201);
        $wpSiteId = $wpSiteRes->json('data.id');

        // -------------------------------------------------------------
        // Bước 3: Đặt mua gói Pro (1.250.000 VND / 7.000 credit)
        // -------------------------------------------------------------
        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        $orderRes = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/billing/orders', [
                'plan_id' => $proPlan->id,
                'customer_note' => 'Thanh toán gói Pro cho công ty',
            ]);

        $orderRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount_vnd', 1250000)
            ->assertJsonPath('data.status', PaymentOrderStatus::PENDING_PAYMENT->value);

        $orderId = $orderRes->json('data.id');
        $referenceCode = $orderRes->json('data.reference_code');
        $this->assertStringStartsWith('SEOPRO_', $referenceCode);

        // -------------------------------------------------------------
        // Bước 4: Khách hàng gửi bằng chứng thanh toán chuyển khoản
        // -------------------------------------------------------------
        $proofRes = $this->withHeader('Authorization', 'Bearer '.$customerToken)
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/proof", [
                'proof_image_path' => 'payment_proofs/bill_chuyen_khoan_1250k.png',
                'customer_note' => 'Đã chuyển 1.250.000 từ Vietcombank lúc 10:15',
            ]);

        $proofRes->assertStatus(200)
            ->assertJsonPath('data.status', PaymentOrderStatus::PENDING_VERIFICATION->value);

        // -------------------------------------------------------------
        // Bước 5: Platform Admin kiểm tra và duyệt đơn hàng
        // -------------------------------------------------------------
        Sanctum::actingAs($this->adminUser);

        $approveRes = $this->postJson("/api/v1/admin/payment-orders/{$orderId}/approve", [
            'admin_note' => 'Đã khớp sao kê Vietcombank 1.250.000 VND',
        ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', PaymentOrderStatus::APPROVED->value);

        // Kiểm tra Subscription được kích hoạt
        $this->assertDatabaseHas('subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => $proPlan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'activated_by_order_id' => $orderId,
        ]);

        // Kiểm tra ví Credit: carryover=false nên 100 free cũ được reset và cấp 7.000 credit gói Pro
        $creditAccount->refresh();
        $this->assertEquals(7000, $creditAccount->available_credits);

        // -------------------------------------------------------------
        // Bước 6: Khách hàng tạo từ khóa và dispatch sinh bài viết AI
        // -------------------------------------------------------------
        Sanctum::actingAs($customer);

        $kwRes = $this->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/keywords', [
                'keyword' => 'Cẩm nang du lịch Phú Quốc 3 ngày 2 đêm',
                'website_id' => $websiteId,
                'wordpress_site_id' => $wpSiteId,
            ]);

        $kwRes->assertStatus(201);
        $keywordId = $kwRes->json('data.id');

        Queue::fake();

        $genRes = $this->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/articles/generate', [
                'keyword_id' => $keywordId,
                'wordpress_site_id' => $wpSiteId,
            ]);

        $genRes->assertStatus(202);
        $articleId = $genRes->json('article.id');

        // Kiểm tra Credit đã được reserve trước khi chạy (20 credit)
        $creditAccount->refresh();
        $this->assertEquals(6980, $creditAccount->available_credits);
        $this->assertEquals(20, $creditAccount->reserved_credits);

        // Kiểm tra Task đã được tạo ở trạng thái PENDING/QUEUED
        $task = Task::where('article_id', $articleId)->firstOrFail();
        $this->assertEquals($workspace->id, $task->workspace_id);
        $this->assertEquals(20, $task->credits_reserved);

        // -------------------------------------------------------------
        // Bước 7: Giả lập Pipeline tạo bài viết hoàn tất kèm FAQ Schema
        // -------------------------------------------------------------
        $article = Article::findOrFail($articleId);
        $article->update([
            'title' => 'Cẩm Nang Du Lịch Phú Quốc 3 Ngày 2 Đêm Tự Túc Chi Tiết',
            'content' => '<h2>1. Thời điểm đẹp nhất đi Phú Quốc</h2><p>Từ tháng 11 đến tháng 4 năm sau là mùa khô tuyệt đẹp.</p>',
            'status' => ArticleStatus::APPROVED,
            'faqs' => [
                [
                    'question' => 'Đi Phú Quốc 3N2Đ hết bao nhiêu tiền?',
                    'answer' => 'Chi phí trung bình khoảng 3.500.000 - 5.000.000 VNĐ/người.',
                ],
            ],
            'meta_title' => 'Cẩm nang du lịch Phú Quốc 3N2Đ tự túc 2026',
            'meta_description' => 'Khám phá lịch trình du lịch Phú Quốc 3 ngày 2 đêm tự túc chi tiết từ A-Z.',
        ]);

        // -------------------------------------------------------------
        // Bước 8: Đăng bài viết lên WordPress REST API
        // -------------------------------------------------------------
        Http::fake([
            'https://dulichviet.example.com/wp-json/wp/v2/users/me' => Http::response(['id' => 1, 'name' => 'editor_wp'], 200),
            'https://dulichviet.example.com/wp-json/wp/v2/posts' => function ($request) {
                $payload = json_decode($request->body(), true);
                $content = $payload['content'] ?? '';

                // Bắt buộc phải có FAQPage JSON-LD schema được nhúng vào nội dung bài
                $hasFaqSchema = str_contains($content, 'FAQPage') && str_contains($content, 'Đi Phú Quốc 3N2Đ hết bao nhiêu tiền?');
                if (! $hasFaqSchema) {
                    return Http::response(['error' => 'FAQ schema missing'], 422);
                }

                return Http::response([
                    'id' => 8888,
                    'link' => 'https://dulichviet.example.com/cam-nang-du-lich-phu-quoc',
                ], 201);
            },
        ]);

        $publishJob = new PublishToWordPressJob($article->id, $wpSiteId);
        $publishJob->handle(
            publishService: app(WordPressPublishService::class),
            preflight: app(WordPressPreflightCheckService::class),
            pipeline: app(ArticlePipelineService::class)
        );

        $article->refresh();
        $this->assertEquals(ArticleStatus::PUBLISHED, $article->status);
        $this->assertEquals(8888, $article->wordpress_post_id);

        // -------------------------------------------------------------
        // Bước 9: Quyết toán credit (Settle credit reserved thành used qua TaskService)
        // -------------------------------------------------------------
        $this->taskService->completeTask($task, [
            'article_id' => $article->id,
            'wordpress_post_id' => 8888,
        ]);

        $creditAccount->refresh();
        $this->assertEquals(6980, $creditAccount->available_credits);
        $this->assertEquals(0, $creditAccount->reserved_credits);
        $this->assertEquals(20, $creditAccount->cycle_used_credits);

        // Kiểm tra bản ghi giao dịch Credit và Usage Record
        $this->assertDatabaseHas('credit_transactions', [
            'workspace_id' => $workspace->id,
            'type' => CreditTransactionType::DEDUCT->value,
            'amount' => -20,
            'balance_after' => 6980,
        ]);
    }

    /**
     * TASK-08.1 & TASK-08.2: Kiểm tra chu kỳ reset credit không cộng dồn:
     * - Free reset 100/24h.
     * - Paid monthly reset khi gia hạn.
     */
    public function test_credit_cycle_reset_without_rollover_for_daily_free_and_monthly_paid_plans(): void
    {
        $tenant = Tenant::create(['name' => 'Cycle Tenant', 'slug' => 'cycle-tenant', 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'legacy_tenant_id' => $tenant->id,
            'name' => 'Cycle Workspace',
            'slug' => 'cycle-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        // 1. Gói Free ban đầu cấp 100 credit
        $grantFree1 = $this->creditLedger->ensureDailyFreeCredits($workspace);
        $this->assertEquals(100, $this->creditLedger->getBalance($workspace)->availableCredits);

        // Khách hàng tiêu 40 credit -> còn 60 credit
        $this->creditLedger->deductCredits($workspace, 40, 'test', 1);
        $this->assertEquals(60, $this->creditLedger->getBalance($workspace)->availableCredits);

        // Giả lập sau 25 tiếng (hết hạn ngày)
        $grantFree1->update([
            'granted_at' => now()->subHours(25),
            'expires_at' => now()->subHours(1),
        ]);

        // Cấp ngày mới -> Phải reset về đúng 100 credit, KHÔNG được cộng dồn thành 160 credit!
        $grantFree2 = $this->creditLedger->ensureDailyFreeCredits($workspace);
        $this->assertNotEquals($grantFree1->id, $grantFree2->id);
        $this->assertEquals(100, $this->creditLedger->getBalance($workspace)->availableCredits);

        // 2. Chuyển sang gói Basic (2.000 credit/tháng, carryover = false)
        $basicPlan = Plan::where('code', PlanCode::BASIC)->firstOrFail();
        $this->subscriptionService->subscribe($workspace, $basicPlan);
        $this->assertEquals(2000, $this->creditLedger->getBalance($workspace)->availableCredits);

        // Tiêu 300 credit -> còn 1.700 credit
        $this->creditLedger->deductCredits($workspace, 300, 'article', 10);
        $this->assertEquals(1700, $this->creditLedger->getBalance($workspace)->availableCredits);

        // Gia hạn chu kỳ tháng sau của Basic
        $this->subscriptionService->subscribe($workspace, $basicPlan);

        // Số dư phải được reset sạch và cấp đúng 2.000 credit, KHÔNG được thành 3.700 credit!
        $this->assertEquals(2000, $this->creditLedger->getBalance($workspace)->availableCredits);

        $expireTx = CreditTransaction::where('workspace_id', $workspace->id)
            ->where('type', CreditTransactionType::EXPIRE)
            ->latest('id')
            ->first();
        $this->assertNotNull($expireTx);
        $this->assertEquals(-1700, $expireTx->amount);
    }

    /**
     * TASK-08.1: Kiểm tra tính toàn vẹn transaction:
     * Khi có lỗi phát sinh giữa chừng, toàn bộ thay đổi credit được rollback, không bị hao hụt.
     */
    public function test_transaction_rollback_integrity_on_failed_operation(): void
    {
        $tenant = Tenant::create(['name' => 'Tx Tenant', 'slug' => 'tx-tenant', 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'legacy_tenant_id' => $tenant->id,
            'name' => 'Tx Workspace',
            'slug' => 'tx-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $this->creditLedger->grantCredits($workspace, 100, CreditGrantSource::PROMO, now()->addDays(30));
        $this->assertEquals(100, $this->creditLedger->getBalance($workspace)->availableCredits);

        $initialTxCount = CreditTransaction::count();

        // Thử thực hiện một transaction có hành động trừ credit nhưng gặp Exception ở cuối
        try {
            DB::transaction(function () use ($workspace) {
                $this->creditLedger->deductCredits($workspace, 50, 'atomic_test', 999);

                // Giả lập lỗi runtime đột ngột
                throw new \RuntimeException('Database connection lost or unhandled service failure');
            });
        } catch (\RuntimeException $e) {
            // Exception expected
        }

        // Số dư credit vẫn phải là 100, không bị trừ 50
        $this->assertEquals(100, $this->creditLedger->getBalance($workspace)->availableCredits);

        // Bản ghi giao dịch không bị lưu mồ côi
        $this->assertEquals($initialTxCount, CreditTransaction::count());
    }
}
