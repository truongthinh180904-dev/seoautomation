<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\Tools\Adapters\AiImageToolAdapter;
use App\Services\Chatbot\Tools\Adapters\BulkGenerationAdapter;
use App\Services\Chatbot\Tools\Adapters\CompetitorAnalysisAdapter;
use App\Services\Chatbot\Tools\Adapters\ContentGapAdapter;
use App\Services\Chatbot\Tools\Adapters\ContentStudioWriteAdapter;
use App\Services\Chatbot\Tools\Adapters\InternalLinksAdapter;
use App\Services\Chatbot\Tools\Adapters\KeywordResearchAdapter;
use App\Services\Chatbot\Tools\Adapters\SeoScoreAdapter;
use App\Services\Chatbot\Tools\Adapters\StockImageToolAdapter;
use App\Services\Chatbot\Tools\Adapters\TopicClusterAdapter;
use App\Services\Chatbot\Tools\Adapters\WordPressPublishAdapter;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\Chatbot\Tools\SeoToolRegistry;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class SeoToolCallingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Website $websiteA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Website $websiteB;

    protected CreditLedgerService $creditLedger;

    protected SeoToolRegistry $registry;

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

        // 1. Thiết lập Workspace A
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

        // Cấp 500 credit vào ví Workspace A
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 500,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Test seed credit allocation'
        );

        // 2. Thiết lập Workspace B để kiểm tra Multi-Tenant Isolation
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'competitor-tenant-b',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'manager@competitor.com',
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

        // Cấp credit cho Workspace B
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceB,
            amount: 200,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $this->registry = app(SeoToolRegistry::class);
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
     * P2.5.4.1: Kiểm tra SeoToolRegistry đăng ký đầy đủ 10+ công cụ chuẩn hóa.
     */
    public function test_seo_tool_registry_registers_all_prebuilt_tools(): void
    {
        $this->assertGreaterThanOrEqual(10, $this->registry->count());

        $expectedTools = [
            'keyword_research',
            'competitor_analysis',
            'topic_cluster',
            'content_gap',
            'content_studio_write',
            'seo_score',
            'internal_links',
            'stock_image_free',
            'ai_image',
            'bulk_generation',
            'wordpress_publish',
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertTrue($this->registry->has($toolName), "Registry thiếu công cụ: {$toolName}");
            $tool = $this->registry->get($toolName);
            $this->assertInstanceOf(ChatToolInterface::class, $tool);
            $this->assertSame($toolName, $tool->getName());
            $this->assertNotEmpty($tool->getTitle());
            $this->assertNotEmpty($tool->getDescription());
            $this->assertIsArray($tool->getParametersSchema());
        }

        $definitions = $this->registry->getDefinitions();
        $this->assertArrayHasKey('competitor_analysis', $definitions);
        $this->assertArrayHasKey('stock_image_free', $definitions);
        $this->assertArrayHasKey('wordpress_publish', $definitions);
        $this->assertTrue($definitions['wordpress_publish']['is_sensitive']);
        $this->assertTrue($definitions['bulk_generation']['is_sensitive']);
        $this->assertFalse($definitions['seo_score']['is_sensitive']);
    }

    /**
     * P2.5.4.2: CompetitorAnalysisAdapter chỉ phân tích Top 3 đối thủ và lấy #1 làm Mỏ Neo Chuẩn Xác.
     */
    public function test_competitor_analysis_adapter_focuses_strictly_on_top_3_and_anchors_on_number_one(): void
    {
        /** @var CompetitorAnalysisAdapter $adapter */
        $adapter = $this->registry->get('competitor_analysis');
        $this->assertInstanceOf(CompetitorAnalysisAdapter::class, $adapter);

        $result = $adapter->execute($this->workspaceA, [
            'keyword' => 'bọc răng sứ thẩm mỹ',
            'language' => 'vi',
            'country' => 'vn',
        ], $this->userA);

        $this->assertSame('competitor_analysis', $result['tool']);
        $this->assertSame('bọc răng sứ thẩm mỹ', $result['keyword']);

        // Xác nhận nghiêm ngặt: Tổng số đối thủ được cào & phân tích tối đa là 3
        $this->assertLessThanOrEqual(3, $result['total_analyzed']);
        $this->assertLessThanOrEqual(3, count($result['top_3_competitors']));

        // Xác nhận có đối thủ xếp hạng #1 làm Anchor Benchmark
        $this->assertNotNull($result['anchor_competitor']);
        $anchor = $result['anchor_competitor'];
        $this->assertArrayHasKey('domain', $anchor);
        $this->assertArrayHasKey('word_count', $anchor);
        $this->assertArrayHasKey('headings_count', $anchor);
        $this->assertArrayHasKey('lsi_keywords', $anchor);

        // Xác nhận có khuyến nghị chiến lược vượt trội đối thủ đứng đầu #1
        $this->assertArrayHasKey('outrank_recommendations', $result);
        $recs = $result['outrank_recommendations'];
        $this->assertGreaterThan($anchor['word_count'], $recs['target_word_count']);
        $this->assertGreaterThanOrEqual(($anchor['headings_count']['h2'] ?? 5) + 2, $recs['target_h2_count']);
        $this->assertNotEmpty($recs['recommended_lsi_entities']);
        $this->assertStringContainsString('vượt vị trí #1', $recs['strategy_note']);
    }

    /**
     * P2.5.4.2: CompetitorAnalysisAdapter từ chối từ khóa rỗng.
     */
    public function test_competitor_analysis_adapter_validates_empty_keyword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Từ khóa phân tích đối thủ không được để trống.');

        $adapter = $this->registry->get('competitor_analysis');
        $adapter->execute($this->workspaceA, ['keyword' => '   ']);
    }

    /**
     * P2.5.4.3: StockImageToolAdapter tìm kiếm ảnh 100% không bản quyền và nén WebP dưới 150KB.
     */
    public function test_stock_image_tool_adapter_guarantees_commercial_free_and_webp_under_150kb(): void
    {
        /** @var StockImageToolAdapter $adapter */
        $adapter = $this->registry->get('stock_image_free');
        $this->assertInstanceOf(StockImageToolAdapter::class, $adapter);

        $result = $adapter->execute($this->workspaceA, [
            'query' => 'nha khoa bọc răng sứ',
            'limit' => 3,
        ], $this->userA);

        $this->assertSame('stock_image_free', $result['tool']);
        $this->assertTrue($result['is_commercial_free']);
        $this->assertStringContainsString('Free Commercial Use', $result['license']);
        $this->assertCount(3, $result['images']);

        foreach ($result['images'] as $image) {
            $this->assertTrue($image['is_commercial_free']);
            $this->assertSame('webp', $image['format']);
            $this->assertSame('image/webp', $image['mime_type']);

            // Tiêu chuẩn vàng: Dung lượng nhỏ hơn hoặc bằng 150KB (153,600 bytes)
            $this->assertTrue($image['is_under_150kb']);
            $this->assertLessThanOrEqual(153600, $image['size_bytes']);
            $this->assertNotEmpty($image['alt']);
            $this->assertNotEmpty($image['caption']);
        }
    }

    /**
     * P2.5.4.3: Kiểm tra trực tiếp thuật toán nén WebP bằng GD của StockImageToolAdapter.
     */
    public function test_stock_image_compress_to_webp_ensures_valid_gd_webp_below_150kb(): void
    {
        /** @var StockImageToolAdapter $adapter */
        $adapter = $this->registry->get('stock_image_free');

        $synthetic = $adapter->generateOptimizedStockWebp(1200, 675, 'Thử nghiệm nén WebP');
        $this->assertSame('image/webp', $synthetic['mime_type']);
        $this->assertLessThanOrEqual(153600, $synthetic['size_bytes']);
        $this->assertGreaterThan(0, $synthetic['size_bytes']);

        $compressed = $adapter->compressToWebp($synthetic['contents'], 153600);
        $this->assertSame('image/webp', $compressed['mime_type']);
        $this->assertLessThanOrEqual(153600, $compressed['size_bytes']);
    }

    /**
     * P2.5.4.4: KeywordResearchAdapter gọi thành công và phân loại volume, KD, intent.
     */
    public function test_keyword_research_adapter_executes_successfully(): void
    {
        $adapter = $this->registry->get('keyword_research');
        $this->assertInstanceOf(KeywordResearchAdapter::class, $adapter);

        $result = $adapter->execute($this->workspaceA, [
            'query' => 'trồng răng implant',
            'limit' => 15,
        ], $this->userA);

        $this->assertSame('keyword_research', $result['tool']);
        $this->assertSame('trồng răng implant', $result['query']);
        $this->assertGreaterThan(0, $result['total_keywords']);
        $this->assertNotEmpty($result['keywords']);
        $this->assertSame(2, $result['credits_deducted']);
    }

    /**
     * P2.5.4.4: TopicClusterAdapter khởi tạo cấu trúc cụm chủ đề đa tầng.
     */
    public function test_topic_cluster_adapter_generates_cluster_structure(): void
    {
        $adapter = $this->registry->get('topic_cluster');
        $this->assertInstanceOf(TopicClusterAdapter::class, $adapter);

        $result = $adapter->execute($this->workspaceA, [
            'pillar_keyword' => 'chăm sóc răng miệng',
            'website_id' => $this->websiteA->id,
        ], $this->userA);

        $this->assertSame('topic_cluster', $result['tool']);
        $this->assertSame('chăm sóc răng miệng', $result['pillar_keyword']);
        $this->assertGreaterThan(0, $result['total_clusters']);
        $this->assertNotEmpty($result['clusters']);
    }

    /**
     * P2.5.4.4: ContentGapAdapter phát hiện khoảng trống nội dung với đối thủ.
     */
    public function test_content_gap_adapter_detects_competitor_gaps(): void
    {
        $adapter = $this->registry->get('content_gap');
        $this->assertInstanceOf(ContentGapAdapter::class, $adapter);

        $result = $adapter->execute($this->workspaceA, [
            'competitor_domain' => 'nhakhoaparis.vn',
            'website_id' => $this->websiteA->id,
        ], $this->userA);

        $this->assertSame('content_gap', $result['tool']);
        $this->assertSame('nhakhoaparis.vn', $result['competitor_domain']);
        $this->assertArrayHasKey('total_gaps_found', $result);
        $this->assertArrayHasKey('high_opportunity_count', $result);
    }

    /**
     * P2.5.4.4: ContentStudioWriteAdapter hỗ trợ biên tập rewrite và khởi tạo bài viết.
     */
    public function test_content_studio_write_adapter_handles_rewrite_and_new_article(): void
    {
        $adapter = $this->registry->get('content_studio_write');
        $this->assertInstanceOf(ContentStudioWriteAdapter::class, $adapter);

        // Nhánh 1: Viết lại đoạn văn (rewrite)
        $rewriteResult = $adapter->execute($this->workspaceA, [
            'action' => 'rewrite',
            'text' => 'Bọc răng sứ là phương pháp làm đẹp răng phổ biến.',
        ], $this->userA);

        $this->assertSame('content_studio_write', $rewriteResult['tool']);
        $this->assertSame('rewrite', $rewriteResult['action']);
        $this->assertNotEmpty($rewriteResult['result_text']);
        $this->assertSame(1, $rewriteResult['credits_deducted']);

        // Nhánh 2: Khởi tạo dàn ý bài viết mới (write)
        $writeResult = $adapter->execute($this->workspaceA, [
            'action' => 'write',
            'keyword' => 'giá bọc răng sứ kim loại',
            'website_id' => $this->websiteA->id,
        ], $this->userA);

        $this->assertSame('content_studio_write', $writeResult['tool']);
        $this->assertArrayHasKey('article_id', $writeResult);
        $this->assertNotEmpty($writeResult['outline']);
    }

    /**
     * P2.5.4.4: SeoScoreAdapter chấm điểm SEO 100 điểm tức thì, 0 credit.
     */
    public function test_seo_score_adapter_evaluates_article_at_zero_credit(): void
    {
        $adapter = $this->registry->get('seo_score');
        $this->assertInstanceOf(SeoScoreAdapter::class, $adapter);
        $this->assertSame(0, $adapter->getEstimatedCredits());

        $sampleContent = '<h1>Hướng dẫn tẩy trắng răng tại nhà an toàn</h1>'
            .'<p>Tẩy trắng răng tại nhà đang là xu hướng được nhiều người tìm kiếm để có nụ cười rạng ngời.</p>'
            .'<h2>1. Các phương pháp tẩy trắng răng an toàn</h2>'
            .'<p>Sử dụng máng tẩy trắng kết hợp gel nha khoa chính hãng mang lại hiệu quả cao.</p>'
            .'<h2>2. Lưu ý sau khi tẩy trắng răng</h2>'
            .'<p>Hạn chế uống cà phê, trà đậm và thực phẩm có màu trong 48 giờ đầu tiên.</p>';

        $result = $adapter->execute($this->workspaceA, [
            'content' => $sampleContent,
            'keyword' => 'tẩy trắng răng',
            'title' => 'Hướng dẫn tẩy trắng răng tại nhà an toàn',
        ]);

        $this->assertSame('seo_score', $result['tool']);
        $this->assertArrayHasKey('total_score', $result);
        $this->assertArrayHasKey('readability_score', $result);
        $this->assertSame(0, $result['credits_deducted']);
    }

    /**
     * P2.5.4.4: InternalLinksAdapter quét gợi ý liên kết nội bộ theo thực thể.
     */
    public function test_internal_links_adapter_scans_linking_opportunities(): void
    {
        $adapter = $this->registry->get('internal_links');
        $this->assertInstanceOf(InternalLinksAdapter::class, $adapter);

        $result = $adapter->execute($this->workspaceA, [
            'website_id' => $this->websiteA->id,
        ], $this->userA);

        $this->assertSame('internal_links', $result['tool']);
        $this->assertSame($this->websiteA->id, $result['website_id']);
        $this->assertArrayHasKey('total_suggestions', $result);
        $this->assertSame(0, $result['credits_deducted']);
    }

    /**
     * P2.5.4.4: AiImageToolAdapter tạo MediaAsset WebP chuẩn SEO.
     */
    public function test_ai_image_tool_adapter_generates_webp_media_asset(): void
    {
        $adapter = $this->registry->get('ai_image');
        $this->assertInstanceOf(AiImageToolAdapter::class, $adapter);
        $this->assertSame(5, $adapter->getEstimatedCredits());

        $result = $adapter->execute($this->workspaceA, [
            'heading' => 'Quy trình cấy ghép răng Implant chuẩn y khoa',
        ], $this->userA);

        $this->assertSame('ai_image', $result['tool']);
        $this->assertSame('webp', $result['format']);
        $this->assertTrue($result['is_under_150kb']);
        $this->assertArrayHasKey('media_asset_id', $result);
    }

    /**
     * P2.5.4.4: BulkGenerationAdapter đánh dấu hành động nhạy cảm và đưa vào Queue.
     */
    public function test_bulk_generation_adapter_requires_approval_and_queues_batch(): void
    {
        Queue::fake();

        $adapter = $this->registry->get('bulk_generation');
        $this->assertInstanceOf(BulkGenerationAdapter::class, $adapter);
        $this->assertTrue($adapter->isSensitive(), 'Bulk Generation bắt buộc phải là hành động nhạy cảm.');

        $result = $adapter->execute($this->workspaceA, [
            'keywords' => [
                'niềng răng trong suốt',
                'niềng răng mắc cài kim loại',
                'niềng răng mắc cài sứ',
            ],
            'website_id' => $this->websiteA->id,
        ], $this->userA);

        $this->assertSame('bulk_generation', $result['tool']);
        $this->assertSame(3, $result['total_items']);
        $this->assertTrue($result['is_sensitive']);
        $this->assertArrayHasKey('batch_id', $result);
    }

    /**
     * P2.5.4.4: WordPressPublishAdapter đánh dấu nhạy cảm và đẩy bài sang Queue xuất bản.
     */
    public function test_wordpress_publish_adapter_requires_approval_and_dispatches_push(): void
    {
        Queue::fake();

        $wpSite = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'name' => 'Smile Main Blog',
            'url' => 'https://nhakhoasmile.vn',
            'api_url' => 'https://nhakhoasmile.vn/wp-json',
            'username' => 'wpadmin',
            'app_password' => 'secret_app_password',
            'is_active' => true,
            'connection_status' => 'connected',
        ]);

        $keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'Cẩm nang chăm sóc răng miệng toàn diện',
            'status' => KeywordStatus::PENDING,
        ]);

        $article = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword_id' => $keywordA->id,
            'user_id' => $this->userA->id,
            'wordpress_site_id' => $wpSite->id,
            'title' => 'Cẩm nang chăm sóc răng miệng toàn diện',
            'slug' => 'cam-nang-cham-soc-rang-mieng',
            'content' => '<p>Nội dung bài viết đầy đủ.</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $adapter = $this->registry->get('wordpress_publish');
        $this->assertInstanceOf(WordPressPublishAdapter::class, $adapter);
        $this->assertTrue($adapter->isSensitive(), 'WordPress Publish bắt buộc phải là hành động nhạy cảm.');

        $result = $adapter->execute($this->workspaceA, [
            'article_id' => $article->id,
            'site_id' => $wpSite->id,
        ], $this->userA);

        $this->assertSame('wordpress_publish', $result['tool']);
        $this->assertSame($article->id, $result['article_id']);
        $this->assertSame($wpSite->id, $result['wordpress_site_id']);
        $this->assertTrue($result['is_sensitive']);
        $this->assertSame('dispatched_to_queue', $result['action_status']);

        // Xác nhận bài viết đã được đổi sang APPROVED để được phép dispatch
        $article->refresh();
        $this->assertSame(ArticleStatus::APPROVED, $article->status);
    }

    /**
     * Kiểm tra Multi-Tenancy Isolation: Workspace A không thể gọi WordPressPublishAdapter đối với Article của Workspace B.
     */
    public function test_multi_tenancy_isolation_prevents_cross_workspace_tool_execution(): void
    {
        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Bài viết bí mật của Workspace B',
            'status' => KeywordStatus::PENDING,
        ]);

        $articleB = Article::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'keyword_id' => $keywordB->id,
            'user_id' => $this->userB->id,
            'title' => 'Bài viết bí mật của Workspace B',
            'slug' => 'secret-article-workspace-b',
            'content' => '<p>Dữ liệu riêng tư của Tenant B.</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $this->expectException(ModelNotFoundException::class);

        // Workspace A cố tình gọi publish bài viết thuộc sở hữu của Workspace B
        $this->registry->execute('wordpress_publish', $this->workspaceA, [
            'article_id' => $articleB->id,
        ], $this->userA);
    }

    /**
     * SeoToolRegistry từ chối công cụ không tồn tại.
     */
    public function test_registry_throws_exception_on_unknown_tool(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Công cụ SEO 'unknown_magic_tool' không tồn tại");

        $this->registry->execute('unknown_magic_tool', $this->workspaceA, []);
    }
}
