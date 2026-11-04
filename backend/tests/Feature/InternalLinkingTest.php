<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\KeywordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Keyword;
use App\Models\MediaAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Media\AiImageService;
use App\Services\SEO\InternalLinkingEngine;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected Website $website;

    protected Tenant $otherTenant;

    protected User $otherUser;

    protected Workspace $otherWorkspace;

    protected Website $otherWebsite;

    protected InternalLinkingEngine $linkEngine;

    protected AiImageService $aiImageService;

    protected CreditLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();
        Storage::fake('local');

        $this->seed(PlanSeeder::class);

        // 1. Setup Tenant & Workspace 1
        $this->tenant = Tenant::create([
            'name' => 'SEO Agency Tenant',
            'slug' => 'seo-agency-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'internallink@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'SEO Master Workspace',
            'slug' => 'seo-master-workspace',
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
            'name' => 'Aura Growth Hub',
            'domain' => 'auragrowth.vn',
            'url' => 'https://auragrowth.vn',
            'default_language' => 'vi',
            'niche' => 'Digital Marketing & SEO',
        ]);

        // 2. Setup Tenant & Workspace 2 for isolation testing
        $this->otherTenant = Tenant::create([
            'name' => 'Competitor Agency',
            'slug' => 'competitor-agency',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'otherlead@example.com',
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

        $this->otherWebsite = Website::create([
            'workspace_id' => $this->otherWorkspace->id,
            'name' => 'Other Portal',
            'domain' => 'otherportal.com',
            'url' => 'https://otherportal.com',
            'default_language' => 'en',
            'niche' => 'Tech News',
        ]);

        // 3. Setup Credits
        $this->ledgerService = app(CreditLedgerService::class);
        $this->ledgerService->grantCredits(
            workspace: $this->workspace,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            idempotencyKey: 'sub_test_p28'
        );

        $this->linkEngine = app(InternalLinkingEngine::class);
        $this->aiImageService = app(AiImageService::class);
    }

    protected function createArticle(array $attributes = []): Article
    {
        $tenantId = $attributes['tenant_id'] ?? $this->tenant->id;
        $workspaceId = $attributes['workspace_id'] ?? $this->workspace->id;
        $websiteId = $attributes['website_id'] ?? $this->website->id;
        $userId = $attributes['user_id'] ?? ($tenantId === $this->tenant->id ? $this->user->id : $this->otherUser->id);

        if (! isset($attributes['keyword_id'])) {
            $keyword = Keyword::create([
                'tenant_id' => $tenantId,
                'workspace_id' => $workspaceId,
                'website_id' => $websiteId,
                'user_id' => $userId,
                'keyword' => $attributes['focus_keyword'] ?? ('kw-'.Str::random(8)),
                'status' => KeywordStatus::COMPLETED,
            ]);
            $attributes['keyword_id'] = $keyword->id;
        }

        $attributes['tenant_id'] = $tenantId;
        $attributes['workspace_id'] = $workspaceId;
        $attributes['website_id'] = $websiteId;
        $attributes['user_id'] = $userId;

        return Article::create($attributes);
    }

    public function test_migration_and_model_relations_and_scopes(): void
    {
        $articleA = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Tổng quan về SEO On-Page',
            'slug' => 'tong-quan-ve-seo-on-page',
            'content' => '<p>Hướng dẫn chi tiết về SEO on-page.</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleB = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Nghiên cứu từ khóa thực chiến',
            'slug' => 'nghien-cuu-tu-khoa-thuc-chien',
            'content' => '<p>Cách tìm từ khóa mang lại chuyển đổi.</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $suggestion = InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleA->id,
            'target_article_id' => $articleB->id,
            'suggested_anchor_text' => 'nghiên cứu từ khóa',
            'context_sentence' => 'Bạn nên thực hiện nghiên cứu từ khóa trước khi viết bài.',
            'relevance_score' => 95.50,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('internal_link_suggestions', [
            'id' => $suggestion->id,
            'suggested_anchor_text' => 'nghiên cứu từ khóa',
            'status' => 'pending',
        ]);

        // Check relations
        $this->assertEquals($this->workspace->id, $suggestion->workspace->id);
        $this->assertEquals($this->website->id, $suggestion->website->id);
        $this->assertEquals($articleA->id, $suggestion->sourceArticle->id);
        $this->assertEquals($articleB->id, $suggestion->targetArticle->id);

        // Check scopes
        $this->assertEquals(1, InternalLinkSuggestion::pending()->count());
        $this->assertEquals(0, InternalLinkSuggestion::accepted()->count());
        $this->assertEquals(0, InternalLinkSuggestion::applied()->count());
        $this->assertEquals(1, InternalLinkSuggestion::forWebsite($this->website->id)->count());
        $this->assertEquals(1, InternalLinkSuggestion::forArticle($articleA->id)->count());
    }

    public function test_internal_linking_engine_scans_article_and_generates_suggestions(): void
    {
        $articleTarget = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Kỹ thuật Nghiên Cứu Từ Khóa Nâng Cao',
            'slug' => 'ky-thuat-nghien-cuu-tu-khoa-nang-cao',
            'focus_keyword' => 'nghiên cứu từ khóa',
            'content' => '<p>Nội dung chi tiết nghiên cứu từ khóa...</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleSource = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Cẩm Nang Viết Bài Chuẩn SEO 2026',
            'slug' => 'cam-nang-viet-bai-chuan-seo-2026',
            'focus_keyword' => 'viết bài chuẩn seo',
            'content' => '<p>Để đạt thứ hạng cao trên Google, bước đầu tiên bắt buộc là nghiên cứu từ khóa cẩn thận.</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $suggestions = $this->linkEngine->scanArticle($articleSource);

        $this->assertNotEmpty($suggestions);
        $suggestion = $suggestions[0];
        $this->assertEquals($articleSource->id, $suggestion->source_article_id);
        $this->assertEquals($articleTarget->id, $suggestion->target_article_id);
        $this->assertEquals('nghiên cứu từ khóa', $suggestion->suggested_anchor_text);
        $this->assertGreaterThanOrEqual(90.0, $suggestion->relevance_score);
        $this->assertNotNull($suggestion->context_sentence);
    }

    public function test_internal_linking_engine_applies_suggestion_and_updates_content(): void
    {
        $articleTarget = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Tối Ưu Tốc Độ Tải Trang Website',
            'slug' => 'toi-uu-toc-do-tai-trang-website',
            'focus_keyword' => 'tốc độ tải trang',
            'content' => '<p>Hướng dẫn nén ảnh và cache...</p>',
            'wordpress_post_url' => 'https://auragrowth.vn/toi-uu-toc-do-tai-trang-website',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleSource = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Checklist SEO Technical 2026',
            'slug' => 'checklist-seo-technical-2026',
            'content' => '<p>Một trong những yếu tố xếp hạng quan trọng là tốc độ tải trang của website.</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $suggestion = InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleSource->id,
            'target_article_id' => $articleTarget->id,
            'suggested_anchor_text' => 'tốc độ tải trang',
            'status' => 'pending',
        ]);

        $success = $this->linkEngine->applySuggestion($suggestion);

        $this->assertTrue($success);
        $articleSource->refresh();
        $suggestion->refresh();

        $this->assertEquals('applied', $suggestion->status);
        $this->assertStringContainsString('<a href="https://auragrowth.vn/toi-uu-toc-do-tai-trang-website"', $articleSource->content);
        $this->assertStringContainsString('tốc độ tải trang</a>', $articleSource->content);
        $this->assertNotEmpty($articleSource->internal_links);
    }

    public function test_internal_linking_engine_detects_orphan_articles(): void
    {
        $articleA = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Bài Viết Số 1',
            'slug' => 'bai-viet-so-1',
            'content' => '<p>Nội dung 1</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleB = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Bài Viết Số 2',
            'slug' => 'bai-viet-so-2',
            'content' => '<p>Nội dung 2</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleOrphan = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Bài Viết Mồ Côi',
            'slug' => 'bai-viet-mo-coi',
            'content' => '<p>Không ai trỏ link tới bài này</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        // Mark link A -> B as applied
        InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleA->id,
            'target_article_id' => $articleB->id,
            'suggested_anchor_text' => 'Bài 2',
            'status' => 'applied',
        ]);

        $orphans = $this->linkEngine->getOrphanArticles($this->website->id);
        $orphanIds = array_map(fn ($art) => $art->id, $orphans);

        $this->assertContains($articleOrphan->id, $orphanIds);
        $this->assertContains($articleA->id, $orphanIds); // A has no inbound links
        $this->assertNotContains($articleB->id, $orphanIds); // B has inbound link from A
    }

    public function test_internal_linking_engine_generates_link_graph(): void
    {
        $articleA = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Node A',
            'slug' => 'node-a',
            'content' => '<p>Content A</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleB = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Node B',
            'slug' => 'node-b',
            'content' => '<p>Content B</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleA->id,
            'target_article_id' => $articleB->id,
            'suggested_anchor_text' => 'Link to B',
            'relevance_score' => 88.0,
            'status' => 'applied',
        ]);

        $graph = $this->linkEngine->getLinkGraph($this->website->id);

        $this->assertArrayHasKey('nodes', $graph);
        $this->assertArrayHasKey('edges', $graph);
        $this->assertCount(2, $graph['nodes']);
        $this->assertCount(1, $graph['edges']);
        $this->assertEquals($articleA->id, $graph['edges'][0]['source']);
        $this->assertEquals($articleB->id, $graph['edges'][0]['target']);
    }

    public function test_ai_image_service_builds_prompt_and_alt_text(): void
    {
        $article = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Chiến Lược Content Marketing Đột Phá',
            'slug' => 'chien-luoc-content-marketing',
            'focus_keyword' => 'content marketing',
            'content' => '<p>Content marketing là cốt lõi...</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $prompt = $this->aiImageService->buildPromptFromHeading('Xây Dựng Phễu Bán Hàng Tự Động', $article);
        $this->assertStringContainsString('Xây Dựng Phễu Bán Hàng Tự Động', $prompt);
        $this->assertStringContainsString('content marketing', $prompt);
        $this->assertStringContainsString('16:9', $prompt);

        $altText = $this->aiImageService->buildAltText('Xây Dựng Phễu Bán Hàng Tự Động', $article);
        $this->assertStringContainsString('content marketing', $altText);
        $this->assertStringContainsString('Xây Dựng Phễu Bán Hàng Tự Động', $altText);
    }

    public function test_ai_image_service_generates_and_stores_webp_image(): void
    {
        config(['ai.providers.gemini.api_key' => 'mock_gemini_api_key']);

        // Valid 1x1 transparent PNG bytes
        $validPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    [
                        'bytesBase64Encoded' => $validPngBase64,
                    ],
                ],
            ], 200),
        ]);

        $article = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Hướng Dẫn Email Marketing B2B',
            'slug' => 'huong-dan-email-marketing-b2b',
            'focus_keyword' => 'email marketing',
            'content' => '<h2>Tối Ưu Tỷ Lệ Mở Email</h2><p>Các mẹo hữu ích...</p>',
            'status' => ArticleStatus::DRAFT,
        ]);

        $initialCredits = $this->ledgerService->getBalance($this->workspace->id)->availableCredits;

        $asset = $this->aiImageService->generateImageForHeading($article, 'Tối Ưu Tỷ Lệ Mở Email');

        $this->assertInstanceOf(MediaAsset::class, $asset);
        $this->assertEquals($article->id, $asset->article_id);
        $sourceType = $asset->source_type instanceof \BackedEnum ? $asset->source_type->value : (string) $asset->source_type;
        $status = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;
        $this->assertEquals('ai_generated', $sourceType);
        $this->assertEquals('downloaded', $status);
        $this->assertNotNull($asset->local_path);
        $this->assertStringContainsString('email marketing', $asset->alt_text);

        // Check credit deduction (5 credits)
        $newCredits = $this->ledgerService->getBalance($this->workspace->id)->availableCredits;
        $this->assertEquals($initialCredits - 5, $newCredits);
    }

    public function test_api_endpoints_internal_links_crud_and_actions(): void
    {
        config(['ai.providers.gemini.api_key' => 'mock_gemini_api_key']);

        $validPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    ['bytesBase64Encoded' => $validPngBase64],
                ],
            ], 200),
        ]);

        $articleA = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Bài Viết Nguồn',
            'slug' => 'bai-viet-nguon',
            'focus_keyword' => 'bài viết nguồn',
            'content' => '<p>Tìm hiểu thêm về từ khóa đích tại đây.</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $articleB = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Bài Viết Đích',
            'slug' => 'bai-viet-dich',
            'focus_keyword' => 'từ khóa đích',
            'content' => '<p>Nội dung bài đích...</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $suggestion = InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleA->id,
            'target_article_id' => $articleB->id,
            'suggested_anchor_text' => 'từ khóa đích',
            'context_sentence' => 'Tìm hiểu thêm về từ khóa đích tại đây.',
            'relevance_score' => 90.0,
            'status' => 'pending',
        ]);

        $token = $this->user->createToken('test')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Workspace-Id' => (string) $this->workspace->id,
            'Accept' => 'application/json',
        ];

        // 1. GET /api/v1/internal-links
        $response = $this->getJson('/api/v1/internal-links?website_id='.$this->website->id, $headers);
        $response->assertOk()
            ->assertJsonPath('success', true);

        // 2. POST /api/v1/internal-links/{id}/accept
        $response = $this->postJson("/api/v1/internal-links/{$suggestion->id}/accept", [], $headers);
        $response->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        // 3. POST /api/v1/internal-links/{id}/apply
        $response = $this->postJson("/api/v1/internal-links/{$suggestion->id}/apply", [], $headers);
        $response->assertOk()
            ->assertJsonPath('data.status', 'applied');

        // 4. POST /api/v1/internal-links/{id}/reject
        $suggestion2 = InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleB->id,
            'target_article_id' => $articleA->id,
            'suggested_anchor_text' => 'bài viết nguồn',
            'status' => 'pending',
        ]);
        $response = $this->postJson("/api/v1/internal-links/{$suggestion2->id}/reject", [], $headers);
        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // 5. POST /api/v1/internal-links/scan
        $response = $this->postJson('/api/v1/internal-links/scan', [
            'article_id' => $articleA->id,
        ], $headers);
        $response->assertOk()
            ->assertJsonPath('success', true);

        // 6. GET /api/v1/internal-links/graph
        $response = $this->getJson('/api/v1/internal-links/graph?website_id='.$this->website->id, $headers);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['nodes', 'edges']]);

        // 7. GET /api/v1/internal-links/orphans
        $response = $this->getJson('/api/v1/internal-links/orphans?website_id='.$this->website->id, $headers);
        $response->assertOk()
            ->assertJsonPath('success', true);

        // 8. POST /api/v1/internal-links/generate-image
        $response = $this->postJson('/api/v1/internal-links/generate-image', [
            'article_id' => $articleA->id,
            'heading' => 'Khái Niệm Cốt Lõi Về Internal Link',
        ], $headers);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source_type', 'ai_generated');
    }

    public function test_multi_tenant_isolation(): void
    {
        $articleA = $this->createArticle([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'title' => 'Article in Workspace 1',
            'slug' => 'art-ws-1',
            'content' => '<p>Content</p>',
            'status' => ArticleStatus::PUBLISHED,
        ]);

        $suggestion = InternalLinkSuggestion::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'source_article_id' => $articleA->id,
            'target_article_id' => $articleA->id,
            'suggested_anchor_text' => 'Secret Link',
            'status' => 'pending',
        ]);

        $otherToken = $this->otherUser->createToken('other')->plainTextToken;
        $otherHeaders = [
            'Authorization' => 'Bearer '.$otherToken,
            'X-Workspace-Id' => (string) $this->otherWorkspace->id,
            'Accept' => 'application/json',
        ];

        // Other workspace trying to apply suggestion of Workspace 1 -> 404
        $response = $this->postJson("/api/v1/internal-links/{$suggestion->id}/apply", [], $otherHeaders);
        $response->assertNotFound();

        // Other workspace listing suggestions -> Cannot see Workspace 1's suggestions
        $response = $this->getJson('/api/v1/internal-links', $otherHeaders);
        $response->assertOk();
        $this->assertEmpty($response->json('data.data'));
    }
}
