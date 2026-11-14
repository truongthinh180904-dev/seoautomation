<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\Bulk\ProcessBulkBatchJob;
use App\Models\Article;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Bulk\BulkGenerationService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulkGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected Website $website;

    protected Tenant $otherTenant;

    protected User $otherUser;

    protected Workspace $otherWorkspace;

    protected Website $otherWebsite;

    protected CreditLedgerService $creditLedger;

    protected BulkGenerationService $bulkService;

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
        $this->bulkService = app(BulkGenerationService::class);
        Queue::fake([ProcessBulkBatchJob::class]);

        // 1. Setup Tenant & Workspace 1
        $this->tenant = Tenant::create([
            'name' => 'Bulk Content Tenant',
            'slug' => 'bulk-content-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'bulkoperator@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'SEO Factory Workspace',
            'slug' => 'seo-factory-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $this->user->default_workspace_id = $this->workspace->id;
        $this->user->save();

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->website = Website::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Tech News Portal',
            'domain' => 'technews.vn',
            'url' => 'https://technews.vn',
            'default_language' => 'vi',
            'niche' => 'Technology',
        ]);

        // Cấp 1,000 credit ban đầu cho Workspace 1
        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 1000]);

        // 2. Setup Tenant & Workspace 2 (Dành cho kiểm thử cô lập dữ liệu - Isolation)
        $this->otherTenant = Tenant::create([
            'name' => 'Competitor Tenant',
            'slug' => 'competitor-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'otherbulk@example.com',
            'tenant_id' => $this->otherTenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'owner_id' => $this->otherUser->id,
            'legacy_tenant_id' => $this->otherTenant->id,
            'name' => 'Rival Workspace',
            'slug' => 'rival-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $this->otherUser->default_workspace_id = $this->otherWorkspace->id;
        $this->otherUser->save();

        WorkspaceMember::create([
            'workspace_id' => $this->otherWorkspace->id,
            'user_id' => $this->otherUser->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->otherWebsite = Website::create([
            'workspace_id' => $this->otherWorkspace->id,
            'name' => 'Rival Blog',
            'domain' => 'rivalblog.vn',
            'url' => 'https://rivalblog.vn',
            'default_language' => 'vi',
            'niche' => 'Marketing',
        ]);

        $otherAccount = $this->creditLedger->getOrCreateAccount($this->otherWorkspace);
        $otherAccount->update(['available_credits' => 500]);
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
     * Test P2.9.1 & P2.9.2: Tạo lô 10 bài viết thành công, áp dụng đơn giá ưu đãi 18 credit/bài
     * và giữ trước chính xác 180 credit qua CreditLedgerService.
     */
    public function test_can_create_bulk_batch_and_reserve_credits_with_tier_pricing(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);

        $keywords = [
            'học seo căn bản 2026',
            'tối ưu onpage seo',
            'nghiên cứu từ khóa seo',
            'chiến lược backlink bền vững',
            'tối ưu tốc độ tải trang',
            'cấu trúc silong cho website',
            'entity seo là gì',
            'schema markup cho bài viết',
            'chỉ mục google nhanh nhất',
            'seo thương mại điện tử',
        ];

        $response = $this->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/bulk-batches', [
                'website_id' => $this->website->id,
                'name' => 'Chiến dịch Content SEO Tháng 11',
                'keywords' => $keywords,
                'tone' => 'professional',
                'target_word_count' => 1500,
                'include_images' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 10)
            ->assertJsonPath('data.credits_reserved', 180)
            ->assertJsonPath('data.status', 'pending');

        $batchUuid = $response->json('data.uuid');
        $this->assertDatabaseHas('bulk_batches', [
            'uuid' => $batchUuid,
            'workspace_id' => $this->workspace->id,
            'total_items' => 10,
            'credits_reserved' => 180,
            'status' => 'pending',
        ]);

        // Kiểm tra số dư credit của workspace: ban đầu 1,000 -> giữ 180 -> khả dụng còn 820
        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $this->assertEquals(820, $account->available_credits);
        $this->assertEquals(180, $account->reserved_credits);

        // Đảm bảo Job xử lý ngầm được đưa vào queue
        Queue::assertPushed(ProcessBulkBatchJob::class, function ($job) use ($response) {
            return $job->bulkBatchId === $response->json('data.id')
                && $job->queue === 'bulk-generation';
        });
    }

    /**
     * Test P2.9.2: Từ chối tạo lô bài viết khi số dư credit không đủ (HTTP 402 INSUFFICIENT_CREDITS).
     */
    public function test_cannot_create_bulk_batch_if_insufficient_credits(): void
    {
        Sanctum::actingAs($this->user);

        // Hạ số dư credit xuống chỉ còn 50 credit
        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $account->update([
            'available_credits' => 50,
            'reserved_credits' => 0,
        ]);

        $keywords = [
            'từ khóa 1', 'từ khóa 2', 'từ khóa 3', 'từ khóa 4', 'từ khóa 5',
            'từ khóa 6', 'từ khóa 7', 'từ khóa 8', 'từ khóa 9', 'từ khóa 10',
        ];

        $response = $this->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/bulk-batches', [
                'website_id' => $this->website->id,
                'keywords' => $keywords,
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INSUFFICIENT_CREDITS')
            ->assertJsonPath('required_credits', 180)
            ->assertJsonPath('available_credits', 50);

        // Đảm bảo không có batch nào được tạo trong DB
        $this->assertDatabaseCount('bulk_batches', 0);
    }

    /**
     * Test P2.9.2: Thực thi xử lý trọn vẹn lô 10 bài viết:
     * - Tạo thành công 10 Articles tương ứng trong DB.
     * - Quyết toán trừ thật 180 credit từ phần tạm giữ (credits_settled = 180, credits_reserved = 0).
     * - Cập nhật trạng thái lô thành completed (100%).
     */
    public function test_process_batch_creates_articles_and_settles_credits_from_reserved(): void
    {
        $keywords = [
            'seo onpage chuyên sâu 1',
            'seo onpage chuyên sâu 2',
            'seo onpage chuyên sâu 3',
            'seo onpage chuyên sâu 4',
            'seo onpage chuyên sâu 5',
            'seo onpage chuyên sâu 6',
            'seo onpage chuyên sâu 7',
            'seo onpage chuyên sâu 8',
            'seo onpage chuyên sâu 9',
            'seo onpage chuyên sâu 10',
        ];

        $batch = $this->bulkService->createBatch($this->workspace, $this->website, [
            'name' => 'Lô 10 bài viết test',
            'keywords' => $keywords,
            'delay_seconds' => 0,
        ], $this->user);

        $this->assertEquals(180, $batch->credits_reserved);
        $this->assertEquals('pending', $batch->status);

        // Chạy xử lý batch
        $processedBatch = $this->bulkService->processBatch($batch);

        $this->assertEquals('completed', $processedBatch->status);
        $this->assertEquals(10, $processedBatch->completed_items);
        $this->assertEquals(0, $processedBatch->failed_items);
        $this->assertEquals(180, $processedBatch->credits_settled);
        $this->assertEquals(0, $processedBatch->credits_reserved);
        $this->assertNotNull($processedBatch->finished_at);
        $this->assertEquals(100.0, $processedBatch->progress_percent);

        // Kiểm tra 10 bài viết được tạo trong DB
        $this->assertDatabaseCount('articles', 10);
        $createdArticles = Article::where('workspace_id', $this->workspace->id)->get();
        $this->assertCount(10, $createdArticles);
        foreach ($createdArticles as $article) {
            $this->assertEquals(ArticleStatus::DRAFT, $article->status);
            $this->assertStringContainsString('Nội dung đang được khởi tạo hàng loạt', $article->content);
        }

        // Kiểm tra số dư tài khoản credit: 1000 - 180 = 820 available, 0 reserved
        $account = $this->creditLedger->getOrCreateAccount($this->workspace)->fresh();
        $this->assertEquals(820, $account->available_credits);
        $this->assertEquals(0, $account->reserved_credits);
    }

    /**
     * Test P2.9.3: Tự động hoàn credit (Zero Loss Guarantee) cho các bài viết bị lỗi vĩnh viễn:
     * - Tạo lô 3 bài viết (< 10 bài -> đơn giá chuẩn 20 credit/bài, tổng giữ = 60).
     * - 2 bài thành công, 1 bài gặp lỗi vĩnh viễn (simulate failure).
     * - 40 credit được quyết toán trừ cho 2 bài thành công.
     * - 20 credit của bài bị lỗi được giải phóng hoàn trả ngay lập tức về available_credits.
     * - Trạng thái lô đạt partially_failed.
     */
    public function test_auto_refund_when_bulk_batch_item_fails_permanently(): void
    {
        // Thiết lập available_credits = 200
        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 200, 'reserved_credits' => 0]);

        $keywords = [
            'từ khóa thành công 1',
            'FAIL:từ khóa lỗi vĩnh viễn', // Kích hoạt lỗi giả lập
            'từ khóa thành công 2',
        ];

        // 3 items * 20 credits/unit = 60 credits reserved
        $batch = $this->bulkService->createBatch($this->workspace, $this->website, [
            'name' => 'Lô kiểm thử tự động hoàn credit',
            'keywords' => $keywords,
            'delay_seconds' => 0,
        ], $this->user);

        $account->refresh();
        $this->assertEquals(140, $account->available_credits); // 200 - 60
        $this->assertEquals(60, $account->reserved_credits);

        // Chạy xử lý lô bài viết
        $processedBatch = $this->bulkService->processBatch($batch);

        $this->assertEquals('partially_failed', $processedBatch->status);
        $this->assertEquals(2, $processedBatch->completed_items);
        $this->assertEquals(1, $processedBatch->failed_items);
        $this->assertEquals(40, $processedBatch->credits_settled);
        $this->assertEquals(0, $processedBatch->credits_reserved);

        // Kiểm tra số dư tài khoản sau khi hoàn tiền:
        // Ban đầu 200, trừ 40 cho 2 bài thành công, 20 của bài lỗi được trả lại -> 160 khả dụng!
        $account->refresh();
        $this->assertEquals(160, $account->available_credits);
        $this->assertEquals(0, $account->reserved_credits);

        // Chỉ có 2 bài viết được tạo
        $this->assertDatabaseCount('articles', 2);
    }

    /**
     * Test P2.9.4: Endpoint GET /api/v1/bulk-batches/{uuid}/progress trả về tiến độ thời gian thực chính xác.
     */
    public function test_can_get_batch_progress_via_api(): void
    {
        Sanctum::actingAs($this->user);

        $batch = $this->bulkService->createBatch($this->workspace, $this->website, [
            'name' => 'Theo dõi tiến độ realtime',
            'keywords' => ['từ khóa 1', 'từ khóa 2'],
            'delay_seconds' => 0,
        ], $this->user);

        $response = $this->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/bulk-batches/{$batch->uuid}/progress");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $batch->uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonPath('data.completed_items', 0)
            ->assertJsonPath('data.progress_percent', 0);
    }

    /**
     * Test Hủy lô bài viết (Cancel Batch) và hoàn trả 100% credit tạm giữ còn lại.
     */
    public function test_can_cancel_batch_and_refund_remaining_credits(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 500, 'reserved_credits' => 0]);

        $batch = $this->bulkService->createBatch($this->workspace, $this->website, [
            'name' => 'Lô chuẩn bị hủy',
            'keywords' => ['từ khóa a', 'từ khóa b', 'từ khóa c', 'từ khóa d', 'từ khóa e'],
            'delay_seconds' => 0,
        ], $this->user);

        // 5 items * 20 credits = 100 credits reserved
        $account->refresh();
        $this->assertEquals(400, $account->available_credits);
        $this->assertEquals(100, $account->reserved_credits);

        // Gửi yêu cầu hủy
        $response = $this->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/bulk-batches/{$batch->uuid}/cancel", [
                'reason' => 'Đổi kế hoạch content',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        // Toàn bộ 100 credit tạm giữ phải được hoàn về ví khả dụng
        $account->refresh();
        $this->assertEquals(500, $account->available_credits);
        $this->assertEquals(0, $account->reserved_credits);
    }

    /**
     * Test Multi-Tenancy Isolation: Không thể xem hoặc hủy lô bài viết của Workspace khác.
     */
    public function test_workspace_isolation_cannot_access_other_workspace_bulk_batch(): void
    {
        // Tạo batch trên Workspace 1
        $batch = $this->bulkService->createBatch($this->workspace, $this->website, [
            'name' => 'Batch bí mật của Workspace 1',
            'keywords' => ['từ khóa 1'],
            'delay_seconds' => 0,
        ], $this->user);

        // Người dùng từ Workspace 2 cố truy cập
        Sanctum::actingAs($this->otherUser);

        $response = $this->withHeader('X-Workspace-Id', (string) $this->otherWorkspace->id)
            ->getJson("/api/v1/bulk-batches/{$batch->uuid}/progress");

        $response->assertStatus(404);

        $cancelResponse = $this->withHeader('X-Workspace-Id', (string) $this->otherWorkspace->id)
            ->postJson("/api/v1/bulk-batches/{$batch->uuid}/cancel");

        $cancelResponse->assertStatus(404);
    }
}
