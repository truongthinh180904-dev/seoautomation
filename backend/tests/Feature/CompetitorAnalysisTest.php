<?php

namespace Tests\Feature;

use App\Enums\CreditGrantSource;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\SEO\CompetitorAnalysisService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class CompetitorAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected CreditLedgerService $ledgerService;

    protected CompetitorAnalysisService $analysisService;

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
            'email' => 'competitor-master@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Competitor Intelligence Workspace',
            'slug' => 'competitor-intelligence-workspace',
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
        $this->analysisService = app(CompetitorAnalysisService::class);
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

    public function test_unauthenticated_user_cannot_access_serp_analysis(): void
    {
        $response = $this->getJson('/api/v1/competitor/serp-analysis?keyword=tai+nghe');

        $response->assertStatus(401);
    }

    public function test_serp_analysis_requires_keyword_or_query_parameter(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/competitor/serp-analysis');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['keyword']);
    }

    public function test_serp_analysis_fails_when_workspace_has_insufficient_credits(): void
    {
        $account = $this->ledgerService->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 0]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/competitor/serp-analysis?keyword=tai+nghe+bluetooth');

        $response->assertStatus(402)
            ->assertJsonFragment([
                'error_code' => 'INSUFFICIENT_CREDITS',
            ]);
    }

    public function test_competitor_analysis_service_blocks_ssrf_attacks(): void
    {
        $ssrfPayloads = [
            'http://127.0.0.1/admin',
            'http://localhost:8000/internal',
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/secret',
            'http://192.168.1.1/router',
        ];

        foreach ($ssrfPayloads as $payload) {
            try {
                $this->analysisService->crawlCompetitorUrl($payload, 'test keyword');
                $this->fail("Expected SSRF protection exception for: {$payload}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('SSRF', $e->getMessage());
            }
        }
    }

    public function test_parse_html_extracts_headings_h1_to_h4_schemas_and_density(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Đánh giá tai nghe bluetooth pin trâu nhất 2026</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "Top tai nghe bluetooth pin trâu",
        "author": {"@type": "Person", "name": "Admin"}
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {"@type": "FAQPage"}
        ]
    }
    </script>
</head>
<body>
    <header><p>Menu điều hướng</p></header>
    <article>
        <h1>Top 10 tai nghe bluetooth pin trâu đáng mua nhất hiện nay</h1>
        <p>Tai nghe bluetooth pin trâu đang là lựa chọn hàng đầu cho những ai thường xuyên di chuyển và làm việc văn phòng suốt cả ngày dài.</p>
        
        <h2>1. Tiêu chí chọn tai nghe bluetooth pin trâu</h2>
        <p>Để chọn được tai nghe bluetooth pin trâu chất lượng cao, bạn cần chú ý đến dung lượng dock sạc, chuẩn kết nối Bluetooth 5.4 và khả năng chống nước.</p>
        
        <h3>Dung lượng pin và thời gian sạc nhanh</h3>
        <p>Một chiếc tai nghe bluetooth pin trâu chuẩn mực nên đạt ít nhất 8 đến 10 giờ nghe nhạc liên tục cho một lần sạc.</p>

        <h4>Công nghệ sạc nhanh Type-C và Qi không dây</h4>
        <p>Sạc nhanh 10 phút mang lại 2 giờ nghe là tính năng vô cùng hữu ích.</p>

        <h2>Có nên mua tai nghe bluetooth pin trâu giá rẻ không?</h2>
        <p>Không nên ham rẻ mua các sản phẩm trôi nổi kém chất lượng dễ gây chai pin và nguy cơ cháy nổ.</p>

        <img src="tai-nghe-1.jpg" alt="Tai nghe bluetooth pin trâu cao cấp" />
        <img src="tai-nghe-2.jpg" alt="" />
        <img src="tai-nghe-3.jpg" />
    </article>
    <footer><p>Bản quyền 2026</p></footer>
