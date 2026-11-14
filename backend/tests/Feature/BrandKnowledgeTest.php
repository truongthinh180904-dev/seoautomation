<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\BrandKnowledgeBase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Brand\BrandKnowledgeService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BrandKnowledgeTest extends TestCase
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

    protected BrandKnowledgeService $brandKnowledgeService;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        $this->seed(PlanSeeder::class);

        // 1. Setup Tenant & Workspace 1
        $this->tenant = Tenant::create([
            'name' => 'Brand Agency Tenant',
            'slug' => 'brand-agency-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'brandlead@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Alpha Workspace',
            'slug' => 'alpha-workspace',
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
            'name' => 'Aura Health Blog',
            'domain' => 'aurahealth.vn',
            'url' => 'https://aurahealth.vn',
            'default_language' => 'vi',
            'niche' => 'Health & Wellness',
        ]);

        // 2. Setup Tenant & Workspace 2 (Dành cho kiểm thử cô lập dữ liệu - Isolation)
        $this->otherTenant = Tenant::create([
            'name' => 'Beta Tenant',
            'slug' => 'beta-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'betauser@example.com',
            'tenant_id' => $this->otherTenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'owner_id' => $this->otherUser->id,
            'legacy_tenant_id' => $this->otherTenant->id,
            'name' => 'Beta Workspace',
            'slug' => 'beta-workspace',
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
            'name' => 'Beta Tech Site',
            'domain' => 'betatech.com',
            'url' => 'https://betatech.com',
            'default_language' => 'vi',
            'niche' => 'Technology',
        ]);

        $this->brandKnowledgeService = app(BrandKnowledgeService::class);
    }

    /**
     * Test liệt kê danh sách Brand Knowledge Bases theo website.
     */
    public function test_user_can_list_brand_knowledge_bases_for_website(): void
    {
        BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Aura Health Standard',
            'tagline' => 'Chăm sóc sức khỏe toàn diện',
            'tone_of_voice' => 'empathetic',
            'is_default' => true,
        ]);

        BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Aura Health Pro',
            'tagline' => 'Chuyên gia y tế hàng đầu',
            'tone_of_voice' => 'authoritative',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/brand-knowledge?website_id={$this->website->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test tạo Brand Knowledge Base mới với đầy đủ các trường.
     */
    public function test_user_can_create_brand_knowledge_base_with_full_fields(): void
    {
        $payload = [
            'website_id' => $this->website->id,
            'brand_name' => 'Aura Dental Clinic',
            'tagline' => 'Nụ cười tỏa sáng tự tin',
            'tone_of_voice' => 'professional',
            'target_personas' => ['Nhân viên văn phòng 25-40 tuổi', 'Sinh viên đại học'],
            'unique_selling_points' => ['Công nghệ cấy ghép Implant không đau', 'Bảo hành trọn đời'],
            'product_catalog' => [
                ['name' => 'Tẩy trắng răng Laser', 'description' => 'Công nghệ Mỹ bật 3 tông', 'url' => 'https://aurahealth.vn/tay-trang-rang'],
                ['name' => 'Niềng răng trong suốt', 'description' => 'Khay niềng thẩm mỹ', 'url' => 'https://aurahealth.vn/nieng-rang'],
            ],
            'taboo_words' => ['rẻ tiền', 'kém chất lượng', 'lừa đảo'],
            'preferred_terms' => ['chuyên sâu', 'chuẩn y khoa', 'tận tâm'],
            'custom_rules' => 'Luôn nhắc nhở khách hàng đặt lịch khám định kỳ 6 tháng một lần.',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/brand-knowledge', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.brand_name', 'Aura Dental Clinic')
            ->assertJsonPath('data.tone_of_voice', 'professional')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('brand_knowledge_bases', [
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Aura Dental Clinic',
            'is_default' => 1,
        ]);

        $this->website->refresh();
        $this->assertStringContainsString('Aura Dental Clinic', (string) $this->website->brand_voice_summary);
    }

    /**
     * Test tạo hồ sơ mới đặt làm default thì hồ sơ cũ của website tự động bỏ default.
     */
    public function test_creating_default_kb_unsets_previous_default_for_same_website(): void
    {
        $kb1 = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Profile 1',
            'tone_of_voice' => 'friendly',
            'is_default' => true,
        ]);

        $payload = [
            'website_id' => $this->website->id,
            'brand_name' => 'Profile 2',
            'tone_of_voice' => 'expert',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/brand-knowledge', $payload);

        $response->assertCreated();

        $kb1->refresh();
        $this->assertFalse((bool) $kb1->is_default);

        $this->assertDatabaseHas('brand_knowledge_bases', [
            'brand_name' => 'Profile 2',
            'is_default' => 1,
        ]);
    }

    /**
     * Test cập nhật hồ sơ Brand Knowledge Base.
     */
    public function test_user_can_update_brand_knowledge_base(): void
    {
        $kb = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Old Brand Name',
            'tone_of_voice' => 'casual',
            'taboo_words' => ['old_taboo'],
            'is_default' => true,
        ]);

        $updatePayload = [
            'brand_name' => 'New Premium Brand',
            'tone_of_voice' => 'authoritative',
            'taboo_words' => ['new_taboo_1', 'new_taboo_2'],
            'custom_rules' => 'Viết văn phong chuẩn CEO',
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->putJson("/api/v1/brand-knowledge/{$kb->id}", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.brand_name', 'New Premium Brand')
            ->assertJsonPath('data.tone_of_voice', 'authoritative');

        $this->assertDatabaseHas('brand_knowledge_bases', [
            'id' => $kb->id,
            'brand_name' => 'New Premium Brand',
            'tone_of_voice' => 'authoritative',
        ]);
    }

    /**
     * Test xóa hồ sơ Brand Knowledge Base.
     */
    public function test_user_can_delete_brand_knowledge_base(): void
    {
        $kb = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'To Be Deleted',
            'tone_of_voice' => 'casual',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->deleteJson("/api/v1/brand-knowledge/{$kb->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('brand_knowledge_bases', [
            'id' => $kb->id,
        ]);
    }

    /**
     * Test xóa default record thì tự động chọn record còn lại làm default.
     */
    public function test_deleting_default_kb_promotes_next_record_to_default(): void
    {
        $kbDefault = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Default Record',
            'tone_of_voice' => 'casual',
            'is_default' => true,
        ]);

        $kbSecondary = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Secondary Record',
            'tone_of_voice' => 'expert',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->deleteJson("/api/v1/brand-knowledge/{$kbDefault->id}");

        $response->assertOk();

        $kbSecondary->refresh();
        $this->assertTrue((bool) $kbSecondary->is_default);
    }

    /**
     * Test cô lập multi-tenant: User không thể xem/sửa hồ sơ thuộc Workspace khác.
     */
    public function test_user_cannot_access_or_modify_brand_knowledge_of_another_workspace_tenant_isolation(): void
    {
        $otherKb = BrandKnowledgeBase::create([
            'workspace_id' => $this->otherWorkspace->id,
            'website_id' => $this->otherWebsite->id,
            'brand_name' => 'Secret Beta Brand',
            'tone_of_voice' => 'secretive',
            'is_default' => true,
        ]);

        // 1. Thử xem chi tiết
        $showResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->getJson("/api/v1/brand-knowledge/{$otherKb->id}");
        $showResponse->assertStatus(404);

        // 2. Thử sửa
        $updateResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->putJson("/api/v1/brand-knowledge/{$otherKb->id}", [
                'brand_name' => 'Hacked Brand',
            ]);
        $updateResponse->assertStatus(404);

        // 3. Thử xóa
        $deleteResponse = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->deleteJson("/api/v1/brand-knowledge/{$otherKb->id}");
        $deleteResponse->assertStatus(404);

        // Bản ghi gốc của workspace kia vẫn nguyên vẹn
        $this->assertDatabaseHas('brand_knowledge_bases', [
            'id' => $otherKb->id,
            'brand_name' => 'Secret Beta Brand',
        ]);
    }

    /**
     * Test lỗi validation khi thiếu trường bắt buộc khi tạo mới.
     */
    public function test_validation_errors_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/brand-knowledge', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['website_id', 'brand_name']);
    }

    /**
     * Test endpoint set-default cập nhật trạng thái default chuẩn xác.
     */
    public function test_set_default_endpoint_promotes_record_to_default(): void
    {
        $kb1 = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'KB 1 Initial Default',
            'tone_of_voice' => 'friendly',
            'is_default' => true,
        ]);

        $kb2 = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'KB 2 To Be Default',
            'tone_of_voice' => 'authoritative',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson("/api/v1/brand-knowledge/{$kb2->id}/set-default");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_default', true);

        $kb1->refresh();
        $kb2->refresh();

        $this->assertFalse((bool) $kb1->is_default);
        $this->assertTrue((bool) $kb2->is_default);
    }

    /**
     * Test inject context endpoint tạo và thay thế ngữ cảnh thương hiệu vào Prompt.
     */
    public function test_inject_context_endpoint_generates_and_injects_prompt(): void
    {
        BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Nha Khoa Aura',
            'tagline' => 'Nụ cười chuẩn Châu Âu',
            'tone_of_voice' => 'chuyên gia y khoa',
            'unique_selling_points' => ['Công nghệ cấy ghép Thụy Sĩ', 'Bác sĩ 15 năm kinh nghiệm'],
            'taboo_words' => ['rẻ rách', 'cam kết 100%'],
            'custom_rules' => 'Luôn xưng hô Nha khoa Aura và Quý khách',
            'is_default' => true,
        ]);

        $payload = [
            'website_id' => $this->website->id,
            'prompt' => "Hãy viết bài hướng dẫn chăm sóc răng miệng.\n{{brand_context}}\nBài viết cần có H2, H3 rõ ràng.",
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/brand-knowledge/inject-context', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $contextText = $response->json('data.context_text');
        $injectedPrompt = $response->json('data.injected_prompt');

        $this->assertStringContainsString('Nha Khoa Aura', $contextText);
        $this->assertStringContainsString('Công nghệ cấy ghép Thụy Sĩ', $contextText);
        $this->assertStringContainsString('STRICT TABOO WORDS', $contextText);
        $this->assertStringContainsString('rẻ rách', $contextText);

        $this->assertStringNotContainsString('{{brand_context}}', $injectedPrompt);
        $this->assertStringContainsString('Nha Khoa Aura', $injectedPrompt);
    }

    /**
     * Test validate taboo endpoint phát hiện các từ cấm kỵ tiếng Việt chính xác.
     */
    public function test_validate_taboo_endpoint_detects_forbidden_terms(): void
    {
        BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Aura Pharmacy',
            'tone_of_voice' => 'professional',
            'taboo_words' => ['chữa dứt điểm ung thư', 'thần dược', 'lừa đảo'],
            'is_default' => true,
        ]);

        // 1. Trường hợp có chứa từ cấm
        $dirtyContent = 'Sản phẩm này được coi là thần dược dân gian, chữa dứt điểm ung thư mà không cần xạ trị.';
        $responseBad = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/brand-knowledge/validate-taboo', [
                'website_id' => $this->website->id,
                'content' => $dirtyContent,
            ]);

        $responseBad->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.clean', false)
            ->assertJsonPath('data.taboo_count', 2);

        // 2. Trường hợp nội dung sạch sẽ
        $cleanContent = 'Sản phẩm hỗ trợ tăng cường hệ miễn dịch và nâng cao sức đề kháng hiệu quả.';
        $responseClean = $this->actingAs($this->user)
            ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
            ->postJson('/api/v1/brand-knowledge/validate-taboo', [
                'website_id' => $this->website->id,
                'content' => $cleanContent,
            ]);

        $responseClean->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.clean', true)
            ->assertJsonPath('data.taboo_count', 0);
    }

    /**
     * Test trực tiếp các phương thức logic trong BrandKnowledgeService.
     */
    public function test_service_methods_unit_logic(): void
    {
        $kb = $this->brandKnowledgeService->createBrandKnowledge(
            workspace: $this->workspace,
            websiteId: $this->website->id,
            data: [
                'brand_name' => 'Direct Brand',
                'tone_of_voice' => 'conversational',
                'target_personas' => 'Chủ doanh nghiệp nhỏ, Marketer',
                'unique_selling_points' => 'Tiết kiệm 80% thời gian, Chi phí hợp lý',
                'taboo_words' => 'spam, blackhat',
                'preferred_terms' => 'organic growth, whitehat',
                'is_default' => true,
            ]
        );

        $this->assertCount(2, $kb->target_personas);
        $this->assertCount(2, $kb->unique_selling_points);
        $this->assertCount(2, $kb->taboo_words);

        $promptContext = $this->brandKnowledgeService->buildPromptContext($this->website);
        $this->assertStringContainsString('Direct Brand', $promptContext);
        $this->assertStringContainsString('conversational', $promptContext);

        $injected = $this->brandKnowledgeService->injectIntoPrompt(
            prompt: 'Viết bài theo hướng dẫn dưới đây:',
            website: $this->website
        );
        $this->assertStringContainsString('Direct Brand', $injected);
    }
}
