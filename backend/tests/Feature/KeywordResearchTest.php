<?php

namespace Tests\Feature;

use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Enums\CreditGrantSource;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Campaign;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
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

class KeywordResearchTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

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
            'name' => 'SEO Campaign Workspace',
            'slug' => 'seo-campaign-workspace',
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

    public function test_unauthenticated_user_cannot_access_keyword_research(): void
    {
        $response = $this->getJson('/api/v1/keywords/research?query=tai+nghe');

        $response->assertStatus(401);
    }

    public function test_keyword_research_requires_query_or_keyword_param(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/keywords/research');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query', 'keyword']);
    }

    public function test_keyword_research_fails_when_workspace_has_insufficient_credits(): void
    {
        // Tài khoản có 0 credit
        $account = $this->ledgerService->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 0]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/keywords/research?query=tai+nghe+bluetooth');

        $response->assertStatus(402)
            ->assertJsonFragment([
                'error_code' => 'INSUFFICIENT_CREDITS',
            ]);
    }

    public function test_keyword_research_succeeds_and_deducts_exact_credits(): void
    {
        // Cấp 10 credit ban đầu
        $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 10,
            source: CreditGrantSource::PROMO
        );

        $initialBalance = $this->ledgerService->getBalance($this->workspace)->availableCredits;
        $this->assertSame(10, $initialBalance);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/keywords/research?query=tai+nghe+bluetooth&limit=15');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'seed_keyword',
                    'language',
                    'country',
                    'credits_deducted',
                    'total_keywords',
                    'keywords' => [
                        '*' => [
                            'keyword',
                            'search_intent',
                            'search_volume',
                            'difficulty',
                            'cpc',
                            'lsi_keywords',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame('tai nghe bluetooth', $data['seed_keyword']);
        $this->assertSame(2, $data['credits_deducted']);
        $this->assertNotEmpty($data['keywords']);

        // Xác minh credit đã bị trừ đúng 2 credits (10 - 2 = 8)
        $newBalance = $this->ledgerService->getBalance($this->workspace)->availableCredits;
        $this->assertSame(8, $newBalance);

        // Kiểm tra phân loại Intent hợp lệ
        foreach ($data['keywords'] as $kw) {
            $this->assertContains($kw['search_intent'], ['informational', 'commercial', 'transactional', 'navigational']);
            $this->assertIsInt($kw['search_volume']);
            $this->assertIsInt($kw['difficulty']);
            $this->assertIsArray($kw['lsi_keywords']);
        }
    }

    public function test_keyword_clustering_groups_keywords_by_intent_and_topic(): void
    {
        $keywords = [
            'tai nghe bluetooth là gì',
            'hướng dẫn sử dụng tai nghe bluetooth',
            'tai nghe bluetooth tốt nhất 2026',
            'đánh giá tai nghe sony bluetooth',
            'mua tai nghe bluetooth giá rẻ',
            'báo giá tai nghe không dây chính hãng',
        ];

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->postJson('/api/v1/keywords/cluster', [
                'keywords' => $keywords,
                'language' => 'vi',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_clusters',
                    'total_keywords',
                    'clusters' => [
                        '*' => [
                            'cluster_name',
                            'pillar_topic',
                            'primary_keyword',
                            'search_intent',
                            'total_search_volume',
                            'avg_difficulty',
                            'keywords',
                        ],
                    ],
                ],
            ]);

        $clusters = $response->json('data.clusters');
        $this->assertNotEmpty($clusters);
        $this->assertSame(6, $response->json('data.total_keywords'));
    }

    public function test_keyword_clustering_with_ai_mock_succeeds(): void
    {
        $mockAiResponse = new AIResponseDTO(
            content: json_encode([
                [
                    'cluster_name' => 'Hướng Dẫn Tai Nghe',
                    'pillar_topic' => 'Tai Nghe Bluetooth',
                    'primary_keyword' => 'tai nghe bluetooth là gì',
                    'search_intent' => 'informational',
                    'keywords' => ['tai nghe bluetooth là gì', 'hướng dẫn sử dụng tai nghe bluetooth'],
                ],
            ]),
            provider: AIProvider::GEMINI,
            model: 'gemini-2.5-flash',
            promptTokens: 100,
            completionTokens: 80,
            totalTokens: 180,
            latencyMs: 150,
            rawResponse: []
        );

        $mockAiService = Mockery::mock(AIProviderService::class);
        $mockAiService->shouldReceive('complete')
            ->once()
            ->andReturn($mockAiResponse);

        $this->app->instance(AIProviderService::class, $mockAiService);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->postJson('/api/v1/keywords/cluster', [
                'keywords' => [
                    'tai nghe bluetooth là gì',
                    'hướng dẫn sử dụng tai nghe bluetooth',
                ],
                'language' => 'vi',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_clusters', 1)
            ->assertJsonPath('data.clusters.0.cluster_name', 'Hướng Dẫn Tai Nghe')
            ->assertJsonPath('data.clusters.0.pillar_topic', 'Tai Nghe Bluetooth');
    }

    public function test_save_researched_keywords_to_campaign(): void
    {
        $campaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Chiến dịch Tai Nghe',
            'slug' => 'chien-dich-tai-nghe',
            'status' => 'draft',
        ]);

        $keywordsToSave = [
            [
                'keyword' => 'tai nghe bluetooth giá rẻ',
                'search_intent' => 'transactional',
                'search_volume' => 3200,
                'difficulty' => 45,
                'cpc' => 0.85,
                'pillar_topic' => 'Tai Nghe Bluetooth',
                'content_cluster' => 'Báo Giá & Khuyến Mãi',
                'lsi_keywords' => ['tai nghe tws', 'tai nghe pin trau'],
            ],
            [
                'keyword' => 'cách kết nối tai nghe bluetooth với laptop',
                'search_intent' => 'informational',
                'search_volume' => 1800,
                'difficulty' => 25,
                'cpc' => 0.20,
                'pillar_topic' => 'Tai Nghe Bluetooth',
                'content_cluster' => 'Hướng Dẫn Kỹ Thuật',
                'lsi_keywords' => ['ket noi bluetooth win 11', 'loi khong nhan tai nghe'],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->postJson('/api/v1/keywords/research/save', [
                'campaign_id' => $campaign->id,
                'keywords' => $keywordsToSave,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'saved_count' => 2,
                ],
            ]);

        $this->assertDatabaseHas('keywords', [
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'keyword' => 'tai nghe bluetooth giá rẻ',
            'search_intent' => 'transactional',
            'search_volume' => 3200,
        ]);

        $this->assertDatabaseHas('keywords', [
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'keyword' => 'cách kết nối tai nghe bluetooth với laptop',
            'search_intent' => 'informational',
            'search_volume' => 1800,
        ]);

        $kwModel = Keyword::where('workspace_id', $this->workspace->id)
            ->where('keyword', 'tai nghe bluetooth giá rẻ')
            ->firstOrFail();

        $this->assertIsArray($kwModel->lsi_keywords);
        $this->assertContains('tai nghe tws', $kwModel->lsi_keywords);
    }

    public function test_keyword_model_casts_and_fillable_lsi_keywords(): void
    {
        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'bàn phím cơ không dây',
            'search_intent' => 'commercial',
            'search_volume' => 5400,
            'difficulty' => 50,
            'lsi_keywords' => ['switch linear', 'layout 75%', 'keycap pbt'],
        ]);

        $fresh = Keyword::find($kw->id);
        $this->assertNotNull($fresh);
        $this->assertIsArray($fresh->lsi_keywords);
        $this->assertCount(3, $fresh->lsi_keywords);
        $this->assertSame('switch linear', $fresh->lsi_keywords[0]);
    }
}
