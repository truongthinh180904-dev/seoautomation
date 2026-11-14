<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\CreditAccount;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\GscSearchPerformanceDaily;
use App\Models\RankTrackedKeyword;
use App\Models\SeoIntelligenceInsight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeoIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Tenant $tenantA;

    protected Website $websiteA;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Tenant $tenantB;

    protected Website $websiteB;

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
            'balance' => 500,
            'total_credited' => 500,
            'total_debited' => 0,
        ]);

        // 2. Setup Workspace B (Attacker / Unrelated Tenant)
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
            'name' => 'Auto Spa Vietnam',
            'url' => 'https://autospa.vn',
            'status' => 'active',
        ]);
    }

    public function test_can_detect_ranking_drop_anomaly(): void
    {
        Sanctum::actingAs($this->userA);

        // Keyword dropped from position 4 to 12 (drop 8 positions)
        RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'cấy ghép răng implant',
            'target_url' => 'https://dentalcare.vn/implant',
            'country' => 'vn',
            'device' => 'desktop',
            'tracking_frequency' => 'daily',
            'current_position' => 12,
            'previous_position' => 4,
            'best_position' => 3,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'ranking_drop',
            'severity' => 'critical',
            'suggested_action' => 'investigate_drop',
        ]);
    }

    public function test_can_detect_high_impressions_low_ctr_opportunity(): void
    {
        Sanctum::actingAs($this->userA);

        // Query with 2,500 impressions but only 15 clicks (CTR = 0.6% < 2%)
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => Carbon::today()->subDays(5)->toDateString(),
            'query' => 'niềng răng invisalign giá bao nhiêu',
            'page_url' => 'https://dentalcare.vn/nieng-rang-invisalign',
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 15,
            'impressions' => 2500,
            'ctr' => 0.006,
            'position' => 4.2,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'high_imp_low_ctr',
            'severity' => 'warning',
            'suggested_action' => 'optimize_title_meta',
        ]);
    }

    public function test_can_detect_traffic_decay(): void
    {
        Sanctum::actingAs($this->userA);

        $url = 'https://dentalcare.vn/kinh-nghiem-boc-rang-su';
        $today = Carbon::today();

        // Week 1 (25 days ago): 80 clicks
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $today->copy()->subDays(25)->toDateString(),
            'query' => 'kinh nghiệm bọc răng sứ',
            'page_url' => $url,
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 80,
            'impressions' => 1000,
            'ctr' => 0.08,
            'position' => 3.0,
        ]);

        // Week 2 (18 days ago): 50 clicks
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $today->copy()->subDays(18)->toDateString(),
            'query' => 'kinh nghiệm bọc răng sứ',
            'page_url' => $url,
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 50,
            'impressions' => 700,
            'ctr' => 0.071,
            'position' => 4.5,
        ]);

        // Week 3 (10 days ago): 30 clicks
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $today->copy()->subDays(10)->toDateString(),
            'query' => 'kinh nghiệm bọc răng sứ',
            'page_url' => $url,
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 30,
            'impressions' => 500,
            'ctr' => 0.06,
            'position' => 6.0,
        ]);

        // Week 4 (3 days ago): 15 clicks (Decay > 80%)
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $today->copy()->subDays(3)->toDateString(),
            'query' => 'kinh nghiệm bọc răng sứ',
            'page_url' => $url,
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 15,
            'impressions' => 300,
            'ctr' => 0.05,
            'position' => 8.2,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'traffic_decay',
            'suggested_action' => 'refresh_content',
        ]);
    }

    public function test_can_detect_striking_distance_opportunity(): void
    {
        Sanctum::actingAs($this->userA);

        // Query ranked 5.5 (Page 1 outside Top 3) with 600 impressions
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => Carbon::today()->subDays(4)->toDateString(),
            'query' => 'nhổ răng khôn không đau',
            'page_url' => 'https://dentalcare.vn/nho-rang-khon',
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 25,
            'impressions' => 600,
            'ctr' => 0.041,
            'position' => 5.5,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'striking_distance',
            'severity' => 'info',
            'suggested_action' => 'add_internal_links',
        ]);
    }

    public function test_can_detect_keyword_cannibalization(): void
    {
        Sanctum::actingAs($this->userA);

        // 2 distinct URLs competing for same query in Top 20
        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => Carbon::today()->subDays(6)->toDateString(),
            'query' => 'chi phí trồng răng implant',
            'page_url' => 'https://dentalcare.vn/bang-gia-implant',
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 10,
            'impressions' => 150,
            'ctr' => 0.066,
            'position' => 6.2,
        ]);

        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => Carbon::today()->subDays(6)->toDateString(),
            'query' => 'chi phí trồng răng implant',
            'page_url' => 'https://dentalcare.vn/quy-trinh-implant',
            'country' => 'vnm',
            'device' => 'DESKTOP',
            'clicks' => 8,
            'impressions' => 120,
            'ctr' => 0.066,
            'position' => 11.5,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'cannibalization',
            'severity' => 'warning',
            'suggested_action' => 'canonicalize_or_merge',
        ]);
    }

    public function test_can_detect_high_traffic_low_engagement(): void
    {
        Sanctum::actingAs($this->userA);

        // Landing page with 300 sessions but only 18% engagement rate (< 30%)
        Ga4PagePerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => Carbon::today()->subDays(5)->toDateString(),
            'landing_page' => 'https://dentalcare.vn/cham-soc-rang-mieng-tai-nha',
            'sessions' => 300,
            'active_users' => 280,
            'engagement_rate' => 0.18,
            'conversions' => 1,
            'avg_session_duration' => 22.5,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'high_traffic_low_engagement',
            'severity' => 'warning',
            'suggested_action' => 'improve_content_engagement',
        ]);
    }

    public function test_does_not_create_duplicate_insights_on_subsequent_scans(): void
    {
        Sanctum::actingAs($this->userA);

        RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'tẩy trắng răng tại nhà',
            'target_url' => 'https://dentalcare.vn/tay-trang-rang',
            'country' => 'vn',
            'device' => 'desktop',
            'tracking_frequency' => 'daily',
            'current_position' => 15,
            'previous_position' => 6,
            'is_active' => true,
        ]);

        // Scan 1
        $res1 = $this->postJson('/api/v1/seo-insights/scan', ['website_id' => $this->websiteA->id]);
        $res1->assertStatus(200);
        $countAfterFirst = SeoIntelligenceInsight::where('website_id', $this->websiteA->id)->count();
        $this->assertGreaterThan(0, $countAfterFirst);

        // Scan 2 (Immediate rerun)
        $res2 = $this->postJson('/api/v1/seo-insights/scan', ['website_id' => $this->websiteA->id]);
        $res2->assertStatus(200);
        $countAfterSecond = SeoIntelligenceInsight::where('website_id', $this->websiteA->id)->count();

        // Count should not duplicate
        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_multi_tenant_isolation_prevents_unauthorized_access(): void
    {
        // 1. Create an insight in Workspace A
        $insight = SeoIntelligenceInsight::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'ranking_drop',
            'severity' => 'critical',
            'opportunity_score' => 88,
            'title' => 'Secret Dental Keyword Dropped',
            'description' => 'Confidential business data.',
            'metrics_snapshot' => ['target_key' => 'secret_1'],
            'suggested_action' => 'investigate_drop',
            'status' => 'detected',
        ]);

        // 2. Act as User B (Attacker in Workspace B)
        Sanctum::actingAs($this->userB);

        // Attempt to view Workspace A's insight
        $responseShow = $this->getJson("/api/v1/seo-insights/{$insight->id}");
        $this->assertTrue(in_array($responseShow->status(), [403, 404], true));

        // Attempt to dismiss Workspace A's insight
        $responseDismiss = $this->postJson("/api/v1/seo-insights/{$insight->id}/dismiss");
        $this->assertTrue(in_array($responseDismiss->status(), [403, 404], true));

        // Attempt to scan Workspace A's website
        $responseScan = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);
        $this->assertTrue(in_array($responseScan->status(), [403, 404], true));
    }

    public function test_can_dismiss_and_take_action_on_insight(): void
    {
        Sanctum::actingAs($this->userA);

        $insight = SeoIntelligenceInsight::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'striking_distance',
            'severity' => 'info',
            'opportunity_score' => 90,
            'title' => 'Niềng răng hô Top 4',
            'description' => 'Cơ hội lọt Top 3.',
            'metrics_snapshot' => ['target_key' => 'nieng_rang_ho'],
            'suggested_action' => 'add_internal_links',
            'status' => 'detected',
        ]);

        // 1. Action insight to in_progress
        $actionRes = $this->postJson("/api/v1/seo-insights/{$insight->id}/action", [
            'action' => 'in_progress',
        ]);
        $actionRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'in_progress',
                ],
            ]);

        // 2. Dismiss insight
        $dismissRes = $this->postJson("/api/v1/seo-insights/{$insight->id}/dismiss");
        $dismissRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'dismissed',
                ],
            ]);

        $this->assertDatabaseHas('seo_intelligence_insights', [
            'id' => $insight->id,
            'status' => 'dismissed',
        ]);
    }

    public function test_heuristic_scan_consumes_zero_credits(): void
    {
        Sanctum::actingAs($this->userA);

        $creditAccount = CreditAccount::where('workspace_id', $this->workspaceA->id)->firstOrFail();
        $balanceBefore = $creditAccount->balance;

        RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'trám răng thẩm mỹ',
            'target_url' => 'https://dentalcare.vn/tram-rang',
            'country' => 'vn',
            'device' => 'desktop',
            'tracking_frequency' => 'daily',
            'current_position' => 14,
            'previous_position' => 7,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/seo-insights/scan', [
            'website_id' => $this->websiteA->id,
        ]);
        $response->assertStatus(200);

        $creditAccount->refresh();
        $this->assertEquals($balanceBefore, $creditAccount->balance, 'Heuristic scan must consume 0 credits.');
    }
}
