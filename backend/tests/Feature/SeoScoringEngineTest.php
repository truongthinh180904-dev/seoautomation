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
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\SEO\SeoScoringEngine;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected Website $website;

    protected Keyword $keyword;

    protected Article $article;

    protected Tenant $otherTenant;

    protected User $otherUser;

    protected Workspace $otherWorkspace;

    protected Article $otherArticle;

    protected CreditLedgerService $creditLedger;

    protected SeoScoringEngine $engine;

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
        $this->engine = app(SeoScoringEngine::class);

        // 1. Setup Tenant & Workspace 1
        $this->tenant = Tenant::create([
            'name' => 'SEO Scoring Tenant',
            'slug' => 'seo-scoring-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'seoscorer@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'SEO Scoring Workspace',
            'slug' => 'seo-scoring-workspace',
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
            'name' => 'Aura SEO Hub',
            'domain' => 'auraseohub.vn',
            'url' => 'https://auraseohub.vn',
            'default_language' => 'vi',
            'niche' => 'Marketing',
        ]);

        $this->keyword = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'keyword' => 'trợ lý ai viết bài seo',
            'search_volume' => 2400,
            'difficulty' => 28,
            'cpc' => 0.50,
            'status' => KeywordStatus::COMPLETED,
        ]);

        // Sample rich, high-quality article content
        $highQualityContent = $this->generateSampleHighQualityHtml('trợ lý ai viết bài seo');

        $this->article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'keyword_id' => $this->keyword->id,
            'user_id' => $this->user->id,
            'title' => 'Trợ Lý AI Viết Bài SEO Đỉnh Cao Dành Cho Marketer',
            'slug' => 'tro-ly-ai-viet-bai-seo-dinh-cao',
            'content' => $highQualityContent,
            'seo_title' => 'Trợ Lý AI Viết Bài SEO Đỉnh Cao Dành Cho Marketer',
            'seo_description' => 'Khám phá công nghệ trợ lý AI viết bài SEO đỉnh cao giúp tăng 300% traffic tự nhiên. Tìm hiểu chi tiết ngay hôm nay để dẫn đầu!',
            'focus_keyword' => 'trợ lý ai viết bài seo',
            'word_count' => 1100,
            'status' => ArticleStatus::DRAFT,
            'faqs' => [
                ['question' => 'Trợ lý AI viết bài SEO là gì?', 'answer' => 'Là công cụ ứng dụng trí tuệ nhân tạo hỗ trợ sản xuất nội dung.'],
                ['question' => 'Bài viết AI có bị Google phạt không?', 'answer' => 'Google tập trung vào chất lượng nội dung hữu ích theo chuẩn E-E-A-T.'],
                ['question' => 'Làm sao để tối ưu mật độ từ khóa chuẩn?', 'answer' => 'Duy trì tỷ lệ từ 1% đến 2% và sử dụng các từ khóa ngữ nghĩa LSI.'],
            ],
            'internal_links' => [
                ['url' => '/blog/semantic-seo', 'anchor' => 'Semantic SEO toàn tập'],
                ['url' => '/blog/keyword-research', 'anchor' => 'Nghiên cứu từ khóa chuyên sâu'],
            ],
        ]);

        // 2. Setup Workspace 2 (Dành cho kiểm thử cô lập Multi-Tenant Isolation)
        $this->otherTenant = Tenant::create([
            'name' => 'Isolated Other Tenant',
            'slug' => 'isolated-other-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'isolatedscorer@example.com',
            'tenant_id' => $this->otherTenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'owner_id' => $this->otherUser->id,
            'legacy_tenant_id' => $this->otherTenant->id,
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $this->otherUser->default_workspace_id = $this->otherWorkspace->id;
        $this->otherUser->save();

        WorkspaceMember::create([
            'workspace_id' => $this->otherWorkspace->id,
            'user_id' => $this->otherUser->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->otherArticle = Article::create([
            'tenant_id' => $this->otherTenant->id,
            'workspace_id' => $this->otherWorkspace->id,
            'user_id' => $this->otherUser->id,
            'keyword_id' => $this->keyword->id,
            'title' => 'Bài viết thuộc workspace khác',
            'slug' => 'bai-viet-khac',
            'content' => '<p>Dữ liệu nội bộ workspace khác.</p>',
            'status' => ArticleStatus::DRAFT,
            'word_count' => 10,
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
     * Test người dùng chưa đăng nhập không thể gọi score-audit.
     */
    public function test_unauthenticated_user_cannot_audit_article(): void
    {
        $this->postJson("/api/v1/articles/{$this->article->id}/score-audit")
            ->assertStatus(401);

        $this->postJson('/api/v1/articles/score-audit', [
            'content' => '<p>Nội dung thử nghiệm</p>',
        ])->assertStatus(401);
    }

    /**
     * Test ngăn chặn truy cập trái phép chéo Workspace (Multi-Tenant Isolation).
     */
    public function test_user_cannot_audit_article_from_another_workspace_tenant_isolation(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->otherArticle->id}/score-audit");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /**
     * Test engine chấm điểm 100 điểm với đầy đủ 9 tiêu chí SEO và thời gian chạy < 50ms.
     */
    public function test_seo_scoring_engine_returns_accurate_100_point_score_across_9_criteria(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->article->id}/score-audit");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_score',
                    'seo_score',
                    'readability_score',
                    'status',
                    'execution_time_ms',
                    'criteria' => [
                        'h1_title' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'meta_description' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'heading_structure' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'keyword_density' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'image_alts' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'readability' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'links' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'faqs' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                        'schema_markup' => ['name', 'score', 'max_score', 'passed', 'status', 'message'],
                    ],
                    'summary' => [
                        'word_count',
                        'character_count',
                        'reading_time_minutes',
                        'keyword',
                        'keyword_count',
                        'keyword_density',
                        'headings',
                        'images_count',
                        'links_count',
                        'faqs_count',
                        'has_schema',
                    ],
                    'recommendations',
                    'warnings',
                ],
            ]);

        $data = $response->json('data');

        // Điểm số phải cao (>= 80) cho bài viết mẫu chuẩn
        $this->assertGreaterThanOrEqual(80, $data['total_score']);
        $this->assertLessThanOrEqual(100, $data['total_score']);

        // Tiêu chí thời gian thực thi: < 50ms (Deterministic PHP)
        $this->assertLessThan(50.0, (float) $data['execution_time_ms']);

        // Xác nhận tổng điểm tối đa của 9 tiêu chí đúng bằng 100
        $sumMaxScore = 0;
        foreach ($data['criteria'] as $criterion) {
            $sumMaxScore += $criterion['max_score'];
        }
        $this->assertSame(100, $sumMaxScore);
    }

    /**
     * Test phát hiện nhồi nhét từ khóa (Keyword Stuffing) và trừ điểm nặng.
     */
    public function test_seo_scoring_detects_keyword_stuffing_and_applies_penalty(): void
    {
        // Tạo nội dung nhồi nhét từ khóa lặp lại 40 lần trong 150 từ (mật độ > 15%)
        $kw = 'trợ lý ai viết bài seo';
        $stuffingContent = '<h2>Khám phá trợ lý AI</h2><p>';
        for ($i = 0; $i < 30; $i++) {
            $stuffingContent .= "{$kw} là công cụ tuyệt vời. ";
        }
        $stuffingContent .= '</p>';

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->article->id}/score-audit", [
                'content' => $stuffingContent,
                'focus_keyword' => $kw,
                'persist' => false,
            ]);

        $response->assertOk();
        $densityCriterion = $response->json('data.criteria.keyword_density');

        $this->assertSame('failed', $densityCriterion['status']);
        $this->assertLessThanOrEqual(5, $densityCriterion['score']);
        $this->assertStringContainsString('nhồi nhét từ khóa', $densityCriterion['message']);
    }

    /**
     * Test phát hiện ảnh thiếu thẻ ALT và đưa ra khuyến nghị.
     */
    public function test_seo_scoring_detects_missing_image_alts_and_penalizes(): void
    {
        $contentWithoutAlt = '<h2>Tổng quan</h2><p>Nội dung bài viết minh họa.</p><img src="https://example.com/banner.jpg" /><img src="https://example.com/photo.png" alt="" />';

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->article->id}/score-audit", [
                'content' => $contentWithoutAlt,
                'persist' => false,
            ]);

        $response->assertOk();
        $imageCriterion = $response->json('data.criteria.image_alts');

        $this->assertFalse($imageCriterion['passed']);
        $this->assertLessThan(6, $imageCriterion['score']);
        $this->assertStringContainsString('thiếu thẻ ALT', $imageCriterion['message']);
    }

    /**
     * Test phát hiện bài viết quá ngắn và văn phong kém trôi chảy.
     */
    public function test_seo_scoring_detects_short_content_and_poor_readability(): void
    {
        $shortContent = '<p>Nội dung này quá ngắn chỉ có vài từ đơn giản.</p>';

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->article->id}/score-audit", [
                'content' => $shortContent,
                'persist' => false,
            ]);

        $response->assertOk();
        $readabilityCriterion = $response->json('data.criteria.readability');

        $this->assertFalse($readabilityCriterion['passed']);
        $this->assertSame('failed', $readabilityCriterion['status']);
        $this->assertLessThanOrEqual(3, $readabilityCriterion['score']);
    }

    /**
     * Test lưu trữ kết quả kiểm toán vào Article model khi persist = true.
     */
    public function test_seo_scoring_persists_score_to_article_when_requested(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->article->id}/score-audit", [
                'persist' => true,
            ]);

        $response->assertOk();

        $fresh = $this->article->fresh();
        $this->assertNotNull($fresh->seo_score);
        $this->assertNotNull($fresh->readability_score);
        $this->assertIsArray($fresh->quality_report);
        $this->assertSame($response->json('data.total_score'), $fresh->seo_score);
        $this->assertSame($response->json('data.readability_score'), $fresh->readability_score);
    }

    /**
     * Test không làm biến đổi Article model khi persist = false (Real-time live mode).
     */
    public function test_seo_scoring_allows_live_overrides_without_persisting(): void
    {
        $oldScore = $this->article->seo_score;

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/articles/{$this->article->id}/score-audit", [
                'content' => '<h2>Tiêu đề tạm thời</h2><p>Nội dung thử nghiệm live typing.</p>',
                'persist' => false,
            ]);

        $response->assertOk();
        $this->assertSame($oldScore, $this->article->fresh()->seo_score);
    }

    /**
     * Test endpoint kiểm toán trực tiếp dữ liệu thô: POST /api/v1/articles/score-audit.
     */
    public function test_seo_scoring_live_endpoint_evaluates_raw_content(): void
    {
        $rawHtml = $this->generateSampleHighQualityHtml('tối ưu seo onpage');

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/articles/score-audit', [
                'content' => $rawHtml,
                'title' => 'Tối Ưu SEO Onpage Toàn Tập 2026',
                'seo_title' => 'Tối Ưu SEO Onpage Toàn Tập 2026 Chuẩn Chuyên Gia',
                'seo_description' => 'Hướng dẫn tối ưu SEO Onpage toàn tập 2026 từ A đến Z. Tìm hiểu ngay bí quyết đưa bài viết lên trang nhất Google nhanh chóng!',
                'focus_keyword' => 'tối ưu seo onpage',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.keyword', 'tối ưu seo onpage')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_score',
                    'criteria',
                    'summary',
                ],
            ]);
    }

    /**
     * Test engine kiểm toán SEO tiêu thụ 0 Credit và không động vào Credit Account.
     */
    public function test_seo_scoring_engine_consumes_zero_credits(): void
    {
        // Cấp trước 10 credits
        $this->creditLedger->grantCredits(
            workspace: $this->workspace,
            amount: 10,
            source: CreditGrantSource::PROMO
        );

        $initialBalance = $this->creditLedger->getBalance($this->workspace)->availableCredits;
        $this->assertSame(10, $initialBalance);

        // Gọi audit 3 lần liên tiếp
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->user)
                ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
                ->postJson("/api/v1/articles/{$this->article->id}/score-audit")
                ->assertOk();
        }

        // Số dư phải giữ nguyên chính xác 10 credits
        $newBalance = $this->creditLedger->getBalance($this->workspace)->availableCredits;
        $this->assertSame(10, $newBalance);
    }

    /**
     * Helper sinh mã HTML bài viết chất lượng cao mẫu đạt chuẩn SEO.
     */
    protected function generateSampleHighQualityHtml(string $keyword): string
    {
        $schemaJson = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => 'Trợ Lý AI Viết Bài SEO Đỉnh Cao Dành Cho Marketer',
            'description' => 'Hướng dẫn ứng dụng trợ lý AI viết bài SEO đỉnh cao.',
        ]);

        return <<<HTML
