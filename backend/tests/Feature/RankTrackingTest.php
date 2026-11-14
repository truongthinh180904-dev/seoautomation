<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\SEO\TrackKeywordRankingJob;
use App\Models\Plan;
use App\Models\RankTrackedKeyword;
use App\Models\RankTrackingHistory;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\SEO\RankTracking\RankTrackingService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RankTrackingTest extends TestCase
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

    public function test_can_create_tracked_keyword_successfully(): void
    {
        Sanctum::actingAs($this->userA);

        $payload = [
            'website_id' => $this->websiteA->id,
            'keyword' => 'cấy ghép răng implant',
            'target_url' => 'https://dentalcare.vn/cay-ghep-implant',
            'country' => 'vn',
            'device' => 'desktop',
            'tracking_frequency' => 'daily',
        ];

        $response = $this->postJson('/api/v1/rank-tracker/keywords', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Đã thêm từ khóa vào danh sách theo dõi thứ hạng thành công.',
            ]);

        $this->assertDatabaseHas('rank_tracked_keywords', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'cấy ghép răng implant',
            'country' => 'vn',
            'device' => 'desktop',
        ]);
    }

    public function test_tracked_keyword_uniqueness_per_website_country_device(): void
    {
        Sanctum::actingAs($this->userA);

        RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'niềng răng trong suốt',
            'country' => 'vn',
            'device' => 'desktop',
            'tracking_frequency' => 'daily',
            'is_active' => true,
        ]);

        // Attempt to create duplicate keyword with same country and device
        $response = $this->postJson('/api/v1/rank-tracker/keywords', [
            'website_id' => $this->websiteA->id,
            'keyword' => 'niềng răng trong suốt',
            'country' => 'vn',
            'device' => 'desktop',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Creating for mobile device should succeed
        $responseMobile = $this->postJson('/api/v1/rank-tracker/keywords', [
            'website_id' => $this->websiteA->id,
            'keyword' => 'niềng răng trong suốt',
            'country' => 'vn',
            'device' => 'mobile',
        ]);

        $responseMobile->assertStatus(201);
    }

    public function test_plan_keyword_limit_is_strictly_enforced(): void
    {
        Sanctum::actingAs($this->userB);

        // Attach free plan to workspace B (limit is 10 keywords)
        $freePlan = Plan::firstOrCreate(
            ['code' => 'free'],
            ['name' => 'Free Plan', 'price_monthly' => 0]
        );

        Subscription::create([
            'workspace_id' => $this->workspaceB->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Seed 10 keywords for Website B
        for ($i = 1; $i <= 10; $i++) {
            RankTrackedKeyword::create([
                'workspace_id' => $this->workspaceB->id,
                'website_id' => $this->websiteB->id,
                'keyword' => "dịch vụ chăm sóc xe hơi {$i}",
                'country' => 'vn',
                'device' => 'desktop',
                'is_active' => true,
            ]);
        }

        // The 11th keyword should trigger 403 quota exceeded
        $response = $this->postJson('/api/v1/rank-tracker/keywords', [
            'website_id' => $this->websiteB->id,
            'keyword' => 'dịch vụ chăm sóc xe hơi thứ 11',
            'country' => 'vn',
            'device' => 'desktop',
        ]);

        $response->assertStatus(403);
    }

    public function test_track_ranking_updates_positions_and_stores_history_via_serper(): void
    {
        $keyword = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'tẩy trắng răng tại nhà',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => true,
            'current_position' => 12,
            'best_position' => 12,
        ]);

        // Mock Serper Search API
        Http::fake([
            'https://google.serper.dev/search' => Http::response([
                'searchParameters' => [
                    'q' => 'tẩy trắng răng tại nhà',
                    'gl' => 'vn',
                    'hl' => 'vi',
                    'num' => 100,
                ],
                'organic' => [
                    [
                        'position' => 1,
                        'title' => 'Top nha khoa uy tín',
                        'link' => 'https://other-dental.vn/tay-trang-rang',
                        'snippet' => 'Dịch vụ tẩy trắng',
                    ],
                    [
                        'position' => 2,
                        'title' => 'Nha Khoa Dental Care - Tẩy Trắng Răng Chuyên Nghiệp',
                        'link' => 'https://dentalcare.vn/dich-vu/tay-trang-rang',
                        'snippet' => 'Phương pháp tẩy trắng răng an toàn hiệu quả tại Dental Care.',
                    ],
                ],
                'answerBox' => [
                    'snippet' => 'Chi phí tẩy trắng răng dao động từ 1 đến 3 triệu',
                ],
            ], 200),
        ]);

        $checkDate = Carbon::today()->toDateString();
        $job = new TrackKeywordRankingJob($this->workspaceA->id, $keyword->id, $checkDate);
        $job->handle();

        $keyword->refresh();

        $this->assertEquals(2, $keyword->current_position);
        $this->assertEquals(12, $keyword->previous_position);
        $this->assertEquals(2, $keyword->best_position);
        $this->assertNotNull($keyword->last_checked_at);

        $this->assertDatabaseHas('rank_tracking_history', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'rank_tracked_keyword_id' => $keyword->id,
            'check_date' => $checkDate,
            'position' => 2,
            'found_url' => 'https://dentalcare.vn/dich-vu/tay-trang-rang',
        ]);
    }

    public function test_rank_tracking_history_idempotent_per_date(): void
    {
        $keyword = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'nhổ răng khôn không đau',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => true,
        ]);

        Http::fake([
            'https://google.serper.dev/search' => Http::sequence()
                ->push([
                    'organic' => [
                        [
                            'position' => 5,
                            'link' => 'https://dentalcare.vn/nho-rang-khon',
                            'title' => 'Nhổ răng khôn',
                        ],
                    ],
                ], 200)
                ->push([
                    'organic' => [
                        [
                            'position' => 3,
                            'link' => 'https://dentalcare.vn/nho-rang-khon',
                            'title' => 'Nhổ răng khôn uy tín',
                        ],
                    ],
                ], 200),
        ]);

        $service = app(RankTrackingService::class);
        $today = Carbon::today()->toDateString();

        // Run 1st time
        $service->trackKeyword($keyword, $today);
        $this->assertEquals(1, RankTrackingHistory::where('rank_tracked_keyword_id', $keyword->id)->count());

        // Run 2nd time on same date
        $service->trackKeyword($keyword, $today);
        $this->assertEquals(1, RankTrackingHistory::where('rank_tracked_keyword_id', $keyword->id)->count());

        $latestHistory = RankTrackingHistory::where('rank_tracked_keyword_id', $keyword->id)->first();
        $this->assertEquals(3, $latestHistory->position);
    }

    public function test_tenant_isolation_cannot_access_or_modify_other_workspace_keywords(): void
    {
        $keywordA = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'trám răng thẩm mỹ',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => true,
        ]);

        // User B from Workspace B attempts IDOR operations on Workspace A's keyword
        Sanctum::actingAs($this->userB);

        // 1. Cannot list keywords belonging to Website A
        $listResponse = $this->getJson("/api/v1/rank-tracker/keywords?website_id={$this->websiteA->id}");
        $keywords = $listResponse->json('data') ?? [];
        foreach ($keywords as $kw) {
            $this->assertNotEquals($keywordA->id, $kw['id']);
        }

        // 2. Cannot view history of keyword A
        $historyResponse = $this->getJson("/api/v1/rank-tracker/keywords/{$keywordA->id}/history");
        $historyResponse->assertStatus(404);

        // 3. Cannot refresh keyword A
        $refreshResponse = $this->postJson("/api/v1/rank-tracker/keywords/{$keywordA->id}/refresh");
        $refreshResponse->assertStatus(404);

        // 4. Cannot delete keyword A
        $deleteResponse = $this->deleteJson("/api/v1/rank-tracker/keywords/{$keywordA->id}");
        $deleteResponse->assertStatus(404);
    }

    public function test_delta_rank_calculation_attribute(): void
    {
        // 1. Improved rank (+7)
        $kwImproved = new RankTrackedKeyword([
            'current_position' => 3,
            'previous_position' => 10,
        ]);
        $this->assertEquals(7, $kwImproved->delta_rank);

        // 2. Declined rank (-5)
        $kwDeclined = new RankTrackedKeyword([
            'current_position' => 15,
            'previous_position' => 10,
        ]);
        $this->assertEquals(-5, $kwDeclined->delta_rank);

        // 3. Unchanged rank (0)
        $kwUnchanged = new RankTrackedKeyword([
            'current_position' => 8,
            'previous_position' => 8,
        ]);
        $this->assertEquals(0, $kwUnchanged->delta_rank);

        // 4. Dropped out of Top 100 (-100)
        $kwDropped = new RankTrackedKeyword([
            'current_position' => null,
            'previous_position' => 50,
        ]);
        $this->assertEquals(-100, $kwDropped->delta_rank);

        // 5. Newly entered Top 100
        $kwNewEntry = new RankTrackedKeyword([
            'current_position' => 25,
            'previous_position' => null,
        ]);
        $this->assertGreaterThan(0, $kwNewEntry->delta_rank);
    }

    public function test_get_keyword_history_api(): void
    {
        Sanctum::actingAs($this->userA);

        $keyword = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'bảng giá nha khoa mới nhất',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => true,
        ]);

        RankTrackingHistory::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'rank_tracked_keyword_id' => $keyword->id,
            'check_date' => Carbon::today()->subDays(2)->toDateString(),
            'position' => 18,
            'found_url' => 'https://dentalcare.vn/bang-gia',
        ]);

        RankTrackingHistory::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'rank_tracked_keyword_id' => $keyword->id,
            'check_date' => Carbon::today()->subDay()->toDateString(),
            'position' => 12,
            'found_url' => 'https://dentalcare.vn/bang-gia',
        ]);

        $response = $this->getJson("/api/v1/rank-tracker/keywords/{$keyword->id}/history?days=7");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_refresh_keyword_ranking_api(): void
    {
        Sanctum::actingAs($this->userA);

        $keyword = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword' => 'chỉnh nha thẩm mỹ',
            'country' => 'vn',
            'device' => 'desktop',
            'is_active' => true,
            'current_position' => 20,
        ]);

        Http::fake([
            'https://google.serper.dev/search' => Http::response([
                'organic' => [
                    [
                        'position' => 4,
                        'title' => 'Chỉnh nha chuyên sâu Dental Care',
                        'link' => 'https://dentalcare.vn/chinh-nha',
                        'snippet' => 'Dịch vụ chỉnh nha thẩm mỹ',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson("/api/v1/rank-tracker/keywords/{$keyword->id}/refresh");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Đã làm mới thứ hạng từ khóa thành công.',
                'data' => [
                    'id' => $keyword->id,
                    'current_position' => 4,
                    'previous_position' => 20,
                ],
            ]);
    }
}
