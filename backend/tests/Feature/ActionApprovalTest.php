<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Api\V1\Chatbot\ActionApprovalController;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\Article;
use App\Models\Conversation;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\ActionApprovalService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActionApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Website $websiteA;

    protected WordPressSite $wpSiteA;

    protected Conversation $conversationA;

    protected Article $articleA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Website $websiteB;

    protected Conversation $conversationB;

    protected CreditLedgerService $creditLedger;

    protected ActionApprovalService $approvalService;

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
        $this->approvalService = app(ActionApprovalService::class);

        // Đăng ký route kiểm thử cho ActionApprovalController
        Route::middleware(['auth:sanctum', 'active_account'])->prefix('api/v1/chatbot/actions')->group(function () {
            Route::post('{uuid}/approve', [ActionApprovalController::class, 'approve']);
            Route::post('{uuid}/reject', [ActionApprovalController::class, 'reject']);
            Route::get('{uuid}', [ActionApprovalController::class, 'show']);
        });

        // 1. Thiết lập Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Smile Dental Tenant',
            'slug' => 'smile-dental-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'admin@nhakhoasmile.vn',
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceA = Workspace::create([
            'owner_id' => $this->userA->id,
            'legacy_tenant_id' => $this->tenantA->id,
            'name' => 'Nha Khoa Smile Workspace',
            'slug' => 'nha-khoa-smile-ws',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->userA->update(['default_workspace_id' => $this->workspaceA->id]);

        $this->websiteA = Website::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Nha Khoa Smile Official',
            'domain' => 'nhakhoasmile.vn',
            'url' => 'https://nhakhoasmile.vn',
        ]);

        $this->wpSiteA = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'name' => 'Nha Khoa Smile WordPress Blog',
            'url' => 'https://nhakhoasmile.vn',
            'api_url' => 'https://nhakhoasmile.vn/wp-json',
            'username' => 'wp_admin',
            'app_password' => 'test-app-password',
            'is_active' => true,
        ]);

        $this->conversationA = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'title' => 'Kế hoạch SEO Tổng thể 2026',
        ]);

        $keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'bọc răng sứ emax',
            'status' => KeywordStatus::PENDING,
        ]);

        $this->articleA = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword_id' => $keywordA->id,
            'title' => 'Bọc răng sứ Emax có tốt không? Giá bao nhiêu?',
            'slug' => 'boc-rang-su-emax-co-tot-khong',
            'focus_keyword' => 'bọc răng sứ emax',
            'content' => '<p>Nội dung bài viết về bọc răng sứ thẩm mỹ Emax...</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        // 2. Thiết lập Workspace B (Tenant cô lập)
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'competitor-tenant-b',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'userb@competitor.com',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Competitor Workspace B',
            'slug' => 'competitor-ws-b',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->userB->update(['default_workspace_id' => $this->workspaceB->id]);

        $this->websiteB = Website::create([
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Competitor Website',
            'domain' => 'competitor.com',
            'url' => 'https://competitor.com',
        ]);

        $this->conversationB = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'user_id' => $this->userB->id,
            'title' => 'Hội thoại bảo mật Workspace B',
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
     * P2.5.7.1 & P2.5.7.2: Khởi tạo pending action với thời hạn hết hạn 30 phút và lưu đúng payload.
     */
    public function test_can_create_pending_action_with_30_minutes_expiry(): void
    {
        $payload = [
            'article_id' => $this->articleA->id,
            'site_id' => $this->wpSiteA->id,
            'status' => 'publish',
        ];

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'publish_to_wordpress',
            payload: $payload,
            user: $this->userA
        );

        $this->assertNotEmpty($pendingAction->uuid);
        $this->assertSame('pending', $pendingAction->status);
        $this->assertSame('publish_to_wordpress', $pendingAction->action_name);
        $this->assertSame('article', $pendingAction->target_entity_type);
        $this->assertSame($this->articleA->id, $pendingAction->target_entity_id);
        $this->assertSame(0, $pendingAction->estimated_credits);
        $this->assertTrue($pendingAction->isPending());
        $this->assertFalse($pendingAction->isExpired());

        // Kiểm tra thời gian hết hạn xấp xỉ 30 phút sau
        $diffMinutes = (int) now()->diffInMinutes($pendingAction->expires_at);
        $this->assertGreaterThanOrEqual(29, $diffMinutes);
        $this->assertLessThanOrEqual(30, $diffMinutes);
    }

    /**
     * P2.5.7.3: Phê duyệt hành động xuất bản WordPress kích hoạt PublishToWordPressJob và đổi trạng thái bài viết.
     */
    public function test_approve_wordpress_publish_action_dispatches_publishing_job(): void
    {
        Queue::fake([PublishToWordPressJob::class]);

        $token = $this->userA->createToken('test-token')->plainTextToken;

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'publish_to_wordpress',
            payload: [
                'article_id' => $this->articleA->id,
                'site_id' => $this->wpSiteA->id,
                'status' => 'publish',
            ],
            user: $this->userA
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.action_name', 'publish_to_wordpress');

        $pendingAction->refresh();
        $this->assertSame('approved', $pendingAction->status);
        $this->assertNotNull($pendingAction->reviewed_at);

        $this->articleA->refresh();
        $this->assertSame(ArticleStatus::APPROVED, $this->articleA->status);
        $this->assertSame($this->wpSiteA->id, $this->articleA->wordpress_site_id);

        Queue::assertPushed(PublishToWordPressJob::class, function ($job) {
            return $job->articleId === $this->articleA->id
                && $job->wordpressSiteId === $this->wpSiteA->id;
        });
    }

    /**
     * P2.5.7.3 & P2.5.7.2: Phê duyệt hành động Bulk Generation khấu trừ credit chính xác trong sổ cái giao dịch.
     */
    public function test_approve_bulk_generation_action_checks_and_deducts_credits(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Cấp 100 credit cho Workspace A
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial balance'
        );

        $initialBalance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertSame(100, $initialBalance);

        // Tạo hành động tạo bài hàng loạt 2 từ khóa (ước tính 36 credits)
        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'bulk_generation',
            payload: [
                'keywords' => ['bọc răng sứ thẩm mỹ', 'giá bọc răng sứ cercon'],
                'website_id' => $this->websiteA->id,
            ],
            user: $this->userA,
            estimatedCredits: 36
        );

        $this->assertSame(36, $pendingAction->estimated_credits);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.credits_deducted', 36);

        // Kiểm tra số dư credit còn lại 64 (100 - 36)
        $currentBalance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertSame(64, $currentBalance);

        // Kiểm tra lịch sử giao dịch trong sổ cái
        $this->assertDatabaseHas('credit_transactions', [
            'workspace_id' => $this->workspaceA->id,
            'amount' => -36,
            'reference_type' => 'chat_approval_action',
            'reference_id' => $pendingAction->id,
        ]);
    }

    /**
     * P2.5.7.3: Báo lỗi HTTP 402 nếu Workspace không đủ số dư credit để phê duyệt hành động.
     */
    public function test_approve_fails_when_workspace_has_insufficient_credits(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Cấp chỉ 10 credit vào tài khoản
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 10,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Low balance'
        );

        // Yêu cầu hành động tiêu tốn 50 credits
        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'bulk_generation',
            payload: [
                'keywords' => ['từ khóa 1', 'từ khóa 2', 'từ khóa 3'],
                'website_id' => $this->websiteA->id,
            ],
            user: $this->userA,
            estimatedCredits: 50
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INSUFFICIENT_CREDITS');

        $pendingAction->refresh();
        $this->assertSame('pending', $pendingAction->status);

        // Số dư ví không bị trừ
        $balance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertSame(10, $balance);
    }

    /**
     * P2.5.7.3: Từ chối / Hủy bỏ hành động thành công mà không trừ bất kỳ credit nào (Zero Loss).
     */
    public function test_reject_pending_action_cancels_safely_without_deducting_credits(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 50,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Safety balance'
        );

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'bulk_generation',
            payload: ['keywords' => ['răng sứ thẩm mỹ']],
            user: $this->userA,
            estimatedCredits: 18
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/reject", [
                'reason' => 'Người dùng đổi ý không muốn chạy tác vụ này.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason', 'Người dùng đổi ý không muốn chạy tác vụ này.');

        $pendingAction->refresh();
        $this->assertSame('rejected', $pendingAction->status);
        $this->assertNotNull($pendingAction->reviewed_at);

        // Số dư 50 credit giữ nguyên tuyệt đối
        $balance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertSame(50, $balance);
    }

    /**
     * P2.5.7.2: Hành động quá hạn 30 phút tự động bị từ chối phê duyệt và chuyển sang status 'expired'.
     */
    public function test_expired_pending_action_cannot_be_approved_or_rejected(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'publish_to_wordpress',
            payload: ['article_id' => $this->articleA->id],
            user: $this->userA
        );

        // Giả lập đã trôi qua quá 30 phút
        $pendingAction->update(['expires_at' => now()->subMinutes(5)]);

        // 1. Thử Approve -> Lỗi 422
        $resApprove = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");

        $resApprove->assertStatus(422)
            ->assertJsonPath('success', false);

        $pendingAction->refresh();
        $this->assertSame('expired', $pendingAction->status);

        // 2. Thử Reject -> Lỗi 422
        $resReject = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/reject");

        $resReject->assertStatus(422);
    }

    /**
     * Multi-Tenancy Isolation: Workspace A tuyệt đối không thể phê duyệt hoặc từ chối hành động của Workspace B.
     */
    public function test_multi_tenancy_isolation_prevents_workspace_a_from_approving_workspace_b_action(): void
    {
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;

        // Tạo hành động của Workspace B
        $actionB = $this->approvalService->createPendingAction(
            conversation: $this->conversationB,
            actionName: 'publish_to_wordpress',
            payload: ['article_id' => 9999],
            user: $this->userB
        );

        // User A cố tình gửi request duyệt action của Workspace B
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$actionB->uuid}/approve");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);

        // Trạng thái action của B không đổi
        $actionB->refresh();
        $this->assertSame('pending', $actionB->status);
    }

    /**
     * Chống bấm đúp / Replay Protection: Không thể duyệt 2 lần trên cùng một hành động đã được duyệt.
     */
    public function test_double_submit_or_replay_prevention_on_already_approved_action(): void
    {
        Queue::fake([PublishToWordPressJob::class]);

        $token = $this->userA->createToken('test-token')->plainTextToken;

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'publish_to_wordpress',
            payload: [
                'article_id' => $this->articleA->id,
                'site_id' => $this->wpSiteA->id,
            ],
            user: $this->userA
        );

        // Lần 1: Thành công
        $res1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");
        $res1->assertStatus(200);

        // Lần 2: Từ chối với lỗi 422
        $res2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");

        $res2->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Hành động này đã được phê duyệt trước đó.');
    }

    /**
     * P2.5.7.3: Endpoint show trả về thông tin chi tiết đầy đủ của pending action.
     */
    public function test_show_action_details_endpoint_returns_rich_information(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'publish_to_wordpress',
            payload: [
                'article_id' => $this->articleA->id,
                'site_id' => $this->wpSiteA->id,
            ],
            user: $this->userA
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/actions/{$pendingAction->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $pendingAction->uuid)
            ->assertJsonPath('data.action_name', 'publish_to_wordpress')
            ->assertJsonPath('data.target_article_title', $this->articleA->title)
            ->assertJsonPath('data.target_website_name', $this->wpSiteA->name)
            ->assertJsonPath('data.is_expired', false);
    }

    /**
     * P2.5.7.2: expireStaleActions tự động chuyển đổi các hành động quá hạn sang expired.
     */
    public function test_expire_stale_actions_service_method(): void
    {
        $action1 = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'publish_to_wordpress',
            payload: ['article_id' => $this->articleA->id],
            user: $this->userA
        );
        $action1->update(['expires_at' => now()->subMinutes(10)]);

        $action2 = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'bulk_generation',
            payload: ['keywords' => ['từ khóa test']],
            user: $this->userA
        );
        $action2->update(['expires_at' => now()->subMinutes(1)]);

        $action3 = $this->approvalService->createPendingAction(
            conversation: $this->conversationA,
            actionName: 'bulk_generation',
            payload: ['keywords' => ['từ khóa còn hạn']],
            user: $this->userA
        );

        $expiredCount = $this->approvalService->expireStaleActions();
        $this->assertSame(2, $expiredCount);

        $this->assertSame('expired', $action1->fresh()->status);
        $this->assertSame('expired', $action2->fresh()->status);
        $this->assertSame('pending', $action3->fresh()->status);
    }
}
