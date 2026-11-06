<?php

namespace Tests\Feature;

use App\DTOs\Chatbot\ExecutionPlanDTO;
use App\DTOs\Chatbot\UserIntentDTO;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\BrandKnowledgeBase;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Chatbot\AiOrchestratorService;
use App\Services\Chatbot\IntentParserService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiOrchestratorTest extends TestCase
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

    protected AiOrchestratorService $orchestrator;

    protected IntentParserService $intentParser;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        // 1. Thiết lập Tenant & Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Nha Khoa Smile Tenant',
            'slug' => 'smile-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'admin@nhakhoasmile.vn',
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceA = Workspace::create([
            'owner_id' => $this->userA->id,
            'legacy_tenant_id' => $this->tenantA->id,
            'name' => 'Nha Khoa Smile Workspace',
            'slug' => 'smile-workspace',
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
            'name' => 'Nha Khoa Smile Tech',
            'domain' => 'nhakhoasmile.vn',
            'url' => 'https://nhakhoasmile.vn',
        ]);

        // 2. Thiết lập Tenant & Workspace B (Phục vụ kiểm thử cô lập Multi-Tenant)
        $this->tenantB = Tenant::create([
            'name' => 'Thẩm Mỹ Viện Tenant',
            'slug' => 'thammy-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'admin@thammyvien.com',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Thẩm Mỹ Viện Workspace',
            'slug' => 'thammy-workspace',
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
            'name' => 'Thẩm Mỹ Viện Quốc Tế',
            'domain' => 'thammyvien.com',
            'url' => 'https://thammyvien.com',
        ]);

        $this->intentParser = app(IntentParserService::class);
        $this->orchestrator = app(AiOrchestratorService::class);
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
     * P2.5.3.1: Kiểm tra System Prompt định hình Senior SEO Specialist và nhúng danh sách 10 SEO Tools.
     */
    public function test_ai_orchestrator_builds_senior_seo_system_prompt_with_tools_and_capabilities(): void
    {
        $prompt = $this->orchestrator->buildSystemPrompt($this->websiteA);

        // Định vị vai trò & Nguyên tắc
        $this->assertStringContainsString('Aura SEO Assistant', $prompt);
        $this->assertStringContainsString('Senior SEO Specialist', $prompt);
        $this->assertStringContainsString('Top 3 đối thủ Google SERP', $prompt);
        $this->assertStringContainsString('Anchor Benchmark', $prompt);
        $this->assertStringContainsString('Xử lý ảnh 3 tầng', $prompt);
        $this->assertStringContainsString('Gemini Vision', $prompt);
        $this->assertStringContainsString('Zero Loss', $prompt);

        // Kiểm tra danh sách 10 công cụ SEO sẵn có từ Phase 1 & 2
        $this->assertStringContainsString('keyword_research', $prompt);
        $this->assertStringContainsString('competitor_analysis', $prompt);
        $this->assertStringContainsString('topic_cluster', $prompt);
        $this->assertStringContainsString('content_gap', $prompt);
        $this->assertStringContainsString('content_studio_write', $prompt);
        $this->assertStringContainsString('seo_score', $prompt);
        $this->assertStringContainsString('internal_links', $prompt);
        $this->assertStringContainsString('stock_image_free', $prompt);
        $this->assertStringContainsString('ai_image', $prompt);
        $this->assertStringContainsString('bulk_generation', $prompt);
        $this->assertStringContainsString('wordpress_publish', $prompt);

        // Kiểm tra thông tin website mục tiêu
        $this->assertStringContainsString('Nha Khoa Smile Tech', $prompt);
        $this->assertStringContainsString('https://nhakhoasmile.vn', $prompt);
    }

    /**
     * P2.5.3.4: Kiểm tra tự động nạp BrandKnowledgeBase của Website vào ngữ cảnh Prompt.
     */
    public function test_ai_orchestrator_injects_brand_knowledge_base_into_context_when_website_has_brand(): void
    {
        $brandKnowledge = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'brand_name' => 'Nha Khoa Smile Tech Global',
            'tagline' => 'Kiến tạo nụ cười hoàn mỹ',
            'tone_of_voice' => 'Chuyên gia y khoa uy tín và ân cần',
            'target_personas' => ['Khách hàng 25-45 tuổi', 'Người cần bọc răng sứ thẩm mỹ'],
            'unique_selling_points' => ['100% Bác sĩ tu nghiệp Pháp', 'Bảo hành trọn đời'],
            'taboo_words' => ['răng đểu', 'chữa cháy', 'kém chất lượng'],
            'preferred_terms' => ['Răng sứ cao cấp Emax', 'Công nghệ vô trùng'],
            'custom_rules' => 'Luôn nhắc đến tiêu chuẩn vô trùng phòng khám ở phần kết bài.',
            'is_default' => true,
        ]);

        $prompt = $this->orchestrator->buildSystemPrompt($this->websiteA, $brandKnowledge);

        $this->assertStringContainsString('Nha Khoa Smile Tech Global', $prompt);
        $this->assertStringContainsString('Kiến tạo nụ cười hoàn mỹ', $prompt);
        $this->assertStringContainsString('Chuyên gia y khoa uy tín và ân cần', $prompt);
        $this->assertStringContainsString('100% Bác sĩ tu nghiệp Pháp', $prompt);
        $this->assertStringContainsString('TABOO WORDS', $prompt);
        $this->assertStringContainsString('răng đểu', $prompt);
        $this->assertStringContainsString('Răng sứ cao cấp Emax', $prompt);
        $this->assertStringContainsString('tiêu chuẩn vô trùng phòng khám', $prompt);
    }

    /**
     * P2.5.3.4: Xử lý an toàn khi Website chưa cấu hình BrandKnowledgeBase.
     */
    public function test_ai_orchestrator_handles_missing_brand_knowledge_gracefully(): void
    {
        $prompt = $this->orchestrator->buildSystemPrompt($this->websiteA, null);

        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('Website này chưa cấu hình Brand Knowledge Base riêng', $prompt);
    }

    /**
     * P2.5.3.2: Nhận diện chính xác 8 ý định cốt lõi của SEO.
     */
    public function test_intent_parser_detects_8_core_seo_intents(): void
    {
        // 1. Nghiên cứu từ khóa
        $resKw = $this->intentParser->parse('Tìm cho tôi 20 từ khóa về dịch vụ nha khoa bọc sứ');
        $this->assertEquals(UserIntentDTO::INTENT_KEYWORD_RESEARCH, $resKw->intent);
        $this->assertEquals(20, $resKw->getParameter('count'));
        $this->assertFalse($resKw->needsClarification);

        // 2. Phân tích đối thủ
        $resComp = $this->intentParser->parse('Phân tích 3 đối thủ mạnh nhất của domain nhakhoasmile.vn');
        $this->assertEquals(UserIntentDTO::INTENT_COMPETITOR_ANALYSIS, $resComp->intent);
        $this->assertEquals('nhakhoasmile.vn', $resComp->getParameter('target_domain'));
        $this->assertFalse($resComp->needsClarification);

        // 3. Topic Cluster
        $resCluster = $this->intentParser->parse('Lập topic cluster cho chủ đề bọc răng sứ');
        $this->assertEquals(UserIntentDTO::INTENT_TOPIC_CLUSTER, $resCluster->intent);
        $this->assertFalse($resCluster->needsClarification);

        // 4. Viết bài SEO
        $resWrite = $this->intentParser->parse("Viết một bài SEO 2.500 từ theo từ khóa 'giá bọc răng sứ thẩm mỹ'");
        $this->assertEquals(UserIntentDTO::INTENT_ARTICLE_WRITE, $resWrite->intent);
        $this->assertEquals('giá bọc răng sứ thẩm mỹ', $resWrite->getParameter('keyword'));
        $this->assertEquals(2500, $resWrite->getParameter('word_count'));
        $this->assertFalse($resWrite->needsClarification);

        // 5. Viết lại bài / Biên tập
        $resRewrite = $this->intentParser->parse('Đoạn mở đầu hơi khô cứng, viết lại hấp dẫn và gây tò mò hơn');
        $this->assertEquals(UserIntentDTO::INTENT_ARTICLE_REWRITE, $resRewrite->intent);
        $this->assertEquals('mở đầu', $resRewrite->getParameter('target_section'));
        $this->assertFalse($resRewrite->needsClarification);

        // 6. Chấm điểm SEO
        $resScore = $this->intentParser->parse('Chấm điểm SEO bài viết số 15 theo tiêu chuẩn 100 điểm');
        $this->assertEquals(UserIntentDTO::INTENT_SEO_SCORE, $resScore->intent);
        $this->assertEquals(15, $resScore->getParameter('article_id'));
        $this->assertFalse($resScore->needsClarification);

        // 7. Tạo bài hàng loạt
        $resBulk = $this->intentParser->parse('Tạo bài viết hàng loạt cho lô 10 từ khóa sau: implant, niềng răng, bọc sứ');
        $this->assertEquals(UserIntentDTO::INTENT_BULK_GENERATION, $resBulk->intent);
        $this->assertEquals(10, $resBulk->getParameter('count'));
        $this->assertFalse($resBulk->needsClarification);

        // 8. Đăng bài WordPress
        $resPub = $this->intentParser->parse('Đăng bài viết số 25 lên WordPress website chính');
        $this->assertEquals(UserIntentDTO::INTENT_PUBLISH_WORDPRESS, $resPub->intent);
        $this->assertEquals(25, $resPub->getParameter('article_id'));
        $this->assertFalse($resPub->needsClarification);
    }

    /**
     * P2.5.3.3: Xử lý trường hợp người dùng đưa yêu cầu mơ hồ / thiếu dữ kiện.
     * Kiểm tra đúng ví dụ đặc tả: Người dùng nói "Viết bài đi" -> AI lịch sự hỏi lại.
     */
    public function test_intent_parser_detects_missing_info_and_requests_clarification_for_vague_prompts(): void
    {
        // 1. Kiểm tra chính xác ca "Viết bài đi" trong Roadmap P2.5.3.3
        $resVagueWrite = $this->intentParser->parse('Viết bài đi');
        $this->assertEquals(UserIntentDTO::INTENT_ARTICLE_WRITE, $resVagueWrite->intent);
        $this->assertTrue($resVagueWrite->needsClarification);
        $this->assertContains('keyword', $resVagueWrite->missingFields);
        $this->assertEquals(
            'Bạn muốn viết về từ khóa nào? Đã có dàn ý chưa hay để mình lập dàn ý giúp bạn?',
            $resVagueWrite->clarificationQuestion
        );

        // Các biến thể khác của viết bài mơ hồ
        $resVagueWrite2 = $this->intentParser->parse('Tạo bài mới');
        $this->assertTrue($resVagueWrite2->needsClarification);
        $this->assertContains('keyword', $resVagueWrite2->missingFields);

        // 2. Phân tích đối thủ thiếu thông tin
        $resVagueComp = $this->intentParser->parse('Phân tích đối thủ đi');
        $this->assertEquals(UserIntentDTO::INTENT_COMPETITOR_ANALYSIS, $resVagueComp->intent);
        $this->assertTrue($resVagueComp->needsClarification);
        $this->assertContains('keyword_or_domain', $resVagueComp->missingFields);
        $this->assertStringContainsString('từ khóa nào hoặc domain/URL đối thủ', $resVagueComp->clarificationQuestion);

        // 3. Nghiên cứu từ khóa mơ hồ
        $resVagueKw = $this->intentParser->parse('Nghiên cứu từ khóa');
        $this->assertEquals(UserIntentDTO::INTENT_KEYWORD_RESEARCH, $resVagueKw->intent);
        $this->assertTrue($resVagueKw->needsClarification);
        $this->assertContains('seed_keyword', $resVagueKw->missingFields);
        $this->assertStringContainsString('từ khóa hạt giống', $resVagueKw->clarificationQuestion);

        // 4. Lập cụm chủ đề mơ hồ
        $resVagueCluster = $this->intentParser->parse('Lập topic cluster');
        $this->assertEquals(UserIntentDTO::INTENT_TOPIC_CLUSTER, $resVagueCluster->intent);
        $this->assertTrue($resVagueCluster->needsClarification);
        $this->assertContains('pillar_topic', $resVagueCluster->missingFields);

        // 5. Đăng bài mơ hồ không có ID bài viết
        $resVaguePublish = $this->intentParser->parse('Đăng bài lên WordPress');
        $this->assertEquals(UserIntentDTO::INTENT_PUBLISH_WORDPRESS, $resVaguePublish->intent);
        $this->assertTrue($resVaguePublish->needsClarification);
        $this->assertContains('article_id', $resVaguePublish->missingFields);
    }

    /**
     * Kiểm tra trích xuất tham số chi tiết từ câu lệnh phức tạp.
     */
    public function test_intent_parser_extracts_parameters_when_prompt_is_detailed(): void
    {
        $prompt = "Viết một bài SEO 3.000 từ theo từ khóa 'cấy ghép Implant All-on-4' và dùng văn phong chuyên gia y khoa";
        $result = $this->intentParser->parse($prompt);

        $this->assertEquals(UserIntentDTO::INTENT_ARTICLE_WRITE, $result->intent);
        $this->assertFalse($result->needsClarification);
        $this->assertEquals('cấy ghép Implant All-on-4', $result->getParameter('keyword'));
        $this->assertEquals(3000, $result->getParameter('word_count'));
        $this->assertEquals('chuyên gia', $result->getParameter('tone'));
    }

    /**
     * P2.5.3: Lập kế hoạch thực thi nhiều bước (Execution Plan) cho yêu cầu viết bài SEO.
     */
    public function test_orchestrator_generates_multi_step_execution_plan_for_valid_intent(): void
    {
        $intent = new UserIntentDTO(
            intent: UserIntentDTO::INTENT_ARTICLE_WRITE,
            confidence: 0.95,
            parameters: [
                'keyword' => 'Giá bọc răng sứ kim loại và titan',
                'word_count' => 2200,
            ],
            needsClarification: false
        );

        $plan = $this->orchestrator->generateExecutionPlan($intent, $this->websiteA);

        $this->assertInstanceOf(ExecutionPlanDTO::class, $plan);
        $this->assertEquals(UserIntentDTO::INTENT_ARTICLE_WRITE, $plan->intent);
        $this->assertStringContainsString('Giá bọc răng sứ kim loại và titan', $plan->title);
        $this->assertCount(5, $plan->steps);

        // Bước 1: Top 3 đối thủ
        $this->assertEquals('competitor_analysis', $plan->steps[0]['tool']);
        $this->assertEquals(3, $plan->steps[0]['estimated_credits']);

        // Bước 2: Dàn ý
        $this->assertEquals('content_studio_write', $plan->steps[1]['tool']);
        $this->assertEquals('build_outline', $plan->steps[1]['action']);

        // Bước 3: Soạn bài (20 credits)
        $this->assertEquals('content_studio_write', $plan->steps[2]['tool']);
        $this->assertEquals(20, $plan->steps[2]['estimated_credits']);

        // Bước 4: Xử lý ảnh thác nước
        $this->assertEquals('stock_image_free', $plan->steps[3]['tool']);

        // Bước 5: Chấm điểm SEO (0 credit)
        $this->assertEquals('seo_score', $plan->steps[4]['tool']);
        $this->assertEquals(0, $plan->steps[4]['estimated_credits']);

        $this->assertEquals(23, $plan->totalEstimatedCredits);
        $this->assertFalse($plan->requiresApproval);
        $this->assertTrue($plan->canExecuteImmediately);
    }

    /**
     * P2.5.3 / P2.5.7: Đánh dấu các hành động nhạy cảm đòi hỏi Human Approval.
     */
    public function test_orchestrator_flags_sensitive_actions_requiring_human_approval(): void
    {
        // 1. Xuất bản WordPress
        $intentPublish = new UserIntentDTO(
            intent: UserIntentDTO::INTENT_PUBLISH_WORDPRESS,
            confidence: 0.95,
            parameters: ['article_id' => 10],
            needsClarification: false
        );
        $planPublish = $this->orchestrator->generateExecutionPlan($intentPublish, $this->websiteA);

        $this->assertTrue($planPublish->requiresApproval);
        $this->assertFalse($planPublish->canExecuteImmediately);
        $this->assertTrue($planPublish->hasSensitiveActions());

        // 2. Chạy bài viết hàng loạt (Bulk Generation)
        $intentBulk = new UserIntentDTO(
            intent: UserIntentDTO::INTENT_BULK_GENERATION,
            confidence: 0.95,
            parameters: ['count' => 15],
            needsClarification: false
        );
        $planBulk = $this->orchestrator->generateExecutionPlan($intentBulk, $this->websiteA);

        $this->assertTrue($planBulk->requiresApproval);
        $this->assertFalse($planBulk->canExecuteImmediately);
        $this->assertEquals(270, $planBulk->totalEstimatedCredits); // 15 bài * 18 credits
    }

    /**
     * Kiểm thử luồng xử lý tổng thể (process) trong phiên trò chuyện.
     */
    public function test_orchestrator_process_end_to_end_in_conversation_flow(): void
    {
        $conversation = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Phiên tư vấn SEO',
            'current_mode' => 'general',
            'last_message_at' => now(),
        ]);

        // Trường hợp A: Yêu cầu mơ hồ -> trả về status clarification_needed
        $resClarification = $this->orchestrator->process('Viết bài đi', $conversation);

        $this->assertEquals('clarification_needed', $resClarification['status']);
        $this->assertTrue($resClarification['intent']->needsClarification);
        $this->assertStringContainsString('Bạn muốn viết về từ khóa nào', $resClarification['response_message']);
        $this->assertFalse($resClarification['requires_approval']);

        // Trường hợp B: Yêu cầu đầy đủ dữ kiện -> trả về status plan_ready kèm bảng các bước
        $resReady = $this->orchestrator->process(
            "Viết bài SEO 2000 từ về từ khóa 'răng sứ Zirconia'",
            $conversation
        );

        $this->assertEquals('plan_ready', $resReady['status']);
        $this->assertFalse($resReady['intent']->needsClarification);
        $this->assertInstanceOf(ExecutionPlanDTO::class, $resReady['plan']);
        $this->assertStringContainsString('răng sứ Zirconia', $resReady['response_message']);
        $this->assertStringContainsString('Các bước thực thi dự kiến', $resReady['response_message']);
    }

    /**
     * Tiêu chí 06 Reviewer: Multi-Tenancy Isolation.
     * Xác minh Orchestrator tại Workspace A tuyệt đối không thể nạp Brand Knowledge của Workspace B.
     */
    public function test_multi_tenancy_isolation_orchestrator_cannot_use_brand_knowledge_from_another_workspace(): void
    {
        // Tạo Brand Knowledge bí mật thuộc Workspace B
        $brandB = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'brand_name' => 'Bí Mật Thương Hiệu Thẩm Mỹ Viện B',
            'tone_of_voice' => 'Sang trọng quý phái',
            'is_default' => true,
        ]);

        // Thử nạp tri thức thương hiệu với bối cảnh Workspace A nhưng truyền ID website B
        $loaded = $this->orchestrator->loadBrandKnowledge($this->websiteB, $this->workspaceA->id);

        $this->assertNull($loaded, 'Orchestrator không được phép nạp BrandKnowledgeBase của Workspace khác.');
    }
}