<script type="application/ld+json">{$schemaJson}</script>
<p>Trong kỷ nguyên số hóa mạnh mẽ, <strong>{$keyword}</strong> đã trở thành vũ khí bí mật giúp các doanh nghiệp bứt phá lưu lượng truy cập tìm kiếm tự nhiên một cách bền vững và hiệu quả.</p>

<h2>Tổng quan về vai trò của {$keyword}</h2>
<p>Công nghệ trí tuệ nhân tạo đang giúp rút ngắn thời gian nghiên cứu và biên tập nội dung từ hàng giờ xuống chỉ còn vài phút. Nhờ đó, người làm marketing có thể tập trung vào chiến lược tổng thể thay vì các công việc thủ công lặp đi lặp lại.</p>
<p>Khi sử dụng {$keyword}, người viết cần hiểu rõ ý định tìm kiếm của độc giả để tinh chỉnh dàn ý sao cho sát với nhu cầu thực tế nhất.</p>

<img src="https://auraseohub.vn/assets/ai-assistant-seo.webp" alt="Mô hình hoạt động của {$keyword} thế hệ mới" />

<h2>Các lợi ích nổi bật khi triển khai {$keyword}</h2>
<p>Việc ứng dụng {$keyword} mang lại hàng loạt ưu thế cạnh tranh vượt trội trên bảng xếp hạng Google:</p>
<p>Thứ nhất, tốc độ sản xuất bài viết tăng gấp 5 lần so với phương pháp truyền thống. Thứ hai, khả năng tối ưu hóa thực thể ngữ nghĩa và mật độ từ khóa chuẩn xác theo hướng dẫn của Google Search Central.</p>

