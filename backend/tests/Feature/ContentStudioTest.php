<?php

namespace Tests\Feature;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Article;
use App\Models\BrandKnowledgeBase;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AI\AIProviderService;
use App\Services\Billing\CreditLedgerService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ContentStudioTest extends TestCase
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

        // 1. Setup Tenant & Workspace 1
        $this->tenant = Tenant::create([
            'name' => 'Studio Prime Tenant',
            'slug' => 'studio-prime-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'studioeditor@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Studio Workspace',
            'slug' => 'studio-workspace',
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
            'name' => 'Aura Studio Tech',
            'domain' => 'auratech.vn',
            'url' => 'https://auratech.vn',
            'default_language' => 'vi',
            'niche' => 'Technology',
        ]);

        $this->keyword = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'keyword' => 'trợ lý ai viết bài seo',
            'search_volume' => 1500,
            'difficulty' => 25,
            'cpc' => 0.45,
            'status' => KeywordStatus::COMPLETED,
        ]);

        $this->article = Article::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'keyword_id' => $this->keyword->id,
            'user_id' => $this->user->id,
            'title' => 'Trợ Lý AI Viết Bài SEO Đỉnh Cao 2026',
            'slug' => 'tro-ly-ai-viet-bai-seo-dinh-cao-2026',
            'content' => '<p>Công nghệ trí tuệ nhân tạo đang thay đổi hoàn toàn cách chúng ta sáng tạo nội dung trực tuyến.</p><p>Trợ lý AI giúp tối ưu hóa thời gian và nâng cao thứ hạng từ khóa.</p>',
            'seo_title' => 'Trợ Lý AI Viết Bài SEO Đỉnh Cao 2026 - Aura',
            'seo_description' => 'Khám phá công nghệ trợ lý AI viết bài chuẩn SEO thế hệ mới giúp tăng traffic tự nhiên.',
            'focus_keyword' => 'trợ lý ai viết bài seo',
            'word_count' => 35,
            'status' => ArticleStatus::DRAFT,
            'outline' => [
                ['id' => 'sec-1', 'level' => 2, 'title' => 'Tổng quan về AI Content'],
                ['id' => 'sec-2', 'level' => 2, 'title' => 'Lợi ích vượt trội của AI Copilot'],
            ],
        ]);

        // 2. Setup Workspace 2 (Dành cho kiểm thử cô lập dữ liệu - Isolation)
        $this->otherTenant = Tenant::create([
            'name' => 'Isolated Other Tenant',
            'slug' => 'isolated-other-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'othereditor@example.com',
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

        $otherKeyword = Keyword::create([
            'tenant_id' => $this->otherTenant->id,
            'user_id' => $this->otherUser->id,
            'workspace_id' => $this->otherWorkspace->id,
            'keyword' => 'từ khóa bảo mật khác',
            'status' => KeywordStatus::COMPLETED,
        ]);

        $this->otherArticle = Article::create([
            'tenant_id' => $this->otherTenant->id,
            'workspace_id' => $this->otherWorkspace->id,
            'keyword_id' => $otherKeyword->id,
            'user_id' => $this->otherUser->id,
            'title' => 'Bài viết độc quyền của Workspace khác',
            'slug' => 'bai-viet-doc-quyen-khac',
            'content' => '<p>Dữ liệu mật cấm truy cập chéo giữa các workspace.</p>',
            'status' => ArticleStatus::DRAFT,
            'word_count' => 12,
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
     * Test người dùng chưa đăng nhập không thể truy cập Content Studio.
     */
    public function test_unauthenticated_user_cannot_access_content_studio(): void
    {
        $this->getJson("/api/v1/content-studio/{$this->article->id}")
            ->assertStatus(401);

        $this->postJson('/api/v1/content-studio/assist', [
            'article_id' => $this->article->id,
            'action' => 'rewrite',
            'selected_text' => 'Đoạn văn cần sửa',
        ])->assertStatus(401);

        $this->postJson('/api/v1/content-studio/autosave', [
            'article_id' => $this->article->id,
            'content' => '<p>Nội dung mới</p>',
        ])->assertStatus(401);
    }

    /**
     * Test ngăn chặn truy cập trái phép chéo Workspace (Multi-Tenant Isolation).
     */
    public function test_user_cannot_access_or_edit_article_from_another_workspace_tenant_isolation(): void
    {
        // User 1 cố gắng xem bài viết của Workspace 2
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/content-studio/{$this->otherArticle->id}");

        $response->assertStatus(404);

        // User 1 cố gắng gọi AI Assist trên bài viết của Workspace 2
        $assistResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/assist', [
                'article_id' => $this->otherArticle->id,
                'action' => 'rewrite',
                'selected_text' => 'Cố gắng sửa bài viết người khác',
            ]);

        $assistResponse->assertStatus(404);

        // User 1 cố gắng Autosave bài viết của Workspace 2
        $autosaveResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/autosave', [
                'article_id' => $this->otherArticle->id,
                'content' => '<p>Ghi đè trái phép</p>',
            ]);

        $autosaveResponse->assertStatus(404);

        // User 1 cố gắng xem phiên bản của bài viết Workspace 2
        $versionsResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/content-studio/{$this->otherArticle->id}/versions");

        $versionsResponse->assertStatus(404);
    }

    /**
     * Test tải dữ liệu Content Studio thành công cho bài viết thuộc Workspace.
     */
    public function test_user_can_load_studio_data_for_article(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/content-studio/{$this->article->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->article->id)
            ->assertJsonPath('data.title', $this->article->title)
            ->assertJsonPath('data.website.id', $this->website->id)
            ->assertJsonPath('data.keyword.id', $this->keyword->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'content',
                    'outline',
                    'seo_title',
                    'seo_description',
                    'focus_keyword',
                    'word_count',
                    'status',
                    'website',
                    'keyword',
                    'recent_versions',
                    'last_saved_at',
                ],
            ]);
    }

    /**
     * Test gọi AI Copilot thất bại khi Workspace không đủ credit (0 credit).
     */
    public function test_ai_assist_fails_when_workspace_has_insufficient_credits(): void
    {
        $account = $this->creditLedger->getOrCreateAccount($this->workspace);
        $account->update(['available_credits' => 0]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/assist', [
                'article_id' => $this->article->id,
                'action' => 'rewrite',
                'selected_text' => 'Trí tuệ nhân tạo đang thay đổi cách chúng ta viết bài.',
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INSUFFICIENT_CREDITS');
    }

    /**
     * Test gọi AI Copilot thành công và trừ chính xác 1 credit.
     */
    public function test_ai_assist_succeeds_and_deducts_exactly_one_credit(): void
    {
        // 1. Cấp 5 credit ban đầu
        $this->creditLedger->grantCredits(
            workspace: $this->workspace,
            amount: 5,
            source: CreditGrantSource::PROMO
        );

        $initialBalance = $this->creditLedger->getBalance($this->workspace)->availableCredits;
        $this->assertSame(5, $initialBalance);

        // 2. Mock AIProviderService
        $mockAiResponse = new AIResponseDTO(
            content: 'Công nghệ trí tuệ nhân tạo hiện đại đang định hình lại toàn bộ quy trình biên tập nội dung số.',
            provider: AIProvider::GEMINI,
            model: 'gemini-2.5-flash',
            promptTokens: 80,
            completionTokens: 30,
            totalTokens: 110,
            latencyMs: 120,
            rawResponse: []
        );

        $mockAiService = Mockery::mock(AIProviderService::class);
        $mockAiService->shouldReceive('complete')
            ->once()
            ->with(Mockery::type(AIRequestDTO::class))
            ->andReturn($mockAiResponse);

        $this->app->instance(AIProviderService::class, $mockAiService);

        // 3. Thực hiện gọi API Copilot assist
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/assist', [
                'article_id' => $this->article->id,
                'action' => 'rewrite',
                'selected_text' => 'Công nghệ trí tuệ nhân tạo đang thay đổi hoàn toàn cách chúng ta sáng tạo nội dung trực tuyến.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'rewrite')
            ->assertJsonPath('data.credits_deducted', 1)
            ->assertJsonPath('data.result_text', 'Công nghệ trí tuệ nhân tạo hiện đại đang định hình lại toàn bộ quy trình biên tập nội dung số.');

        // 4. Xác nhận số dư credit giảm đi đúng 1 credit (5 -> 4)
        $newBalance = $this->creditLedger->getBalance($this->workspace)->availableCredits;
        $this->assertSame(4, $newBalance);
    }

    /**
     * Test AI Copilot hỗ trợ đầy đủ các hành động (expand, shorten, change_tone, add_example, fix_grammar, custom).
     */
    public function test_ai_assist_supports_different_actions(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspace,
            amount: 20,
            source: CreditGrantSource::PROMO
        );

        $actions = [
            'expand' => ['instruction' => 'Mở rộng chi tiết kỹ thuật'],
            'shorten' => [],
            'change_tone' => ['tone' => 'persuasive'],
            'add_example' => [],
            'fix_grammar' => [],
            'custom' => ['instruction' => 'Chuyển đoạn này thành dạng 3 gạch đầu dòng súc tích'],
        ];

        foreach ($actions as $action => $extraParams) {
            $mockAiResponse = new AIResponseDTO(
                content: "Kết quả xử lý mẫu cho hành động [{$action}].",
                provider: AIProvider::GEMINI,
                model: 'gemini-2.5-flash',
                promptTokens: 50,
                completionTokens: 25,
                totalTokens: 75,
                latencyMs: 90,
                rawResponse: []
            );

            $mockAiService = Mockery::mock(AIProviderService::class);
            $mockAiService->shouldReceive('complete')->once()->andReturn($mockAiResponse);
            $this->app->instance(AIProviderService::class, $mockAiService);

            $payload = array_merge([
                'article_id' => $this->article->id,
                'action' => $action,
                'selected_text' => 'Văn bản thử nghiệm tính năng copilot.',
            ], $extraParams);

            $res = $this->actingAs($this->user)
                ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
                ->postJson('/api/v1/content-studio/assist', $payload);

            $res->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.action', $action)
                ->assertJsonPath('data.credits_deducted', 1);
        }
    }

    /**
     * Test tự động tích hợp Brand Knowledge Base vào AI Prompt khi bài viết gắn với Website có KB.
     */
    public function test_ai_assist_injects_brand_knowledge_context_into_prompt(): void
    {
        // Tạo Brand Knowledge Base cho website
        BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Aura Health AI',
            'tagline' => 'Chuyên gia nội dung chuẩn y khoa',
            'tone_of_voice' => 'authoritative',
            'unique_selling_points' => ['Nghiên cứu lâm sàng minh bạch', 'Đội ngũ bác sĩ cố vấn'],
            'taboo_words' => ['rẻ tiền', 'kém chất lượng'],
            'is_default' => true,
        ]);

        $this->creditLedger->grantCredits(
            workspace: $this->workspace,
            amount: 5,
            source: CreditGrantSource::PROMO
        );

        $mockAiService = Mockery::mock(AIProviderService::class);
        $mockAiService->shouldReceive('complete')
            ->once()
            ->withArgs(function (AIRequestDTO $dto) {
                // Kiểm tra system prompt phải chứa thông tin thương hiệu
                return str_contains($dto->systemPrompt, 'Aura Health AI')
                    && str_contains($dto->systemPrompt, 'authoritative')
                    && str_contains($dto->systemPrompt, 'Nghiên cứu lâm sàng minh bạch');
            })
            ->andReturn(new AIResponseDTO(
                content: 'Nội dung đã được biên tập theo giọng văn chuyên gia y khoa.',
                provider: AIProvider::GEMINI,
                model: 'gemini-2.5-flash',
                promptTokens: 120,
                completionTokens: 40,
                totalTokens: 160,
                latencyMs: 110,
                rawResponse: []
            ));

        $this->app->instance(AIProviderService::class, $mockAiService);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/assist', [
                'article_id' => $this->article->id,
                'action' => 'rewrite',
                'selected_text' => 'Bổ sung dinh dưỡng giúp tăng cường đề kháng.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * Test autosave cập nhật nội dung bài viết và tính toán lại word_count.
     */
    public function test_autosave_updates_content_and_recalculates_word_count(): void
    {
        $newContent = '<h2>Khám phá thế giới AI</h2><p>Trí tuệ nhân tạo đem lại những bước nhảy vọt trong xử lý ngôn ngữ tự nhiên. Nhờ đó các chuyên gia có thể tập trung vào chiến lược tổng thể thay vì các công việc thủ công lặp đi lặp lại.</p>';

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/autosave', [
                'article_id' => $this->article->id,
                'title' => 'Tiêu đề đã được cập nhật từ Studio',
                'content' => $newContent,
                'seo_title' => 'SEO Title mới tối ưu CTR',
                'seo_description' => 'Meta Description mới ngắn gọn 150 ký tự.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article_id', $this->article->id)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'article_id',
                    'word_count',
                    'saved_at',
                    'version_number',
                    'has_version_created',
                    'status',
                ],
            ]);

        $freshArticle = $this->article->fresh();
        $this->assertSame('Tiêu đề đã được cập nhật từ Studio', $freshArticle->title);
        $this->assertSame($newContent, $freshArticle->content);
        $this->assertSame('SEO Title mới tối ưu CTR', $freshArticle->seo_title);
        $this->assertGreaterThan(20, $freshArticle->word_count);
    }

    /**
     * Test autosave tạo phiên bản ArticleVersion khi bật create_version = true.
     */
    public function test_autosave_creates_article_version_when_requested(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/autosave', [
                'article_id' => $this->article->id,
                'content' => '<p>Phiên bản snapshot quan trọng trước khi xuất bản lên WordPress.</p>',
                'create_version' => true,
                'version_reason' => 'Hoàn tất chỉnh sửa dàn ý và duyệt nội dung',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_version_created', true)
            ->assertJsonPath('data.version_number', 1);

        $this->assertDatabaseHas('article_versions', [
            'article_id' => $this->article->id,
            'version_number' => 1,
            'change_reason' => 'Hoàn tất chỉnh sửa dàn ý và duyệt nội dung',
            'changed_by' => $this->user->id,
        ]);
    }

    /**
     * Test liệt kê lịch sử phiên bản của bài viết trong Content Studio.
     */
    public function test_user_can_list_article_versions(): void
    {
        // Tạo 2 phiên bản cho bài viết
        $this->article->createVersion([
            'title' => 'Bản nháp 1',
            'content' => '<p>Nội dung bản 1</p>',
            'seo_title' => 'SEO Title 1',
            'seo_description' => 'Desc 1',
            'word_count' => 10,
            'seo_score' => 70,
        ], $this->user, 'Khởi tạo bài viết');

        $this->article->createVersion([
            'title' => 'Bản nháp 2',
            'content' => '<p>Nội dung bản 2 mở rộng</p>',
            'seo_title' => 'SEO Title 2',
            'seo_description' => 'Desc 2',
            'word_count' => 25,
            'seo_score' => 85,
        ], $this->user, 'Tối ưu lại từ khóa');

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/content-studio/{$this->article->id}/versions");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 2)
            ->assertJsonPath('data.1.version_number', 1);
    }

    /**
     * Test validate dữ liệu đầu vào bắt buộc của AI Copilot assist.
     */
    public function test_ai_assist_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/assist', [
                // Thiếu article_id, action, selected_text
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['article_id', 'action', 'selected_text']);
    }

    /**
     * Test validate dữ liệu đầu vào bắt buộc của autosave.
     */
    public function test_autosave_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/content-studio/autosave', [
                // Thiếu content
                'article_id' => $this->article->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }
}
