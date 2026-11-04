<?php

namespace Tests\Feature;

use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\ContentGap;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\TopicCluster;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AI\AIProviderService;
use App\Services\Billing\CreditLedgerService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TopicClusterTest extends TestCase
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

    protected CreditLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);

        // 1. Tạo Tenant & Workspace 1
        $this->tenant = Tenant::create([
            'name' => 'SEO Agency Tenant',
            'slug' => 'seo-agency-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'seomaster@example.com',
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
            'name' => 'SEO Growth Blog',
            'domain' => 'growthblog.com',
            'url' => 'https://growthblog.com',
            'default_language' => 'vi',
            'niche' => 'Digital Marketing',
        ]);

        // Cấp credit cho Workspace 1
        $this->ledgerService = app(CreditLedgerService::class);
        $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            expiresAt: now()->addMonth(),
            idempotencyKey: 'test-grant-cluster-1',
        );

        // 2. Tạo Tenant & Workspace 2 (Dùng để kiểm thử cô lập dữ liệu - Isolation)
        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'otheruser@example.com',
            'tenant_id' => $this->otherTenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'owner_id' => $this->otherUser->id,
            'legacy_tenant_id' => $this->otherTenant->id,
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
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
            'name' => 'Other Brand',
            'domain' => 'otherbrand.com',
            'url' => 'https://otherbrand.com',
            'default_language' => 'vi',
            'niche' => 'E-commerce',
        ]);
    }

    protected function tearDown(): void
    {
        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        app()->forgetInstance(AIProviderService::class);
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }

    public function test_can_create_topic_cluster_with_tenant_isolation(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/topic-clusters', [
                'website_id' => $this->website->id,
                'name' => 'Cụm Dịch Vụ SEO Tổng Thể',
                'description' => 'Tất cả các bài viết về dịch vụ SEO và chiến lược lên top.',
                'target_audience' => 'Doanh nghiệp SMEs',
                'status' => 'planning',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Cụm Dịch Vụ SEO Tổng Thể')
            ->assertJsonPath('data.slug', 'cum-dich-vu-seo-tong-the');

        $clusterId = $response->json('data.id');

        $this->assertDatabaseHas('topic_clusters', [
            'id' => $clusterId,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'name' => 'Cụm Dịch Vụ SEO Tổng Thể',
        ]);

        // Xác minh Workspace 2 không thể xem hoặc truy cập cluster của Workspace 1 (404 Isolation)
        $unauthorizedResponse = $this->actingAs($this->otherUser)
            ->withHeader('X-Workspace-Id', (string) $this->otherWorkspace->id)
            ->getJson("/api/v1/topic-clusters/{$clusterId}");

        $unauthorizedResponse->assertStatus(404);
    }

    public function test_can_assign_keywords_and_recalculate_metrics(): void
    {
        // 1. Tạo từ khóa chính và 2 từ khóa phụ
        $pillarKeyword = Keyword::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'keyword' => 'dịch vụ seo tổng thể',
            'search_volume' => 1000,
            'difficulty' => 40,
            'status' => KeywordStatus::PENDING,
        ]);

        $subKeyword1 = Keyword::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'keyword' => 'báo giá dịch vụ seo',
            'search_volume' => 2000,
            'difficulty' => 50,
            'status' => KeywordStatus::PENDING,
        ]);

        $subKeyword2 = Keyword::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'keyword' => 'quy trình seo website',
            'search_volume' => 1500,
            'difficulty' => 30,
            'status' => KeywordStatus::PENDING,
        ]);

        // 2. Tạo Topic Cluster gắn pillar_keyword
        $cluster = TopicCluster::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'pillar_keyword_id' => $pillarKeyword->id,
            'name' => 'SEO B2B',
            'slug' => 'seo-b2b',
            'status' => 'planning',
        ]);

        // Gán 2 từ khóa phụ vào cluster qua API
        $assignResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/topic-clusters/{$cluster->id}/assign-keywords", [
                'keyword_ids' => [$subKeyword1->id, $subKeyword2->id],
            ]);

        $assignResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_count', 2);

        // Kiểm tra metrics đã tính toán: Total Volume = 1000 + 2000 + 1500 = 4500
        // Avg Difficulty = (40 + 50 + 30) / 3 = 40.00
        $cluster->refresh();
        $this->assertEquals(4500, $cluster->total_search_volume);
        $this->assertEquals(40.00, $cluster->avg_difficulty);

        // 3. Gỡ 1 từ khóa ra khỏi cluster
        $removeResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/topic-clusters/{$cluster->id}/remove-keyword", [
                'keyword_id' => $subKeyword1->id,
            ]);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Metrics cập nhật lại: Còn pillarKeyword (1000, 40) + subKeyword2 (1500, 30)
        // Total Volume = 2500, Avg Difficulty = 35.00
        $cluster->refresh();
        $this->assertEquals(2500, $cluster->total_search_volume);
        $this->assertEquals(35.00, $cluster->avg_difficulty);
    }

    public function test_can_update_and_delete_cluster_without_deleting_keywords(): void
    {
        $keyword = Keyword::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'keyword' => 'tối ưu onpage',
            'search_volume' => 800,
            'difficulty' => 25,
            'status' => KeywordStatus::PENDING,
        ]);

        $cluster = TopicCluster::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'name' => 'Onpage SEO Silo',
            'slug' => 'onpage-seo-silo',
            'status' => 'planning',
        ]);

        $keyword->forceFill(['topic_cluster_id' => $cluster->id])->save();

        // Cập nhật cluster
        $updateResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->putJson("/api/v1/topic-clusters/{$cluster->id}", [
                'name' => 'Onpage SEO Master',
                'status' => 'in_progress',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Onpage SEO Master')
            ->assertJsonPath('data.status', 'in_progress');

        // Xóa cluster
        $deleteResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->deleteJson("/api/v1/topic-clusters/{$cluster->id}");

        $deleteResponse->assertStatus(200);

        // Xác nhận cluster bị xóa
        $this->assertDatabaseMissing('topic_clusters', ['id' => $cluster->id]);

        // Nhưng Keyword vẫn nguyên vẹn trong DB và topic_cluster_id được set về null
        $this->assertDatabaseHas('keywords', [
            'id' => $keyword->id,
            'topic_cluster_id' => null,
        ]);
    }

    public function test_can_generate_ai_clusters_with_mocked_provider(): void
    {
        $mockAiResponse = json_encode([
            [
                'name' => 'Cụm Tối Ưu Tốc Độ Web',
                'slug' => 'cum-toi-uu-toc-do-web',
                'description' => 'Hướng dẫn tối ưu Core Web Vitals cho SEO.',
                'target_audience' => 'Lập trình viên và SEOer',
                'pillar_keyword' => [
                    'keyword' => 'tối ưu core web vitals',
                    'search_volume' => 3200,
                    'difficulty' => 38,
                    'search_intent' => 'informational',
                ],
                'supporting_keywords' => [
                    [
                        'keyword' => 'cách giảm lcp',
                        'search_volume' => 900,
                        'difficulty' => 25,
                        'search_intent' => 'informational',
                    ],
                    [
                        'keyword' => 'tối ưu cls wordpress',
                        'search_volume' => 650,
                        'difficulty' => 20,
                        'search_intent' => 'informational',
                    ],
                ],
            ],
        ]);

        $mockAiResponseDto = new AIResponseDTO(
            content: $mockAiResponse,
            provider: AIProvider::GEMINI,
            model: 'gemini-2.5-flash',
            promptTokens: 300,
            completionTokens: 250,
            totalTokens: 550,
            latencyMs: 150,
            rawResponse: []
        );

        $mockAiService = Mockery::mock(AIProviderService::class);
        $mockAiService->shouldReceive('complete')
            ->andReturn($mockAiResponseDto);
        $mockAiService->shouldReceive('sendRequest')
            ->andReturn($mockAiResponseDto);

        $this->app->instance(AIProviderService::class, $mockAiService);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/topic-clusters/generate-ai', [
                'website_id' => $this->website->id,
                'seed_topic' => 'Tối ưu tốc độ website',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_clusters', 1)
            ->assertJsonPath('data.clusters.0.name', 'Cụm Tối Ưu Tốc Độ Web')
            ->assertJsonPath('data.clusters.0.pillar_keyword.keyword', 'tối ưu core web vitals');
    }

    public function test_content_gap_analysis_calculates_opportunity_score_and_stores_gaps(): void
    {
        // Tạo một từ khóa hiện có trên website của mình để đối chiếu
        Keyword::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'keyword' => 'bảng giá seo mới nhất',
            'search_volume' => 1200,
            'difficulty' => 35,
            'status' => KeywordStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/competitor/content-gaps/analyze', [
                'website_id' => $this->website->id,
                'competitor_domain' => 'https://vietnamseo.vn/dich-vu',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.competitor_domain', 'vietnamseo.vn');

        $this->assertGreaterThan(0, $response->json('data.total_gaps'));
        $this->assertGreaterThan(0, $response->json('data.average_opportunity_score'));

        // Xác nhận dữ liệu được lưu vào bảng content_gaps kèm điểm opportunity_score
        $this->assertDatabaseHas('content_gaps', [
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'competitor_domain' => 'vietnamseo.vn',
            'status' => 'identified',
        ]);
    }

    public function test_can_target_content_gap_to_create_keyword(): void
    {
        $gap = ContentGap::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'competitor_domain' => 'rivalseo.com',
            'target_keyword' => 'chiến lược entity seo',
            'search_volume' => 1800,
            'keyword_difficulty' => 28,
            'competitor_rank' => 3,
            'our_rank' => null,
            'opportunity_score' => 82.50,
            'status' => 'identified',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/competitor/content-gaps/{$gap->id}/target");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gap.status', 'targeted')
            ->assertJsonPath('data.keyword.keyword', 'chiến lược entity seo');

        // Xác nhận keyword mới đã được tạo trong bảng keywords
        $this->assertDatabaseHas('keywords', [
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'keyword' => 'chiến lược entity seo',
            'search_volume' => 1800,
        ]);
    }

    public function test_content_gap_tenant_isolation(): void
    {
        $gap = ContentGap::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'competitor_domain' => 'toprank.vn',
            'target_keyword' => 'audit seo kỹ thuật',
            'search_volume' => 950,
            'keyword_difficulty' => 22,
            'competitor_rank' => 4,
            'opportunity_score' => 75.00,
            'status' => 'identified',
        ]);

        // Workspace 2 không thể truy cập hoặc target gap của Workspace 1
        $response = $this->actingAs($this->otherUser)
            ->withHeader('X-Workspace-Id', (string) $this->otherWorkspace->id)
            ->postJson("/api/v1/competitor/content-gaps/{$gap->id}/target");

        $response->assertStatus(404);
    }

    public function test_can_ignore_and_delete_content_gap(): void
    {
        $gap = ContentGap::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'competitor_domain' => 'spamsite.org',
            'target_keyword' => 'mua tool spam backlink',
            'search_volume' => 500,
            'keyword_difficulty' => 15,
            'competitor_rank' => 1,
            'opportunity_score' => 60.00,
            'status' => 'identified',
        ]);

        // 1. Đánh dấu bỏ qua
        $ignoreResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/competitor/content-gaps/{$gap->id}/ignore");

        $ignoreResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'ignored');

        // 2. Xóa gap
        $deleteResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->deleteJson("/api/v1/competitor/content-gaps/{$gap->id}");

        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('content_gaps', ['id' => $gap->id]);
    }
}