</body>
</html>
HTML;

        $parsed = $this->analysisService->parseHtml($html, 'tai nghe bluetooth pin trâu');

        // 1. Headings H1..H4 count
        $this->assertSame(1, $parsed['headings_count']['h1']);
        $this->assertSame(2, $parsed['headings_count']['h2']);
        $this->assertSame(1, $parsed['headings_count']['h3']);
        $this->assertSame(1, $parsed['headings_count']['h4']);
        $this->assertSame(5, $parsed['headings_count']['total']);

        // 2. Schema JSON-LD extraction
        $this->assertContains('Article', $parsed['schemas']);
        $this->assertContains('FAQPage', $parsed['schemas']);

        // 3. Image analysis
        $this->assertSame(3, $parsed['images_count']);
        $this->assertSame(1, $parsed['images_with_alt']);
        $this->assertSame(2, $parsed['images_without_alt']);

        // 4. Keyword density & occurrences
        $this->assertGreaterThanOrEqual(4, $parsed['keyword_occurrences']);
        $this->assertGreaterThan(0.0, $parsed['keyword_density']);

        // 5. FAQs extraction
        $this->assertNotEmpty($parsed['faqs']);
        $this->assertStringContainsString('Có nên mua tai nghe bluetooth pin trâu giá rẻ không?', $parsed['faqs'][0]['question']);

        // 6. LSI entities
        $this->assertNotEmpty($parsed['lsi_keywords']);
    }

    public function test_serp_analysis_succeeds_and_deducts_exact_credits(): void
    {
        $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 10,
            source: CreditGrantSource::PROMO
        );

        $initialBalance = $this->ledgerService->getBalance($this->workspace)->availableCredits;
        $this->assertSame(10, $initialBalance);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/competitor/serp-analysis?keyword=tai+nghe+bluetooth&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'keyword',
                    'language',
                    'country',
                    'credits_deducted',
                    'total_competitors',
                    'benchmark' => [
                        'total_analyzed',
                        'avg_word_count',
                        'min_word_count',
                        'max_word_count',
                        'avg_h2_count',
                        'avg_h3_count',
                        'avg_h4_count',
                        'avg_headings_count',
                        'avg_image_count',
                        'avg_keyword_density',
                        'recommended_word_count',
                        'recommended_h2_count',
                        'recommended_image_count',
                        'common_schemas',
                        'common_lsi_keywords',
                    ],
                    'competitors' => [
                        '*' => [
                            'position',
                            'url',
                            'domain',
                            'title',
                            'word_count',
                            'headings_count',
                            'schemas',
                            'keyword_density',
                            'images_count',
                            'is_safe',
                        ],
                    ],
                    'related_searches',
                    'people_also_ask',
                ],
            ]);

        $this->assertSame('tai nghe bluetooth', $response->json('data.keyword'));
        $this->assertSame(3, $response->json('data.credits_deducted'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.total_competitors'));

        // Kiểm tra số dư credit giảm chính xác 3
        $finalBalance = $this->ledgerService->getBalance($this->workspace)->availableCredits;
        $this->assertSame(7, $finalBalance);
    }

    public function test_benchmark_matrix_calculation_and_recommendations(): void
    {
        $mockCompetitors = [
            [
                'position' => 1,
                'url' => 'https://competitor1.com/post-1',
                'domain' => 'competitor1.com',
                'word_count' => 2000,
                'headings_count' => ['h1' => 1, 'h2' => 6, 'h3' => 4, 'h4' => 0, 'total' => 11],
                'schemas' => ['Article', 'BreadcrumbList'],
                'images_count' => 6,
                'keyword_density' => 1.5,
                'lsi_keywords' => ['pin trâu', 'chống nước', 'sạc nhanh'],
            ],
            [
                'position' => 2,
                'url' => 'https://competitor2.com/post-2',
                'domain' => 'competitor2.com',
                'word_count' => 3000,
                'headings_count' => ['h1' => 1, 'h2' => 8, 'h3' => 6, 'h4' => 2, 'total' => 17],
                'schemas' => ['Article', 'FAQPage'],
                'images_count' => 10,
                'keyword_density' => 2.1,
                'lsi_keywords' => ['pin trâu', 'chống ồn', 'âm bass mạnh'],
            ],
        ];

        $benchmark = $this->analysisService->calculateBenchmark($mockCompetitors, 'tai nghe bluetooth');

        $this->assertSame(2, $benchmark['total_analyzed']);
        $this->assertSame(2500, $benchmark['avg_word_count']);
        $this->assertSame(2000, $benchmark['min_word_count']);
        $this->assertSame(3000, $benchmark['max_word_count']);
        $this->assertSame(7.0, $benchmark['avg_h2_count']);
        $this->assertSame(5.0, $benchmark['avg_h3_count']);
        $this->assertSame(1.0, $benchmark['avg_h4_count']);
        $this->assertSame(14.0, $benchmark['avg_headings_count']);
        $this->assertSame(8.0, $benchmark['avg_image_count']);
        $this->assertSame(1.8, $benchmark['avg_keyword_density']);

        // Khuyến nghị: avg * 1.15 = 2500 * 1.15 = 2875 từ
        $this->assertSame(2875, $benchmark['recommended_word_count']);
        $this->assertSame(7, $benchmark['recommended_h2_count']);
        $this->assertSame(8, $benchmark['recommended_image_count']);

        // Schema: Article xuất hiện ở 2/2 đối thủ (100%), FAQPage ở 1/2 đối thủ (50%)
        $schemaNames = array_column($benchmark['common_schemas'], 'name');
        $this->assertContains('Article', $schemaNames);
        $this->assertContains('FAQPage', $schemaNames);

        // LSI: 'pin trâu' xuất hiện ở cả 2 đối thủ
        $this->assertContains('pin trâu', $benchmark['common_lsi_keywords']);
    }

    public function test_competitor_analysis_respects_workspace_isolation(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'Isolated Tenant 2',
            'slug' => 'isolated-tenant-2',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        // Tạo workspace 2 không có credit
        $workspace2 = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $tenant2->id,
            'name' => 'Isolated Workspace 2',
            'slug' => 'isolated-workspace-2',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace2->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        // Cấp 5 credit cho workspace 1
        $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 5,
            source: CreditGrantSource::PROMO
        );

        // Workspace 2 gọi sẽ bị 402 Insufficient credits vì không có credit
        $response2 = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace2->id])
            ->getJson('/api/v1/competitor/serp-analysis?keyword=iphone+16');

        $response2->assertStatus(402);

        // Workspace 1 gọi thành công 200
        $response1 = $this->actingAs($this->user)
            ->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->getJson('/api/v1/competitor/serp-analysis?keyword=iphone+16');

        $response1->assertStatus(200);
        $this->assertSame(2, $this->ledgerService->getBalance($this->workspace)->availableCredits);
    }
}
