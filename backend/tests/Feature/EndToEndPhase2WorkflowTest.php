<?php

namespace Tests\Feature;

use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\Bulk\ProcessBulkBatchJob;
use App\Models\Article;
use App\Models\BulkBatch;
use App\Models\ContentGap;
use App\Models\InternalLinkSuggestion;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Article\ContentStudioService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Bulk\BulkGenerationService;
use App\Services\SEO\ContentGapService;
use App\Services\SEO\InternalLinkingEngine;
use App\Services\SEO\KeywordResearchService;
use App\Services\SEO\SeoScoringEngine;
use App\Services\SEO\TopicClusterService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndPhase2WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected Website $website;

    protected Tenant $competitorTenant;

    protected User $competitorUser;

    protected Workspace $competitorWorkspace;

    protected Website $competitorWebsite;

    protected CreditLedgerService $creditLedger;

    protected BrandKnowledgeService $brandService;

    protected KeywordResearchService $keywordService;

    protected TopicClusterService $clusterService;

    protected ContentGapService $gapService;

    protected BulkGenerationService $bulkService;

    protected SeoScoringEngine $seoEngine;

    protected InternalLinkingEngine $linkEngine;

    protected ContentStudioService $studioService;

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
        $this->brandService = app(BrandKnowledgeService::class);
        $this->keywordService = app(KeywordResearchService::class);
        $this->clusterService = app(TopicClusterService::class);
        $this->gapService = app(ContentGapService::class);
        $this->bulkService = app(BulkGenerationService::class);
        $this->seoEngine = app(SeoScoringEngine::class);
        $this->linkEngine = app(InternalLinkingEngine::class);
        $this->studioService = app(ContentStudioService::class);
        Queue::fake([ProcessBulkBatchJob::class]);

        // 1. Khởi tạo Workspace chính
        $this->tenant = Tenant::create([
            'name' => 'Aura Global Media',
            'slug' => 'aura-global-media',
            'plan' => 'enterprise',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'chief-seo@auramedia.vn',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Aura Enterprise HQ',
            'slug' => 'aura-enterprise-hq',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $this->user->default_workspace_id = $this->workspace->id;
        $this->user->save();

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->website = Website::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Aura Marketing Portal',
            'domain' => 'auramarketing.vn',
            'url' => 'https://auramarketing.vn',
            'default_language' => 'vi',
            'niche' => 'Digital Marketing & SEO',
        ]);

        // Cấp 2,000 credit ban đầu cho Workspace
        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 2000]);

        // 2. Khởi tạo Workspace đối thủ (Kiểm tra cô lập dữ liệu - Multi-Tenancy Isolation)
        $this->competitorTenant = Tenant::create([
            'name' => 'Rival Media Group',
            'slug' => 'rival-media-group',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->competitorUser = User::factory()->create([
            'email' => 'spy@rivalmedia.vn',
            'tenant_id' => $this->competitorTenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->competitorWorkspace = Workspace::create([
            'owner_id' => $this->competitorUser->id,
            'legacy_tenant_id' => $this->competitorTenant->id,
            'name' => 'Rival Spy Workspace',
            'slug' => 'rival-spy-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $this->competitorUser->default_workspace_id = $this->competitorWorkspace->id;
        $this->competitorUser->save();

        WorkspaceMember::create([
            'workspace_id' => $this->competitorWorkspace->id,
            'user_id' => $this->competitorUser->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->competitorWebsite = Website::create([
            'workspace_id' => $this->competitorWorkspace->id,
            'name' => 'Rival Media Site',
            'domain' => 'rivalmedia.vn',
            'url' => 'https://rivalmedia.vn',
            'default_language' => 'vi',
            'niche' => 'Digital Marketing',
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
     * KỊCH BẢN E2E TOÀN DIỆN PHASE 2:
     * 1. [P2.5] Thiết lập Brand Knowledge Base (USP, Persona, Taboo Words).
     * 2. [P2.4] Xây dựng Topic Cluster với Pillar Keyword & các bài bổ trợ.
     * 3. [P2.4] Phân tích Content Gap so với đối thủ và phát hiện cơ hội SEO.
     * 4. [P2.9] Khởi tạo Bulk Batch 10 bài viết theo lô, giữ 180 credit (18/bài).
     * 5. [P2.9] Xử lý lô bài viết hàng loạt, quyết toán chính xác 180 credit không thất thoát.
     * 6. [P2.7] Kiểm định chất lượng SEO tự động bằng mã nguồn thuần 100 điểm (<50ms, 0 credit).
     * 7. [P2.8] Quét đồ thị liên kết nội bộ, đề xuất câu chèn Anchor Text và chấp thuận.
     * 8. [P2.6] Biên tập tương tác trên Content Studio với AI Copilot Assist (-1 credit).
     * 9. [P2.9] Xác nhận tính cô lập Multi-Tenant 100% qua tất cả các phân hệ.
     */
    public function test_complete_phase_2_autonomous_workflow_e2e(): void
    {
        Sanctum::actingAs($this->user);
        $headers = ['X-Workspace-Id' => (string) $this->workspace->id];

        // ---------------------------------------------------------------------
        // BƯỚC 1: [P2.5] Thiết lập Brand Knowledge Base
        // ---------------------------------------------------------------------
        $brandResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/brand-knowledge', [
                'website_id' => $this->website->id,
                'brand_name' => 'Aura SEO AI',
                'tagline' => 'Tự động hóa sản xuất nội dung SEO chuẩn chuyên gia',
                'tone_of_voice' => 'authoritative',
                'unique_selling_points' => [
                    'Tích hợp 9 Agent AI phối hợp chuyên sâu',
                    'Engine chấm điểm SEO Deterministic 100 điểm',
                    'Tự động phòng chống thất thoát credit',
                ],
                'target_personas' => [
                    'Trưởng phòng Marketing - Chi phí viết bài outsource quá cao',
                ],
                'taboo_words' => ['lừa đảo', 'hàng nhái', 'kém chất lượng'],
                'preferred_terms' => ['AI Content Intelligence', 'Top 1 Google'],
                'is_default' => true,
            ]);

        $brandResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.brand_name', 'Aura SEO AI');

        $brandId = $brandResponse->json('data.id');
        $this->assertDatabaseHas('brand_knowledge_bases', ['id' => $brandId, 'workspace_id' => $this->workspace->id]);

        // ---------------------------------------------------------------------
        // BƯỚC 2: [P2.4] Xây dựng Topic Cluster (Pillar & Supporting Keywords)
        // ---------------------------------------------------------------------
        $pillarKw = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'keyword' => 'dịch vụ seo tổng thể aura',
            'search_volume' => 5400,
            'difficulty' => 35,
            'search_intent' => 'commercial',
            'status' => KeywordStatus::COMPLETED,
        ]);

        $clusterResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/topic-clusters', [
                'website_id' => $this->website->id,
                'name' => 'Cụm Dịch Vụ SEO Tổng Thể 2026',
                'pillar_keyword_id' => $pillarKw->id,
                'description' => 'Cụm chủ đề chiến lược dẫn đầu ngành SEO tự động',
                'target_audience' => 'Doanh nghiệp B2B & E-commerce',
            ]);

        $clusterResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $clusterId = $clusterResponse->json('data.id');

        // ---------------------------------------------------------------------
        // BƯỚC 3: [P2.4] Phân tích Content Gap so với đối thủ
        // ---------------------------------------------------------------------
        $gap = ContentGap::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'competitor_domain' => 'competitor-rank1.vn',
            'target_keyword' => 'chiến lược entity seo cho doanh nghiệp',
            'search_volume' => 3200,
            'keyword_difficulty' => 25,
            'competitor_rank' => 3,
            'our_rank' => null,
            'opportunity_score' => 84.50,
            'status' => 'identified',
        ]);

        $this->assertDatabaseHas('content_gaps', [
            'id' => $gap->id,
            'workspace_id' => $this->workspace->id,
        ]);

        // ---------------------------------------------------------------------
        // BƯỚC 4 & 5: [P2.9] Khởi tạo & Xử lý Bulk Batch 10 bài viết theo lô
        // ---------------------------------------------------------------------
        $bulkKeywords = [
            'chiến lược entity seo cho doanh nghiệp',
            'tối ưu cấu trúc internal links tự động',
            'cách viết bài chuẩn seo lên top nhanh',
            'phân tích đối thủ qua cấu trúc h1 h4',
            'nghiên cứu search intent người dùng',
            'tự động sinh ảnh minh họa theo h2',
            'tối ưu tốc độ trang cho thiết bị di động',
            'cách tạo schema faq page bằng json ld',
            'chiến thuật cụm chủ đề topic cluster',
            'kinh nghiệm seo web b2b hiệu quả cao',
        ];

        // 10 bài viết -> Ưu đãi 18 credit/bài = giữ trước 180 credit
        $bulkCreateResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/bulk-batches', [
                'website_id' => $this->website->id,
                'name' => 'Lô Sản Xuất Content Trọng Điểm Q4',
                'keywords' => $bulkKeywords,
                'tone' => 'authoritative',
                'target_word_count' => 1500,
                'include_images' => true,
            ]);

        $bulkCreateResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 10)
            ->assertJsonPath('data.credits_reserved', 180);

        $batchUuid = $bulkCreateResponse->json('data.uuid');
        $batchModel = BulkBatch::where('uuid', $batchUuid)->firstOrFail();

        // Số dư sau khi giữ: 2000 - 180 = 1820 khả dụng, 180 tạm giữ
        $account = $this->creditLedger->getOrCreateAccount($this->workspace)->fresh();
        $this->assertEquals(1820, $account->available_credits);
        $this->assertEquals(180, $account->reserved_credits);

        // Chạy xử lý lô bài viết qua service
        $processedBatch = $this->bulkService->processBatch($batchModel);

        $this->assertEquals('completed', $processedBatch->status);
        $this->assertEquals(10, $processedBatch->completed_items);
        $this->assertEquals(0, $processedBatch->failed_items);
        $this->assertEquals(180, $processedBatch->credits_settled);
        $this->assertEquals(0, $processedBatch->credits_reserved);

        // Kiểm tra số dư sau khi quyết toán: 1820 khả dụng, 0 tạm giữ (180 đã trừ thật)
        $account = $this->creditLedger->getOrCreateAccount($this->workspace)->fresh();
        $this->assertEquals(1820, $account->available_credits);
        $this->assertEquals(0, $account->reserved_credits);

        // ---------------------------------------------------------------------
        // BƯỚC 6: [P2.7] Kiểm định SEO Deterministic bằng SeoScoringEngine
        // ---------------------------------------------------------------------
        $sampleArticle = Article::where('workspace_id', $this->workspace->id)
            ->where('focus_keyword', 'chiến lược entity seo cho doanh nghiệp')
            ->firstOrFail();

        // Cập nhật nội dung đầy đủ để engine chấm điểm chính xác
        $richHtml = '<h1>Chiến lược entity seo cho doanh nghiệp năm 2026</h1>'
            .'<p>Khám phá chiến lược entity seo cho doanh nghiệp giúp nâng tầm thương hiệu và tối ưu thứ hạng bền vững.</p>'
            .'<h2>Tầm quan trọng của Entity SEO</h2>'
            .'<p>Entity SEO giúp Google hiểu rõ thực thể doanh nghiệp của bạn. Nội dung này có độ dài phù hợp và từ khóa tự nhiên.</p>'
            .'<img src="https://example.com/image.webp" alt="Chiến lược entity seo cho doanh nghiệp chuẩn chất lượng" />'
            .'<h3>Các bước triển khai chi tiết</h3>'
            .'<p>Bước đầu tiên là khai báo Schema và xây dựng liên kết ngữ nghĩa chặt chẽ.</p>'
            .'<a href="https://auramarketing.vn/bai-viet-cu">Tham khảo thêm tại đây</a>';

        $sampleArticle->update([
            'content' => $richHtml,
            'seo_title' => 'Chiến lược entity seo cho doanh nghiệp hiệu quả nhất',
            'seo_description' => 'Khám phá chiến lược entity seo cho doanh nghiệp giúp bứt phá doanh số và khẳng định vị thế thương hiệu ngay hôm nay.',
            'word_count' => 600,
        ]);

        $scoreResponse = $this->withHeaders($headers)
            ->postJson("/api/v1/articles/{$sampleArticle->id}/score-audit");

        $scoreResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $auditData = $scoreResponse->json('data');
        $score = $auditData['score'] ?? $auditData['seo_score'] ?? $auditData['total_score'] ?? 0;
        $this->assertGreaterThan(50, $score);
        $this->assertTrue(isset($auditData['criteria']) || isset($auditData['breakdown']));

        // Xác nhận SeoScoringEngine 0 tốn credit (số dư vẫn là 1820)
        $account = $this->creditLedger->getOrCreateAccount($this->workspace)->fresh();
        $this->assertEquals(1820, $account->available_credits);

        // ---------------------------------------------------------------------
        // BƯỚC 7: [P2.8] Đồ thị Internal Linking & Chấp thuận gợi ý liên kết
        // ---------------------------------------------------------------------
        $targetArticle = Article::where('workspace_id', $this->workspace->id)
            ->where('id', '!=', $sampleArticle->id)
            ->firstOrFail();

        $linkSuggestion = InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $sampleArticle->id,
            'target_article_id' => $targetArticle->id,
            'suggested_anchor_text' => 'dịch vụ seo chuyên sâu',
            'context_sentence' => 'Để tối ưu hóa toàn diện, bạn nên kết hợp dịch vụ seo chuyên sâu của chúng tôi.',
            'relevance_score' => 92.50,
            'status' => 'pending',
        ]);

        $acceptResponse = $this->withHeaders($headers)
            ->postJson("/api/v1/internal-links/{$linkSuggestion->id}/accept");

        $acceptResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertEquals('accepted', $linkSuggestion->fresh()->status);

        // ---------------------------------------------------------------------
        // BƯỚC 8: [P2.6] AI Content Studio Assist (Trừ 1 credit chuẩn)
        // ---------------------------------------------------------------------
        $assistResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/content-studio/assist', [
                'article_id' => $sampleArticle->id,
                'action' => 'rewrite',
                'selected_text' => 'Entity SEO giúp Google hiểu rõ thực thể doanh nghiệp của bạn.',
                'instruction' => 'Viết lại theo giọng văn chuyên gia phân tích',
            ]);

        $assistResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Xác nhận 1 credit được trừ: 1820 -> 1819 khả dụng
        $account = $this->creditLedger->getOrCreateAccount($this->workspace)->fresh();
        $this->assertEquals(1819, $account->available_credits);

        // ---------------------------------------------------------------------
        // BƯỚC 9: Xác nhận tính cô lập Multi-Tenancy 100% đối với đối thủ
        // ---------------------------------------------------------------------
        Sanctum::actingAs($this->competitorUser);
        $competitorHeaders = ['X-Workspace-Id' => (string) $this->competitorWorkspace->id];

        // Đối thủ không thể xem Bulk Batch của Workspace 1
        $this->withHeaders($competitorHeaders)
            ->getJson("/api/v1/bulk-batches/{$batchUuid}/progress")
            ->assertStatus(404);

        // Đối thủ không thể xem Brand Knowledge của Workspace 1
        $this->withHeaders($competitorHeaders)
            ->getJson("/api/v1/brand-knowledge/{$brandId}")
            ->assertStatus(404);

        // Đối thủ không thể xem Topic Cluster của Workspace 1
        $this->withHeaders($competitorHeaders)
            ->getJson("/api/v1/topic-clusters/{$clusterId}")
            ->assertStatus(404);

        // Đối thủ không thể xem Article Studio của Workspace 1
        $this->withHeaders($competitorHeaders)
            ->getJson("/api/v1/content-studio/{$sampleArticle->id}")
            ->assertStatus(404);
    }
}
