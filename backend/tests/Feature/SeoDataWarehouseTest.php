<?php

namespace Tests\Feature;

use App\DTOs\SEO\Ga4PerformanceDTO;
use App\DTOs\SEO\GscSearchMetricDTO;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\SEO\SyncWebsiteGa4DataJob;
use App\Jobs\SEO\SyncWebsiteGscDataJob;
use App\Models\Article;
use App\Models\CreditAccount;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\GoogleAccount;
use App\Models\GscSearchPerformanceDaily;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteGoogleAnalytics;
use App\Models\WebsiteSearchConsole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\SEO\Analytics\SeoAnalyticsAggregatorService;
use App\Services\SEO\GA4\GoogleAnalyticsService;
use App\Services\SEO\GSC\GoogleSearchConsoleService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SeoDataWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Tenant $tenantA;

    protected Website $websiteA;

    protected GoogleAccount $googleAccountA;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Tenant $tenantB;

    protected Website $websiteB;

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

        // 1. Setup Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Dental Care Corp',
            'slug' => 'dental-care-corp',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'seo-lead@dentalcare.vn',
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

        $this->googleAccountA = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'google-user-id-a',
            'email' => 'marketing@dentalcare.vn',
            'access_token' => 'mock-access-token-a',
            'refresh_token' => 'mock-refresh-token-a',
            'token_expires_at' => Carbon::now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly', 'https://www.googleapis.com/auth/analytics.readonly'],
            'status' => 'active',
        ]);

        // 2. Setup Workspace B (Attacker / Different Tenant)
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Corp',
            'slug' => 'competitor-corp',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'hacker@competitor.vn',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Competitor Workspace',
            'slug' => 'competitor-ws',
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
            'domain' => 'competitor.vn',
            'name' => 'Competitor Website',
            'url' => 'https://competitor.vn',
            'status' => 'active',
        ]);

        BelongsToWorkspace::setExecutionContext($this->workspaceA->id);
        BelongsToTenant::setExecutionContext($this->tenantA->id);
    }

    /**
     * Test Upsert idempotency for GscSearchPerformanceDaily: duplicate records are updated, not duplicated.
     */
    public function test_gsc_performance_daily_upsert_idempotency(): void
    {
        $date = '2026-11-01';
        $query = 'niềng răng trong suốt';
        $url = 'https://dentalcare.vn/nieng-rang-trong-suot';
        $pageHash = GscSearchPerformanceDaily::generatePageHash($url);
        $queryHash = GscSearchPerformanceDaily::generateQueryHash($query);

        // First insert
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $date,
            'query' => $query,
            'page_url' => $url,
            'page_hash' => $pageHash,
            'query_hash' => $queryHash,
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1000,
            'position' => 3.5,
        ]);

        $this->assertDatabaseCount('gsc_search_performance_daily', 1);

        // Upsert updated stats
        GscSearchPerformanceDaily::upsert(
            [
                [
                    'workspace_id' => $this->workspaceA->id,
                    'website_id' => $this->websiteA->id,
                    'date' => $date,
                    'query' => $query,
                    'page_url' => $url,
                    'page_hash' => $pageHash,
                    'query_hash' => $queryHash,
                    'country' => 'vnm',
                    'device' => 'DESKTOP',
                    'clicks' => 25,
                    'impressions' => 200,
                    'ctr' => 0.1250,
                    'position' => 2.8,
                ],
            ],
            ['website_id', 'date', 'page_hash', 'query_hash', 'country', 'device'],
            ['clicks', 'impressions', 'ctr', 'position', 'query', 'page_url']
        );

        // Count must still be exactly 1
        $this->assertDatabaseCount('gsc_search_performance_daily', 1);

        $row = GscSearchPerformanceDaily::first();
        $this->assertEquals(25, $row->clicks);
        $this->assertEquals(200, $row->impressions);
        $this->assertEquals(2.8, $row->position);
    }

    /**
     * Test Upsert idempotency for Ga4PagePerformanceDaily.
     */
    public function test_ga4_performance_daily_upsert_idempotency(): void
    {
        $date = '2026-11-01';
        $landingPage = '/nieng-rang-trong-suot';
        $pageHash = Ga4PagePerformanceDaily::generateHash($landingPage);

        Ga4PagePerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $date,
            'landing_page' => $landingPage,
            'page_hash' => $pageHash,
            'sessions' => 50,
            'active_users' => 45,
            'engagement_rate' => 0.6500,
            'conversions' => 3,
            'avg_session_duration' => 120.5,
        ]);

        $this->assertDatabaseCount('ga4_page_performance_daily', 1);

        // Re-upsert with updated numbers
        Ga4PagePerformanceDaily::upsert(
            [
                [
                    'workspace_id' => $this->workspaceA->id,
                    'website_id' => $this->websiteA->id,
                    'article_id' => null,
                    'date' => $date,
                    'landing_page' => $landingPage,
                    'page_hash' => $pageHash,
                    'sessions' => 80,
                    'active_users' => 70,
                    'engagement_rate' => 0.7200,
                    'conversions' => 5,
                    'avg_session_duration' => 145.0,
                ],
            ],
            ['website_id', 'date', 'page_hash'],
            ['sessions', 'active_users', 'engagement_rate', 'conversions', 'avg_session_duration']
        );

        $this->assertDatabaseCount('ga4_page_performance_daily', 1);
        $row = Ga4PagePerformanceDaily::first();
        $this->assertEquals(80, $row->sessions);
        $this->assertEquals(5, $row->conversions);
    }

    /**
     * Test SyncWebsiteGscDataJob execution and backfill without credit consumption.
     */
    public function test_sync_website_gsc_data_job_executes_successfully(): void
    {
        $creditAccount = CreditAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'available_credits' => 500,
            'reserved_credits' => 0,
            'cycle_granted_credits' => 500,
            'cycle_used_credits' => 0,
        ]);

        $wGsc = WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://dentalcare.vn/',
            'property_type' => 'url_prefix',
            'is_verified' => true,
            'sync_status' => 'idle',
        ]);

        $mockGsc = Mockery::mock(GoogleSearchConsoleService::class);
        $mockGsc->shouldReceive('fetchAllSearchAnalytics')
            ->once()
            ->andReturn([
                new GscSearchMetricDTO(
                    date: '2026-11-02',
                    query: 'trồng răng implant',
                    pageUrl: 'https://dentalcare.vn/trong-rang-implant',
                    clicks: 15,
                    impressions: 300,
                    ctr: 0.05,
                    position: 4.2,
                    country: 'vnm',
                    device: 'DESKTOP'
                ),
                new GscSearchMetricDTO(
                    date: '2026-11-03',
                    query: 'giá niềng răng',
                    pageUrl: 'https://dentalcare.vn/gia-nieng-rang',
                    clicks: 22,
                    impressions: 450,
                    ctr: 0.0488,
                    position: 3.1,
                    country: 'vnm',
                    device: 'MOBILE'
                ),
            ]);

        $this->app->instance(GoogleSearchConsoleService::class, $mockGsc);

        $job = new SyncWebsiteGscDataJob(
            workspaceId: $this->workspaceA->id,
            websiteId: $this->websiteA->id,
            startDate: '2026-11-02',
            endDate: '2026-11-03'
        );

        $job->handle();

        $wGsc->refresh();
        $this->assertEquals('idle', $wGsc->sync_status);
        $this->assertNotNull($wGsc->last_synced_at);

        $this->assertDatabaseHas('gsc_search_performance_daily', [
            'website_id' => $this->websiteA->id,
            'query' => 'trồng răng implant',
            'clicks' => 15,
        ]);

        // Zero credit consumed!
        $creditAccount->refresh();
        $this->assertEquals(500, $creditAccount->available_credits);
    }

    /**
     * Test SyncWebsiteGa4DataJob maps article_id correctly based on slug and WordPress URL.
     */
    public function test_sync_website_ga4_data_job_maps_article_correctly(): void
    {
        $wGa4 = WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/987654321',
            'property_name' => 'Dental Care GA4',
            'time_zone' => 'Asia/Ho_Chi_Minh',
            'is_verified' => true,
            'sync_status' => 'idle',
        ]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'bảng giá trồng răng implant',
            'status' => 'completed',
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword_id' => $kw->id,
            'title' => 'Bảng giá trồng răng implant mới nhất',
            'slug' => 'bang-gia-trong-rang-implant',
            'content' => 'Nội dung mẫu',
            'status' => 'published',
            'wordpress_post_url' => 'https://dentalcare.vn/bang-gia-trong-rang-implant',
        ]);

        $mockGa4 = Mockery::mock(GoogleAnalyticsService::class);
        $mockGa4->shouldReceive('fetchPagePerformance')
            ->once()
            ->andReturn([
                new Ga4PerformanceDTO(
                    date: '2026-11-03',
                    landingPage: 'https://dentalcare.vn/bang-gia-trong-rang-implant?utm_source=facebook',
                    sessions: 120,
                    activeUsers: 105,
                    totalUsers: 110,
                    screenPageViews: 230,
                    engagementRate: 0.78,
                    bounceRate: 0.22,
                    conversions: 8.0,
                    averageSessionDuration: 135.5
                ),
            ]);

        $this->app->instance(GoogleAnalyticsService::class, $mockGa4);

        $job = new SyncWebsiteGa4DataJob(
            workspaceId: $this->workspaceA->id,
            websiteId: $this->websiteA->id,
            startDate: '2026-11-03',
            endDate: '2026-11-03'
        );

        $job->handle();

        $wGa4->refresh();
        $this->assertEquals('idle', $wGa4->sync_status);

        $this->assertDatabaseHas('ga4_page_performance_daily', [
            'website_id' => $this->websiteA->id,
            'article_id' => $article->id,
            'landing_page' => '/bang-gia-trong-rang-implant',
            'sessions' => 120,
            'conversions' => 8,
        ]);
    }

    /**
     * Test SeoAnalyticsAggregatorService calculates overview & delta accurately.
     */
    public function test_seo_analytics_aggregator_calculates_overview_and_delta(): void
    {
        $aggregator = app(SeoAnalyticsAggregatorService::class);

        // Previous period (2026-10-01 to 2026-10-03)
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => '2026-10-01',
            'query' => 'nha khoa uy tin',
            'page_url' => 'https://dentalcare.vn/',
            'clicks' => 50,
            'impressions' => 1000,
            'ctr' => 0.0500,
            'position' => 5.0,
        ]);

        // Current period (2026-10-04 to 2026-10-06)
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => '2026-10-04',
            'query' => 'nha khoa uy tin',
            'page_url' => 'https://dentalcare.vn/',
            'clicks' => 100,
            'impressions' => 1500,
            'ctr' => 0.0667,
            'position' => 3.5,
        ]);

        $res = $aggregator->getPerformanceOverview(
            workspaceId: $this->workspaceA->id,
            websiteId: $this->websiteA->id,
            dateFrom: '2026-10-04',
            dateTo: '2026-10-06',
            compare: true
        );

        $this->assertArrayHasKey('summary', $res);
        $this->assertArrayHasKey('timeseries', $res);

        // Clicks grew from 50 to 100 => +100%
        $this->assertEquals(100, $res['summary']['clicks']['current']);
        $this->assertEquals(50, $res['summary']['clicks']['previous']);
        $this->assertEquals(100.0, $res['summary']['clicks']['delta_percent']);

        // Position changed from 5.0 to 3.5 => delta -1.5 (improved rank)
        $this->assertEquals(3.5, $res['summary']['position']['current']);
        $this->assertEquals(5.0, $res['summary']['position']['previous']);
        $this->assertEquals(-1.5, $res['summary']['position']['delta_value']);
    }

    /**
     * Test RESTful API endpoints and Strict Multi-Tenant Isolation.
     */
    public function test_api_performance_endpoints_and_tenant_isolation(): void
    {
        // Populate data for Workspace A
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => '2026-11-05',
            'query' => 'tẩy trắng răng giá bao nhiêu',
            'page_url' => 'https://dentalcare.vn/tay-trang-rang',
            'clicks' => 45,
            'impressions' => 900,
            'ctr' => 0.05,
            'position' => 2.5,
        ]);

        // Populate data for Workspace B
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'date' => '2026-11-05',
            'query' => 'bọc răng sứ titan',
            'page_url' => 'https://competitor.vn/boc-rang-su',
            'clicks' => 200,
            'impressions' => 4000,
            'ctr' => 0.05,
            'position' => 1.2,
        ]);

        Sanctum::actingAs($this->userA, ['*']);

        // 1. GET /api/v1/analytics/seo/performance
        $response = $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->getJson('/api/v1/analytics/seo/performance?date_from=2026-11-01&date_to=2026-11-06');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.clicks.current', 45); // Must not include Workspace B's 200 clicks

        // 2. GET /api/v1/analytics/seo/top-queries
        $queryResp = $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->getJson('/api/v1/analytics/seo/top-queries?date_from=2026-11-01&date_to=2026-11-06');

        $queryResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['query' => 'tẩy trắng răng giá bao nhiêu'])
            ->assertJsonMissing(['query' => 'bọc răng sứ titan']);

        // 3. IDOR Attack: User A tries to query Workspace B's website
        $idorResp = $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->getJson("/api/v1/analytics/seo/performance?website_id={$this->websiteB->id}");

        $idorResp->assertNotFound();

        // 4. POST /api/v1/analytics/seo/sync-now triggers queue jobs
        Queue::fake();

        $syncResp = $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson('/api/v1/analytics/seo/sync-now', [
                'website_id' => $this->websiteA->id,
                'days' => 7,
            ]);

        $syncResp->assertOk()->assertJsonPath('success', true);

        Queue::assertPushed(SyncWebsiteGscDataJob::class);
        Queue::assertPushed(SyncWebsiteGa4DataJob::class);
    }
}
