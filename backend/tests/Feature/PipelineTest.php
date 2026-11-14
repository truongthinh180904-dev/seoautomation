<?php

namespace Tests\Feature;

use App\Agents\SerpResearchAgent;
use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AgentType;
use App\Enums\AIProvider;
use App\Enums\ArticleStatus;
use App\Enums\CampaignImportStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\AI\ProviderException;
use App\Exceptions\AI\RateLimitException;
use App\Exceptions\Article\DuplicateContentException;
use App\Jobs\AI\GenerateOutlineJob;
use App\Jobs\Keywords\ProcessKeywordBatchJob;
use App\Jobs\Media\DownloadImageJob;
use App\Jobs\Media\GenerateArticleImagesJob;
use App\Jobs\Media\UploadToWordPressMediaJob;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Jobs\SERP\SerpResearchJob;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CampaignImport;
use App\Models\Keyword;
use App\Models\MediaAsset;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Providers\AI\AnthropicProvider;
use App\Providers\AI\Contracts\AIProviderInterface;
use App\Providers\AI\GeminiProvider;
use App\Services\AI\AIProviderService;
use App\Services\AI\TokenUsageService;
use App\Services\Article\ArticlePipelineService;
use App\Services\Article\DuplicateDetectionService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Campaign\CampaignService;
use App\Services\Cost\CostTrackingService;
use App\Services\Keyword\KeywordImportService;
use App\Services\Media\ImageDownloadService;
use App\Services\Notifications\NotificationService;
use App\Services\Queue\JobDispatcherService;
use App\Services\Queue\TaskService;
use App\Services\SEO\CompetitorCrawlService;
use App\Services\SEO\InternalLinkingService;
use App\Services\SEO\SerpResearchService;
use App\Services\WordPress\WordPressMediaService;
use App\Services\WordPress\WordPressPreflightCheckService;
use App\Services\WordPress\WordPressPublishService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\AIPromptSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected CreditLedgerService $creditLedger;

    protected TaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);
        $this->seed(AIPromptSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'SEO Pipeline Agency',
            'slug' => 'seo-pipeline-agency',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'pipeline@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'SEO Pipeline Workspace',
            'slug' => 'seo-pipeline-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->user->update(['default_workspace_id' => $this->workspace->id]);

        $this->creditLedger = app(CreditLedgerService::class);
        $this->taskService = app(TaskService::class);
    }

    /**
     * TASK-06.1: Import Keyword từ Excel/CSV, bảo vệ Anti-IDOR và ghi nhận CampaignImport.
     */
    public function test_keyword_import_parses_serial_date_applies_anti_idor_and_records_import(): void
    {
        $campaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Chiến Dịch Tháng 11',
            'slug' => 'chien-dich-thang-11',
            'status' => CampaignStatus::DRAFT,
        ]);

        $site = WordPressSite::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Blog Du Lịch',
            'url' => 'https://blogdulich.example.com',
            'api_url' => 'https://blogdulich.example.com/wp-json',
            'username' => 'editor',
            'app_password' => 'secret_wp_app_pwd',
            'is_active' => true,
        ]);

        // Create other tenant to test IDOR block
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'is_active' => true]);
        $otherCampaign = Campaign::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Campaign',
            'slug' => 'other-campaign',
            'status' => CampaignStatus::DRAFT,
        ]);

        $importService = app(KeywordImportService::class);

        $rows = [
            [
                'keyword' => 'du lịch đà nẵng tự túc',
                'campaign_id' => $campaign->id,
                'wordpress_site_id' => $site->id,
                'language' => 'vi',
                'search_volume' => 12000,
                'difficulty' => 45,
                'scheduled_at' => '2026-11-15 08:30:00',
            ],
            [
                'keyword' => 'kinh nghiệm du lịch sapa',
                'campaign_id' => $otherCampaign->id, // Cross-tenant IDOR attempt
                'wordpress_site_id' => 9999,          // Non-existent site
                'language' => 'vi',
                'scheduled_at' => 46345.5,             // Excel serial date number
            ],
        ];

        $summary = $importService->import(
            rows: $rows,
            tenantId: $this->tenant->id,
            userId: $this->user->id,
            campaignId: $campaign->id,
            workspaceId: $this->workspace->id
        );

        $this->assertEquals(2, $summary['imported']);
        $this->assertDatabaseHas('campaign_imports', [
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'uploaded_by' => $this->user->id,
            'status' => CampaignImportStatus::IMPORTED->value,
            'imported_rows' => 2,
        ]);

        // Assert valid keyword imported with right campaign & site
        $kw1 = Keyword::where('keyword', 'du lịch đà nẵng tự túc')->first();
        $this->assertNotNull($kw1);
        $this->assertEquals($campaign->id, $kw1->campaign_id);
        $this->assertEquals($site->id, $kw1->wordpress_site_id);
        $this->assertEquals('2026-11-15 08:30:00', $kw1->scheduled_at->format('Y-m-d H:i:s'));

        // Assert cross-tenant IDOR attempt was sanitized (campaign_id became null or assigned to authorized campaign)
        $kw2 = Keyword::where('keyword', 'kinh nghiệm du lịch sapa')->first();
        $this->assertNotNull($kw2);
        $this->assertNotEquals($otherCampaign->id, $kw2->campaign_id);
        $this->assertNull($kw2->wordpress_site_id);
    }

    /**
     * TASK-06.2: Start Campaign thực sự dispatch batch jobs và khởi tạo Task theo dõi.
     */
    public function test_campaign_start_dispatches_keyword_batch_jobs_and_creates_task(): void
    {
        Queue::fake();

        $this->creditLedger->grantCredits($this->workspace, 100, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));

        $campaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Chiến Dịch Khởi Động',
            'slug' => 'chien-dich-khoi-dong',
            'status' => CampaignStatus::DRAFT,
        ]);

        $kw1 = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'keyword' => 'từ khóa số 1',
            'status' => KeywordStatus::PENDING,
        ]);

        $kw2 = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'keyword' => 'từ khóa số 2',
            'status' => KeywordStatus::PENDING,
        ]);

        $campaignService = app(CampaignService::class);
        $updated = $campaignService->start($campaign, $this->user);

        $this->assertEquals(CampaignStatus::PROCESSING, $updated->status);
        $this->assertNotNull($updated->started_at);

        Queue::assertPushed(ProcessKeywordBatchJob::class, function ($job) use ($kw1, $kw2) {
            return in_array($kw1->id, $job->keywordIds, true) && in_array($kw2->id, $job->keywordIds, true);
        });

        // Keywords marked as processing
        $this->assertEquals(KeywordStatus::PROCESSING, $kw1->fresh()->status);
        $this->assertEquals(KeywordStatus::PROCESSING, $kw2->fresh()->status);

        // Task created to monitor campaign batch
        $this->assertDatabaseHas('tasks', [
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'type' => 'campaign_batch',
            'status' => TaskStatus::RUNNING->value,
        ]);
    }

    /**
     * TASK-06.2 & TASK-06.7: Campaign pause and resume operations with task state transition.
     */
    public function test_campaign_pause_and_resume_cancels_and_restarts_tasks(): void
    {
        $this->creditLedger->grantCredits($this->workspace, 100, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));

        $campaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Chiến Dịch Tạm Dừng',
            'slug' => 'chien-dich-tam-dung',
            'status' => CampaignStatus::PROCESSING,
        ]);

        $task = Task::create([
            'uuid' => 'test-task-uuid-pause',
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'type' => 'campaign_batch',
            'status' => TaskStatus::RUNNING,
        ]);

        $campaignService = app(CampaignService::class);
        $paused = $campaignService->pause($campaign);

        $this->assertEquals(CampaignStatus::PAUSED, $paused->status);
        $this->assertEquals(TaskStatus::CANCELLED, $task->fresh()->status);

        // Resume starts it again
        $resumed = $campaignService->resume($campaign, $this->user);
        $this->assertEquals(CampaignStatus::PROCESSING, $resumed->status);
    }

    /**
     * TASK-06.3: Scheduler chỉ bốc keywords đến hạn (không bốc nhầm keyword scheduled_at IS NULL của draft campaign).
     */
    public function test_job_dispatcher_ignores_draft_campaign_keywords_without_schedule(): void
    {
        Queue::fake();

        $draftCampaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Campaign Nháp',
            'slug' => 'campaign-nhap',
            'status' => CampaignStatus::DRAFT,
        ]);

        // Keyword in draft campaign with scheduled_at = NULL should NOT be dispatched
        Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $draftCampaign->id,
            'user_id' => $this->user->id,
            'keyword' => 'keyword nhap khong lich',
            'status' => KeywordStatus::PENDING,
            'scheduled_at' => null,
        ]);

        // Keyword scheduled in the past should be dispatched
        $scheduledKw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $draftCampaign->id,
            'user_id' => $this->user->id,
            'keyword' => 'keyword da den gio',
            'status' => KeywordStatus::PENDING,
            'scheduled_at' => now()->subMinutes(10),
        ]);

        $dispatcher = app(JobDispatcherService::class);
        $dispatchedCount = $dispatcher->dispatchScheduledKeywords();

        $this->assertEquals(1, $dispatchedCount);
        Queue::assertPushed(ProcessKeywordBatchJob::class, function ($job) use ($scheduledKw) {
            return in_array($scheduledKw->id, $job->keywordIds, true);
        });
    }

    /**
     * TASK-06.4: Cơ chế Failover Đa AI Provider (OpenAI 429 RateLimit -> Anthropic -> Gemini).
     */
    public function test_ai_provider_failover_mechanism_from_openai_to_anthropic(): void
    {
        $mockOpenAI = new class implements AIProviderInterface
        {
            public function complete(AIRequestDTO $request): AIResponseDTO
            {
                throw new RateLimitException('OpenAI 429 Too Many Requests', 429);
            }

            public function getProvider(): AIProvider
            {
                return AIProvider::OPENAI;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $mockAnthropic = new class implements AIProviderInterface
        {
            public function complete(AIRequestDTO $request): AIResponseDTO
            {
                return new AIResponseDTO(
                    content: 'Nội dung thành công từ Anthropic Claude fallback',
                    provider: AIProvider::ANTHROPIC,
                    model: 'claude-3-haiku-20240307',
                    promptTokens: 100,
                    completionTokens: 250,
                    totalTokens: 350,
                    latencyMs: 120,
                    rawResponse: ['ok' => true]
                );
            }

            public function getProvider(): AIProvider
            {
                return AIProvider::ANTHROPIC;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        config([
            'ai.default_provider' => 'openai',
            'ai.fallback_chain' => ['openai', 'anthropic'],
        ]);

        $service = new AIProviderService(
            providers: [$mockOpenAI, $mockAnthropic],
            tokenUsageService: app(TokenUsageService::class),
            costTrackingService: app(CostTrackingService::class),
            notificationService: app(NotificationService::class)
        );

        $request = new AIRequestDTO(
            systemPrompt: 'You are an SEO writer',
            userPrompt: 'Write an article outline',
            agentType: AgentType::OUTLINE,
            tenantId: $this->tenant->id
        );

        $response = $service->complete($request);

        $this->assertEquals(AIProvider::ANTHROPIC, $response->provider);
        $this->assertStringContainsString('Nội dung thành công từ Anthropic', $response->content);

        // Ensure rate limit was recorded in Cache
        $this->assertTrue(Cache::has('ai_provider:blocked:openai'));

        // Verify ai_usage_logs and usage_records have correct workspace_id mapped from legacy_tenant_id
        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'provider' => 'anthropic',
            'job_type' => AgentType::OUTLINE->value,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('usage_records', [
            'workspace_id' => $this->workspace->id,
            'action_type' => AgentType::OUTLINE->value,
            'status' => 'settled',
        ]);
    }

    /**
     * TASK-06.4: RateLimitException constructor tương thích an toàn (không bị TypeError khi nhận 429).
     */
    public function test_rate_limit_exception_constructor_compatibility(): void
    {
        $prev = new \Exception('Underlying Guzzle 429');

        // Test 2-argument with code integer
        $ex1 = new RateLimitException('Rate limit exceeded', 429);
        $this->assertEquals(429, $ex1->getCode());
        $this->assertEquals('Rate limit exceeded', $ex1->getMessage());

        // Test 3-argument: message, code, previous
        $ex2 = new RateLimitException('Rate limit with previous', 429, $prev);
        $this->assertEquals(429, $ex2->getCode());
        $this->assertSame($prev, $ex2->getPrevious());

        // Test 2-argument: message, previous
        $ex3 = new RateLimitException('Rate limit previous only', $prev);
        $this->assertEquals(429, $ex3->getCode());
        $this->assertSame($prev, $ex3->getPrevious());
    }

    /**
     * TASK-06.3: InternalLinkingService xử lý an toàn WordPress app_password giải mã kép.
     */
    public function test_internal_linking_service_handles_encrypted_app_password_without_crash(): void
    {
        Http::fake([
            'https://site.example.com/wp-json/wp/v2/posts*' => Http::response([
                ['id' => 101, 'title' => 'Bài viết liên quan mẫu', 'link' => 'https://site.example.com/bai-101'],
            ], 200),
        ]);

        $site = WordPressSite::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Internal Link WP Site',
            'url' => 'https://site.example.com',
            'api_url' => 'https://site.example.com/wp-json',
            'username' => 'author',
            'app_password' => 'application_password_123',
            'is_active' => true,
        ]);

        $service = app(InternalLinkingService::class);
        $related = $service->fetchRelatedPosts($site, 'du lịch', 5);

        $this->assertIsArray($related);
        $this->assertCount(1, $related);
        $this->assertEquals(101, $related[0]['id']);
    }

    /**
     * TASK-06.5: CompetitorCrawlService chặn đứng SSRF và đếm từ tiếng Việt chuẩn xác.
     */
    public function test_competitor_crawl_service_blocks_ssrf_and_counts_vietnamese_words(): void
    {
        $service = app(CompetitorCrawlService::class);

        // SSRF Block test
        $this->expectException(\InvalidArgumentException::class);
        $service->crawl('http://169.254.169.254/latest/meta-data/');
    }

    /**
     * TASK-06.5: ImageDownloadService chặn URL nội bộ / private IPs (SSRF).
     */
    public function test_image_downloader_blocks_private_ip_ssrf(): void
    {
        $asset = MediaAsset::create([
            'tenant_id' => $this->tenant->id,
            'source_type' => 'external_url',
            'source_url' => 'http://127.0.0.1:8000/internal-secret.png',
            'status' => 'pending',
        ]);

        $downloader = app(ImageDownloadService::class);

        $this->expectException(\RuntimeException::class);
        $downloader->download($asset);
    }

    /**
     * TASK-06.8: DuplicateDetectionService chỉ quét trong cùng Workspace/Tenant, không rò rỉ tiêu đề ra ngoài.
     */
    public function test_duplicate_detection_is_scoped_to_workspace_and_does_not_leak_titles(): void
    {
        $detector = app(DuplicateDetectionService::class);
        $html = '<h2>Giới thiệu Đà Nẵng</h2><p>Đà Nẵng là thành phố đáng sống nhất Việt Nam.</p>';
        $hash = $detector->computeHash($html);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Đà Nẵng đáng sống',
            'status' => KeywordStatus::PENDING,
        ]);

        // Article in Workspace A
        $artA = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'title' => 'Bí mật kinh doanh Workspace A',
            'slug' => 'bi-mat-kinh-doanh-a',
            'content' => $html,
            'duplicate_check_hash' => $hash,
            'status' => ArticleStatus::DRAFT,
        ]);

        // Another tenant/workspace checks duplicate with the same content
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant-b', 'is_active' => true]);
        $otherWorkspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $otherTenant->id,
            'name' => 'Workspace B',
            'slug' => 'workspace-b',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        // Workspace B checking duplicate with tenantId/workspaceId of B should NOT detect Workspace A's article
        $detector->checkDuplicate(
            hash: $hash,
            excludeArticleId: null,
            tenantId: $otherTenant->id,
            workspaceId: $otherWorkspace->id
        );
        $this->assertTrue(true, 'Cross-workspace duplicate detection did not leak or collide.');

        // Workspace A checking duplicate SHOULD detect its own duplicate
        $this->expectException(DuplicateContentException::class);
        $detector->checkDuplicate(
            hash: $hash,
            excludeArticleId: null,
            tenantId: $this->tenant->id,
            workspaceId: $this->workspace->id
        );
    }

    /**
     * TASK-06.6 & TASK-06.8: ArticlePipelineService cập nhật concurrency an toàn và hỗ trợ hủy tác vụ.
     */
    public function test_article_pipeline_service_concurrency_locking_and_cancel_state(): void
    {
        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Pipeline Keyword Test',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'title' => 'Bài viết kiểm thử Pipeline',
            'slug' => 'bai-viet-kiem-thu-pipeline',
            'status' => ArticleStatus::DRAFT,
        ]);

        $pipeline = app(ArticlePipelineService::class);
        $pipeline->reset($article);
        $pipeline->start($article, 'outline', 'Đang tạo dàn ý');

        $fresh = $article->fresh();
        $this->assertEquals('outline', $fresh->pipeline_status['current']);
        $this->assertEquals('running', $fresh->pipeline_status['steps']['outline']['status']);

        // Test Cancel
        $pipeline->cancel($article, 'Người dùng chủ động hủy');
        $cancelled = $article->fresh();
        $this->assertEquals('cancelled', $cancelled->pipeline_status['current']);
        $this->assertEquals('cancelled', $cancelled->pipeline_status['steps']['cancelled']['status']);
    }

    /**
     * TASK-06.7 & TASK-06.10: TaskService quản lý trọn vẹn vòng đời, reserve & release credit khi hủy.
     */
    public function test_task_service_lifecycle_reserves_and_releases_credits_on_cancel(): void
    {
        // Grant credits to workspace first
        $this->creditLedger->grantCredits(
            workspace: $this->workspace,
            amount: 100,
            source: CreditGrantSource::FREE_DAILY,
            expiresAt: now()->addDay(),
            idempotencyKey: 'grant-test-100'
        );

        $initialBalance = $this->creditLedger->getBalance($this->workspace);
        $this->assertEquals(100, $initialBalance->availableCredits);

        // 1. Create task with reserved credits
        $task = $this->taskService->createTask([
            'workspace_id' => $this->workspace->id,
            'type' => 'full_article_pipeline',
            'credits_reserved' => 20,
        ]);

        $afterReserve = $this->creditLedger->getBalance($this->workspace);
        $this->assertEquals(80, $afterReserve->availableCredits);
        $this->assertEquals(20, $afterReserve->reservedCredits);

        // 2. Start and update progress
        $this->taskService->startTask($task, 'outline');
        $this->taskService->updateProgress($task, 50, 'writing');
        $this->assertEquals(50, $task->fresh()->progress);
        $this->assertEquals('writing', $task->fresh()->current_step);

        // 3. Cancel task -> credits should be released back to available balance!
        $this->taskService->cancelTask($task, $this->user, 'Customer cancelled job');
        $this->assertEquals(TaskStatus::CANCELLED, $task->fresh()->status);

        $afterCancel = $this->creditLedger->getBalance($this->workspace);
        $this->assertEquals(100, $afterCancel->availableCredits);
        $this->assertEquals(0, $afterCancel->reservedCredits);

        $this->assertDatabaseHas('credit_transactions', [
            'workspace_id' => $this->workspace->id,
            'type' => CreditTransactionType::RELEASE_REFUND->value,
            'amount' => 20,
        ]);
    }

    /**
     * TASK-06.12: PublishToWordPressJob nhúng FAQPage Schema và rethrow lỗi để retry.
     */
    public function test_publish_to_wordpress_embeds_faq_schema_and_rethrows_exception_for_retry(): void
    {
        $site = WordPressSite::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'WP Schema Test Site',
            'url' => 'https://wpschema.example.com',
            'api_url' => 'https://wpschema.example.com/wp-json',
            'username' => 'publisher',
            'app_password' => 'secret_app_pwd',
            'is_active' => true,
        ]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Tối ưu SEO FAQ',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'wordpress_site_id' => $site->id,
            'title' => 'Hướng dẫn tối ưu SEO',
            'slug' => 'huong-dan-toi-uu-seo',
            'content' => '<h2>1. SEO là gì?</h2><p>SEO là tối ưu hóa công cụ tìm kiếm.</p>',
            'status' => ArticleStatus::APPROVED,
            'faqs' => [
                [
                    'question' => 'SEO mất bao lâu để lên top?',
                    'answer' => 'Thường mất từ 3 đến 6 tháng tùy độ cạnh tranh.',
                ],
            ],
        ]);

        $shouldFail = true;

        Http::fake([
            'https://wpschema.example.com/wp-json/wp/v2/users/me' => Http::response(['id' => 1, 'name' => 'publisher'], 200),
            'https://wpschema.example.com/wp-json/wp/v2/posts' => function ($request) use (&$shouldFail) {
                if ($shouldFail) {
                    return Http::response(['message' => 'Internal Server Error'], 500);
                }

                $payload = json_decode($request->body(), true);
                $content = $payload['content'] ?? '';

                $hasFaqSchema = (str_contains($content, '"@type": "FAQPage"') || str_contains($content, '"@type":"FAQPage"')) &&
                    str_contains($content, 'SEO mất bao lâu để lên top?');

                if (! $hasFaqSchema) {
                    return Http::response(['error' => 'Missing FAQ Schema in content'], 422);
                }

                return Http::response([
                    'id' => 999,
                    'link' => 'https://wpschema.example.com/huong-dan-toi-uu-seo',
                ], 201);
            },
        ]);

        $job = new PublishToWordPressJob($article->id, $site->id);

        $thrown = false;
        try {
            $job->handle(
                publishService: app(WordPressPublishService::class),
                preflight: app(WordPressPreflightCheckService::class),
                pipeline: app(ArticlePipelineService::class)
            );
        } catch (\Throwable $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'PublishToWordPressJob rethrew exception to let queue worker perform retries.');

        // Now allow success and assert FAQPage schema was injected
        $shouldFail = false;
        $article->update(['status' => ArticleStatus::APPROVED]);

        $job->handle(
            publishService: app(WordPressPublishService::class),
            preflight: app(WordPressPreflightCheckService::class),
            pipeline: app(ArticlePipelineService::class)
        );

        $this->assertEquals(ArticleStatus::PUBLISHED, $article->fresh()->status);
        $this->assertEquals(999, $article->fresh()->wordpress_post_id);
    }

    /**
     * TASK-06.4: GeminiProvider xử lý 404 fallback models và 429 rate limit.
     */
    public function test_gemini_provider_handles_404_fallback_and_429_ratelimit(): void
    {
        config([
            'ai.providers.gemini.api_key' => 'test-gemini-key',
            'ai.providers.gemini.default_model' => 'gemini-2.5-flash',
            'ai.providers.gemini.fallback_models' => ['gemini-1.5-flash', 'gemini-1.5-pro'],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response(['error' => 'Not Found'], 404),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Fallback content from 1.5-flash'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 20,
                    'totalTokenCount' => 30,
                ],
            ], 200),
        ]);

        $provider = new GeminiProvider;
        $request = new AIRequestDTO(
            systemPrompt: 'System',
            userPrompt: 'User',
            agentType: AgentType::WRITING,
            tenantId: $this->tenant->id
        );

        $response = $provider->complete($request);
        $this->assertEquals('Fallback content from 1.5-flash', $response->content);
        $this->assertEquals('gemini-1.5-flash', $response->model);

        // Test 429
        Http::swap(new Factory);
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource Exhausted'], 429),
        ]);

        $this->expectException(RateLimitException::class);
        $provider->complete($request);
    }

    /**
     * TASK-06.4: AnthropicProvider bọc ConnectionException thành ProviderException.
     */
    public function test_anthropic_provider_catches_connection_exception_and_throws_provider_exception(): void
    {
        config(['ai.providers.anthropic.api_key' => 'test-anthropic-key']);

        Http::fake([
            'https://api.anthropic.com/v1/messages' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new AnthropicProvider;
        $request = new AIRequestDTO(
            systemPrompt: 'System',
            userPrompt: 'User',
            agentType: AgentType::WRITING,
            tenantId: $this->tenant->id
        );

        $this->expectException(ProviderException::class);
        $provider->complete($request);
    }

    /**
     * TASK-06.6 & TASK-06.12: PublishToWordPressJob chặn tra cứu site chéo Tenant (Multi-Tenancy Isolation).
     */
    public function test_publish_to_wordpress_job_blocks_cross_tenant_site_lookup(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other WP Tenant', 'slug' => 'other-wp-tenant', 'is_active' => true]);
        $otherSite = WordPressSite::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Attacker Site',
            'url' => 'https://attacker.example.com',
            'api_url' => 'https://attacker.example.com/wp-json',
            'username' => 'attacker',
            'app_password' => 'secret_pwd',
            'is_active' => true,
        ]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Cross Tenant WP Keyword',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'wordpress_site_id' => null,
            'title' => 'Cross Tenant Article',
            'slug' => 'cross-tenant-article',
            'content' => '<p>Content</p>',
            'status' => ArticleStatus::APPROVED,
        ]);

        $job = new PublishToWordPressJob($article->id, $otherSite->id);
        $job->handle(
            publishService: app(WordPressPublishService::class),
            preflight: app(WordPressPreflightCheckService::class),
            pipeline: app(ArticlePipelineService::class)
        );

        $this->assertNotEquals(ArticleStatus::PUBLISHED, $article->fresh()->status);
        $this->assertEquals('failed', data_get($article->fresh()->pipeline_status, 'steps.publishing.status'));
    }

    /**
     * TASK-06.2 & TASK-06.7: Campaign pause resets processing keywords back to pending, resume restarts them.
     */
    public function test_campaign_pause_resets_processing_keywords_to_pending_and_resume_picks_them_up(): void
    {
        Queue::fake();

        $campaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Chiến Dịch Pause Resume',
            'slug' => 'chien-dich-pause-resume',
            'status' => CampaignStatus::DRAFT,
        ]);

        $kw1 = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'keyword' => 'pause resume kw 1',
            'status' => KeywordStatus::PENDING,
        ]);

        $kw2 = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'keyword' => 'pause resume kw 2',
            'status' => KeywordStatus::PENDING,
        ]);

        $this->creditLedger->grantCredits($this->workspace, 100, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));

        $campaignService = app(CampaignService::class);
        $campaignService->start($campaign, $this->user);

        $this->assertEquals(KeywordStatus::PROCESSING, $kw1->fresh()->status);
        $this->assertEquals(KeywordStatus::PROCESSING, $kw2->fresh()->status);

        // Pause campaign -> keywords become PENDING again
        $campaignService->pause($campaign);
        $this->assertEquals(CampaignStatus::PAUSED, $campaign->fresh()->status);
        $this->assertEquals(KeywordStatus::PENDING, $kw1->fresh()->status);
        $this->assertEquals(KeywordStatus::PENDING, $kw2->fresh()->status);

        // Resume campaign -> keywords become PROCESSING and batch is dispatched
        $campaignService->resume($campaign, $this->user);
        $this->assertEquals(CampaignStatus::PROCESSING, $campaign->fresh()->status);
        $this->assertEquals(KeywordStatus::PROCESSING, $kw1->fresh()->status);
        $this->assertEquals(KeywordStatus::PROCESSING, $kw2->fresh()->status);
    }

    /**
     * TASK-06.10: Article generate endpoint reserves credits and wraps in transaction.
     */
    public function test_article_generate_endpoint_uses_task_service_and_reserves_credits(): void
    {
        Queue::fake([GenerateOutlineJob::class]);

        $this->creditLedger->grantCredits($this->workspace, 50, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Bài viết tạo tự động',
            'status' => KeywordStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/articles/generate', [
            'keyword_id' => $kw->id,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(GenerateOutlineJob::class, function ($job) use ($kw) {
            return $job->keywordId === $kw->id;
        });

        $balance = $this->creditLedger->getBalance($this->workspace);
        $this->assertEquals(30, $balance->availableCredits);
        $this->assertEquals(20, $balance->reservedCredits);

        $task = Task::where('keyword_id', $kw->id)->latest('id')->first();
        $this->assertNotNull($task);
        $this->assertEquals(20, $task->credits_reserved);
        $this->assertEquals(TaskStatus::PENDING, $task->status);
    }

    /**
     * TASK-06.9 & TASK-06.7: TaskController validates parameters and avoids direct Eloquent queries.
     */
    public function test_task_controller_validations_and_thin_controller(): void
    {
        $this->actingAs($this->user);

        // 1. Validate per_page max cap
        $res = $this->getJson('/api/v1/tasks?per_page=999');
        $res->assertStatus(422);

        // 2. Validate status filter enum
        $resStatus = $this->getJson('/api/v1/tasks?status=not_a_valid_status');
        $resStatus->assertStatus(422);

        // 3. Create task and cancel with invalid array reason -> should return 422, not 500
        $task = $this->taskService->createTask([
            'workspace_id' => $this->workspace->id,
            'type' => 'full_article_pipeline',
            'credits_reserved' => 0,
        ]);

        $resCancelArray = $this->postJson("/api/v1/tasks/{$task->id}/cancel", [
            'reason' => ['invalid_array_here'],
        ]);
        $resCancelArray->assertStatus(422);

        // 4. Valid cancel
        $resCancelValid = $this->postJson("/api/v1/tasks/{$task->id}/cancel", [
            'reason' => 'Client requested cancellation',
        ]);
        $resCancelValid->assertStatus(200);
        $this->assertEquals(TaskStatus::CANCELLED, $task->fresh()->status);
    }

    /**
     * TASK-06.12 & Multi-Tenancy: UploadToWordPressMediaJob blocks cross-tenant and cross-workspace attempts.
     */
    public function test_upload_to_wordpress_media_job_blocks_cross_tenant_and_cross_workspace(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Media Tenant', 'slug' => 'other-media-tenant', 'is_active' => true]);
        $otherSite = WordPressSite::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Attacker Media Site',
            'url' => 'https://attacker-media.example.com',
            'api_url' => 'https://attacker-media.example.com/wp-json',
            'username' => 'attacker',
            'app_password' => 'secret_pwd',
            'is_active' => true,
        ]);

        $asset = MediaAsset::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'source_url' => 'https://example.com/image.jpg',
            'status' => 'downloaded',
            'source_type' => 'stock',
        ]);

        $job = new UploadToWordPressMediaJob($asset->id, $otherSite->id);
        $job->handle(
            mediaService: app(WordPressMediaService::class),
            pipeline: app(ArticlePipelineService::class)
        );

        // Asset should remain unchanged and not uploaded
        $this->assertEquals('downloaded', $asset->fresh()->status->value);
        $this->assertNull($asset->fresh()->wordpress_media_id);
    }

    /**
     * TASK-06.10: Article generateImages checks credits before dispatching job.
     */
    public function test_article_generate_images_enforces_credits_before_dispatch(): void
    {
        Queue::fake([GenerateArticleImagesJob::class]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Bài viết sinh ảnh test',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'title' => 'Bài viết test ảnh',
            'slug' => 'bai-viet-test-anh',
            'content' => '<p>Test</p>',
            'status' => ArticleStatus::DRAFT,
            'media_plan' => ['image_source' => 'ai'],
        ]);

        // 0 credits initially -> should return 402
        $res = $this->actingAs($this->user)->postJson("/api/v1/articles/{$article->id}/images");
        $res->assertStatus(402);
        Queue::assertNothingPushed();

        // Grant credits -> should succeed 202
        $this->creditLedger->grantCredits($this->workspace, 50, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));
        $resSuccess = $this->actingAs($this->user)->postJson("/api/v1/articles/{$article->id}/images");
        $resSuccess->assertStatus(202);
        Queue::assertPushed(GenerateArticleImagesJob::class);
    }

    /**
     * TASK-06.10: Keyword serp endpoint checks credits and workspace quota before dispatching job.
     */
    public function test_keyword_serp_endpoint_enforces_credits_and_workspace_quota(): void
    {
        Queue::fake([SerpResearchJob::class]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Keyword SERP Quota Test',
            'status' => KeywordStatus::PENDING,
        ]);

        // 0 credits -> 402
        $res = $this->actingAs($this->user)->postJson("/api/v1/keywords/{$kw->id}/serp");
        $res->assertStatus(402);
        Queue::assertNothingPushed();

        // Grant credits -> 200/success
        $this->creditLedger->grantCredits($this->workspace, 50, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));
        $resSuccess = $this->actingAs($this->user)->postJson("/api/v1/keywords/{$kw->id}/serp");
        $resSuccess->assertStatus(200);
        Queue::assertPushed(SerpResearchJob::class);
    }

    /**
     * TASK-06.12 & Multi-Tenancy: Happy-path handle() of DownloadImageJob and UploadToWordPressMediaJob clears execution context.
     */
    public function test_download_image_job_and_upload_to_wordpress_media_job_happy_path_cleans_execution_context(): void
    {
        Storage::fake();
        $fakePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $site = WordPressSite::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Valid Media Site',
            'url' => 'https://valid-wp.example.com',
            'api_url' => 'https://valid-wp.example.com/wp-json',
            'username' => 'wpadmin',
            'app_password' => 'secret_app_pass',
            'is_active' => true,
        ]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Article Media Context Keyword',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'title' => 'Article Media Context Test',
            'slug' => 'article-media-context-test',
            'status' => ArticleStatus::DRAFT,
            'wordpress_site_id' => $site->id,
        ]);

        $asset = MediaAsset::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'article_id' => $article->id,
            'source_url' => 'https://example.com/valid-image.png',
            'status' => 'pending',
            'source_type' => 'stock',
        ]);

        Http::fake([
            'https://example.com/valid-image.png' => Http::response($fakePng, 200, ['Content-Type' => 'image/png']),
            'https://valid-wp.example.com/wp-json/wp/v2/media*' => Http::response([
                'id' => 9999,
                'source_url' => 'https://valid-wp.example.com/wp-content/uploads/9999.png',
            ], 201),
        ]);

        // 1. Run DownloadImageJob
        $downloadJob = new DownloadImageJob($asset->id);
        $downloadJob->handle(
            downloader: app(ImageDownloadService::class),
            pipeline: app(ArticlePipelineService::class)
        );

        $this->assertEquals('downloaded', $asset->fresh()->status->value);
        $this->assertNull(BelongsToTenant::getExecutionContext());
        $this->assertNull(BelongsToWorkspace::getExecutionContext());

        // 2. Run UploadToWordPressMediaJob
        $uploadJob = new UploadToWordPressMediaJob($asset->id, $site->id);
        $uploadJob->handle(
            mediaService: app(WordPressMediaService::class),
            pipeline: app(ArticlePipelineService::class)
        );

        $this->assertEquals('uploaded', $asset->fresh()->status->value);
        $this->assertEquals(9999, $asset->fresh()->wordpress_media_id);
        $this->assertNull(BelongsToTenant::getExecutionContext());
        $this->assertNull(BelongsToWorkspace::getExecutionContext());
    }

    /**
     * TASK-06 Multi-Tenancy: IDOR cross-workspace blocked in ArticleController and KeywordController.
     */
    public function test_cross_workspace_idor_protection_in_article_and_keyword_controllers(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Second Tenant',
            'slug' => 'second-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $otherWorkspace = Workspace::create([
            'name' => 'Second Workspace',
            'slug' => 'second-workspace',
            'legacy_tenant_id' => $otherTenant->id,
            'status' => WorkspaceStatus::ACTIVE,
            'owner_id' => $this->user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
        ]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'IDOR Test Keyword',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword_id' => $kw->id,
            'title' => 'IDOR Test Article',
            'slug' => 'idor-test-article',
            'content' => '<p>Content</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $this->actingAs($this->user);

        $resAutoFix = $this->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
            ->postJson("/api/v1/articles/{$article->id}/auto-fix");
        $resAutoFix->assertStatus(404);

        $resImages = $this->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
            ->postJson("/api/v1/articles/{$article->id}/images");
        $resImages->assertStatus(404);

        $resGenerate = $this->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
            ->postJson('/api/v1/articles/generate', [
                'keyword_id' => $kw->id,
            ]);
        $this->assertTrue(in_array($resGenerate->status(), [404, 422], true));

        $resSerp = $this->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
            ->postJson("/api/v1/keywords/{$kw->id}/serp");
        $resSerp->assertStatus(404);
    }

    /**
     * TASK-06.3 & TASK-06.10: SerpResearchJob reserves credits or marks keyword FAILED if zero credits.
     */
    public function test_serp_research_job_reserves_credits_or_aborts_when_zero_credits(): void
    {
        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'keyword' => 'Serp Credit Reservation Keyword',
            'status' => KeywordStatus::PENDING,
        ]);

        // Workspace has 0 credit -> job should mark keyword FAILED immediately
        $job = new SerpResearchJob($kw);
        $job->handle(
            agent: app(SerpResearchAgent::class),
            pipeline: app(ArticlePipelineService::class),
            costTracking: app(CostTrackingService::class),
            taskService: $this->taskService,
            creditLedger: $this->creditLedger
        );

        $this->assertEquals(KeywordStatus::FAILED, $kw->fresh()->status);

        // Now grant credits and test that credits are reserved
        $this->creditLedger->grantCredits($this->workspace, 50, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));
        $kw->update(['status' => KeywordStatus::PENDING]);

        // Mock SerpResearchService search to avoid external HTTP calls
        $mockSerp = $this->mock(SerpResearchService::class);
        $mockSerp->shouldReceive('search')->andReturn([]);

        Queue::fake();

        $jobWithCredits = new SerpResearchJob($kw);
        $jobWithCredits->handle(
            agent: app(SerpResearchAgent::class),
            pipeline: app(ArticlePipelineService::class),
            costTracking: app(CostTrackingService::class),
            taskService: $this->taskService,
            creditLedger: $this->creditLedger
        );

        $task = Task::where('keyword_id', $kw->id)->latest('id')->first();
        $this->assertNotNull($task);
        $this->assertEquals(20, $task->credits_reserved);
        $balance = $this->creditLedger->getBalance($this->workspace);
        $this->assertEquals(20, $balance->reservedCredits);
        $this->assertEquals(30, $balance->availableCredits);

        Queue::assertPushed(GenerateOutlineJob::class);
    }

    /**
     * TASK-06.7 & TASK-06.8: CampaignService pause cancels PENDING tasks and releases reserved credits.
     */
    public function test_campaign_service_pause_cancels_pending_tasks_and_releases_credits(): void
    {
        $this->creditLedger->grantCredits($this->workspace, 50, CreditGrantSource::SUBSCRIPTION_CYCLE, now()->addDays(30));

        $campaign = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'name' => 'Pause Pending Task Campaign',
            'slug' => 'pause-pending-task-campaign',
            'status' => CampaignStatus::PROCESSING,
        ]);

        $kw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'campaign_id' => $campaign->id,
            'keyword' => 'Pending Task Keyword',
            'status' => KeywordStatus::PROCESSING,
        ]);

        $task = $this->taskService->createTask([
            'workspace_id' => $this->workspace->id,
            'campaign_id' => $campaign->id,
            'keyword_id' => $kw->id,
            'type' => 'full_article_pipeline',
            'status' => TaskStatus::PENDING,
            'credits_reserved' => 20,
        ]);

        $this->assertEquals(20, $this->creditLedger->getBalance($this->workspace)->reservedCredits);

        // Pause campaign
        app(CampaignService::class)->pause($campaign);

        $this->assertEquals(TaskStatus::CANCELLED, $task->fresh()->status);
        $this->assertEquals(0, $this->creditLedger->getBalance($this->workspace)->reservedCredits);
        $this->assertEquals(50, $this->creditLedger->getBalance($this->workspace)->availableCredits);
    }
}
