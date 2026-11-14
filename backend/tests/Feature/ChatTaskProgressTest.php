<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Api\V1\Chatbot\ChatStreamController;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\Keyword;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Queue\TaskService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTaskProgressTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Website $websiteA;

    protected Campaign $campaignA;

    protected Keyword $keywordA;

    protected Article $articleA;

    protected Task $taskA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Task $taskB;

    protected CreditLedgerService $creditLedger;

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
        $this->creditLedger = app(CreditLedgerService::class);
        $this->taskService = app(TaskService::class);

        // Đăng ký route kiểm thử cho ChatStreamController
        Route::middleware(['auth:sanctum', 'active_account'])->prefix('api/v1/chatbot/tasks')->group(function () {
            Route::get('{uuid}/status', [ChatStreamController::class, 'status']);
            Route::get('{uuid}/stream', [ChatStreamController::class, 'stream']);
            Route::post('{uuid}/resume', [ChatStreamController::class, 'resume']);
            Route::post('{uuid}/retry', [ChatStreamController::class, 'resume']);
            Route::post('{uuid}/cancel', [ChatStreamController::class, 'cancel']);
        });

        // 1. Khởi tạo Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Smile Dental Tenant',
            'slug' => 'smile-dental-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'author@nhakhoasmile.vn',
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

        // Cấp credit cho Workspace A
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 500,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial credits for task execution'
        );

        $this->campaignA = Campaign::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'name' => 'Chiến dịch SEO Răng Sứ 2026',
            'slug' => 'chien-dich-seo-rang-su-2026',
        ]);

        $this->keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'campaign_id' => $this->campaignA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'chi phí bọc răng sứ emax',
            'status' => KeywordStatus::PROCESSING,
        ]);

        $this->articleA = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'campaign_id' => $this->campaignA->id,
            'keyword_id' => $this->keywordA->id,
            'user_id' => $this->userA->id,
            'title' => 'Chi phí bọc răng sứ Emax bao nhiêu tiền 1 chiếc?',
            'slug' => 'chi-phi-boc-rang-su-emax',
            'focus_keyword' => 'chi phí bọc răng sứ emax',
            'content' => '<p>Đang trong quá trình khởi tạo nội dung...</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $this->taskA = Task::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'campaign_id' => $this->campaignA->id,
            'keyword_id' => $this->keywordA->id,
            'article_id' => $this->articleA->id,
            'triggered_by' => $this->userA->id,
            'type' => 'full_article_pipeline',
            'current_step' => 'Đang phân tích Top 3 đối thủ Google SERP...',
            'status' => TaskStatus::RUNNING,
            'progress' => 25,
            'credits_reserved' => 20,
            'credits_settled' => 0,
            'attempts' => 1,
            'max_attempts' => 3,
            'payload' => [
                'target_word_count' => 2500,
                'model' => 'gemini-2.5-pro',
            ],
            'started_at' => now(),
        ]);

        // 2. Khởi tạo Workspace B (Đối thủ cạnh tranh)
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

        $this->taskB = Task::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceB->id,
            'triggered_by' => $this->userB->id,
            'type' => 'keyword_research_batch',
            'current_step' => 'Bảo mật tuyệt đối của B',
            'status' => TaskStatus::RUNNING,
            'progress' => 50,
            'credits_reserved' => 5,
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
     * P2.5.6.1: Polling endpoint trả về đúng trạng thái, tiến độ và bước hiện tại.
     */
    public function test_polling_endpoint_returns_task_progress_and_step(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/status");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $this->taskA->uuid)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.progress', 25)
            ->assertJsonPath('data.current_step', 'Đang phân tích Top 3 đối thủ Google SERP...')
            ->assertJsonPath('data.is_finished', false)
            ->assertJsonPath('data.credits_reserved', 20)
            ->assertJsonPath('data.article.id', $this->articleA->id)
            ->assertJsonPath('data.keyword.keyword', 'chi phí bọc răng sứ emax')
            ->assertJsonPath('data.campaign.name', 'Chiến dịch SEO Răng Sứ 2026');
    }

    /**
     * P2.5.6.1 & P2.5.6.2: Polling phản ánh chính xác các bước tiến độ được cập nhật tuần tự.
     */
    public function test_polling_reflects_step_by_step_updates(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Cập nhật bước 2: Viết nội dung bài viết
        $this->taskService->updateProgress($this->taskA, 60, 'Đang viết nội dung 2.500 từ chuẩn Brand Voice...');

        $resStep2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/status");

        $resStep2->assertStatus(200)
            ->assertJsonPath('data.progress', 60)
            ->assertJsonPath('data.current_step', 'Đang viết nội dung 2.500 từ chuẩn Brand Voice...');

        // Cập nhật bước 3: Xử lý và nén ảnh WebP
        $this->taskService->updateProgress($this->taskA, 85, 'Đang tìm ảnh stock và nén WebP dưới 150KB...');

        $resStep3 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/status");

        $resStep3->assertStatus(200)
            ->assertJsonPath('data.progress', 85)
            ->assertJsonPath('data.current_step', 'Đang tìm ảnh stock và nén WebP dưới 150KB...');
    }

    /**
     * P2.5.6.1: Polling trả về trạng thái SUCCESS và is_finished = true khi hoàn tất 100%.
     */
    public function test_polling_returns_success_status_on_task_completion(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Hoàn thành tác vụ qua TaskService
        $this->taskService->completeTask($this->taskA, ['final_seo_score' => 92]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.current_step', 'completed')
            ->assertJsonPath('data.is_finished', true)
            ->assertJsonPath('data.can_resume', false)
            ->assertJsonPath('data.payload.final_seo_score', 92);
    }

    /**
     * P2.5.6.1: Server-Sent Events (SSE) stream endpoint trả về stream text/event-stream chuẩn xác.
     */
    public function test_server_sent_events_stream_endpoint_returns_event_stream(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->get("/api/v1/chatbot/tasks/{$this->taskA->uuid}/stream?once=1");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        // Kiểm tra nội dung SSE frame
        $content = $response->streamedContent();
        $this->assertStringContainsString('event: progress', $content);
        $this->assertStringContainsString($this->taskA->uuid, $content);
        $this->assertStringContainsString('Đang phân tích Top 3 đối thủ Google SERP...', $content);
    }

    /**
     * P2.5.6.1: SSE stream phát event 'complete' khi tác vụ thành công.
     */
    public function test_server_sent_events_stream_emits_complete_event_when_task_success(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $this->taskA->update([
            'status' => TaskStatus::SUCCESS,
            'progress' => 100,
            'current_step' => 'Tác vụ đã hoàn tất thành công.',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->get("/api/v1/chatbot/tasks/{$this->taskA->uuid}/stream");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: complete', $content);
        $this->assertStringContainsString('"status":"success"', $content);
        $this->assertStringContainsString('"progress":100', $content);
    }

    /**
     * P2.5.6.1: SSE stream phát event 'error' khi tác vụ thất bại.
     */
    public function test_server_sent_events_stream_emits_error_event_when_task_failed(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $this->taskA->update([
            'status' => TaskStatus::FAILED,
            'error_code' => 'AI_PROVIDER_RATE_LIMIT',
            'error_message' => 'Hạn mức token của Gemini tạm thời bị nghẽn.',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->get("/api/v1/chatbot/tasks/{$this->taskA->uuid}/stream");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: error', $content);
        $this->assertStringContainsString('AI_PROVIDER_RATE_LIMIT', $content);
        $this->assertStringContainsString('Hạn mức token của Gemini tạm thời bị nghẽn.', $content);
    }

    /**
     * P2.5.6.3: Resume / Retry khôi phục thành công tác vụ bị lỗi.
     */
    public function test_resume_endpoint_recovers_failed_task(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Giả lập tác vụ gặp sự cố mạng
        $this->taskService->failTask($this->taskA, 'Mạng gián đoạn trong lúc cào SERP', 'NETWORK_TIMEOUT');
        $this->taskA->refresh();
        $this->assertSame(TaskStatus::FAILED, $this->taskA->status);

        // Kích hoạt Resume
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/resume");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.error_message', null)
            ->assertJsonPath('data.error_code', null)
            ->assertJsonPath('data.attempts', 2);

        $this->taskA->refresh();
        $this->assertSame(TaskStatus::RUNNING, $this->taskA->status);
        $this->assertNull($this->taskA->error_message);
    }

    /**
     * P2.5.6.3: Resume từ chối trên tác vụ đã thành công (tránh chạy lại lãng phí).
     */
    public function test_resume_fails_on_already_completed_task(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $this->taskA->update(['status' => TaskStatus::SUCCESS]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/resume");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Chỉ có thể tiếp tục tác vụ đã thất bại hoặc bị hủy.');
    }

    /**
     * P2.5.6.3: Cancel dừng tác vụ đang chạy và giải phóng credit đã giữ trước (Zero Loss).
     */
    public function test_cancel_endpoint_cancels_running_task_and_releases_credits(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Đảm bảo task đã reserve 20 credit
        $this->creditLedger->reserveCredits(
            workspace: $this->workspaceA,
            amount: 20,
            referenceType: 'task',
            referenceId: $this->taskA->id,
            idempotencyKey: 'task:reserve:test:'.$this->taskA->id
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/tasks/{$this->taskA->uuid}/cancel", [
                'reason' => 'Người dùng đổi ý không muốn tạo bài viết này nữa',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->taskA->refresh();
        $this->assertSame(TaskStatus::CANCELLED, $this->taskA->status);
        $this->assertNotNull($this->taskA->cancelled_at);
        $this->assertStringContainsString('Người dùng đổi ý', (string) $this->taskA->error_message);
    }

    /**
     * Multi-Tenancy Isolation: Workspace A tuyệt đối không thể đọc hoặc can thiệp tác vụ của Workspace B.
     */
    public function test_multi_tenancy_isolation_prevents_cross_workspace_task_access(): void
    {
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;

        // 1. Không thể xem status
        $resStatus = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/tasks/{$this->taskB->uuid}/status");
        $resStatus->assertStatus(404);

        // 2. Không thể stream
        $resStream = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->get("/api/v1/chatbot/tasks/{$this->taskB->uuid}/stream");
        $resStream->assertStatus(404);

        // 3. Không thể resume
        $resResume = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/tasks/{$this->taskB->uuid}/resume");
        $resResume->assertStatus(404);

        // 4. Không thể cancel
        $resCancel = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/chatbot/tasks/{$this->taskB->uuid}/cancel");
        $resCancel->assertStatus(404);
    }

    /**
     * Xử lý trường hợp UUID không tồn tại trả về 404 an toàn.
     */
    public function test_task_not_found_returns_404(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;
        $randomUuid = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/tasks/{$randomUuid}/status");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
