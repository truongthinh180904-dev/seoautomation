<?php

namespace Tests\Feature;

use App\DTOs\SEO\GscSearchMetricDTO;
use App\Enums\CreditGrantSource;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\GoogleAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSearchConsole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\SEO\GSC\GoogleSearchConsoleService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleSearchConsoleTest extends TestCase
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

        config([
            'services.google.client_id' => 'test-google-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-google-client-secret-xyz',
            'services.google.redirect_uri' => 'http://localhost:8000/api/v1/integrations/google/callback',
        ]);

        // 1. Setup Tenant & Workspace A
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
            'name' => 'Nha Khoa Smile',
            'domain' => 'nhakhoasmile.vn',
            'url' => 'https://nhakhoasmile.vn',
        ]);

        // Grant initial credits
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 500,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial credit grant'
        );

        // Active Google Account for Workspace A
        $this->googleAccountA = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'google_sub_1234567890',
            'email' => 'admin@nhakhoasmile.vn',
            'access_token' => 'mock_access_token_abc',
            'refresh_token' => 'mock_refresh_token_def',
            'token_expires_at' => Carbon::now()->addHour(),
            'scopes' => [
                'openid',
                'https://www.googleapis.com/auth/webmasters.readonly',
            ],
            'status' => 'active',
        ]);

        // 2. Setup Tenant & Workspace B for Multi-Tenant IDOR verification
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Clinic',
            'slug' => 'competitor-clinic',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'competitor@clinic.vn',
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
            'name' => 'Nha Khoa Đô Thị',
            'domain' => 'nhakhoadothi.vn',
            'url' => 'https://nhakhoadothi.vn',
        ]);
    }

    public function test_get_properties_returns_sites_list_and_marks_connected(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
                'siteEntry' => [
                    [
                        'siteUrl' => 'https://nhakhoasmile.vn/',
                        'permissionLevel' => 'siteOwner',
                    ],
                    [
                        'siteUrl' => 'sc-domain:nhakhoasmile.vn',
                        'permissionLevel' => 'siteFullUser',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->getJson("/api/v1/websites/{$this->websiteA->id}/gsc/properties");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'properties')
            ->assertJsonPath('properties.0.site_url', 'https://nhakhoasmile.vn/')
            ->assertJsonPath('properties.0.property_type', 'url_prefix')
            ->assertJsonPath('properties.0.is_connected', false)
            ->assertJsonPath('properties.1.site_url', 'sc-domain:nhakhoasmile.vn')
            ->assertJsonPath('properties.1.property_type', 'domain')
            ->assertJsonPath('properties.1.is_connected', false);
    }

    public function test_get_properties_returns_422_when_no_google_account_connected(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->getJson("/api/v1/websites/{$this->websiteB->id}/gsc/properties");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('needs_google_connect', true);
    }

    public function test_connect_gsc_property_creates_record_and_sets_verified(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'siteUrl' => 'https://nhakhoasmile.vn/',
                'permissionLevel' => 'siteOwner',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/connect", [
            'site_url' => 'https://nhakhoasmile.vn/',
            'property_type' => 'url_prefix',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.site_url', 'https://nhakhoasmile.vn/')
            ->assertJsonPath('data.property_type', 'url_prefix')
            ->assertJsonPath('data.is_verified', true);

        $this->assertDatabaseHas('website_search_consoles', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://nhakhoasmile.vn/',
            'property_type' => 'url_prefix',
            'is_verified' => 1,
            'sync_status' => 'idle',
        ]);
    }

    public function test_connect_gsc_is_idempotent_and_updates_existing_record(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'siteUrl' => 'sc-domain:nhakhoasmile.vn',
                'permissionLevel' => 'siteOwner',
            ], 200),
        ]);

        // First connection
        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://nhakhoasmile.vn/',
            'property_type' => 'url_prefix',
            'permission_level' => 'siteOwner',
            'is_verified' => true,
        ]);

        // Reconnect with domain property
        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/connect", [
            'site_url' => 'sc-domain:nhakhoasmile.vn',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.site_url', 'sc-domain:nhakhoasmile.vn')
            ->assertJsonPath('data.property_type', 'domain');

        // Verify only 1 record exists for websiteA
        $this->assertEquals(1, WebsiteSearchConsole::where('website_id', $this->websiteA->id)->count());
        $this->assertEquals('sc-domain:nhakhoasmile.vn', WebsiteSearchConsole::where('website_id', $this->websiteA->id)->first()->site_url);
    }

    public function test_connect_gsc_fails_when_property_not_accessible(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'User does not have sufficient permission for site.',
                ],
            ], 403),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/connect", [
            'site_url' => 'https://unauthorized-domain.com/',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('website_search_consoles', [
            'website_id' => $this->websiteA->id,
            'site_url' => 'https://unauthorized-domain.com/',
        ]);
    }

    public function test_auto_refreshes_token_when_expiring_soon(): void
    {
        Sanctum::actingAs($this->userA);

        // Set token as expired
        $this->googleAccountA->update([
            'token_expires_at' => Carbon::now()->subMinutes(10),
            'access_token' => 'stale_expired_token',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed_live_access_token_888',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites' => function ($request) {
                if ($request->header('Authorization')[0] === 'Bearer refreshed_live_access_token_888') {
                    return Http::response([
                        'siteEntry' => [
                            ['siteUrl' => 'https://nhakhoasmile.vn/', 'permissionLevel' => 'siteOwner'],
                        ],
                    ], 200);
                }

                return Http::response(['error' => 'Invalid Credentials'], 401);
            },
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->getJson("/api/v1/websites/{$this->websiteA->id}/gsc/properties");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Account token in DB should now be refreshed
        $this->googleAccountA->refresh();
        $this->assertEquals('refreshed_live_access_token_888', $this->googleAccountA->access_token);
        $this->assertTrue($this->googleAccountA->token_expires_at->isFuture());
    }

    public function test_test_connection_verifies_property_ownership(): void
    {
        Sanctum::actingAs($this->userA);

        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://nhakhoasmile.vn/',
            'property_type' => 'url_prefix',
            'permission_level' => 'siteOwner',
            'is_verified' => true,
        ]);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'siteUrl' => 'https://nhakhoasmile.vn/',
                'permissionLevel' => 'siteOwner',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/test");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.site_url', 'https://nhakhoasmile.vn/');
    }

    public function test_disconnect_gsc_removes_configuration(): void
    {
        Sanctum::actingAs($this->userA);

        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://nhakhoasmile.vn/',
            'property_type' => 'url_prefix',
            'is_verified' => true,
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/disconnect");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('website_search_consoles', [
            'website_id' => $this->websiteA->id,
        ]);
    }

    public function test_query_search_analytics_returns_structured_metrics_dtos(): void
    {
        Sanctum::actingAs($this->userA);

        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://nhakhoasmile.vn/',
            'property_type' => 'url_prefix',
            'is_verified' => true,
        ]);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    [
                        'keys' => ['bọc răng sứ thẩm mỹ', 'https://nhakhoasmile.vn/boc-rang-su', '2026-11-01', 'DESKTOP', 'vnm'],
                        'clicks' => 45,
                        'impressions' => 620,
                        'ctr' => 0.0725,
                        'position' => 3.4,
                    ],
                    [
                        'keys' => ['trồng răng implant giá bao nhiêu', 'https://nhakhoasmile.vn/implant', '2026-11-01', 'MOBILE', 'vnm'],
                        'clicks' => 30,
                        'impressions' => 410,
                        'ctr' => 0.0731,
                        'position' => 2.1,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/query", [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-07',
            'dimensions' => ['query', 'page', 'date', 'device', 'country'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('data.0.query', 'bọc răng sứ thẩm mỹ')
            ->assertJsonPath('data.0.page_url', 'https://nhakhoasmile.vn/boc-rang-su')
            ->assertJsonPath('data.0.clicks', 45)
            ->assertJsonPath('data.0.impressions', 620)
            ->assertJsonPath('data.0.ctr', 0.0725)
            ->assertJsonPath('data.0.position', 3.4)
            ->assertJsonPath('data.0.device', 'DESKTOP')
            ->assertJsonPath('data.0.country', 'vnm');
    }

    public function test_fetch_all_search_analytics_paginates_multiple_batches(): void
    {
        $gscService = app(GoogleSearchConsoleService::class);

        // Create dummy batch 1 (25000 rows simulated as 2 items for test) and batch 2 (1 item)
        $batch1 = [
            ['keys' => ['query 1', 'https://example.com/p1', '2026-11-01', 'DESKTOP', 'vnm'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 1.0],
            ['keys' => ['query 2', 'https://example.com/p2', '2026-11-01', 'DESKTOP', 'vnm'], 'clicks' => 20, 'impressions' => 200, 'ctr' => 0.1, 'position' => 2.0],
        ];

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => $batch1,
            ], 200),
        ]);

        $results = $gscService->fetchAllSearchAnalytics(
            account: $this->googleAccountA,
            siteUrl: 'https://nhakhoasmile.vn/',
            startDate: '2026-11-01',
            endDate: '2026-11-02',
            maxRows: 25000
        );

        $this->assertCount(2, $results);
        $this->assertInstanceOf(GscSearchMetricDTO::class, $results[0]);
        $this->assertEquals('query 1', $results[0]->query);
        $this->assertEquals(10, $results[0]->clicks);
    }

    public function test_multi_tenant_isolation_prevents_unauthorized_access(): void
    {
        Sanctum::actingAs($this->userB); // User B from Workspace B

        // User B tries to view GSC properties of Website A
        $response1 = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->getJson("/api/v1/websites/{$this->websiteA->id}/gsc/properties");

        $response1->assertStatus(404);

        // User B tries to connect GSC to Website A
        $response2 = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/connect", [
            'site_url' => 'https://hacked-domain.com/',
        ]);

        $response2->assertStatus(404);

        // User B tries to disconnect GSC of Website A
        $response3 = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/disconnect");

        $response3->assertStatus(404);
    }

    public function test_zero_credit_consumption_during_all_gsc_operations(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites*' => Http::response([
                'siteUrl' => 'https://nhakhoasmile.vn/',
                'permissionLevel' => 'siteOwner',
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    [
                        'keys' => ['tẩy trắng răng', 'https://nhakhoasmile.vn/tay-trang', '2026-11-01', 'DESKTOP', 'vnm'],
                        'clicks' => 15,
                        'impressions' => 120,
                        'ctr' => 0.125,
                        'position' => 1.8,
                    ],
                ],
            ], 200),
        ]);

        $initialCredits = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertEquals(500, $initialCredits);

        // 1. Connect
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/connect", [
                'site_url' => 'https://nhakhoasmile.vn/',
            ])
            ->assertStatus(200);

        // 2. Test
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/test")
            ->assertStatus(200);

        // 3. Query
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson("/api/v1/websites/{$this->websiteA->id}/gsc/query", [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-02',
            ])
            ->assertStatus(200);

        // Verify credit balance is 100% untouched
        $finalCredits = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertEquals(500, $finalCredits);
    }
}
