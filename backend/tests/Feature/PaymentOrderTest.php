<?php

namespace Tests\Feature;

use App\Enums\PlanCode;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\AuditLog;
use App\Models\CreditAccount;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\PaymentOrder;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $customerUser;

    protected Workspace $customerWorkspace;

    protected User $adminUser;

    protected Tenant $otherTenant;

    protected User $otherCustomerUser;

    protected Workspace $otherWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();
        Storage::fake('public');

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        // Customer User
        $this->customerUser = User::factory()->create([
            'email' => 'customer@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->customerWorkspace = Workspace::create([
            'owner_id' => $this->customerUser->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Customer Workspace',
            'slug' => 'customer-ws',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->customerWorkspace->id,
            'user_id' => $this->customerUser->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->customerUser->update(['default_workspace_id' => $this->customerWorkspace->id]);

        // Platform Admin User
        $this->adminUser = User::factory()->create([
            'email' => 'platform_admin@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_platform_admin' => true,
            'is_active' => true,
        ]);

        // Other Customer & Workspace (for IDOR / Tenant isolation testing)
        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherCustomerUser = User::factory()->create([
            'email' => 'other_customer@example.com',
            'tenant_id' => $this->otherTenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'owner_id' => $this->otherCustomerUser->id,
            'legacy_tenant_id' => $this->otherTenant->id,
            'name' => 'Other Workspace',
            'slug' => 'other-ws',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->otherWorkspace->id,
            'user_id' => $this->otherCustomerUser->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->otherCustomerUser->update(['default_workspace_id' => $this->otherWorkspace->id]);
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

    public function test_customer_can_list_active_plans(): void
    {
        Sanctum::actingAs($this->customerUser);

        $response = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->getJson('/api/v1/billing/plans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'price_monthly_vnd',
                        'price_formatted',
                        'billing_interval',
                        'credits_per_cycle',
                        'features',
                    ],
                ],
            ]);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Free', $names);
        $this->assertContains('Basic', $names);
        $this->assertContains('Pro', $names);
        $this->assertContains('Business', $names);
    }

    public function test_customer_can_create_payment_order_for_paid_plan(): void
    {
        Sanctum::actingAs($this->customerUser);

        $basicPlan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $response = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', [
                'plan_id' => $basicPlan->id,
                'customer_note' => 'Đăng ký gói Basic theo tháng',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount_vnd', 450000)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.currency', 'VND');

        $referenceCode = $response->json('data.reference_code');
        $this->assertStringStartsWith('SEOBAS_', $referenceCode);

        // Verify bank_info_snapshot
        $bankInfo = $response->json('data.bank_info_snapshot');
        $this->assertNotEmpty($bankInfo['bank_name']);
        $this->assertNotEmpty($bankInfo['account_number']);
        $this->assertEquals($referenceCode, $bankInfo['transfer_syntax']);
        $this->assertEquals(450000, $bankInfo['amount_vnd']);

        // Check DB
        $this->assertDatabaseHas('payment_orders', [
            'workspace_id' => $this->customerWorkspace->id,
            'user_id' => $this->customerUser->id,
            'plan_id' => $basicPlan->id,
            'reference_code' => $referenceCode,
            'status' => 'pending_payment',
        ]);

        // Check AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'action' => 'billing.order_created',
        ]);
    }

    public function test_customer_cannot_create_order_for_free_plan(): void
    {
        Sanctum::actingAs($this->customerUser);

        $freePlan = Plan::where('code', PlanCode::FREE)->firstOrFail();

        $response = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', [
                'plan_id' => $freePlan->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_submit_payment_proof(): void
    {
        Sanctum::actingAs($this->customerUser);

        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        $orderResponse = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', [
                'plan_id' => $proPlan->id,
            ]);

        $orderId = $orderResponse->json('data.id');

        $fakeImage = UploadedFile::fake()->image('receipt.png', 600, 800);

        $proofResponse = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/proof", [
                'proof_image' => $fakeImage,
                'customer_note' => 'Đã chuyển 1.250.000 qua MB Bank, nhờ Admin duyệt sớm!',
            ]);

        $proofResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending_verification')
            ->assertJsonPath('data.customer_note', 'Đã chuyển 1.250.000 qua MB Bank, nhờ Admin duyệt sớm!');

        $this->assertNotNull($proofResponse->json('data.proof_image_path'));

        $this->assertDatabaseHas('payment_orders', [
            'id' => $orderId,
            'status' => 'pending_verification',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'action' => 'billing.proof_submitted',
        ]);
    }

    public function test_customer_can_cancel_pending_order(): void
    {
        Sanctum::actingAs($this->customerUser);

        $plan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $orderResponse = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', [
                'plan_id' => $plan->id,
            ]);

        $orderId = $orderResponse->json('data.id');

        $cancelResponse = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/cancel", [
                'reason' => 'Đổi ý nâng lên gói Pro',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('payment_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'action' => 'billing.order_cancelled',
        ]);
    }

    public function test_non_admin_cannot_approve_or_reject_orders(): void
    {
        Sanctum::actingAs($this->customerUser);

        $plan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $orderResponse = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', [
                'plan_id' => $plan->id,
            ]);

        $orderId = $orderResponse->json('data.id');

        // Regular user tries to approve -> 403
        $approveResponse = $this->postJson("/api/v1/admin/payment-orders/{$orderId}/approve", [
            'admin_note' => 'Hacker cố duyệt',
        ]);
        $approveResponse->assertStatus(403);

        // Regular user tries to reject -> 403
        $rejectResponse = $this->postJson("/api/v1/admin/payment-orders/{$orderId}/reject", [
            'rejection_reason' => 'Hacker cố từ chối',
        ]);
        $rejectResponse->assertStatus(403);
    }

    public function test_admin_can_approve_order_and_activate_subscription_with_credits(): void
    {
        // 1. Customer creates order for Basic plan (2000 credits, 450,000 VND)
        Sanctum::actingAs($this->customerUser);
        $basicPlan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $createRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $basicPlan->id]);
        $orderId = $createRes->json('data.id');
        $referenceCode = $createRes->json('data.reference_code');

        // Customer submits proof
        $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/proof", [
                'proof_image_path' => 'payment_proofs/mock_receipt.jpg',
            ]);

        // 2. Admin approves order
        Sanctum::actingAs($this->adminUser);

        $approveRes = $this->postJson("/api/v1/admin/payment-orders/{$orderId}/approve", [
            'admin_note' => 'Đã khớp sao kê MB Bank 450k lúc 14:00',
        ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $this->adminUser->id)
            ->assertJsonPath('data.admin_note', 'Đã khớp sao kê MB Bank 450k lúc 14:00');

        // Check PaymentOrder in DB
        $this->assertDatabaseHas('payment_orders', [
            'id' => $orderId,
            'status' => 'approved',
            'reviewed_by' => $this->adminUser->id,
        ]);

        // Check PaymentTransaction
        $this->assertDatabaseHas('payment_transactions', [
            'payment_order_id' => $orderId,
            'workspace_id' => $this->customerWorkspace->id,
            'amount_vnd' => 450000,
            'direction' => 'inbound',
            'status' => 'verified',
            'verified_by' => $this->adminUser->id,
        ]);

        // Check Subscription activated
        $this->assertDatabaseHas('subscriptions', [
            'workspace_id' => $this->customerWorkspace->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'activated_by_order_id' => $orderId,
        ]);

        // Check CreditAccount balance
        $account = CreditAccount::where('workspace_id', $this->customerWorkspace->id)->firstOrFail();
        $this->assertEquals(2000, $account->available_credits);
        $this->assertEquals(2000, $account->cycle_granted_credits);

        // Check CreditGrant
        $this->assertDatabaseHas('credit_grants', [
            'workspace_id' => $this->customerWorkspace->id,
            'payment_order_id' => $orderId,
            'source_type' => 'subscription_cycle',
            'credits_granted' => 2000,
        ]);

        // Check AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'actor_id' => $this->adminUser->id,
            'action' => 'billing.order_approved',
        ]);

        $auditLog = AuditLog::where('workspace_id', $this->customerWorkspace->id)
            ->where('action', 'billing.order_approved')
            ->firstOrFail();
        $this->assertEquals($this->adminUser->id, $auditLog->actor_id);
        $this->assertEquals(450000, $auditLog->new_values['amount_vnd']);
        $this->assertEquals($this->adminUser->id, $auditLog->new_values['admin_id']);
    }

    public function test_admin_approve_is_idempotent_no_duplicate_credits(): void
    {
        Sanctum::actingAs($this->customerUser);
        $basicPlan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $createRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $basicPlan->id]);
        $orderId = $createRes->json('data.id');

        Sanctum::actingAs($this->adminUser);

        // First approval
        $this->postJson("/api/v1/admin/payment-orders/{$orderId}/approve", [
            'admin_note' => 'First check',
        ])->assertStatus(200);

        // Second approval (Idempotency test)
        $secondApprove = $this->postJson("/api/v1/admin/payment-orders/{$orderId}/approve", [
            'admin_note' => 'Second check (duplicate)',
        ]);

        $secondApprove->assertStatus(200);

        // Credit balance must remain exactly 2000, not 4000
        $account = CreditAccount::where('workspace_id', $this->customerWorkspace->id)->firstOrFail();
        $this->assertEquals(2000, $account->available_credits);

        // Only 1 transaction record
        $this->assertEquals(1, PaymentTransaction::where('payment_order_id', $orderId)->count());
        $this->assertEquals(1, CreditGrant::where('payment_order_id', $orderId)->count());
    }

    public function test_admin_can_reject_order_with_reason(): void
    {
        Sanctum::actingAs($this->customerUser);
        $proPlan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        $createRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $proPlan->id]);
        $orderId = $createRes->json('data.id');

        Sanctum::actingAs($this->adminUser);

        $rejectRes = $this->postJson("/api/v1/admin/payment-orders/{$orderId}/reject", [
            'rejection_reason' => 'Không tìm thấy giao dịch chuyển tiền khớp với mã tham chiếu.',
            'admin_note' => 'Đã kiểm tra sao kê ngân hàng 3 lần không thấy.',
        ]);

        $rejectRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Không tìm thấy giao dịch chuyển tiền khớp với mã tham chiếu.')
            ->assertJsonPath('data.reviewed_by', $this->adminUser->id);

        $this->assertDatabaseHas('payment_orders', [
            'id' => $orderId,
            'status' => 'rejected',
            'rejection_reason' => 'Không tìm thấy giao dịch chuyển tiền khớp với mã tham chiếu.',
        ]);

        // Ensure no subscription or credit grant was created
        $this->assertDatabaseMissing('subscriptions', [
            'activated_by_order_id' => $orderId,
        ]);

        // Check AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'actor_id' => $this->adminUser->id,
            'action' => 'billing.order_rejected',
        ]);
    }

    public function test_cannot_approve_rejected_order_or_reject_approved_order(): void
    {
        Sanctum::actingAs($this->customerUser);
        $plan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        // 1. Rejected order cannot be approved
        $order1 = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $plan->id])
            ->json('data.id');

        Sanctum::actingAs($this->adminUser);
        $this->postJson("/api/v1/admin/payment-orders/{$order1}/reject", [
            'rejection_reason' => 'Hủy do sai số tiền',
        ])->assertStatus(200);

        $approveFail = $this->postJson("/api/v1/admin/payment-orders/{$order1}/approve");
        $approveFail->assertStatus(422);

        // 2. Approved order cannot be rejected
        Sanctum::actingAs($this->customerUser);
        $order2 = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $plan->id])
            ->json('data.id');

        Sanctum::actingAs($this->adminUser);
        $this->postJson("/api/v1/admin/payment-orders/{$order2}/approve")->assertStatus(200);

        $rejectFail = $this->postJson("/api/v1/admin/payment-orders/{$order2}/reject", [
            'rejection_reason' => 'Cố từ chối đơn đã duyệt',
        ]);
        $rejectFail->assertStatus(422);
    }

    public function test_admin_can_adjust_workspace_credit_manually(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/v1/admin/workspaces/{$this->customerWorkspace->id}/credit-adjust", [
            'amount' => 500,
            'reason' => 'Tặng thêm credit khuyến mại hội thảo SEO',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.new_balance.available_credits', 500);

        // Check CreditAccount
        $account = CreditAccount::where('workspace_id', $this->customerWorkspace->id)->firstOrFail();
        $this->assertEquals(500, $account->available_credits);

        // Check CreditTransaction
        $this->assertDatabaseHas('credit_transactions', [
            'workspace_id' => $this->customerWorkspace->id,
            'type' => 'admin_adjust',
            'amount' => 500,
            'actor_id' => $this->adminUser->id,
        ]);

        // Check AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'actor_id' => $this->adminUser->id,
            'action' => 'credit.manual_adjusted',
        ]);
    }

    public function test_admin_can_suspend_and_activate_workspace(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Suspend workspace
        $suspendRes = $this->postJson("/api/v1/admin/workspaces/{$this->customerWorkspace->id}/status", [
            'status' => 'suspended',
            'reason' => 'Vi phạm chính sách nội dung cấm',
        ]);

        $suspendRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('workspaces', [
            'id' => $this->customerWorkspace->id,
            'status' => 'suspended',
        ]);

        // Customer tries to access workspace-scoped API -> 403 WORKSPACE_SUSPENDED
        Sanctum::actingAs($this->customerUser);
        $blockedRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->getJson('/api/v1/websites');

        $blockedRes->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKSPACE_SUSPENDED');

        // Admin re-activates workspace
        Sanctum::actingAs($this->adminUser);
        $activateRes = $this->postJson("/api/v1/admin/workspaces/{$this->customerWorkspace->id}/status", [
            'status' => 'active',
            'reason' => 'Khách hàng đã giải trình thành công',
        ]);

        $activateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        // Customer can now access normally
        Sanctum::actingAs($this->customerUser);
        $allowedRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->getJson('/api/v1/websites');

        $allowedRes->assertStatus(200);
    }

    public function test_tenant_isolation_cross_workspace_payment_order_protection(): void
    {
        // Customer 1 creates an order
        Sanctum::actingAs($this->customerUser);
        $plan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $createRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $plan->id]);
        $orderId = $createRes->json('data.id');

        // Customer 2 (other workspace) tries to view Customer 1's order -> 403 Forbidden
        Sanctum::actingAs($this->otherCustomerUser);

        $viewRes = $this->withHeader('X-Workspace-Id', $this->otherWorkspace->id)
            ->getJson("/api/v1/billing/orders/{$orderId}");
        $viewRes->assertStatus(403);

        // Customer 2 tries to submit proof for Customer 1's order -> 403 Forbidden
        $proofRes = $this->withHeader('X-Workspace-Id', $this->otherWorkspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/proof", [
                'proof_image_path' => 'hack.jpg',
            ]);
        $proofRes->assertStatus(403);

        // Customer 2 tries to cancel Customer 1's order -> 403 Forbidden
        $cancelRes = $this->withHeader('X-Workspace-Id', $this->otherWorkspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/cancel", [
                'reason' => 'Cố ý hủy',
            ]);
        $cancelRes->assertStatus(403);
    }

    public function test_customer_can_view_credit_history_and_balance(): void
    {
        Sanctum::actingAs($this->customerUser);

        // Check initial balance
        $balanceRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->getJson('/api/v1/billing/balance');

        $balanceRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_credits', 0);

        // Check history
        $historyRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->getJson('/api/v1/billing/credit-history');

        $historyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_admin_can_list_and_filter_payment_orders(): void
    {
        Sanctum::actingAs($this->customerUser);
        $plan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $plan->id]);

        Sanctum::actingAs($this->adminUser);

        $listRes = $this->getJson('/api/v1/admin/payment-orders?status=pending_payment');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'reference_code',
                        'amount_vnd',
                        'status',
                        'workspace',
                        'user',
                        'plan',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));
    }

    public function test_cross_workspace_idor_prevented_in_body_parameter(): void
    {
        Sanctum::actingAs($this->customerUser);
        $plan = Plan::where('code', PlanCode::PRO)->firstOrFail();

        // Customer user attempts to create order in other tenant's workspace via body workspace_id
        $response = $this->postJson('/api/v1/billing/orders', [
            'plan_id' => $plan->id,
            'workspace_id' => $this->otherWorkspace->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_path_traversal_in_payment_proof_is_rejected(): void
    {
        Sanctum::actingAs($this->customerUser);
        $plan = Plan::where('code', PlanCode::BASIC)->firstOrFail();

        $createRes = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson('/api/v1/billing/orders', ['plan_id' => $plan->id]);
        $orderId = $createRes->json('data.id');

        // Path traversal payload
        $response = $this->withHeader('X-Workspace-Id', $this->customerWorkspace->id)
            ->postJson("/api/v1/billing/orders/{$orderId}/proof", [
                'proof_image_path' => '../../etc/passwd',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['proof_image_path']);
    }

    public function test_queue_status_accessible_under_legacy_and_admin_route(): void
    {
        // Non-admin -> 403
        Sanctum::actingAs($this->customerUser);
        $this->getJson('/api/v1/queue/status')->assertStatus(403);
        $this->getJson('/api/v1/admin/queue/status')->assertStatus(403);

        // Super Admin -> 200
        Sanctum::actingAs($this->adminUser);
        $this->getJson('/api/v1/queue/status')->assertStatus(200);
        $this->getJson('/api/v1/admin/queue/status')->assertStatus(200);
    }

    public function test_admin_workspace_status_update_and_credit_adjustment_atomic_audit_log(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Update workspace status
        $statusRes = $this->postJson("/api/v1/admin/workspaces/{$this->customerWorkspace->id}/status", [
            'status' => 'suspended',
            'reason' => 'Vi phạm chính sách thanh toán',
        ]);

        $statusRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('workspaces', [
            'id' => $this->customerWorkspace->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'action' => 'workspace.status_updated',
            'actor_id' => $this->adminUser->id,
        ]);

        // 2. Adjust credit
        $creditRes = $this->postJson("/api/v1/admin/workspaces/{$this->customerWorkspace->id}/credit-adjust", [
            'amount' => 500,
            'reason' => 'Tặng credit hỗ trợ khách hàng',
        ]);

        $creditRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.new_balance.available_credits', 500);

        $this->assertDatabaseHas('credit_transactions', [
            'workspace_id' => $this->customerWorkspace->id,
            'amount' => 500,
            'type' => 'admin_adjust',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->customerWorkspace->id,
            'action' => 'credit.manual_adjusted',
            'actor_id' => $this->adminUser->id,
        ]);
    }
}
