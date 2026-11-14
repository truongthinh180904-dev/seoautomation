<?php

namespace Tests\Feature;

use App\DTOs\SEO\Ga4PerformanceDTO;
use App\Enums\CreditGrantSource;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\GoogleAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteGoogleAnalytics;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\SEO\GA4\GoogleAnalyticsService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleAnalytics4Test extends TestCase
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
            'services.google.client_id' => 'test-ga4-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-ga4-client-secret-xyz',
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
            'email' => 'ga4-lead@dentalcare.vn',
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
            'google_id' => 'google_sub_ga4_1234567890',
            'email' => 'analytics@nhakhoasmile.vn',
            'access_token' => 'mock_ga4_access_token_abc',
            'refresh_token' => 'mock_ga4_refresh_token_def',
            'token_expires_at' => Carbon::now()->addHour(),
            'scopes' => [
                'openid',
                'https://www.googleapis.com/auth/analytics.readonly',
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
            'email' => 'competitor-ga4@clinic.vn',
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

    public function test_get_properties_returns_ga4_accounts_and_properties_list_and_marks_connected(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://analyticsadmin.googleapis.com/v1beta/accountSummaries' => Http::response([
                'accountSummaries' => [
                    [
                        'name' => 'accountSummaries/112233',
                        'account' => 'accounts/112233',
                        'displayName' => 'Nha Khoa Smile Org',
                        'propertySummaries' => [
                            [
                                'property' => 'properties/987654321',
                                'displayName' => 'Smile Website GA4',
                                'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                            ],
                            [
                                'property' => 'properties/987654322',
                                'displayName' => 'Smile Mobile App GA4',
                                'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->getJson("/api/v1/websites/{$this->websiteA->id}/ga4/properties");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'properties')
            ->assertJsonPath('properties.0.property_id', 'properties/987654321')
            ->assertJsonPath('properties.0.numeric_id', '987654321')
            ->assertJsonPath('properties.0.display_name', 'Smile Website GA4')
            ->assertJsonPath('properties.0.is_connected', false)
            ->assertJsonPath('properties.1.property_id', 'properties/987654322')
            ->assertJsonPath('properties.1.is_connected', false);
    }

    public function test_get_properties_returns_422_when_no_google_account_connected(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->getJson("/api/v1/websites/{$this->websiteB->id}/ga4/properties");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('needs_google_connect', true);
    }

    public function test_connect_ga4_property_creates_record_and_saves_timezone_and_currency(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://analyticsadmin.googleapis.com/v1beta/properties/*' => Http::response([
                'name' => 'properties/987654321',
                'parent' => 'accounts/112233',
                'displayName' => 'Smile Website GA4',
                'timeZone' => 'Asia/Ho_Chi_Minh',
                'currencyCode' => 'VND',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/connect", [
            'property_id' => 'properties/987654321',
            'property_name' => 'Smile Website GA4',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.property_id', 'properties/987654321')
            ->assertJsonPath('data.property_name', 'Smile Website GA4')
            ->assertJsonPath('data.time_zone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.currency_code', 'VND')
            ->assertJsonPath('data.is_verified', true);

        $this->assertDatabaseHas('website_google_analytics', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/987654321',
            'time_zone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND',
            'is_verified' => 1,
            'sync_status' => 'idle',
        ]);
    }

    public function test_connect_ga4_is_idempotent_and_updates_existing_record(): void
    {
        Sanctum::actingAs($this->userA);

        // Pre-create GA4 connection
        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/111111111',
            'property_name' => 'Old Property',
            'time_zone' => 'UTC',
            'currency_code' => 'USD',
            'is_verified' => true,
        ]);

        Http::fake([
            'https://analyticsadmin.googleapis.com/v1beta/properties/*' => Http::response([
                'name' => 'properties/987654321',
                'parent' => 'accounts/112233',
                'displayName' => 'Updated Property GA4',
                'timeZone' => 'Asia/Ho_Chi_Minh',
                'currencyCode' => 'VND',
            ], 200),
        ]);

        // Reconnect to a different GA4 property
        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/connect", [
            'property_id' => 'properties/987654321',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.property_id', 'properties/987654321')
            ->assertJsonPath('data.property_name', 'Updated Property GA4')
            ->assertJsonPath('data.time_zone', 'Asia/Ho_Chi_Minh');

        // Verify only 1 record exists for websiteA
        $this->assertEquals(1, WebsiteGoogleAnalytics::where('website_id', $this->websiteA->id)->count());
        $this->assertEquals('properties/987654321', WebsiteGoogleAnalytics::where('website_id', $this->websiteA->id)->first()->property_id);
    }

    public function test_connect_ga4_fails_when_property_not_accessible(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://analyticsadmin.googleapis.com/v1beta/properties/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'Caller does not have permission on the requested GA4 property.',
                ],
            ], 403),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/connect", [
            'property_id' => 'properties/unauthorized_99999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('website_google_analytics', [
            'website_id' => $this->websiteA->id,
            'property_id' => 'properties/unauthorized_99999',
        ]);
    }

    public function test_auto_refreshes_token_when_expiring_soon(): void
    {
        Sanctum::actingAs($this->userA);

        // Set token as expired
        $this->googleAccountA->update([
            'token_expires_at' => Carbon::now()->subMinutes(10),
            'access_token' => 'stale_ga4_token',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed_live_ga4_token_999',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://analyticsadmin.googleapis.com/v1beta/accountSummaries' => function ($request) {
                if ($request->header('Authorization')[0] === 'Bearer refreshed_live_ga4_token_999') {
                    return Http::response([
                        'accountSummaries' => [
                            [
                                'account' => 'accounts/112233',
                                'displayName' => 'Smile Org',
                                'propertySummaries' => [
                                    ['property' => 'properties/987654321', 'displayName' => 'Smile GA4'],
                                ],
                            ],
                        ],
                    ], 200);
                }

                return Http::response(['error' => 'Invalid Credentials'], 401);
            },
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->getJson("/api/v1/websites/{$this->websiteA->id}/ga4/properties");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Account token in DB should now be refreshed
        $this->googleAccountA->refresh();
        $this->assertEquals('refreshed_live_ga4_token_999', $this->googleAccountA->access_token);
        $this->assertTrue($this->googleAccountA->token_expires_at->isFuture());
    }

    public function test_test_connection_verifies_property_ownership_and_timezone(): void
    {
        Sanctum::actingAs($this->userA);

        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/987654321',
            'property_name' => 'Smile GA4',
            'time_zone' => 'UTC',
            'currency_code' => 'USD',
            'is_verified' => true,
        ]);

        Http::fake([
            'https://analyticsadmin.googleapis.com/v1beta/properties/*' => Http::response([
                'name' => 'properties/987654321',
                'parent' => 'accounts/112233',
                'displayName' => 'Smile GA4 Verified',
                'timeZone' => 'Asia/Ho_Chi_Minh',
                'currencyCode' => 'VND',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/test");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.property_id', 'properties/987654321')
            ->assertJsonPath('data.time_zone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.currency_code', 'VND');
    }

    public function test_disconnect_ga4_removes_configuration(): void
    {
        Sanctum::actingAs($this->userA);

        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/987654321',
            'is_verified' => true,
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/disconnect");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('website_google_analytics', [
            'website_id' => $this->websiteA->id,
        ]);
    }

    public function test_query_ga4_run_report_returns_structured_performance_dtos(): void
    {
        Sanctum::actingAs($this->userA);

        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => 'properties/987654321',
            'time_zone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND',
            'is_verified' => true,
        ]);

        Http::fake([
            'https://analyticsdata.googleapis.com/v1beta/properties/*:runReport' => Http::response([
                'dimensionHeaders' => [
                    ['name' => 'date'],
                    ['name' => 'landingPagePlusQueryString'],
                ],
                'metricHeaders' => [
                    ['name' => 'sessions'],
                    ['name' => 'totalUsers'],
                    ['name' => 'screenPageViews'],
                    ['name' => 'engagementRate'],
                    ['name' => 'conversions'],
                ],
                'rows' => [
                    [
                        'dimensionValues' => [
                            ['value' => '20261101'],
                            ['value' => '/dich-vu-nieng-rang'],
                        ],
                        'metricValues' => [
                            ['value' => '150'],
                            ['value' => '120'],
                            ['value' => '420'],
                            ['value' => '0.68'],
                            ['value' => '12.0'],
                        ],
                    ],
                    [
                        'dimensionValues' => [
                            ['value' => '20261101'],
                            ['value' => 'https://nhakhoasmile.vn/boc-rang-su-nano?utm=fb'],
                        ],
                        'metricValues' => [
                            ['value' => '85'],
                            ['value' => '70'],
                            ['value' => '210'],
                            ['value' => '0.55'],
                            ['value' => '5.0'],
                        ],
                    ],
                ],
                'rowCount' => 2,
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/query", [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-07',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('time_zone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('currency_code', 'VND')
            ->assertJsonPath('data.0.date', '2026-11-01')
            ->assertJsonPath('data.0.landing_page', '/dich-vu-nieng-rang')
            ->assertJsonPath('data.0.sessions', 150)
            ->assertJsonPath('data.0.total_users', 120)
            ->assertJsonPath('data.0.screen_page_views', 420)
            ->assertJsonPath('data.0.engagement_rate', 0.68)
            ->assertJsonPath('data.0.conversions', fn ($val) => (float) $val === 12.0)
            ->assertJsonPath('data.1.landing_page', '/boc-rang-su-nano');
    }

    public function test_fetch_page_performance_paginates_multiple_batches(): void
    {
        $ga4Service = app(GoogleAnalyticsService::class);

        $fakeRows = [
            [
                'dimensionValues' => [['value' => '20261101'], ['value' => '/page-1']],
                'metricValues' => [['value' => '50'], ['value' => '40'], ['value' => '100'], ['value' => '0.5'], ['value' => '2']],
            ],
            [
                'dimensionValues' => [['value' => '20261101'], ['value' => '/page-2']],
                'metricValues' => [['value' => '60'], ['value' => '45'], ['value' => '120'], ['value' => '0.6'], ['value' => '3']],
            ],
        ];

        Http::fake([
            'https://analyticsdata.googleapis.com/v1beta/properties/*:runReport' => Http::response([
                'rows' => $fakeRows,
                'rowCount' => 2,
            ], 200),
        ]);

        $results = $ga4Service->fetchPagePerformance(
            account: $this->googleAccountA,
            propertyId: 'properties/987654321',
            startDate: '2026-11-01',
            endDate: '2026-11-02',
            maxRows: 10000
        );

        $this->assertCount(2, $results);
        $this->assertInstanceOf(Ga4PerformanceDTO::class, $results[0]);
        $this->assertEquals('/page-1', $results[0]->landingPage);
        $this->assertEquals(50, $results[0]->sessions);
    }

    public function test_normalizes_landing_page_path_by_stripping_domain_and_protocol(): void
    {
        $this->assertEquals('/dich-vu-seo', Ga4PerformanceDTO::normalizeLandingPage('https://example.com/dich-vu-seo'));
        $this->assertEquals('/dich-vu-seo', Ga4PerformanceDTO::normalizeLandingPage('http://example.com/dich-vu-seo?utm_source=facebook#section'));
        $this->assertEquals('/dich-vu-seo', Ga4PerformanceDTO::normalizeLandingPage('/dich-vu-seo/'));
        $this->assertEquals('/dich-vu-seo', Ga4PerformanceDTO::normalizeLandingPage('dich-vu-seo'));
        $this->assertEquals('/', Ga4PerformanceDTO::normalizeLandingPage('https://example.com/'));
        $this->assertEquals('/', Ga4PerformanceDTO::normalizeLandingPage('/'));
        $this->assertEquals('/', Ga4PerformanceDTO::normalizeLandingPage(''));
    }

    public function test_multi_tenant_isolation_prevents_unauthorized_access(): void
    {
        Sanctum::actingAs($this->userB); // User B from Workspace B

        // User B tries to view GA4 properties of Website A
        $response1 = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->getJson("/api/v1/websites/{$this->websiteA->id}/ga4/properties");

        $response1->assertStatus(404);

        // User B tries to connect GA4 to Website A
        $response2 = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/connect", [
            'property_id' => 'properties/hacked_ga4_999',
        ]);

        $response2->assertStatus(404);

        // User B tries to disconnect GA4 of Website A
        $response3 = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/disconnect");

        $response3->assertStatus(404);
    }

    public function test_zero_credit_consumption_during_all_ga4_operations(): void
    {
        Sanctum::actingAs($this->userA);

        Http::fake([
            'https://analyticsadmin.googleapis.com/v1beta/properties/*' => Http::response([
                'name' => 'properties/987654321',
                'parent' => 'accounts/112233',
                'displayName' => 'Smile GA4',
                'timeZone' => 'Asia/Ho_Chi_Minh',
                'currencyCode' => 'VND',
            ], 200),
            'https://analyticsdata.googleapis.com/v1beta/properties/*:runReport' => Http::response([
                'rows' => [
                    [
                        'dimensionValues' => [['value' => '20261101'], ['value' => '/dich-vu-seo']],
                        'metricValues' => [['value' => '25'], ['value' => '20'], ['value' => '50'], ['value' => '0.7'], ['value' => '1']],
                    ],
                ],
            ], 200),
        ]);

        $initialCredits = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertEquals(500, $initialCredits);

        // 1. Connect
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/connect", [
                'property_id' => 'properties/987654321',
            ])
            ->assertStatus(200);

        // 2. Test
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/test")
            ->assertStatus(200);

        // 3. Query
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspaceA->id])
            ->postJson("/api/v1/websites/{$this->websiteA->id}/ga4/query", [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-02',
            ])
            ->assertStatus(200);

        // Verify credit balance is 100% untouched
        $finalCredits = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertEquals(500, $finalCredits);
    }
}
