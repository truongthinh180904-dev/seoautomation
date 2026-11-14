<?php

namespace Tests\Feature;

use App\DTOs\Chatbot\UserIntentDTO;
use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Article;
use App\Models\Conversation;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\GoogleAccount;
use App\Models\GscSearchPerformanceDaily;
use App\Models\Keyword;
use App\Models\RankTrackedKeyword;
use App\Models\RankTrackingHistory;
use App\Models\SeoIntelligenceInsight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteGoogleAnalytics;
use App\Models\WebsiteSearchConsole;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\ActionApprovalService;
use App\Services\Chatbot\AiOrchestratorService;
use App\Services\Chatbot\Tools\Adapters\ContentRefreshToolAdapter;
use App\Services\Chatbot\Tools\Adapters\Ga4ToolAdapter;
use App\Services\Chatbot\Tools\Adapters\GscToolAdapter;
use App\Services\Chatbot\Tools\Adapters\RankTrackerToolAdapter;
use App\Services\Chatbot\Tools\Adapters\SeoIntelligenceToolAdapter;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\Chatbot\Tools\SeoToolRegistry;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ChatbotSeoAutomationToolTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Website $websiteA;

    protected GoogleAccount $googleAccountA;

    protected WordPressSite $wpSiteA;

    protected Article $articleA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Website $websiteB;

    protected WordPressSite $wpSiteB;

    protected Article $articleB;

    protected CreditLedgerService $creditLedger;

    protected SeoToolRegistry $registry;

    protected ActionApprovalService $approvalService;

    protected AiOrchestratorService $orchestrator;

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
        $this->registry = app(SeoToolRegistry::class);
        $this->approvalService = app(ActionApprovalService::class);
        $this->orchestrator = app(AiOrchestratorService::class);

        // 1. Khởi tạo Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Smile Dental Tenant',
            'slug' => 'smile-dental-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'doctor@nhakhoasmile.vn',
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceA = Workspace::create([
            'owner_id' => $this->userA->id,
            'legacy_tenant_id' => $this->tenantA->id,
            'name' => 'Nha Khoa Smile Workspace',
            'slug' => 'nha-khoa-smile-ws',
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
            'name' => 'Nha Khoa Smile Official',
            'domain' => 'nhakhoasmile.vn',
            'url' => 'https://nhakhoasmile.vn',
        ]);

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
                'https://www.googleapis.com/auth/analytics.readonly',
            ],
            'status' => 'active',
        ]);

        $this->wpSiteA = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'name' => 'WordPress Nha Khoa Smile',
            'url' => 'https://nhakhoasmile.vn',
            'api_url' => 'https://nhakhoasmile.vn/wp-json',
            'username' => 'wp_admin',
            'app_password' => 'secret123',
            'is_active' => true,
        ]);

        $keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'nieng rang',
            'status' => 'completed',
        ]);

        $this->articleA = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword_id' => $keywordA->id,
            'user_id' => $this->userA->id,
            'wordpress_site_id' => $this->wpSiteA->id,
            'wordpress_post_id' => 101,
            'title' => 'Top 10 Phuong Phap Nieng Rang Uy Tin',
            'slug' => 'top-10-phuong-phap-nieng-rang-uy-tin',
            'content' => '<h2>1. Gioi thieu</h2><p>Nieng rang la phuong phap chinh nha pho bien giup cai thien nu cuoi.</p>',
            'status' => ArticleStatus::PUBLISHED,
            'seo_score' => 65,
            'word_count' => 150,
            'focus_keyword' => 'nieng rang',
        ]);

        // Cấp 500 credits cho Workspace A
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 500,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial credits for Workspace A'
        );

        // 2. Khởi tạo Workspace B (Đối thủ / Cô lập multi-tenant)
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'competitor-tenant-b',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'admin@competitor.com',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Competitor Workspace B',
            'slug' => 'competitor-ws-b',
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
            'name' => 'Competitor Dental Site',
            'domain' => 'competitordental.com',
            'url' => 'https://competitordental.com',
        ]);

        $this->wpSiteB = WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'name' => 'WordPress Competitor',
            'url' => 'https://competitordental.com',
            'api_url' => 'https://competitordental.com/wp-json',
            'username' => 'comp_admin',
            'app_password' => 'secret456',
            'is_active' => true,
        ]);

        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'bao mat nha khoa',
            'status' => 'completed',
        ]);

        $this->articleB = Article::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'keyword_id' => $keywordB->id,
            'user_id' => $this->userB->id,
            'wordpress_site_id' => $this->wpSiteB->id,
            'wordpress_post_id' => 202,
            'title' => 'Bai Viet Bao Mat Workspace B',
            'slug' => 'bai-viet-bao-mat-workspace-b',
            'content' => '<p>Noi dung doc quyen cua Workspace B khong duoc tiet lo.</p>',
            'status' => ArticleStatus::PUBLISHED,
            'seo_score' => 70,
            'word_count' => 120,
            'focus_keyword' => 'bao mat nha khoa',
        ]);
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

    /**
     * P3.9.1: SeoToolRegistry chứa đầy đủ 5 Tool Adapters mới của Phase 3.
     */
    public function test_seo_tool_registry_contains_all_phase3_adapters(): void
    {
        $phase3Tools = [
            'gsc_query' => [
                'class' => GscToolAdapter::class,
                'credit_cost' => 0,
            ],
            'ga4_query' => [
                'class' => Ga4ToolAdapter::class,
                'credit_cost' => 0,
            ],
            'rank_tracker' => [
                'class' => RankTrackerToolAdapter::class,
                'credit_cost' => 0,
            ],
            'seo_intelligence' => [
                'class' => SeoIntelligenceToolAdapter::class,
                'credit_cost' => 0,
            ],
            'content_refresh' => [
                'class' => ContentRefreshToolAdapter::class,
                'credit_cost' => 2,
            ],
        ];

        foreach ($phase3Tools as $toolName => $expected) {
            $this->assertTrue(
                $this->registry->has($toolName),
                "Tool registry must contain Phase 3 adapter: {$toolName}"
            );

            $tool = $this->registry->get($toolName);
            $this->assertInstanceOf(ChatToolInterface::class, $tool);
            $this->assertInstanceOf($expected['class'], $tool);
            $this->assertSame($toolName, $tool->getName());
            $this->assertNotEmpty($tool->getTitle());
            $this->assertNotEmpty($tool->getDescription());
            $this->assertSame($expected['credit_cost'], $tool->getEstimatedCredits());

            $schema = $tool->getParametersSchema();
            $this->assertIsArray($schema);
            $this->assertArrayHasKey('type', $schema);
            $this->assertArrayHasKey('properties', $schema);
        }
    }

    /**
     * P3.9.2: GscToolAdapter truy vấn số liệu GSC thành công (overview, top_queries, declining_pages).
     */
    public function test_gsc_tool_adapter_queries_overview_and_top_queries(): void
    {
        WebsiteSearchConsole::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'site_url' => 'https://nhakhoasmile.vn',
            'is_verified' => true,
            'sync_status' => 'synced',
        ]);

        $date1 = Carbon::now()->subDays(5)->toDateString();
        $date2 = Carbon::now()->subDays(2)->toDateString();

        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $date1,
            'query' => 'nieng rang mac cai',
            'page_url' => 'https://nhakhoasmile.vn/nieng-rang',
            'page_hash' => md5('https://nhakhoasmile.vn/nieng-rang'),
            'query_hash' => md5('nieng rang mac cai'),
            'clicks' => 50,
            'impressions' => 1000,
            'ctr' => 0.05,
            'position' => 4.2,
        ]);

        GscSearchPerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $date2,
            'query' => 'nieng rang trong suot',
            'page_url' => 'https://nhakhoasmile.vn/nieng-rang-trong-suot',
            'page_hash' => md5('https://nhakhoasmile.vn/nieng-rang-trong-suot'),
            'query_hash' => md5('nieng rang trong suot'),
            'clicks' => 30,
            'impressions' => 800,
            'ctr' => 0.0375,
            'position' => 7.8,
        ]);

        /** @var GscToolAdapter $adapter */
        $adapter = $this->registry->get('gsc_query');

        // Test action: overview
        $overviewResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'overview', 'days' => 30],
            actor: $this->userA
        );

        $this->assertSame(0, $adapter->getEstimatedCredits());
        $this->assertArrayHasKey('summary', $overviewResult);
        $this->assertSame('overview', $overviewResult['query_type']);
        $this->assertEquals(80, $overviewResult['summary']['clicks']['current']);
        $this->assertEquals(1800, $overviewResult['summary']['impressions']['current']);

        // Test action: top_queries
        $queriesResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'top_queries', 'limit' => 5],
            actor: $this->userA
        );

        $this->assertSame('top_queries', $queriesResult['query_type']);
        $this->assertArrayHasKey('items', $queriesResult);
        $this->assertNotEmpty($queriesResult['items']);
        $this->assertSame('nieng rang mac cai', $queriesResult['items'][0]['query']);

        // Test action: declining_pages
        $decliningResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'declining_pages'],
            actor: $this->userA
        );
        $this->assertSame('declining_pages', $decliningResult['query_type']);
        $this->assertArrayHasKey('items', $decliningResult);
    }

    /**
     * P3.9.3: Ga4ToolAdapter truy vấn lưu lượng GA4 thành công (overview, top_landing_pages).
     */
    public function test_ga4_tool_adapter_queries_traffic_and_landing_pages(): void
    {
        WebsiteGoogleAnalytics::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'google_account_id' => $this->googleAccountA->id,
            'property_id' => '987654321',
            'stream_id' => '12345678',
            'measurement_id' => 'G-SMILETEST',
            'sync_status' => 'synced',
        ]);

        $yesterday = Carbon::yesterday()->toDateString();

        Ga4PagePerformanceDaily::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'date' => $yesterday,
            'landing_page' => '/nieng-rang-tham-my',
            'page_hash' => md5('/nieng-rang-tham-my'),
            'sessions' => 120,
            'active_users' => 105,
            'engagement_rate' => 0.72,
            'conversions' => 10,
            'avg_session_duration' => 145.5,
        ]);

        /** @var Ga4ToolAdapter $adapter */
        $adapter = $this->registry->get('ga4_query');

        // Test action: overview
        $overviewResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'overview', 'days' => 30],
            actor: $this->userA
        );

        $this->assertSame(0, $adapter->getEstimatedCredits());
        $this->assertSame('overview', $overviewResult['query_type']);
        $this->assertArrayHasKey('summary', $overviewResult);
        $this->assertEquals(120, $overviewResult['summary']['sessions']['current']);
        $this->assertEquals(105, $overviewResult['summary']['active_users']['current']);

        // Test action: top_landing_pages
        $pagesResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'top_landing_pages', 'limit' => 5],
            actor: $this->userA
        );

        $this->assertSame('top_landing_pages', $pagesResult['query_type']);
        $this->assertArrayHasKey('items', $pagesResult);
        $this->assertNotEmpty($pagesResult['items']);
        $this->assertSame('/nieng-rang-tham-my', $pagesResult['items'][0]['page_url']);
    }

    /**
     * P3.9.4: RankTrackerToolAdapter tra cứu thứ hạng từ khóa và lịch sử biến động.
     */
    public function test_rank_tracker_tool_adapter_returns_ranking_summary_and_keyword_position(): void
    {
        $keyword = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'trong rang implant',
            'search_volume' => 5400,
            'difficulty' => 45,
            'cpc' => 1.5,
            'search_intent' => 'commercial',
            'status' => 'completed',
        ]);

        $trackedKeyword = RankTrackedKeyword::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword_id' => $keyword->id,
            'keyword' => 'trong rang implant',
            'target_url' => 'https://nhakhoasmile.vn/trong-rang-implant',
            'country' => 'VN',
            'device' => 'desktop',
            'tracking_frequency' => 'daily',
            'current_position' => 3,
            'previous_position' => 7,
            'best_position' => 2,
            'last_checked_at' => now(),
            'is_active' => true,
        ]);

        RankTrackingHistory::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'rank_tracked_keyword_id' => $trackedKeyword->id,
            'check_date' => Carbon::yesterday()->toDateString(),
            'position' => 7,
            'found_url' => 'https://nhakhoasmile.vn/trong-rang-implant',
        ]);

        /** @var RankTrackerToolAdapter $adapter */
        $adapter = $this->registry->get('rank_tracker');

        // Test action: summary
        $summaryResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'summary'],
            actor: $this->userA
        );

        $this->assertSame(0, $adapter->getEstimatedCredits());
        $this->assertSame('summary', $summaryResult['action']);
        $this->assertArrayHasKey('summary', $summaryResult);
        $this->assertEquals(1, $summaryResult['summary']['total_keywords']);
        $this->assertEquals(1, $summaryResult['summary']['top_3']);

        // Test action: check_keyword
        $checkResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'check_keyword', 'keyword' => 'trong rang implant'],
            actor: $this->userA
        );

        $this->assertSame('check_keyword', $checkResult['action']);
        $this->assertSame(3, $checkResult['current_position']);
        $this->assertSame(7, $checkResult['previous_position']);
        $this->assertStringContainsString('Tăng', $checkResult['change_label']);
    }

    /**
     * P3.9.5: SeoIntelligenceToolAdapter báo cáo danh sách phát hiện & giải thích anomaly.
     */
    public function test_seo_intelligence_tool_adapter_returns_insights_and_anomaly_explanation(): void
    {
        $insight = SeoIntelligenceInsight::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'article_id' => $this->articleA->id,
            'type' => 'traffic_decay',
            'severity' => 'warning',
            'opportunity_score' => 85,
            'title' => 'Sụt giảm traffic bài viết niềng răng',
            'description' => 'Trang niềng răng bị giảm 45% traffic trong 14 ngày gần nhất.',
            'metrics_snapshot' => [
                'current_clicks' => 30,
                'previous_clicks' => 120,
                'drop_percent' => 75.0,
            ],
            'suggested_action' => 'Làm mới nội dung và bổ sung các từ khóa LSI mới.',
            'status' => 'detected',
        ]);

        /** @var SeoIntelligenceToolAdapter $adapter */
        $adapter = $this->registry->get('seo_intelligence');

        // Test action: summary
        $summaryResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'summary'],
            actor: $this->userA
        );

        $this->assertSame(0, $adapter->getEstimatedCredits());
        $this->assertSame('summary', $summaryResult['action']);
        $this->assertArrayHasKey('summary', $summaryResult);
        $this->assertGreaterThanOrEqual(1, $summaryResult['summary']['total_active']);

        // Test action: explain_anomaly
        $explainResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'explain_anomaly', 'insight_id' => $insight->id],
            actor: $this->userA
        );

        $this->assertSame('explain_anomaly', $explainResult['action']);
        $this->assertSame($insight->id, $explainResult['insight_id']);
        $this->assertSame($insight->description, $explainResult['description']);
        $this->assertSame($insight->suggested_action, $explainResult['suggested_action']);
    }

    /**
     * P3.9.6: ContentRefreshToolAdapter tạo kế hoạch tối ưu, trừ đúng 2 credits và xem trước Diff.
     */
    public function test_content_refresh_tool_adapter_generates_plan_and_deducts_two_credits(): void
    {
        $initialBalance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertEquals(500, $initialBalance);

        /** @var ContentRefreshToolAdapter $adapter */
        $adapter = $this->registry->get('content_refresh');

        // 1. Action: plan (tiêu tốn 2 credits)
        $planResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: [
                'action' => 'plan',
                'article_id' => $this->articleA->id,
            ],
            actor: $this->userA
        );

        $this->assertSame('plan', $planResult['action']);
        $this->assertSame(2, $adapter->getEstimatedCredits(['action' => 'plan']));
        $this->assertSame($this->articleA->id, $planResult['article_id']);
        $this->assertArrayHasKey('diff', $planResult);

        // Kiểm tra số dư ví trừ đúng 2 credits
        $newBalance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        $this->assertEquals(498, $newBalance);

        // 2. Action: preview (0 credit)
        $previewResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: [
                'action' => 'preview',
                'article_id' => $this->articleA->id,
            ],
            actor: $this->userA
        );

        $this->assertSame('preview', $previewResult['action']);
        $this->assertSame(498, $this->creditLedger->getBalance($this->workspaceA)->availableCredits);

        // 3. Action: apply (cập nhật nội dung bài viết)
        $applyResult = $adapter->execute(
            workspace: $this->workspaceA,
            params: [
                'action' => 'apply',
                'article_id' => $this->articleA->id,
            ],
            actor: $this->userA
        );

        $this->assertSame('apply', $applyResult['action']);
        $this->articleA->refresh();
        $this->assertNotNull($this->articleA->last_optimized_at);
    }

    /**
     * P3.9.7: ContentRefreshToolAdapter ném ngoại lệ khi workspace không đủ 2 credits.
     */
    public function test_content_refresh_fails_when_insufficient_credits(): void
    {
        // Rút sạch toàn bộ credit của Workspace A
        $currentBalance = $this->creditLedger->getBalance($this->workspaceA)->availableCredits;
        if ($currentBalance > 0) {
            $this->creditLedger->deductCredits(
                workspace: $this->workspaceA,
                amount: $currentBalance,
                referenceType: 'test_drain',
                referenceId: 9999
            );
        }

        $this->assertEquals(0, $this->creditLedger->getBalance($this->workspaceA)->availableCredits);

        /** @var ContentRefreshToolAdapter $adapter */
        $adapter = $this->registry->get('content_refresh');

        $this->expectException(InsufficientCreditsException::class);

        $adapter->execute(
            workspace: $this->workspaceA,
            params: [
                'action' => 'plan',
                'article_id' => $this->articleA->id,
            ],
            actor: $this->userA
        );
    }

    /**
     * P3.9.8: AiOrchestratorService tích hợp 5 tool mới vào System Prompt và sinh Execution Plan chuẩn xác.
     */
    public function test_ai_orchestrator_builds_prompt_with_phase3_tools_and_execution_plan(): void
    {
        // 1. Kiểm tra hằng số AVAILABLE_TOOLS
        $this->assertArrayHasKey('gsc_query', AiOrchestratorService::AVAILABLE_TOOLS);
        $this->assertArrayHasKey('ga4_query', AiOrchestratorService::AVAILABLE_TOOLS);
        $this->assertArrayHasKey('rank_tracker', AiOrchestratorService::AVAILABLE_TOOLS);
        $this->assertArrayHasKey('seo_intelligence', AiOrchestratorService::AVAILABLE_TOOLS);
        $this->assertArrayHasKey('content_refresh', AiOrchestratorService::AVAILABLE_TOOLS);

        // 2. Kiểm tra System Prompt chứa mô tả các tool
        $reflection = new ReflectionClass(AiOrchestratorService::class);
        $method = $reflection->getMethod('buildSystemPrompt');
        $method->setAccessible(true);

        $systemPrompt = $method->invoke($this->orchestrator, $this->workspaceA);

        $this->assertStringContainsString('gsc_query', $systemPrompt);
        $this->assertStringContainsString('ga4_query', $systemPrompt);
        $this->assertStringContainsString('rank_tracker', $systemPrompt);
        $this->assertStringContainsString('seo_intelligence', $systemPrompt);
        $this->assertStringContainsString('content_refresh', $systemPrompt);

        // 3. Kiểm tra Execution Plan cho intent GSC
        $gscIntent = new UserIntentDTO(
            intent: 'gsc_analytics',
            confidence: 0.95,
            parameters: ['action' => 'overview', 'days' => 28],
            rawQuery: 'Xem số liệu Google Search Console tháng này'
        );

        $gscPlan = $this->orchestrator->generateExecutionPlan($gscIntent, $this->workspaceA);
        $primaryTool = $gscPlan->primaryTool ?? ($gscPlan->steps[0]['tool'] ?? null);
        $this->assertSame('gsc_query', $primaryTool);
        $this->assertFalse($gscPlan->requiresHumanConfirmation ?? $gscPlan->requiresApproval);

        // 4. Kiểm tra Execution Plan cho intent Refresh Content
        $refreshIntent = new UserIntentDTO(
            intent: 'content_refresh',
            confidence: 0.92,
            parameters: ['article_id' => $this->articleA->id, 'action' => 'plan'],
            rawQuery: 'Tối ưu lại bài viết cũ về niềng răng'
        );

        $refreshPlan = $this->orchestrator->generateExecutionPlan($refreshIntent, $this->workspaceA);
        $refreshPrimaryTool = $refreshPlan->primaryTool ?? ($refreshPlan->steps[0]['tool'] ?? null);
        $this->assertSame('content_refresh', $refreshPrimaryTool);
        $this->assertFalse($refreshPlan->requiresHumanConfirmation ?? $refreshPlan->requiresApproval);
    }

    /**
     * P3.9.9: ActionApprovalService phê duyệt hành động cập nhật bài viết lên WordPress an toàn (Human-in-the-Loop).
     */
    public function test_action_approval_service_approves_wordpress_update(): void
    {
        $conversation = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'title' => 'Chatbot cập nhật WordPress',
        ]);

        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $conversation,
            actionName: 'push_update_wordpress',
            payload: [
                'article_id' => $this->articleA->id,
                'publish_status' => 'publish',
            ],
            user: $this->userA
        );

        $this->assertSame('pending', $pendingAction->status);
        $this->assertSame('push_update_wordpress', $pendingAction->action_name);
        $this->assertSame(0, $pendingAction->estimated_credits);

        // Giả lập WordPress API cập nhật thành công bài viết post 101
        Http::fake([
            'https://nhakhoasmile.vn/wp-json/wp/v2/posts/101' => Http::response([
                'id' => 101,
                'title' => ['rendered' => $this->articleA->title],
                'link' => 'https://nhakhoasmile.vn/top-10-phuong-phap-nieng-rang-uy-tin',
                'status' => 'publish',
            ], 200),
        ]);

        $result = $this->approvalService->approveAction($pendingAction, $this->userA);

        $this->assertTrue($result['success']);
        $this->assertSame('approved', $result['action']['status']);

        $pendingAction->refresh();
        $this->assertSame('approved', $pendingAction->status);
        $this->assertNotNull($pendingAction->reviewed_at);
    }

    /**
     * P3.9.10: Kiểm tra cô lập Multi-Tenant triệt để chống IDOR qua toàn bộ các Adapter.
     */
    public function test_multi_tenancy_isolation_prevents_unauthorized_access(): void
    {
        /** @var ContentRefreshToolAdapter $refreshAdapter */
        $refreshAdapter = $this->registry->get('content_refresh');

        // 1. Workspace A cố tình tối ưu bài viết của Workspace B -> ném ModelNotFoundException
        $this->expectException(ModelNotFoundException::class);
        $refreshAdapter->execute(
            workspace: $this->workspaceA,
            params: ['action' => 'preview', 'article_id' => $this->articleB->id],
            actor: $this->userA
        );
    }

    /**
     * P3.9.11: Workspace A cố tình cập nhật WordPress post của Workspace B bị chặn bởi ActionApprovalService.
     */
    public function test_approval_service_rejects_cross_tenant_wordpress_update(): void
    {
        $conversationA = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'title' => 'Tấn công IDOR sang Workspace B',
        ]);

        // Tạo pending action trên Workspace A nhưng truyền article_id của Workspace B
        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $conversationA,
            actionName: 'update_wordpress_post',
            payload: [
                'article_id' => $this->articleB->id,
            ],
            user: $this->userA
        );

        $this->expectException(ModelNotFoundException::class);
        $this->approvalService->approveAction($pendingAction, $this->userA);
    }
}
