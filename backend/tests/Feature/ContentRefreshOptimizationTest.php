<?php

namespace Tests\Feature;

use App\DTOs\PublishingDTO;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\WordPress\WordPressAuthException;
use App\Exceptions\WordPress\WordPressPublishException;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\BrandKnowledgeBase;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\GscSearchPerformanceDaily;
use App\Models\Keyword;
use App\Models\RankTrackedKeyword;
use App\Models\SeoIntelligenceInsight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WordPress\WordPressPublishService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentRefreshOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Tenant $tenantA;

    protected Website $websiteA;

    protected Article $articleA;

    protected Keyword $keywordA;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Tenant $tenantB;

    protected Website $websiteB;

    protected Article $articleB;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);

        // 1. Setup Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Dental Care Corp',
            'slug' => 'dental-care-corp',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'seo-master@dentalcare.vn',
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceA = Workspace::create([
            'owner_id' => $this->userA->id,
            'legacy_tenant_id' => $this->tenantA->id,
            'name' => 'Dental Care Workspace',
            'slug' => 'dental-care-ws',
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
            'domain' => 'dentalcare.vn',
            'name' => 'Nha Khoa Dental Care',
            'url' => 'https://dentalcare.vn',
            'status' => 'active',
        ]);

        CreditAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'available_credits' => 10,
            'reserved_credits' => 0,
            'cycle_granted_credits' => 10,
            'cycle_used_credits' => 0,
            'version' => 1,
        ]);

        $this->keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'cách chăm sóc răng sứ',
            'search_volume' => 1200,
            'difficulty' => 25,
            'status' => KeywordStatus::COMPLETED,
        ]);

        $this->articleA = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword_id' => $this->keywordA->id,
            'user_id' => $this->userA->id,
            'title' => 'Hướng dẫn cách chăm sóc răng sứ tại nhà',
            'slug' => 'huong-dan-cach-cham-soc-rang-su-tai-nha',
            'content' => '<h2>1. Vệ sinh hàng ngày</h2><p>Đánh răng 2 lần mỗi ngày bằng bàn chải lông mềm.</p><h2>2. Chế độ ăn uống</h2><p>Hạn chế đồ quá cứng hoặc quá nóng lạnh.</p>',
            'seo_title' => 'Cách chăm sóc răng sứ tại nhà hiệu quả',
            'seo_description' => 'Tìm hiểu cách chăm sóc răng sứ chuẩn nha khoa giúp kéo dài tuổi thọ răng sứ tối đa.',
            'focus_keyword' => 'cách chăm sóc răng sứ',
            'word_count' => 80,
            'seo_score' => 55,
            'readability_score' => 60,
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => now()->subMonths(6),
            'wordpress_post_id' => 101,
            'wordpress_post_url' => 'https://dentalcare.vn/huong-dan-cach-cham-soc-rang-su-tai-nha',
        ]);

        // 2. Setup Workspace B (Tenant B)
        $this->tenantB = Tenant::create([
            'name' => 'Auto Spa Vietnam',
            'slug' => 'auto-spa-vietnam',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'hacker@autospa.vn',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Auto Spa Workspace',
            'slug' => 'auto-spa-ws',
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
            'domain' => 'autospa.vn',
            'name' => 'Auto Spa VN',
            'url' => 'https://autospa.vn',
            'status' => 'active',
        ]);

        CreditAccount::create([
            'workspace_id' => $this->workspaceB->id,
            'available_credits' => 10,
            'reserved_credits' => 0,
            'cycle_granted_credits' => 10,
            'cycle_used_credits' => 0,
            'version' => 1,
        ]);

        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'chăm sóc xe hơi',
            'search_volume' => 500,
            'difficulty' => 15,
            'status' => KeywordStatus::COMPLETED,
        ]);

        $this->articleB = Article::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'keyword_id' => $keywordB->id,
            'user_id' => $this->userB->id,
            'title' => 'Chăm sóc xe hơi mùa mưa',
            'slug' => 'cham-soc-xe-hoi-mua-mua',
            'content' => '<p>Hướng dẫn bảo vệ gầm và sơn xe hơi mùa mưa bão.</p>',
            'seo_title' => 'Chăm sóc xe hơi',
            'seo_description' => 'Mẹo chăm sóc xe hơi.',
            'focus_keyword' => 'chăm sóc xe hơi',
            'word_count' => 60,
            'seo_score' => 50,
            'status' => ArticleStatus::PUBLISHED,
        ]);
    }

    /**
     * Test không thể tạo kế hoạch tối ưu khi không đủ 2 credits.
     */
    public function test_cannot_generate_optimization_plan_without_sufficient_credits(): void
    {
        CreditAccount::where('workspace_id', $this->workspaceA->id)->update(['available_credits' => 1]);

        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/optimization-plan");

        $response->assertStatus(402);
        $response->assertJson([
            'success' => false,
            'required_credits' => 2,
        ]);
    }

    /**
     * Test sinh kế hoạch tối ưu thành công và khấu trừ đúng 2 credits.
     */
    public function test_generates_optimization_plan_and_deducts_exactly_two_credits(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/optimization-plan");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'articleId',
                'originalTitle',
                'proposedTitle',
                'proposedContent',
                'scoreBefore',
                'scoreAfter',
                'scoreDelta',
                'addedHeadings',
                'diffSegments',
            ],
        ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual($data['scoreBefore'], $data['scoreAfter']);
        $this->assertNotEmpty($data['addedHeadings']);
        $this->assertNotEmpty($data['diffSegments']);

        // Xác nhận số dư bị trừ đúng 2 credits: 10 -> 8
        $account = CreditAccount::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertEquals(8, $account->available_credits);

        // Xác nhận bản ghi transaction
        $this->assertDatabaseHas(CreditTransaction::class, [
            'workspace_id' => $this->workspaceA->id,
            'reference_type' => 'content_optimization_plan',
            'reference_id' => $this->articleA->id,
            'amount' => -2,
        ]);
    }

    /**
     * Test kế hoạch tối ưu tích hợp bối cảnh GSC, GA4, Rank Tracking và SeoIntelligenceInsight.
     */
    public function test_optimization_plan_incorporates_gsc_and_ga4_context_and_insight(): void
    {
        // 1. GSC metric
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => now()->subDay()->toDateString(),
            'query' => 'bảng giá bọc răng sứ mới nhất',
            'page_url' => $this->articleA->wordpress_post_url,
            'page_hash' => md5($this->articleA->wordpress_post_url),
            'query_hash' => md5('bảng giá bọc răng sứ mới nhất'),
            'country' => 'vnm',
            'device' => 'mobile',
            'clicks' => 5,
            'impressions' => 1200,
            'ctr' => 0.004,
            'position' => 8.2,
        ]);

        // 2. GA4 metric
        Ga4PagePerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'article_id' => $this->articleA->id,
            'date' => now()->subDay()->toDateString(),
            'landing_page' => '/huong-dan-cach-cham-soc-rang-su-tai-nha',
            'page_hash' => md5('/huong-dan-cach-cham-soc-rang-su-tai-nha'),
            'sessions' => 350,
            'active_users' => 290,
            'engagement_rate' => 0.25,
            'conversions' => 2,
            'avg_session_duration' => 35.0,
        ]);

        // 3. Rank Tracked
        $trackedKeyword = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'cách chăm sóc răng sứ',
            'current_position' => 7,
            'previous_position' => 4,
            'is_active' => true,
        ]);

        // 4. Insight
        $insight = SeoIntelligenceInsight::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'article_id' => $this->articleA->id,
            'type' => 'ranking_drop',
            'severity' => 'warning',
            'opportunity_score' => 82,
            'title' => 'Từ khóa "cách chăm sóc răng sứ" tụt 3 bậc',
            'description' => 'Từ khóa tụt từ vị trí 4 xuống 7.',
            'metrics_snapshot' => [
                'keyword' => 'cách chăm sóc răng sứ',
                'current_position' => 7,
                'previous_position' => 4,
            ],
            'suggested_action' => 'Làm mới nội dung bổ sung intent mới',
            'status' => 'detected',
        ]);

        // 5. Brand Knowledge
        BrandKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'brand_name' => 'Nha Khoa Dental Care',
            'tone_of_voice' => 'chuyên gia, ân cần',
            'brand_persona' => 'Bác sĩ chuyên khoa phục hình răng',
            'taboo_words' => ['chữa khỏi 100%', 'răng sứ vĩnh cửu'],
        ]);

        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/optimization-plan", [
                'insight_id' => $insight->id,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($insight->id, $data['insightId']);
        $this->assertContains('bảng giá bọc răng sứ mới nhất', $data['missingIntentsAddressed']);
        $this->assertStringContainsString('bảng giá bọc răng sứ mới nhất', $data['proposedContent']);
    }

    /**
     * Test Diff Preview đọc từ cache không bị trừ thêm credit.
     */
    public function test_diff_preview_returns_cached_plan_without_recharging_credits(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        // 1. Tạo plan (trừ 2 credit: 10 -> 8)
        $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/optimization-plan");

        $accountAfterPlan = CreditAccount::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertEquals(8, $accountAfterPlan->available_credits);

        // 2. Gọi diff-preview (0 credit, số dư vẫn phải là 8)
        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->getJson("/api/v1/articles/{$this->articleA->id}/diff-preview");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertNotEmpty($response->json('data.diffSegments'));

        $accountAfterPreview = CreditAccount::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertEquals(8, $accountAfterPreview->available_credits);
    }

    /**
     * Test áp dụng bản tối ưu tạo bản lưu phiên bản cũ (ArticleVersion) và không tự publish lên WP.
     */
    public function test_apply_optimization_creates_version_backup_and_does_not_publish_to_wordpress(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $originalPublishedAt = $this->articleA->published_at;

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/apply-optimization", [
                'proposed_title' => 'Hướng dẫn cách chăm sóc răng sứ tại nhà chuẩn y khoa 2026',
                'proposed_content' => '<h2>1. Vệ sinh hàng ngày</h2><p>Đánh răng 2 lần mỗi ngày.</p><h2>2. Cập nhật xu hướng 2026</h2><p>Áp dụng máy tăm nước hiện đại.</p>',
                'proposed_seo_title' => 'Chăm sóc răng sứ 2026',
                'proposed_seo_description' => 'Hướng dẫn chi tiết năm 2026.',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Kiểm tra ArticleVersion được tạo với ghi chú AI Content Refresh
        $this->assertDatabaseHas(ArticleVersion::class, [
            'article_id' => $this->articleA->id,
            'change_reason' => 'AI Content Refresh',
            'title' => 'Hướng dẫn cách chăm sóc răng sứ tại nhà',
        ]);

        // Kiểm tra Article được cập nhật
        $fresh = $this->articleA->fresh();
        $this->assertEquals('Hướng dẫn cách chăm sóc răng sứ tại nhà chuẩn y khoa 2026', $fresh->title);
        $this->assertNotNull($fresh->last_optimized_at);
        $this->assertNotNull($fresh->pre_optimization_score);

        // Tuyệt đối không thay đổi published_at hay tự push WP ở bước này
        $this->assertEquals($originalPublishedAt->toDateTimeString(), $fresh->published_at->toDateTimeString());
    }

    /**
     * Test cổng Human-in-the-Loop: push update WordPress bắt buộc xác nhận từ người dùng.
     */
    public function test_push_update_to_wordpress_requires_confirmation(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/push-update-wordpress", [
                'confirm' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('xác nhận', $response->json('message'));
    }

    /**
     * Test cập nhật bài viết lên WordPress thành công khi người dùng xác nhận.
     */
    public function test_push_update_to_wordpress_successfully_updates_post(): void
    {
        $site = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Dental Care WordPress',
            'url' => 'https://dentalcare.vn',
            'api_url' => 'https://dentalcare.vn/wp-json',
            'username' => 'admin_wp',
            'app_password' => 'secret_wp_app_pwd',
            'status' => 'active',
        ]);

        $this->articleA->update([
            'wordpress_site_id' => $site->id,
            'wordpress_post_id' => 888,
        ]);

        $insight = SeoIntelligenceInsight::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'article_id' => $this->articleA->id,
            'type' => 'traffic_decay',
            'severity' => 'warning',
            'opportunity_score' => 75,
            'title' => 'Lưu lượng bài viết suy giảm',
            'description' => 'Giảm 30% clicks trong 4 tuần.',
            'metrics_snapshot' => [],
            'suggested_action' => 'Làm mới nội dung',
            'status' => 'in_progress',
        ]);

        Http::fake([
            'https://dentalcare.vn/wp-json/wp/v2/posts/888' => Http::response([
                'id' => 888,
                'link' => 'https://dentalcare.vn/cham-soc-rang-su-updated',
                'status' => 'publish',
            ], 200),
        ]);

        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/push-update-wordpress", [
                'confirm' => true,
                'insight_id' => $insight->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'post_id' => 888,
                'url' => 'https://dentalcare.vn/cham-soc-rang-su-updated',
            ],
        ]);

        // Insight được đóng trạng thái resolved
        $this->assertEquals('resolved', $insight->fresh()->status);
        $this->assertNotNull($insight->fresh()->resolved_at);
    }

    /**
     * Test WordPressPublishService::updatePost xử lý chính xác lỗi 401 và 404.
     */
    public function test_wordpress_publish_service_update_post_handles_401_and_404_errors(): void
    {
        $site = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Dental Care WP Test',
            'url' => 'https://dentalcare.vn',
            'api_url' => 'https://dentalcare.vn/wp-json',
            'username' => 'admin_wp',
            'app_password' => 'secret_wp_app_pwd',
            'status' => 'active',
        ]);

        $service = app(WordPressPublishService::class);
        $dto = new PublishingDTO(
            title: 'Test Update Post',
            content: '<p>Test content</p>',
            publishStatus: 'publish'
        );

        // 1. Kiểm tra lỗi 401 Unauthorized
        Http::fake([
            'https://dentalcare.vn/wp-json/wp/v2/posts/123' => Http::response([
                'code' => 'rest_cannot_edit',
                'message' => 'Sorry, you are not allowed to edit this post.',
            ], 401),
        ]);

        try {
            $service->updatePost(123, $dto, $site);
            $this->fail('Expected WordPressAuthException was not thrown on 401 response.');
        } catch (WordPressAuthException $e) {
            $this->assertStringContainsString('Authentication failed', $e->getMessage());
        }

        // 2. Kiểm tra lỗi 404 Not Found
        Http::fake([
            'https://dentalcare.vn/wp-json/wp/v2/posts/999' => Http::response([
                'code' => 'rest_post_invalid_id',
                'message' => 'Invalid post ID.',
            ], 404),
        ]);

        try {
            $service->updatePost(999, $dto, $site);
            $this->fail('Expected WordPressPublishException was not thrown on 404 response.');
        } catch (WordPressPublishException $e) {
            $this->assertStringContainsString('not found', strtolower($e->getMessage()));
        }
    }

    /**
     * Test cô lập Multi-Tenancy: Workspace khác không thể tạo plan hay cập nhật bài viết.
     */
    public function test_multi_tenancy_isolation_prevents_unauthorized_cross_workspace_optimization(): void
    {
        Sanctum::actingAs($this->userB, ['*']);

        // Hacker từ Workspace B cố gắng gọi optimization-plan trên bài viết của Workspace A
        $responsePlan = $this->withHeaders(['X-Workspace-Id' => $this->workspaceB->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/optimization-plan");

        $responsePlan->assertStatus(403);

        // Hacker cố gắng gọi push-update-wordpress trên bài viết của Workspace A
        $responsePush = $this->withHeaders(['X-Workspace-Id' => $this->workspaceB->id])
            ->postJson("/api/v1/articles/{$this->articleA->id}/push-update-wordpress", [
                'confirm' => true,
            ]);

        $responsePush->assertStatus(403);
    }
}
