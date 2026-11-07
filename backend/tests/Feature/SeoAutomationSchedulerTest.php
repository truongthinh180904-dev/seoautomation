<?php

namespace Tests\Feature;

use App\Console\Kernel;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\SEO\SyncWebsiteGa4DataJob;
use App\Jobs\SEO\SyncWebsiteGscDataJob;
use App\Jobs\SEO\TrackKeywordRankingJob;
use App\Models\GoogleAccount;
use App\Models\Notification;
use App\Models\RankTrackedKeyword;
use App\Models\SeoIntelligenceInsight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAutomationRule;
use App\Models\WebsiteGoogleAnalytics;
use App\Models\WebsiteSearchConsole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\SEO\Intelligence\SeoIntelligenceService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeoAutomationSchedulerTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);

        // 1. Setup Tenant & Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Dental Care Corp',
            'slug' => 'dental-care-corp',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'admin@dentalcare.vn',
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
            'google_id' => 'google-user-12345',
            'email' => 'seo@dentalcare.vn',
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
            'status' => 'active',
        ]);

        // 2. Setup Workspace B (Tenant isolation / Attacker)
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

    /**
     * Test WebsiteAutomationRule creation and default properties.
     */
    public function test_website_automation_rule_model_creation_and_defaults(): void
    {
        $rule = WebsiteAutomationRule::getOrCreateForWebsite($this->workspaceA->id, $this->websiteA->id);

        $this->assertInstanceOf(WebsiteAutomationRule::class, $rule);
        $this->assertEquals($this->workspaceA->id, $rule->workspace_id);
        $this->assertEquals($this->websiteA->id, $rule->website_id);
        $this->assertTrue($rule->sync_gsc_enabled);
        $this->assertTrue($rule->sync_ga4_enabled);
        $this->assertTrue($rule->rank_tracking_enabled);
        $this->assertTrue($rule->anomaly_scan_enabled);
        $this->assertEquals(3, $rule->alert_ranking_drop_threshold);
        $this->assertEquals(20, $rule->alert_traffic_drop_percentage);
        $this->assertTrue($rule->email_notifications_enabled);
    }

    /**
     * Test Kernel registers all 4 daily SEO automation commands with distributed lock withoutOverlapping.
     */
    public function test_scheduler_kernel_registers_all_four_daily_seo_jobs_with_locks(): void
    {
        $schedule = new Schedule;
        $kernel = app(Kernel::class);
        $kernel->defineSchedule($schedule);

        $events = collect($schedule->events());

        // 1. Verify GSC sync at 02:00
        $gscEvent = $events->first(function (Event $event) {
            return str_contains($event->command, 'seo:sync-all') && str_contains($event->command, '--service=gsc');
        });
        $this->assertNotNull($gscEvent, 'GSC sync command must be registered in scheduler.');
        $this->assertEquals('0 2 * * *', $gscEvent->expression);
        $this->assertTrue($gscEvent->withoutOverlapping, 'Scheduler must enforce withoutOverlapping distributed lock.');

        // 2. Verify GA4 sync at 03:00
        $ga4Event = $events->first(function (Event $event) {
            return str_contains($event->command, 'seo:sync-all') && str_contains($event->command, '--service=ga4');
        });
        $this->assertNotNull($ga4Event, 'GA4 sync command must be registered in scheduler.');
        $this->assertEquals('0 3 * * *', $ga4Event->expression);
        $this->assertTrue($ga4Event->withoutOverlapping);

        // 3. Verify Rank Tracking at 04:00
        $rankEvent = $events->first(function (Event $event) {
            return str_contains($event->command, 'seo:track-rankings');
        });
        $this->assertNotNull($rankEvent, 'Rank tracking command must be registered in scheduler.');
        $this->assertEquals('0 4 * * *', $rankEvent->expression);
        $this->assertTrue($rankEvent->withoutOverlapping);

        // 4. Verify Anomaly Scan at 05:00
        $anomalyEvent = $events->first(function (Event $event) {
            return str_contains($event->command, 'seo:detect-anomalies');
        });
        $this->assertNotNull($anomalyEvent, 'Anomaly scan command must be registered in scheduler.');
        $this->assertEquals('0 5 * * *', $anomalyEvent->expression);
        $this->assertTrue($anomalyEvent->withoutOverlapping);
    }

    /**
     * Test seo:sync-all command dispatches GSC and GA4 sync jobs when enabled.
     */
    public function test_seo_sync_all_command_dispatches_gsc_and_ga4_jobs_when_enabled(): void
    {
        Queue::fake();

        // Connect verified GSC
        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://dentalcare.vn',
            'is_verified' => true,
            'sync_status' => 'idle',
        ]);

        // Connect verified GA4
        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/99887766',
            'property_name' => 'Dental Care Analytics',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_verified' => true,
            'sync_status' => 'idle',
        ]);

        // Run sync-all command
        $exitCode = $this->artisan('seo:sync-all', ['--website' => $this->websiteA->id])->run();
        $this->assertEquals(0, $exitCode);

        // Assert jobs were dispatched
        Queue::assertPushed(SyncWebsiteGscDataJob::class, function ($job) {
            return $job->websiteId === $this->websiteA->id && $job->workspaceId === $this->workspaceA->id;
        });

        Queue::assertPushed(SyncWebsiteGa4DataJob::class, function ($job) {
            return $job->websiteId === $this->websiteA->id && $job->workspaceId === $this->workspaceA->id;
        });
    }

    /**
     * Test seo:sync-all command respects website automation rule toggles.
     */
    public function test_seo_sync_all_command_skips_when_automation_disabled(): void
    {
        Queue::fake();

        // Connect verified GSC and GA4
        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://dentalcare.vn',
            'is_verified' => true,
            'sync_status' => 'idle',
        ]);

        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/99887766',
            'property_name' => 'Dental Care Analytics',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_verified' => true,
            'sync_status' => 'idle',
        ]);

        // Disable GSC and GA4 automation in rules
        $rule = WebsiteAutomationRule::getOrCreateForWebsite($this->workspaceA->id, $this->websiteA->id);
        $rule->update([
            'sync_gsc_enabled' => false,
            'sync_ga4_enabled' => false,
        ]);

        $exitCode = $this->artisan('seo:sync-all', ['--website' => $this->websiteA->id])->run();
        $this->assertEquals(0, $exitCode);

        // Verify neither job was dispatched
        Queue::assertNotPushed(SyncWebsiteGscDataJob::class);
        Queue::assertNotPushed(SyncWebsiteGa4DataJob::class);
    }

    /**
     * Test seo:track-rankings command dispatches jobs for active tracked keywords.
     */
    public function test_seo_track_rankings_command_dispatches_active_keywords(): void
    {
        Queue::fake();

        $kwActive = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'trồng răng implant giá bao nhiêu',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => true,
        ]);

        $kwInactive = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'niềng răng thẩm mỹ',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => false,
        ]);

        $exitCode = $this->artisan('seo:track-rankings', ['--website' => $this->websiteA->id])->run();
        $this->assertEquals(0, $exitCode);

        // Dispatched only for active keyword
        Queue::assertPushed(TrackKeywordRankingJob::class, function ($job) use ($kwActive) {
            return $job->rankTrackedKeywordId === $kwActive->id;
        });

        Queue::assertNotPushed(TrackKeywordRankingJob::class, function ($job) use ($kwInactive) {
            return $job->rankTrackedKeywordId === $kwInactive->id;
        });
    }

    /**
     * Test seo:detect-anomalies command scans and triggers alert notification when threshold breached.
     */
    public function test_seo_detect_anomalies_command_scans_and_triggers_event_and_notification(): void
    {
        // 1. Create critical insight simulating ranking drop beyond threshold
        $insight = SeoIntelligenceInsight::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'type' => 'ranking_drop',
            'severity' => 'critical',
            'title' => 'Từ khóa "trồng răng implant" tụt 6 bậc',
            'description' => 'Vị trí giảm mạnh từ #4 xuống #10 trong 7 ngày qua.',
            'metrics_snapshot' => [
                'keyword' => 'trồng răng implant',
                'drop_amount' => 6,
                'previous_position' => 4,
                'current_position' => 10,
            ],
            'suggested_action' => 'review_competitors_and_refresh',
            'status' => 'detected',
        ]);

        // Mock intelligence service to return this critical insight
        $mockIntelligence = $this->createMock(SeoIntelligenceService::class);
        $mockIntelligence->method('analyzeWebsite')
            ->willReturn(new EloquentCollection([$insight]));

        $this->app->instance(SeoIntelligenceService::class, $mockIntelligence);

        $exitCode = $this->artisan('seo:detect-anomalies', ['--website' => $this->websiteA->id])->run();
        $this->assertEquals(0, $exitCode);

        // Assert notification created in database
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'subject' => '[Cảnh báo SEO] Từ khóa "trồng răng implant" tụt 6 bậc',
            'status' => 'sent',
        ]);
    }

    /**
     * Test getting and updating website automation rules via API endpoints.
     */
    public function test_get_and_update_automation_rules_api_endpoints(): void
    {
        Sanctum::actingAs($this->userA);

        // 1. GET /api/v1/websites/{id}/automation-rules
        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->getJson("/api/v1/websites/{$this->websiteA->id}/automation-rules");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sync_gsc_enabled', true)
            ->assertJsonPath('data.alert_ranking_drop_threshold', 3);

        // 2. PUT /api/v1/websites/{id}/automation-rules
        $updateResponse = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->putJson("/api/v1/websites/{$this->websiteA->id}/automation-rules", [
                'sync_gsc_enabled' => false,
                'alert_ranking_drop_threshold' => 5,
                'alert_traffic_drop_percentage' => 30,
                'email_notifications_enabled' => false,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sync_gsc_enabled', false)
            ->assertJsonPath('data.alert_ranking_drop_threshold', 5)
            ->assertJsonPath('data.alert_traffic_drop_percentage', 30)
            ->assertJsonPath('data.email_notifications_enabled', false);

        $this->assertDatabaseHas('website_automation_rules', [
            'website_id' => $this->websiteA->id,
            'sync_gsc_enabled' => 0,
            'alert_ranking_drop_threshold' => 5,
            'alert_traffic_drop_percentage' => 30,
            'email_notifications_enabled' => 0,
        ]);
    }

    /**
     * Test tenant isolation prevents IDOR on automation rules API.
     */
    public function test_automation_rules_api_tenant_isolation_prevents_idor(): void
    {
        // User B from workspace B attempts to access website A's automation rules
        Sanctum::actingAs($this->userB);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceB->id])
            ->getJson("/api/v1/websites/{$this->websiteA->id}/automation-rules");

        $response->assertStatus(403);

        $putResponse = $this->withHeaders(['X-Workspace-Id' => $this->workspaceB->id])
            ->putJson("/api/v1/websites/{$this->websiteA->id}/automation-rules", [
                'sync_gsc_enabled' => false,
            ]);

        $putResponse->assertStatus(403);
    }

    /**
     * Test settings/automation-schedules overview API returns valid schedule and rule info.
     */
    public function test_settings_automation_schedules_overview_api(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders(['X-Workspace-Id' => $this->workspaceA->id])
            ->getJson('/api/v1/settings/automation-schedules');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'workspace_id',
                    'schedules',
                    'distributed_locking' => [
                        'driver',
                        'lock_timeout_minutes',
                        'prevent_overlapping',
                    ],
                    'websites',
                ],
            ]);
    }
}
