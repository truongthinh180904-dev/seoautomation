<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\PaymentOrderStatus;
use App\Enums\PlanCode;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WebsiteStatus;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CreditAccount;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\Keyword;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected User $adminUser;

    protected CreditLedgerService $creditLedger;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);
        $this->creditLedger = app(CreditLedgerService::class);

        // Setup Tenant & Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Agency Alpha',
            'slug' => 'agency-alpha',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'usera_sec@example.com',
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceA = Workspace::create([
            'owner_id' => $this->userA->id,
            'legacy_tenant_id' => $this->tenantA->id,
            'name' => 'Workspace Alpha',
            'slug' => 'workspace-alpha',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->userA->update(['default_workspace_id' => $this->workspaceA->id]);

        // Setup Tenant & Workspace B
        $this->tenantB = Tenant::create([
            'name' => 'Agency Beta',
            'slug' => 'agency-beta',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'userb_sec@example.com',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Workspace Beta',
            'slug' => 'workspace-beta',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->userB->update(['default_workspace_id' => $this->workspaceB->id]);

        // Setup Platform Admin
        $this->adminUser = User::factory()->create([
            'email' => 'admin_sec@example.com',
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
     * TASK-08.3: Chống rò rỉ thông tin mật (Secrets & Tokens Leakage).
     */
    public function test_secret_credentials_and_tokens_are_never_exposed_via_api(): void
    {
        Sanctum::actingAs($this->userA);

        // 1. WordPress Site app_password
        $site = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Security Test Site',
            'url' => 'https://sectest.com',
            'api_url' => 'https://sectest.com/wp-json',
            'username' => 'sec_user',
            'app_password' => 'super_secret_app_pass_999',
            'is_active' => true,
        ]);

        // Raw DB must be encrypted
        $rawInDb = DB::table('wordpress_sites')->where('id', $site->id)->value('app_password');
        $this->assertNotEquals('super_secret_app_pass_999', $rawInDb);

        // API show response must NOT contain app_password
        $showRes = $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/wordpress-sites/{$site->id}");
        $showRes->assertStatus(200);
        $this->assertArrayNotHasKey('app_password', $showRes->json('data'));

        // API index response must NOT contain app_password
        $indexRes = $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson('/api/v1/wordpress-sites');
        $indexRes->assertStatus(200);
        $this->assertStringNotContainsString('super_secret_app_pass_999', $indexRes->getContent());

        // 2. User password hash in /auth/me
        $meRes = $this->getJson('/api/v1/auth/me');
        $meRes->assertStatus(200);
        $this->assertArrayNotHasKey('password', $meRes->json('data'));
        $this->assertArrayNotHasKey('remember_token', $meRes->json('data'));

        // 3. Article review_token in articles list
        $kw = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'Sec Kw',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'keyword_id' => $kw->id,
            'title' => 'Sec Title',
            'slug' => 'sec-title',
            'status' => ArticleStatus::DRAFT,
            'review_token' => 'top_secret_review_token_12345',
        ]);

        $this->assertArrayNotHasKey('review_token', $article->fresh()->toArray());
    }

    /**
     * TASK-08.3: Kiểm thử cô lập Multi-Tenant & Chặn IDOR trên mọi tài nguyên.
     */
    public function test_strict_multi_tenant_isolation_prevents_idor_across_all_domain_resources(): void
    {
        // Tạo tài nguyên thuộc Workspace B
        $websiteB = Website::create([
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Website B Private',
            'url' => 'https://beta.com',
            'domain' => 'beta.com',
            'status' => WebsiteStatus::ACTIVE,
        ]);

        $siteB = WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'name' => 'WP B Private',
            'url' => 'https://wp.beta.com',
            'api_url' => 'https://wp.beta.com/wp-json',
            'username' => 'beta_adm',
            'app_password' => 'secret_b',
        ]);

        $campaignB = Campaign::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->userB->id,
            'name' => 'Campaign B Private',
            'slug' => 'campaign-b-private',
            'status' => CampaignStatus::READY,
        ]);

        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Từ khóa riêng tư B',
            'status' => KeywordStatus::PENDING,
        ]);

        $articleB = Article::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'keyword_id' => $keywordB->id,
            'title' => 'Bài viết nội bộ B',
            'slug' => 'bai-viet-noi-bo-b',
            'status' => ArticleStatus::DRAFT,
        ]);

        $taskB = Task::create([
            'uuid' => 'task-beta-uuid-001',
            'workspace_id' => $this->workspaceB->id,
            'triggered_by' => $this->userB->id,
            'type' => 'full_article_pipeline',
            'status' => TaskStatus::PENDING,
        ]);

        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();
        $orderB = PaymentOrder::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'plan_id' => $proPlan->id,
            'reference_code' => 'SEOPRO_BETA123',
            'amount_vnd' => 1250000,
            'currency' => 'VND',
            'payment_method' => 'bank_transfer',
            'status' => PaymentOrderStatus::PENDING_PAYMENT,
            'idempotency_key' => 'IDEM_SEC_BETA_123',
        ]);

        // Đăng nhập bằng User A (Workspace A)
        Sanctum::actingAs($this->userA);

        // 1. Thử xem Website B -> 404
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/websites/{$websiteB->id}")
            ->assertStatus(404);

        // 2. Thử xem WordPress Site B -> 404
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/wordpress-sites/{$siteB->id}")
            ->assertStatus(404);

        // 3. Thử xem Campaign B -> 404
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/campaigns/{$campaignB->id}")
            ->assertStatus(404);

        // 4. Thử xem Keyword B -> 404
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/keywords/{$keywordB->id}")
            ->assertStatus(404);

        // 5. Thử xem Article B -> 404
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/articles/{$articleB->id}")
            ->assertStatus(404);

        // 6. Thử xem Task B -> 404
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/tasks/{$taskB->id}")
            ->assertStatus(404);

        // 7. Thử xem Đơn hàng B -> 403
        $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/billing/orders/{$orderB->id}")
            ->assertStatus(403);

        // 8. Thử switch sang Workspace B khi không phải thành viên -> 422
        $this->postJson("/api/v1/workspaces/{$this->workspaceB->id}/switch")
            ->assertStatus(422);

        // 9. Thử truy cập route Admin -> 403 Forbidden
        $this->getJson('/api/v1/admin/payment-orders')->assertStatus(403);
        $this->getJson('/api/v1/admin/workspaces')->assertStatus(403);
        $this->getJson('/api/v1/queue/status')->assertStatus(403);
    }

    /**
     * TASK-08.3: Kiểm thử Race Condition & Concurrency khi trừ credit:
     * Khóa pessimistic lock ngăn chặn số dư khả dụng bị âm.
     */
    public function test_concurrency_race_condition_protection_on_credit_deduction_with_pessimistic_lock(): void
    {
        // Cấp đúng 30 credit
        $this->creditLedger->grantCredits($this->workspaceA, 30, CreditGrantSource::PROMO, now()->addDays(30));
        $this->assertEquals(30, $this->creditLedger->getBalance($this->workspaceA)->availableCredits);

        // Lần 1: Trừ 20 credit thành công
        $tx1 = $this->creditLedger->reserveCredits($this->workspaceA, 20, 'task', 1);
        $this->assertNotNull($tx1);
        $this->assertEquals(10, $this->creditLedger->getBalance($this->workspaceA)->availableCredits);

        // Lần 2: Yêu cầu thêm 20 credit khi chỉ còn 10 credit -> Phải chặn đứng
        $exceptionThrown = false;
        try {
            $this->creditLedger->reserveCredits($this->workspaceA, 20, 'task', 2);
        } catch (InsufficientCreditsException $e) {
            $exceptionThrown = true;
            $this->assertEquals(20, $e->requiredCredits);
            $this->assertEquals(10, $e->availableCredits);
        }

        $this->assertTrue($exceptionThrown);

        // Số dư khả dụng tuyệt đối không bị âm
        $balance = $this->creditLedger->getBalance($this->workspaceA);
        $this->assertEquals(10, $balance->availableCredits);
        $this->assertEquals(20, $balance->reservedCredits);
        $this->assertGreaterThanOrEqual(0, $balance->availableCredits);
    }

    /**
     * TASK-08.3: Chống duyệt trùng lặp (Idempotent Admin Order Approval):
     * Không thể duyệt đơn 2 lần để nhân đôi credit.
     */
    public function test_idempotent_admin_operations_prevent_duplicate_credit_grants(): void
    {
        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        $order = PaymentOrder::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $proPlan->id,
            'reference_code' => 'SEOPRO_DUP_TEST',
            'amount_vnd' => 1250000,
            'currency' => 'VND',
            'payment_method' => 'bank_transfer',
            'status' => PaymentOrderStatus::PENDING_VERIFICATION,
            'idempotency_key' => 'IDEM_SEC_DUP_TEST',
        ]);

        Sanctum::actingAs($this->adminUser);

        // Lần 1: Duyệt thành công
        $res1 = $this->postJson("/api/v1/admin/payment-orders/{$order->id}/approve", [
            'admin_note' => 'Duyệt lần 1',
        ]);
        $res1->assertStatus(200);

        $account = CreditAccount::where('workspace_id', $this->workspaceA->id)->firstOrFail();
        $this->assertEquals(7000, $account->available_credits);

        // Lần 2: Duyệt lại (mô phỏng click đúp hoặc lặp webhook)
        $res2 = $this->postJson("/api/v1/admin/payment-orders/{$order->id}/approve", [
            'admin_note' => 'Duyệt lần 2 trùng lặp',
        ]);
        $res2->assertStatus(200);

        // Số dư credit vẫn là đúng 7.000, KHÔNG nhân đôi thành 14.000
        $account->refresh();
        $this->assertEquals(7000, $account->available_credits);

        // Chỉ có đúng 1 CreditGrant và 1 PaymentTransaction được ghi nhận
        $this->assertEquals(1, CreditGrant::where('payment_order_id', $order->id)->count());
        $this->assertEquals(1, CreditTransaction::where('reference_id', $order->id)->where('type', 'grant')->count());
    }

    /**
     * TASK-08.3: Chống SSRF (Server-Side Request Forgery) & Path Traversal:
     * - URL internal loopback hoặc metadata AWS bị chặn.
     * - Đường dẫn có traversal bị chặn.
     */
    public function test_ssrf_and_path_traversal_payloads_are_strictly_rejected(): void
    {
        Sanctum::actingAs($this->userA);

        // 1. Thử tạo Website với loopback IP -> 422
        $resLoopback = $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/websites', [
                'name' => 'Exploit Loopback',
                'url' => 'http://127.0.0.1:8080/internal',
            ]);
        $resLoopback->assertStatus(422)
            ->assertJsonValidationErrors(['url']);

        // 2. Thử tạo Website với AWS Metadata IP -> 422
        $resMetadata = $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/websites', [
                'name' => 'Exploit AWS Metadata',
                'url' => 'http://169.254.169.254/latest/meta-data/',
            ]);
        $resMetadata->assertStatus(422)
            ->assertJsonValidationErrors(['url']);

        // 3. Thử submit proof có chứa path traversal -> 422
        $plan = Plan::where('code', PlanCode::BASIC)->firstOrFail();
        $order = PaymentOrder::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $plan->id,
            'reference_code' => 'SEOBAS_TRAVERSAL',
            'amount_vnd' => 450000,
            'currency' => 'VND',
            'payment_method' => 'bank_transfer',
            'status' => PaymentOrderStatus::PENDING_PAYMENT,
            'idempotency_key' => 'IDEM_SEC_TRAVERSAL_TEST',
        ]);

        $resTraversal = $this->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/billing/orders/{$order->id}/proof", [
                'proof_image_path' => '../../../../etc/shadow',
            ]);
        $resTraversal->assertStatus(422)
            ->assertJsonValidationErrors(['proof_image_path']);
    }

    /**
     * TASK-08.1: Kiểm tra tính toàn vẹn khóa ngoại (Foreign Key & Cascading):
     * Xóa Workspace sẽ dọn sạch các bảng phụ thuộc hoặc tuân thủ đúng ràng buộc DB.
     */
    public function test_foreign_key_and_cascade_safety_in_database_schema(): void
    {
        $tenant = Tenant::create(['name' => 'Cascade Tenant', 'slug' => 'cascade-tenant', 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'legacy_tenant_id' => $tenant->id,
            'name' => 'Cascade Workspace',
            'slug' => 'cascade-ws',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $member = WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $website = Website::create([
            'workspace_id' => $workspace->id,
            'name' => 'Cascade Site',
            'url' => 'https://cascade.com',
            'domain' => 'cascade.com',
        ]);

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('workspace_members', ['id' => $member->id]);
        $this->assertDatabaseHas('websites', ['id' => $website->id]);

        // Xóa workspace (sử dụng forceDelete để kích hoạt cascading delete ở tầng foreign key của DB)
        $workspace->forceDelete();

        // Kiểm tra cascading: workspace_members và websites phải tự động bị xóa theo khóa ngoại CASCADE
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseMissing('workspace_members', ['id' => $member->id]);
        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
    }
}