<h3>Phân tích dữ liệu đối thủ tự động</h3>
<p>Thay vì đọc thủ công hàng chục bài viết top đầu, {$keyword} tự động bóc tách cấu trúc thẻ heading, số từ trung bình và các chủ đề còn thiếu để đề xuất góc nhìn độc đáo.</p>

<h3>Tối ưu hóa khả năng đọc hiểu trên thiết bị di động</h3>
<p>Trình chấm điểm tự động phân tích độ dài từng câu, gợi ý ngắt đoạn hợp lý để tăng thời gian lưu trang (Time on page) và giảm tỷ lệ thoát trang.</p>

<img src="https://auraseohub.vn/assets/serp-benchmark.webp" alt="Biểu đồ so sánh hiệu suất bài viết chuẩn SEO" />

<h2>Hệ thống liên kết và tài nguyên tham khảo</h2>
<p>Để tìm hiểu thêm về kiến trúc thông tin, bạn có thể tham khảo bài viết <a href="/blog/semantic-seo">Semantic SEO toàn tập</a> và cẩm nang <a href="/blog/keyword-research">Nghiên cứu từ khóa chuyên sâu</a> trên trang của chúng tôi. Ngoài ra, hãy đọc kỹ báo cáo uy tín từ <a href="https://developers.google.com/search">Google Search Central Documentation</a> để nắm bắt các nguyên tắc chất lượng E-E-A-T mới nhất.</p>

<h2>Câu hỏi thường gặp về {$keyword} (FAQ)</h2>
<h3>Trợ lý AI viết bài SEO có phù hợp với người mới không?</h3>
<p>Rất phù hợp vì hệ thống có sẵn các mẫu dàn ý và trợ lý kiểm toán SEO 100 điểm hướng dẫn từng bước.</p>
<h3>Mật độ từ khóa bao nhiêu là chuẩn?</h3>
<p>Mật độ từ khóa lý tưởng nên duy trì trong khoảng từ 1.0% đến 2.0% tổng số từ của bài viết.</p>
<h3>Làm sao để tránh bị Google coi là nội dung rác?</h3>
<p>Luôn bổ sung trải nghiệm thực tế, số liệu thống kê tin cậy và giải quyết trọn vẹn thắc mắc của độc giả.</p>
HTML;
    }
}
