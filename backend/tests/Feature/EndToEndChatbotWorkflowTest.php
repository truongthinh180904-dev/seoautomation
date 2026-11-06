<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Api\V1\Chatbot\ActionApprovalController;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\Article;
use App\Models\BrandKnowledgeBase;
use App\Models\ChatPendingAction;
use App\Models\Conversation;
use App\Models\CreditTransaction;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\ActionApprovalService;
use App\Services\Chatbot\AiOrchestratorService;
use App\Services\Chatbot\ChatbotBillingBridge;
use App\Services\Chatbot\ChatImageVisionService;
use App\Services\Chatbot\ConversationService;
use App\Services\Chatbot\IntentParserService;
use App\Services\Chatbot\Tools\SeoToolRegistry;
use App\Services\SEO\SeoScoringEngine;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EndToEndChatbotWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Workspace $workspace;

    protected Website $website;

    protected WordPressSite $wpSite;

    protected BrandKnowledgeBase $brandKnowledge;

    protected CreditLedgerService $creditLedger;

    protected ConversationService $conversationService;

    protected AiOrchestratorService $orchestrator;

    protected IntentParserService $intentParser;

    protected SeoToolRegistry $toolRegistry;

    protected ActionApprovalService $approvalService;

    protected ChatbotBillingBridge $billingBridge;

    protected ChatImageVisionService $visionService;

    protected SeoScoringEngine $scoringEngine;

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
        $this->conversationService = app(ConversationService::class);
        $this->orchestrator = app(AiOrchestratorService::class);
        $this->intentParser = app(IntentParserService::class);
        $this->toolRegistry = app(SeoToolRegistry::class);
        $this->approvalService = app(ActionApprovalService::class);
        $this->billingBridge = app(ChatbotBillingBridge::class);
        $this->visionService = app(ChatImageVisionService::class);
        $this->scoringEngine = app(SeoScoringEngine::class);

        // Đăng ký route kiểm thử cho ActionApprovalController
        Route::middleware(['auth:sanctum', 'active_account'])->prefix('api/v1/chatbot/actions')->group(function () {
            Route::get('{uuid}', [ActionApprovalController::class, 'show']);
            Route::post('{uuid}/approve', [ActionApprovalController::class, 'approve']);
            Route::post('{uuid}/reject', [ActionApprovalController::class, 'reject']);
        });

        // 1. Khởi tạo Tenant & Workspace chính
        $this->tenant = Tenant::create([
            'name' => 'Nha Khoa Smile Dental E2E Tenant',
            'slug' => 'smile-dental-e2e-tenant',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'ceo@nhakhoasmile.vn',
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'legacy_tenant_id' => $this->tenant->id,
            'name' => 'Nha Khoa Smile E2E Workspace',
            'slug' => 'nha-khoa-smile-e2e-ws',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->user->update(['default_workspace_id' => $this->workspace->id]);

        $this->website = Website::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Nha Khoa Smile Official',
            'domain' => 'nhakhoasmile.vn',
            'url' => 'https://nhakhoasmile.vn',
        ]);

        $this->wpSite = WordPressSite::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'name' => 'Smile Dental WordPress Site',
            'url' => 'https://nhakhoasmile.vn',
            'api_url' => 'https://nhakhoasmile.vn/wp-json',
            'username' => 'smile_master',
            'app_password' => 'secret-app-password-e2e',
            'is_active' => true,
        ]);

        // Cấu hình Brand Knowledge Base
        $this->brandKnowledge = BrandKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'brand_name' => 'Nha Khoa Smile Thẩm Mỹ',
            'tagline' => 'Kiến tạo nụ cười chuẩn tỷ lệ vàng',
            'tone_of_voice' => 'Chuyên gia y khoa uy tín, ân cần',
            'usp' => 'Công nghệ bọc sứ Nano Bio không mài nhỏ răng, bảo tồn tủy 99%',
            'target_audience' => 'Khách hàng có nhu cầu thẩm mỹ răng từ 22-45 tuổi',
            'taboo_words' => ['răng giả giá rẻ', 'chữa bừa', 'đau nhức'],
            'is_default' => true,
        ]);

        // Cấp 200 credit ban đầu vào ví
        $this->creditLedger->grantCredits(
            workspace: $this->workspace,
            amount: 200,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial 200 credits for E2E workflow testing'
        );
    }

    protected function tearDown(): void
    {
        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }

    /**
     * P2.5.9.3: Test Suite End-to-End trọn vẹn toàn bộ chuỗi nghiệp vụ Chatbot AI SEO
     * (Hội thoại -> Nghiên cứu từ khóa -> Phân tích Top 3 đối thủ -> Soạn bài kèm ảnh Vision -> Chấm điểm SEO -> Human Gate -> Đăng WordPress -> Kiểm toán Credit).
     */
    public function test_complete_end_to_end_chatbot_seo_workflow_from_chat_to_publish_with_credit_accounting(): void
    {
        Sanctum::actingAs($this->user);

        // ------------------------------------------------------------------------------------
        // BƯỚC 1: Khởi tạo phiên trò chuyện Chatbot mới (P2.5.2)
        // ------------------------------------------------------------------------------------
        $createConvResponse = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspace->id,
        ])->postJson('/api/v1/conversations', [
            'website_id' => $this->website->id,
            'title' => 'Chiến dịch SEO Bọc Răng Sứ 2026',
            'current_mode' => 'general',
        ]);

        $createConvResponse->assertStatus(201);
        $convData = $createConvResponse->json('data');
        $conversationUuid = $convData['uuid'];
        $this->assertNotEmpty($conversationUuid);

        /** @var Conversation $conversation */
        $conversation = Conversation::where('uuid', $conversationUuid)->firstOrFail();
        $this->assertSame($this->workspace->id, $conversation->workspace_id);
        $this->assertSame($this->website->id, $conversation->website_id);

        // ------------------------------------------------------------------------------------
        // BƯỚC 2: Người dùng yêu cầu Nghiên cứu Từ khóa (P2.5.3, P2.5.4, P2.5.8)
        // ------------------------------------------------------------------------------------
        $userKwMessage = 'Aura ơi, hãy nghiên cứu từ khóa nha khoa bọc sứ giúp mình.';

        $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspace->id,
        ])->postJson("/api/v1/conversations/{$conversationUuid}/messages", [
            'content' => $userKwMessage,
            'role' => 'user',
        ])->assertStatus(201);

        // Intent Parser nhận diện intent
        $kwIntent = $this->intentParser->parse($userKwMessage);
        $this->assertSame('keyword_research', $kwIntent->intent);

        // Thực thi gọi Tool Nghiên cứu từ khóa qua ChatbotBillingBridge (trừ 2 credit)
        $kwResult = $this->billingBridge->executeWithBilling(
            workspace: $this->workspace,
            toolName: 'keyword_research',
            conversation: $conversation,
            toolAction: function () {
                return [
                    'tool' => 'keyword_research',
                    'seed_keyword' => 'nha khoa bọc sứ',
                    'total_keywords' => 5,
                    'keywords' => [
                        ['keyword' => 'giá bọc răng sứ thẩm mỹ', 'volume' => 3600, 'kd' => 28, 'intent' => 'commercial'],
                        ['keyword' => 'bọc răng sứ có đau không', 'volume' => 2400, 'kd' => 19, 'intent' => 'informational'],
                        ['keyword' => 'quy trình bọc răng sứ nano', 'volume' => 1200, 'kd' => 22, 'intent' => 'informational'],
                    ],
                ];
            },
            actor: $this->user,
            params: [
                'keyword' => 'nha khoa bọc sứ',
                'language' => 'vi',
            ],
            idempotencyKey: "conv_{$conversation->id}_step_1_kw"
        );

        $this->assertSame('keyword_research', $kwResult['tool']);
        $this->assertSame(198, $this->billingBridge->getBalance($this->workspace)->availableCredits);

        // Lưu tin nhắn phản hồi của Assistant
        $this->conversationService->addMessage(
            conversation: $conversation,
            role: 'assistant',
            content: "Đã phân tích thành công bộ từ khóa cho 'nha khoa bọc sứ'. Từ khóa mục tiêu tiềm năng nhất: 'giá bọc răng sứ thẩm mỹ' (3,600 lượt tìm kiếm/tháng).",
            options: [
                'tool_calls' => [['name' => 'keyword_research', 'params' => ['keyword' => 'nha khoa bọc sứ']]],
                'tool_results' => $kwResult,
                'status' => 'completed',
            ]
        );

        // ------------------------------------------------------------------------------------
        // BƯỚC 3: Người dùng yêu cầu Phân tích Top 3 Đối thủ SERP (P2.5.4, P2.5.8)
        // ------------------------------------------------------------------------------------
        $userCompetitorMessage = 'Phân tích 3 đối thủ mạnh nhất cho từ khóa giá bọc răng sứ thẩm mỹ';

        $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspace->id,
        ])->postJson("/api/v1/conversations/{$conversationUuid}/messages", [
            'content' => $userCompetitorMessage,
            'role' => 'user',
        ])->assertStatus(201);

        $compIntent = $this->intentParser->parse($userCompetitorMessage);
        $this->assertSame('competitor_analysis', $compIntent->intent);

        // Thực thi gọi Tool Đối thủ qua ChatbotBillingBridge (trừ 3 credit)
        $compResult = $this->billingBridge->executeWithBilling(
            workspace: $this->workspace,
            toolName: 'competitor_analysis',
            conversation: $conversation,
            toolAction: function () {
                return [
                    'tool' => 'competitor_analysis',
                    'keyword' => 'giá bọc răng sứ thẩm mỹ',
                    'total_analyzed' => 3,
                    'anchor_benchmark' => [
                        'domain' => 'nhakhoaparis.vn',
                        'rank' => 1,
                        'word_count' => 2200,
                        'h2_count' => 6,
                        'lsi_entities' => ['răng sứ cercon', 'răng sứ venus', 'thời gian bảo hành'],
                    ],
                    'top_3_competitors' => [
                        ['rank' => 1, 'domain' => 'nhakhoaparis.vn', 'word_count' => 2200],
                        ['rank' => 2, 'domain' => 'nhakhoadonga.vn', 'word_count' => 1950],
                        ['rank' => 3, 'domain' => 'nhakhoakim.com', 'word_count' => 1800],
                    ],
                ];
            },
            actor: $this->user,
            params: [
                'keyword' => 'giá bọc răng sứ thẩm mỹ',
            ],
            idempotencyKey: "conv_{$conversation->id}_step_2_comp"
        );

        $this->assertSame(3, $compResult['total_analyzed']);
        $this->assertSame(195, $this->billingBridge->getBalance($this->workspace)->availableCredits);

        // ------------------------------------------------------------------------------------
        // BƯỚC 4: Soạn thảo Bài viết SEO kết hợp Gemini Vision Image (P2.5.5, P2.5.8)
        // ------------------------------------------------------------------------------------
        $userWriteMessage = 'Viết bài SEO 2000 từ về giá bọc răng sứ thẩm mỹ nhé.';

        $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspace->id,
        ])->postJson("/api/v1/conversations/{$conversationUuid}/messages", [
            'content' => $userWriteMessage,
            'role' => 'user',
            'images' => [
                [
                    'id' => 'img_smile_01',
                    'previewUrl' => 'https://cdn.nhakhoasmile.vn/user-uploads/patient-smile.jpg',
                    'name' => 'patient-smile.jpg',
                    'size' => 102400,
                ],
            ],
        ])->assertStatus(201);

        // Mô phỏng Gemini Vision đọc hiểu ảnh người dùng gửi và phân tích vị trí đặt ảnh
        $visionAnalysis = $this->visionService->analyzeImage([
            'name' => 'patient-smile.jpg',
            'previewUrl' => 'https://cdn.nhakhoasmile.vn/user-uploads/patient-smile.jpg',
        ]);
        $visionResult = [
            'alt_text' => 'Nha khoa Smile - giá bọc răng sứ thẩm mỹ',
            'caption' => 'Nụ cười rạng rỡ của khách hàng sau khi bọc răng sứ tại Nha khoa Smile.',
            'optimized_webp_url' => 'https://cdn.nhakhoasmile.vn/user-uploads/patient-smile.webp',
            'vision_analysis' => $visionAnalysis,
        ];

        $this->assertNotEmpty($visionResult['alt_text']);
        $this->assertStringContainsString('Nha khoa Smile', $visionResult['alt_text']);

        $articleKeyword = Keyword::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'keyword' => 'giá bọc răng sứ thẩm mỹ',
        ]);

        // Soạn thảo bài viết qua ChatbotBillingBridge (trừ 20 credit)
        $articleData = $this->billingBridge->executeWithBilling(
            workspace: $this->workspace,
            toolName: 'content_studio_write',
            conversation: $conversation,
            toolAction: function () use ($visionResult, $articleKeyword) {
                $articleContent = <<<HTML
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Bảng Giá Bọc Răng Sứ Thẩm Mỹ Mới Nhất 2026","description":"Khám phá bảng giá bọc răng sứ thẩm mỹ mới nhất 2026 tại Nha Khoa Smile."}</script>
<h1>Bảng Giá Bọc Răng Sứ Thẩm Mỹ Mới Nhất 2026 Tại Nha Khoa Smile</h1>
<p>Trong thẩm mỹ nha khoa hiện đại, <strong>giá bọc răng sứ thẩm mỹ</strong> luôn là chủ đề được đông đảo khách hàng quan tâm khi tìm kiếm giải pháp kiến tạo nụ cười hoàn hảo, chuẩn tỷ lệ vàng và bảo tồn tối đa răng thật tại Nha Khoa Smile với công nghệ {$this->brandKnowledge->usp}.</p>
<p>Nhu cầu phục hình răng ngày càng gia tăng, khiến thông tin về giá bọc răng sứ thẩm mỹ trở nên quan trọng hơn bao giờ hết đối với người tiêu dùng thông thái. Một hàm răng khỏe đẹp không chỉ giúp nâng tầm diện mạo mà còn cải thiện chức năng ăn nhai và mang lại sự tự tin bền vững trong cuộc sống hàng ngày.</p>
<h2>Bảng giá bọc răng sứ thẩm mỹ các dòng sứ cao cấp tại Nha Khoa Smile</h2>
<p>Chi tiết chi phí các dòng răng sứ Cercon, Zirconia và Emax chính hãng được niêm yết công khai, minh bạch tại hệ thống nha khoa của chúng tôi. Khi cân nhắc giá bọc răng sứ thẩm mỹ, khách hàng cần nắm rõ đặc tính sinh học cũng như độ bền cơ học của từng chất liệu để đưa ra lựa chọn đầu tư tối ưu nhất.</p>
<p>Mỗi dòng răng sứ sở hữu những ưu điểm vượt trội riêng biệt, từ độ trong bóng tự nhiên đến khả năng chịu lực gấp nhiều lần răng sinh học. Đội ngũ bác sĩ chuyên khoa luôn sẵn sàng tư vấn chi tiết về giá bọc răng sứ thẩm mỹ theo từng trường hợp cụ thể để đảm bảo kết quả phục hình thẩm mỹ đạt chuẩn y khoa cao nhất.</p>
<img src="{$visionResult['optimized_webp_url']}" alt="{$visionResult['alt_text']}" />
<p><em>Hình 1: {$visionResult['caption']}</em></p>
<h2>Ưu điểm vượt trội của công nghệ Nano Bio tại Nha Khoa Smile</h2>
<p>Công nghệ bọc sứ Nano Bio tiên tiến giúp bảo tồn tối đa cùi răng tự nhiên, giảm thiểu xâm lấn mô cứng và bảo vệ tủy răng toàn diện. Yếu tố này ảnh hưởng tích cực đến giá bọc răng sứ thẩm mỹ vì khách hàng không phải chi trả thêm các khoản điều trị phát sinh không mong muốn sau này.</p>
<p>Vật liệu sứ Nano sinh học tương thích tuyệt đối với nướu, ngăn ngừa viêm lợi, đen viền nướu và tình trạng hôi miệng thường gặp ở các mão kim loại truyền thống. Đầu tư vào giá bọc răng sứ thẩm mỹ chất lượng cao chính là bảo vệ sức khỏe răng miệng dài lâu cho cả tương lai của bạn.</p>
<h3>Quy trình bọc răng sứ 5 bước chuẩn y khoa</h3>
<p>Bước 1 là khám tổng quát và chụp phim CT 3D. Bước 2 là lập phác đồ điều trị cá nhân hóa và thông báo rõ ràng về giá bọc răng sứ thẩm mỹ trọn gói cho từng răng. Bước 3 là vệ sinh khoang miệng và sửa soạn cùi răng siêu vi phẫu. Bước 4 là lấy dấu răng kỹ thuật số bằng máy quét 3D chuyên dụng. Bước 5 là gắn mão sứ cố định và hướng dẫn chăm sóc tại nhà.</p>
<p>Toàn bộ quy trình diễn ra trong môi trường vô trùng tuyệt đối, tuân thủ nghiêm ngặt theo các tiêu chuẩn quốc tế của Hiệp hội Nha khoa Hoa Kỳ ADA. Sự chuyên nghiệp trong từng thao tác giúp mức giá bọc răng sứ thẩm mỹ tại cơ sở chúng tôi luôn xứng đáng với giá trị quý khách nhận được.</p>
<h2>Hệ thống liên kết nội bộ và tài liệu y khoa tham khảo</h2>
<p>Để tìm hiểu sâu hơn về công nghệ hiện đại, bạn có thể tham khảo thêm bài viết <a href="/dich-vu/boc-rang-su-nano">Công nghệ bọc răng sứ Nano Bio</a> và tra cứu biểu phí tại chuyên mục <a href="/bang-gia-nha-khoa">Bảng giá nha khoa tổng quát</a>. Ngoài ra, hãy truy cập cổng thông tin của <a href="https://moh.gov.vn">Bộ Y Tế Việt Nam</a> để nắm rõ các khuyến nghị an toàn trong can thiệp thẩm mỹ răng miệng.</p>
<p>Các tài liệu y khoa chính thống luôn nhấn mạnh việc cân nhắc giá bọc răng sứ thẩm mỹ đi liền với chứng chỉ hành nghề của bác sĩ và xuất xứ chính hãng của phôi sứ. Điều này loại bỏ hoàn toàn các rủi ro biến chứng nguy hiểm do sử dụng vật liệu nha khoa trôi nổi trên thị trường.</p>
<h2>Kinh nghiệm chăm sóc răng miệng sau khi bọc sứ</h2>
<p>Chế độ vệ sinh răng miệng hợp lý đóng vai trò quyết định đến tuổi thọ của răng sứ sau khi hoàn thiện phục hình. Sử dụng chỉ nha khoa và máy tăm nước hàng ngày giúp loại bỏ sạch mảng bám thức ăn ở các kẽ răng khó tiếp cận, bảo vệ thành quả của quá trình đầu tư giá bọc răng sứ thẩm mỹ dài lâu.</p>
<p>Bên cạnh đó, việc khám nha khoa định kỳ mỗi sáu tháng một lần là bắt buộc để bác sĩ kiểm tra khớp cắn, độ khít sát của mão răng và lấy vôi răng định kỳ. Chăm sóc đúng cách sẽ giúp nụ cười luôn tỏa sáng rạng ngời và mức giá bọc răng sứ thẩm mỹ ban đầu phát huy tối đa giá trị thẩm mỹ vượt thời gian.</p>
<h2>Câu hỏi thường gặp về giá bọc răng sứ thẩm mỹ (FAQ)</h2>
<h3>Bọc răng sứ thẩm mỹ giá bao nhiêu một răng?</h3>
<p>Mức giá bọc răng sứ thẩm mỹ dao động từ 1.500.000 đồng đến 8.000.000 đồng mỗi răng, tùy thuộc vào chất liệu sứ sinh học và chính sách ưu đãi hiện hành của phòng khám.</p>
<h3>Bọc răng sứ có đau không?</h3>
<p>Quá trình bọc sứ hoàn toàn không đau đớn nhờ công nghệ gây tê vi điểm thế hệ mới và kỹ thuật sửa soạn răng tối thiểu từ bác sĩ chuyên khoa giàu kinh nghiệm.</p>
<h3>Thời gian bảo hành răng sứ thẩm mỹ là bao lâu?</h3>
<p>Nha Khoa Smile cung cấp thẻ bảo hành điện tử chính hãng từ 7 đến 15 năm cho tất cả khách hàng an tâm với mức giá bọc răng sứ thẩm mỹ đã chi trả.</p>
HTML;

                // Tạo bản ghi Article với đầy đủ thực thể và ảnh chuẩn SEO
                $article = Article::create([
                    'tenant_id' => $this->tenant->id,
                    'workspace_id' => $this->workspace->id,
                    'website_id' => $this->website->id,
                    'wordpress_site_id' => $this->wpSite->id,
                    'user_id' => $this->user->id,
                    'keyword_id' => $articleKeyword->id,
                    'title' => 'Bảng Giá Bọc Răng Sứ Thẩm Mỹ Mới Nhất 2026',
                    'slug' => 'bang-gia-boc-rang-su-tham-my-2026',
                    'seo_title' => 'Bảng Giá Bọc Răng Sứ Thẩm Mỹ Mới Nhất 2026 Chuẩn Nha Khoa Smile',
                    'seo_description' => 'Khám phá bảng giá bọc răng sứ thẩm mỹ 2026 tại Nha Khoa Smile. Công nghệ bọc sứ Nano Bio không mài nhỏ răng, bảo tồn tủy 99%, liên hệ nhận ưu đãi ngay!',
                    'focus_keyword' => 'giá bọc răng sứ thẩm mỹ',
                    'content' => $articleContent,
                    'status' => ArticleStatus::DRAFT,
                    'word_count' => 1200,
                    'featured_image_url' => $visionResult['optimized_webp_url'],
                    'faqs' => [
                        ['question' => 'Bọc răng sứ thẩm mỹ giá bao nhiêu một răng?', 'answer' => 'Chi phí bọc răng sứ dao động từ 1.500.000đ đến 8.000.000đ tùy chất liệu sứ.'],
                        ['question' => 'Bọc răng sứ có đau không?', 'answer' => 'Hoàn toàn không đau nhờ công nghệ gây tê hiện đại.'],
                        ['question' => 'Thời gian bảo hành răng sứ thẩm mỹ là bao lâu?', 'answer' => 'Nha Khoa Smile bảo hành chính hãng từ 7 đến 15 năm.'],
                    ],
                    'internal_links' => [
                        ['url' => '/dich-vu/boc-rang-su-nano', 'anchor' => 'Công nghệ bọc răng sứ Nano Bio'],
                        ['url' => '/bang-gia-nha-khoa', 'anchor' => 'Bảng giá nha khoa tổng quát'],
                    ],
                ]);

                return [
                    'tool' => 'content_studio_write',
                    'article_id' => $article->id,
                    'title' => $article->title,
                    'word_count' => 1200,
                    'article' => $article,
                ];
            },
            actor: $this->user,
            params: [
                'keyword' => 'giá bọc răng sứ thẩm mỹ',
                'word_count' => 2000,
            ],
            idempotencyKey: "conv_{$conversation->id}_step_3_write"
        );

        /** @var Article $article */
        $article = $articleData['article'];
        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame(175, $this->billingBridge->getBalance($this->workspace)->availableCredits);

        // ------------------------------------------------------------------------------------
        // BƯỚC 5: Chấm điểm SEO tự động bằng SeoScoringEngine (P2.5.4 - 0 credit)
        // ------------------------------------------------------------------------------------
        $scoreResult = $this->scoringEngine->auditArticle($article);
        $scoreResult['score'] = $scoreResult['total_score'];
        $this->assertGreaterThanOrEqual(75, $scoreResult['score']);
        $this->assertGreaterThanOrEqual(75, $scoreResult['total_score']);
        $this->assertSame(175, $this->billingBridge->getBalance($this->workspace)->availableCredits, 'Chấm điểm SEO thuần PHP không được trừ credit.');

        // ------------------------------------------------------------------------------------
        // BƯỚC 6: Người dùng yêu cầu Đăng bài lên WordPress -> Kích hoạt Human Gate (P2.5.7)
        // ------------------------------------------------------------------------------------
        $userPublishMessage = 'Bài viết đạt điểm SEO cao rồi, đăng bài viết này lên WordPress website chính giúp mình nhé.';

        $publishIntent = $this->intentParser->parse($userPublishMessage, [
            'article_id' => $article->id,
            'website_id' => $this->website->id,
        ]);
        $this->assertSame('publish_wordpress', $publishIntent->intent);

        // Vì wordpress_publish là hành động nhạy cảm (is_sensitive: true), hệ thống KHÔNG tự ý đăng ngay!
        // Thay vào đó tạo Pending Action chờ người dùng duyệt
        $pendingAction = $this->approvalService->createPendingAction(
            conversation: $conversation,
            actionName: 'publish_to_wordpress',
            payload: [
                'article_id' => $article->id,
                'site_id' => $this->wpSite->id,
                'status' => 'publish',
            ],
            user: $this->user,
            estimatedCredits: 0
        );

        $this->assertSame('pending', $pendingAction->status);
        $this->assertSame(175, $this->billingBridge->getBalance($this->workspace)->availableCredits);

        // ------------------------------------------------------------------------------------
        // BƯỚC 7: Người dùng bấm Duyệt trên thẻ Action Card qua API (P2.5.7)
        // ------------------------------------------------------------------------------------
        Queue::fake([PublishToWordPressJob::class]);

        $approveResponse = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspace->id,
        ])->postJson("/api/v1/chatbot/actions/{$pendingAction->uuid}/approve");

        $approveResponse->assertStatus(200);
        $approveResponse->assertJson([
            'success' => true,
            'data' => [
                'status' => 'approved',
                'credits_deducted' => 0,
            ],
        ]);

        $pendingAction->refresh();
        $this->assertSame('approved', $pendingAction->status);
        $this->assertNotNull($pendingAction->reviewed_at);

        // Xác nhận Job đẩy lên WordPress đã được đưa vào hàng đợi an toàn
        Queue::assertPushed(PublishToWordPressJob::class, function ($job) use ($article) {
            return $job->articleId === $article->id;
        });

        // ------------------------------------------------------------------------------------
        // BƯỚC 8: Kiểm toán Sổ cái Credit Ledger & Ghi nhật ký Token (P2.5.8)
        // ------------------------------------------------------------------------------------
        // 1. Kiểm tra số dư cuối cùng
        $finalBalance = $this->billingBridge->getBalance($this->workspace)->availableCredits;
        $this->assertSame(175, $finalBalance); // 200 - 2 (kw) - 3 (comp) - 20 (article) = 175

        // 2. Kiểm tra tính toàn vẹn của sổ cái giao dịch (1 grant + 3 reserves + 3 deductions = 7 transactions)
        $transactions = CreditTransaction::where('workspace_id', $this->workspace->id)->get();
        $this->assertCount(7, $transactions);

        $reserves = $transactions->where('type', CreditTransactionType::RESERVE);
        $this->assertCount(3, $reserves);
        $this->assertSame(-25, (int) $reserves->sum('amount'));

        $deductions = $transactions->where('type', CreditTransactionType::DEDUCT);
        $this->assertCount(3, $deductions);
        $this->assertSame(-25, (int) $deductions->sum('amount'));
        $this->assertSame(25, abs((int) $deductions->sum('amount')));

        // 3. Ghi log token và kiểm tra tính minh bạch chi phí
        $this->billingBridge->logAiUsage(
            conversation: $conversation,
            provider: 'gemini',
            model: 'gemini-2.5-flash',
            promptTokens: 1200,
            completionTokens: 2500,
            durationMs: 500,
            jobType: 'content_studio_write',
            creditsCharged: 20
        );

        $this->assertDatabaseHas('ai_usage_logs', [
            'workspace_id' => $this->workspace->id,
            'model' => 'gemini-2.5-flash',
            'prompt_tokens' => 1200,
            'completion_tokens' => 2500,
            'total_tokens' => 3700,
        ]);
    }

    /**
     * P2.5.9.3: Zero Loss Guarantee — Khi người dùng bấm Từ chối hoặc Tool bị lỗi, 100% credit được bảo toàn.
     */
    public function test_end_to_end_zero_loss_guarantee_on_action_rejection_or_tool_failure(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Kịch bản Người dùng Từ chối (User Rejection)
        $balanceBefore = $this->billingBridge->getBalance($this->workspace)->availableCredits;

        // Tạo một Pending Action chạy lô Bulk 3 bài (3 * 18 = 54 credits)
        $pendingBulkAction = $this->approvalService->createPendingAction(
            conversation: Conversation::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $this->workspace->id,
                'website_id' => $this->website->id,
                'user_id' => $this->user->id,
                'title' => 'Phiên Bulk Generation',
            ]),
            actionName: 'bulk_generation',
            payload: [
                'keywords' => ['răng sứ cercon', 'răng sứ zirconia', 'răng sứ katana'],
            ],
            user: $this->user,
            estimatedCredits: 54
        );

        // Người dùng bấm Hủy bỏ / Từ chối qua API
        $rejectResponse = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspace->id,
        ])->postJson("/api/v1/chatbot/actions/{$pendingBulkAction->uuid}/reject", [
            'reason' => 'Đổi ý không muốn chạy bài hàng loạt lúc này.',
        ]);

        $rejectResponse->assertStatus(200);
        $pendingBulkAction->refresh();
        $this->assertSame('rejected', $pendingBulkAction->status);

        // Số dư ví không hề bị trừ một credit nào
        $balanceAfterReject = $this->billingBridge->getBalance($this->workspace)->availableCredits;
        $this->assertSame($balanceBefore, $balanceAfterReject);

        // 2. Kịch bản Tool gặp sự cố vĩnh viễn (Tool Failure Rollback)
        try {
            $this->billingBridge->executeWithBilling(
                workspace: $this->workspace,
                toolName: 'content_studio_write',
                conversation: $pendingBulkAction->conversation,
                toolAction: function () {
                    throw new RuntimeException('Gemini API timeout hoặc lỗi mạng không mong muốn!');
                },
                actor: $this->user,
                params: ['keyword' => 'từ khóa lỗi']
            );
            $this->fail('Lẽ ra phải ném RuntimeException khi tool bị lỗi');
        } catch (RuntimeException $e) {
            $this->assertSame('Gemini API timeout hoặc lỗi mạng không mong muốn!', $e->getMessage());
        }

        // Toàn bộ credit tạm giữ phải được hoàn lại 100% vào ví (Zero Loss)
        $balanceAfterToolFailure = $this->billingBridge->getBalance($this->workspace)->availableCredits;
        $this->assertSame($balanceBefore, $balanceAfterToolFailure);
    }

    /**
     * P2.5.9.3: Financial Security — Không đủ số dư credit sẽ lập tức từ chối duyệt hành động và trả về HTTP 402.
     */
    public function test_end_to_end_insufficient_credits_prevents_action_approval_and_returns_http_402(): void
    {
        // Tạo một Workspace nghèo credit (chỉ có 5 credits)
        $poorTenant = Tenant::create([
            'name' => 'Poor Tenant',
            'slug' => 'poor-tenant',
            'plan' => 'starter',
            'is_active' => true,
        ]);

        $poorUser = User::factory()->create([
            'email' => 'poor_user@example.com',
            'tenant_id' => $poorTenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $poorWorkspace = Workspace::create([
            'owner_id' => $poorUser->id,
            'legacy_tenant_id' => $poorTenant->id,
            'name' => 'Poor Workspace',
            'slug' => 'poor-ws',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $poorWorkspace->id,
            'user_id' => $poorUser->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $poorUser->update(['default_workspace_id' => $poorWorkspace->id]);

        $poorWebsite = Website::create([
            'workspace_id' => $poorWorkspace->id,
            'name' => 'Poor Website',
            'domain' => 'poorwebsite.com',
            'url' => 'https://poorwebsite.com',
        ]);

        $this->creditLedger->grantCredits(
            workspace: $poorWorkspace,
            amount: 5,
            source: CreditGrantSource::PROMO,
            note: 'Only 5 credits'
        );

        // Tạo Pending Action yêu cầu 20 credits
        $action = ChatPendingAction::create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => Conversation::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $poorWorkspace->id,
                'website_id' => $poorWebsite->id,
                'user_id' => $poorUser->id,
                'title' => 'Poor chat',
            ])->id,
            'workspace_id' => $poorWorkspace->id,
            'user_id' => $poorUser->id,
            'action_name' => 'content_studio_write',
            'estimated_credits' => 20,
            'action_payload' => ['keyword' => 'seo bài viết'],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        Sanctum::actingAs($poorUser);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $poorWorkspace->id,
        ])->postJson("/api/v1/chatbot/actions/{$action->uuid}/approve");

        $response->assertStatus(402);
        $response->assertJson([
            'success' => false,
            'error_code' => 'INSUFFICIENT_CREDITS',
        ]);

        $action->refresh();
        $this->assertSame('pending', $action->status, 'Hành động không được duyệt khi thiếu credit.');
        $this->assertSame(5, $this->billingBridge->getBalance($poorWorkspace)->availableCredits);
    }

    /**
     * P2.5.9.3: AI Orchestrator — Yêu cầu mơ hồ tự động kích hoạt câu hỏi làm rõ (Needs Clarification).
     */
    public function test_end_to_end_multi_step_execution_plan_clarification_flow(): void
    {
        $vaguePrompt = 'Viết bài đi';

        $intent = $this->intentParser->parse($vaguePrompt);
        $this->assertTrue($intent->needsClarification);
        $this->assertNotEmpty($intent->clarificationQuestion);

        $plan = $this->orchestrator->generateExecutionPlan($intent, $this->website);
        $this->assertTrue($plan->clarificationNeeded);
        $this->assertFalse($plan->canExecuteImmediately);
        $this->assertSame(0, $plan->totalEstimatedCredits);
    }
}
