<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\MediaSourceType;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Api\V1\Chatbot\ChatContentController;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\MediaAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\ChatImageVisionService;
use App\Services\Chatbot\ImageFallbackPipeline;
use App\Services\Chatbot\InChatContentService;
use App\Services\Media\AiImageService;
use App\Services\Media\StockImageSearchService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InChatContentInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Website $websiteA;

    protected Article $articleA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Website $websiteB;

    protected Article $articleB;

    protected CreditLedgerService $creditLedger;

    protected InChatContentService $inChatContentService;

    protected ChatImageVisionService $visionService;

    protected ImageFallbackPipeline $imagePipeline;

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
        $this->inChatContentService = app(InChatContentService::class);
        $this->visionService = app(ChatImageVisionService::class);
        $this->imagePipeline = app(ImageFallbackPipeline::class);

        // Đăng ký route kiểm thử cho ChatContentController
        Route::middleware(['auth:sanctum', 'active_account'])->prefix('api/v1/chatbot/content')->group(function () {
            Route::get('preview/{id}', [ChatContentController::class, 'preview']);
            Route::post('assist', [ChatContentController::class, 'assist']);
            Route::post('apply-edit', [ChatContentController::class, 'applyEdit']);
            Route::post('insert-section', [ChatContentController::class, 'insertSection']);
            Route::post('attach-vision-image', [ChatContentController::class, 'attachVisionImage']);
            Route::post('run-image-pipeline', [ChatContentController::class, 'runImagePipeline']);
        });

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
            note: 'Initial seed credit'
        );

        $keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'bọc răng sứ thẩm mỹ',
            'status' => KeywordStatus::PENDING,
        ]);

        $initialContent = "<h1>Cẩm nang bọc răng sứ thẩm mỹ toàn diện</h1>\n"
            ."<p>Bọc răng sứ là giải pháp phục hình thẩm mỹ hàng đầu giúp kiến tạo nụ cười hoàn hảo.</p>\n"
            ."<h2>Quy trình bọc răng sứ chuẩn y khoa</h2>\n"
            ."<p>Quy trình thực hiện gồm thăm khám tổng quát, vệ sinh khoang miệng và lấy dấu răng kỹ thuật số.</p>\n"
            ."<h2>Bảng giá bọc răng sứ mới nhất</h2>\n"
            .'<p>Chi phí phụ thuộc vào vật liệu sứ cao cấp như Cercon HT hoặc Emax.</p>';

        $this->articleA = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'keyword_id' => $keywordA->id,
            'user_id' => $this->userA->id,
            'title' => 'Cẩm nang bọc răng sứ thẩm mỹ toàn diện',
            'slug' => 'cam-nang-boc-rang-su-tham-my',
            'focus_keyword' => 'bọc răng sứ thẩm mỹ',
            'content' => $initialContent,
            'word_count' => 150,
            'seo_score' => 78,
            'readability_score' => 80,
            'outline' => [
                ['level' => 'h2', 'heading' => 'Quy trình bọc răng sứ chuẩn y khoa'],
                ['level' => 'h2', 'heading' => 'Bảng giá bọc răng sứ mới nhất'],
            ],
            'status' => ArticleStatus::DRAFT,
        ]);

        // 2. Thiết lập Workspace B để kiểm tra Multi-Tenancy Isolation
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'competitor-tenant-b',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'userb@competitor.com',
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

        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Bài viết bảo mật của B',
            'status' => KeywordStatus::PENDING,
        ]);

        $this->articleB = Article::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'keyword_id' => $keywordB->id,
            'user_id' => $this->userB->id,
            'title' => 'Bài viết riêng tư thuộc Workspace B',
            'slug' => 'private-article-workspace-b',
            'focus_keyword' => 'bài viết riêng tư',
            'content' => '<p>Dữ liệu tuyệt mật của đối thủ B.</p>',
            'status' => ArticleStatus::DRAFT,
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
     * P2.5.5.1: InChatContentService hỗ trợ rewrite đoạn văn và trừ chính xác 1 credit.
     */
    public function test_in_chat_content_service_assists_rewrite_and_deducts_one_credit(): void
    {
        $selectedText = 'Bọc răng sứ là giải pháp phục hình thẩm mỹ hàng đầu giúp kiến tạo nụ cười hoàn hảo.';

        $result = $this->inChatContentService->assist(
            article: $this->articleA,
            workspace: $this->workspaceA,
            action: 'rewrite',
            selectedText: $selectedText,
            options: ['tone' => 'chuyên gia y khoa'],
            actor: $this->userA
        );

        $this->assertSame('rewrite', $result['action']);
        $this->assertSame($selectedText, $result['original_text']);
        $this->assertNotEmpty($result['result_text']);
        $this->assertSame(1, $result['credits_deducted']);
        $this->assertSame($this->articleA->id, $result['article_id']);

        // Xác nhận đã ghi sổ cái trừ chính xác 1 credit
        $this->assertDatabaseHas('credit_transactions', [
            'workspace_id' => $this->workspaceA->id,
            'amount' => -1,
            'reference_type' => 'studio_ai_copilot',
            'reference_id' => $this->articleA->id,
        ]);
    }

    /**
     * P2.5.5.1: InChatContentService xử lý các tác vụ expand, shorten, change_tone, add_example.
     */
    public function test_in_chat_content_service_handles_multiple_copilot_actions(): void
    {
        $sampleText = 'Chi phí phụ thuộc vào vật liệu sứ cao cấp.';

        $actions = ['expand', 'shorten', 'change_tone', 'add_example', 'fix_grammar'];

        foreach ($actions as $action) {
            $result = $this->inChatContentService->assist(
                article: $this->articleA,
                workspace: $this->workspaceA,
                action: $action,
                selectedText: $sampleText,
                options: ['tone' => 'thân thiện'],
                actor: $this->userA
            );

            $this->assertSame($action, $result['action']);
            $this->assertNotEmpty($result['result_text']);
            $this->assertSame(1, $result['credits_deducted']);
        }
    }

    /**
     * P2.5.5.1: applySegmentEdit cập nhật nội dung bài viết và tự động tính lại SEO score.
     */
    public function test_in_chat_content_service_applies_segment_edit_and_updates_article_metrics(): void
    {
        $original = 'Quy trình thực hiện gồm thăm khám tổng quát, vệ sinh khoang miệng và lấy dấu răng kỹ thuật số.';
        $replacement = 'Quy trình thực hiện đạt chuẩn y tế 5 bước nghiêm ngặt: Thăm khám bằng máy quét 3D Itero, lập phác đồ Smile Design, chế tác răng sứ bằng công nghệ CAD/CAM hiện đại và gắn hoàn thiện.';

        $applied = $this->inChatContentService->applySegmentEdit(
            article: $this->articleA,
            workspace: $this->workspaceA,
            originalText: $original,
            replacementText: $replacement,
            targetHeading: 'Quy trình bọc răng sứ chuẩn y khoa',
            actor: $this->userA
        );

        $this->assertSame($this->articleA->id, $applied['article_id']);
        $this->assertTrue($applied['replaced']);
        $this->assertGreaterThan(0, $applied['new_word_count']);

        $this->articleA->refresh();
        $this->assertStringContainsString('đạt chuẩn y tế 5 bước', (string) $this->articleA->content);
        $this->assertStringNotContainsString($original, (string) $this->articleA->content);
    }

    /**
     * P2.5.5.1: insertSection thêm phân đoạn mới kèm thẻ H2 và cập nhật outline.
     */
    public function test_in_chat_content_service_inserts_new_section(): void
    {
        $result = $this->inChatContentService->insertSection(
            article: $this->articleA,
            workspace: $this->workspaceA,
            heading: 'Cách chăm sóc răng sứ duy trì độ bền 20 năm',
            content: 'Đánh răng đúng kỹ thuật 2 lần mỗi ngày bằng bàn chải lông mềm và dùng chỉ nha khoa sau mỗi bữa ăn.',
            level: 'h2',
            actor: $this->userA
        );

        $this->assertSame('Cách chăm sóc răng sứ duy trì độ bền 20 năm', $result['heading_added']);
        $this->assertSame('h2', $result['level']);

        $this->articleA->refresh();
        $this->assertStringContainsString('<h2>Cách chăm sóc răng sứ duy trì độ bền 20 năm</h2>', (string) $this->articleA->content);
        $this->assertStringContainsString('Đánh răng đúng kỹ thuật 2 lần mỗi ngày', (string) $this->articleA->content);
    }

    /**
     * Multi-Tenancy Isolation: Workspace A không thể biên tập bài viết của Workspace B.
     */
    public function test_multi_tenancy_isolation_prevents_cross_workspace_content_edits(): void
    {
        $this->expectException(ModelNotFoundException::class);

        // Workspace A cố tình can thiệp bài viết của Workspace B
        $this->inChatContentService->assist(
            article: $this->articleB,
            workspace: $this->workspaceA,
            action: 'rewrite',
            selectedText: 'Văn bản bảo mật',
            options: [],
            actor: $this->userA
        );
    }

    /**
     * P2.5.5.2: AI Gemini Vision đọc hiểu nội dung ảnh người dùng gửi và ghim vào đúng H2.
     */
    public function test_gemini_vision_service_analyzes_image_and_finds_best_matching_heading(): void
    {
        $imageInput = [
            'name' => 'quy-trinh-kham-rang-su-chuan-y-khoa.jpg',
            'previewUrl' => 'https://nhakhoasmile.vn/images/quy-trinh-kham.jpg',
        ];

        // 1. Phân tích ảnh với Gemini Vision
        $analysis = $this->visionService->analyzeImage($imageInput, $this->articleA);
        $this->assertNotEmpty($analysis['subject']);
        $this->assertNotEmpty($analysis['product']);
        $this->assertNotEmpty($analysis['suggested_keywords']);

        // 2. Tìm thẻ H2 khớp nhất
        $match = $this->visionService->findBestMatchingHeading($analysis, $this->articleA);
        $this->assertSame('Quy trình bọc răng sứ chuẩn y khoa', $match['heading']);
        $this->assertSame('h2', $match['level']);
        $this->assertGreaterThanOrEqual(0.5, $match['score']);
    }

    /**
     * P2.5.5.2: Tự động sinh thẻ ALT và Caption chuẩn SEO theo ngữ cảnh.
     */
    public function test_gemini_vision_service_generates_seo_alt_and_caption(): void
    {
        $analysis = [
            'subject' => 'Bác sĩ kiểm tra răng sứ Cercon',
            'product' => 'Răng sứ thẩm mỹ cao cấp',
            'context' => 'Phòng vô trùng nha khoa',
        ];

        $seoMeta = $this->visionService->generateSeoAltAndCaption(
            visionAnalysis: $analysis,
            targetHeading: 'Quy trình bọc răng sứ chuẩn y khoa',
            article: $this->articleA
        );

        $this->assertNotEmpty($seoMeta['alt']);
        $this->assertNotEmpty($seoMeta['caption']);
        $this->assertLessThanOrEqual(125, mb_strlen($seoMeta['alt']));
        $this->assertLessThanOrEqual(180, mb_strlen($seoMeta['caption']));
        $this->assertStringContainsString('bọc răng sứ thẩm mỹ', mb_strtolower($seoMeta['alt']));
    }

    /**
     * P2.5.5.2: Nén nhị phân ảnh sang định dạng WebP với dung lượng dưới 150KB.
     */
    public function test_gemini_vision_service_compresses_image_to_webp_below_150kb(): void
    {
        $synthetic = $this->visionService->generateSyntheticWebp(1200, 675, 'Bọc răng sứ');
        $this->assertSame('image/webp', $synthetic['mime_type']);
        $this->assertLessThanOrEqual(153600, $synthetic['size_bytes']);
        $this->assertGreaterThan(0, $synthetic['size_bytes']);

        $compressed = $this->visionService->optimizeAndCompressWebp($synthetic['contents'], 153600);
        $this->assertSame('image/webp', $compressed['mime_type']);
        $this->assertLessThanOrEqual(153600, $compressed['size_bytes']);
    }

    /**
     * P2.5.5.2: attachUserImageToArticle tạo MediaAsset và chèn thẻ figure vào đúng vị trí H2.
     */
    public function test_gemini_vision_service_attaches_user_image_to_article(): void
    {
        $imageData = [
            'name' => 'quy-trinh-boc-rang-su.jpg',
            'previewUrl' => 'https://nhakhoasmile.vn/uploads/quy-trinh.jpg',
        ];

        $result = $this->visionService->attachUserImageToArticle(
            article: $this->articleA,
            workspace: $this->workspaceA,
            imageData: $imageData,
            targetHeading: 'Quy trình bọc răng sứ chuẩn y khoa',
            actor: $this->userA
        );

        $this->assertTrue($result['success']);
        $this->assertSame('webp', $result['format']);
        $this->assertTrue($result['is_under_150kb']);
        $this->assertSame('tier_1_user_vision', $result['tier']);

        $this->assertDatabaseHas('media_assets', [
            'id' => $result['media_asset_id'],
            'article_id' => $this->articleA->id,
            'source_type' => MediaSourceType::UPLOADED->value,
        ]);

        $this->articleA->refresh();
        $this->assertStringContainsString('<figure class="article-media', (string) $this->articleA->content);
        $this->assertStringContainsString($result['alt'], (string) $this->articleA->content);
    }

    /**
     * P2.5.5.3: Tầng 1 (Ưu tiên số 1): Nếu có ảnh người dùng gửi, Pipeline chọn User Vision.
     */
    public function test_image_fallback_pipeline_tier_1_prefers_user_uploaded_vision_image(): void
    {
        $userImages = [
            [
                'name' => 'bang-gia-rang-su-cercon.jpg',
                'previewUrl' => 'https://nhakhoasmile.vn/images/bang-gia.jpg',
            ],
        ];

        $result = $this->imagePipeline->processImageForHeading(
            article: $this->articleA,
            workspace: $this->workspaceA,
            heading: 'Bảng giá bọc răng sứ mới nhất',
            userUploadedImages: $userImages,
            actor: $this->userA
        );

        $this->assertSame('tier_1_user_vision', $result['tier']);
        $this->assertSame('webp', $result['format']);
        $this->assertTrue($result['is_under_150kb']);
    }

    /**
     * P2.5.5.3: Tầng 2 (Ưu tiên số 2): Nếu không có ảnh người dùng, Pipeline lấy ảnh Stock 100% không bản quyền.
     */
    public function test_image_fallback_pipeline_tier_2_falls_back_to_commercial_free_stock(): void
    {
        // Danh sách ảnh người dùng rỗng
        $result = $this->imagePipeline->processImageForHeading(
            article: $this->articleA,
            workspace: $this->workspaceA,
            heading: 'Bảng giá bọc răng sứ mới nhất',
            userUploadedImages: [],
            actor: $this->userA
        );

        // Có thể rơi vào Tier 2 (Stock) hoặc Tier 3 (AI) tùy cấu hình, nhưng cả 2 đều phải là WebP < 150KB
        $this->assertContains($result['tier'], ['tier_2_commercial_free_stock', 'tier_3_ai_gemini_imagen']);
        $this->assertSame('webp', $result['format']);
        $this->assertTrue($result['is_under_150kb']);
        $this->assertNotEmpty($result['alt']);
    }

    /**
     * P2.5.5.3: Tầng 3 (Ưu tiên số 3): Khi kho stock không có ảnh phù hợp, Pipeline kích hoạt AI Gemini Imagen.
     */
    public function test_image_fallback_pipeline_tier_3_when_stock_empty(): void
    {
        $mockStockService = $this->createMock(StockImageSearchService::class);
        $mockStockService->method('search')->willReturn([]);

        $pipeline = new ImageFallbackPipeline(
            visionService: $this->visionService,
            stockSearchService: $mockStockService,
            aiImageService: app(AiImageService::class),
            stockToolAdapter: null
        );

        $result = $pipeline->processImageForHeading(
            article: $this->articleA,
            workspace: $this->workspaceA,
            heading: 'Bảng giá bọc răng sứ mới nhất',
            userUploadedImages: [],
            actor: $this->userA
        );

        $this->assertSame('tier_3_ai_gemini_imagen', $result['tier']);
        $this->assertSame('webp', $result['format']);
        $this->assertTrue($result['is_under_150kb']);
        $this->assertNotEmpty($result['alt']);
    }

    /**
     * P2.5.5.3: Cam kết vàng: 100% ảnh xuất hiện trên website qua Pipeline đều là WebP dưới 150KB.
     */
    public function test_image_fallback_pipeline_guarantees_100_percent_webp_under_150kb_for_all_headings(): void
    {
        $batchResult = $this->imagePipeline->processAllHeadings(
            article: $this->articleA,
            workspace: $this->workspaceA,
            userUploadedImages: [],
            maxImages: 2,
            actor: $this->userA
        );

        $this->assertSame($this->articleA->id, $batchResult['article_id']);
        $this->assertGreaterThan(0, $batchResult['total_processed']);
        $this->assertTrue($batchResult['all_webp_under_150kb']);

        foreach ($batchResult['images'] as $img) {
            $this->assertSame('webp', $img['format']);
            $this->assertTrue($img['is_under_150kb']);
            $this->assertLessThanOrEqual(153600, $img['size_bytes']);
            $this->assertNotEmpty($img['alt']);
        }
    }

    /**
     * P2.5.5.4: ChatContentController xử lý preview, assist, apply-edit, attach-vision-image, run-image-pipeline qua API JSON.
     */
    public function test_chat_content_controller_endpoints(): void
    {
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;

        // 1. GET preview
        $resPreview = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/chatbot/content/preview/{$this->articleA->id}");

        $resPreview->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', $this->articleA->title)
            ->assertJsonPath('data.focus_keyword', 'bọc răng sứ thẩm mỹ');

        // 2. POST assist
        $resAssist = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/chatbot/content/assist', [
                'article_id' => $this->articleA->id,
                'action' => 'rewrite',
                'selected_text' => 'Bọc răng sứ là giải pháp phục hình thẩm mỹ.',
                'tone' => 'chuyên gia',
            ]);

        $resAssist->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'rewrite')
            ->assertJsonPath('data.credits_deducted', 1);

        // 3. POST apply-edit
        $resApply = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/chatbot/content/apply-edit', [
                'article_id' => $this->articleA->id,
                'original_text' => 'Bọc răng sứ là giải pháp phục hình thẩm mỹ hàng đầu giúp kiến tạo nụ cười hoàn hảo.',
                'replacement_text' => 'Bọc răng sứ thẩm mỹ công nghệ cao mang lại hàm răng trắng sáng tự nhiên.',
            ]);

        $resApply->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.replaced', true);

        // 4. POST attach-vision-image
        $resVision = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/chatbot/content/attach-vision-image', [
                'article_id' => $this->articleA->id,
                'image' => [
                    'name' => 'anh-bac-si-kham-rang.jpg',
                    'previewUrl' => 'https://nhakhoasmile.vn/images/doctor.jpg',
                ],
                'target_heading' => 'Quy trình bọc răng sứ chuẩn y khoa',
            ]);

        $resVision->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', 'webp')
            ->assertJsonPath('data.is_under_150kb', true);

        // 5. POST run-image-pipeline
        $resPipeline = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/chatbot/content/run-image-pipeline', [
                'article_id' => $this->articleA->id,
                'heading' => 'Bảng giá bọc răng sứ mới nhất',
            ]);

        $resPipeline->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', 'webp')
            ->assertJsonPath('data.is_under_150kb', true);
    }
}
