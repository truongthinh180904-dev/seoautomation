<?php

namespace Tests\Feature;

use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\UsageRecordStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CreditTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\ChatbotBillingBridge;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ChatbotCreditCostTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Website $websiteA;

    protected Conversation $conversationA;

    protected ConversationMessage $messageA;

    protected Tenant $tenantB;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Website $websiteB;

    protected Conversation $conversationB;

    protected CreditLedgerService $creditLedger;

    protected ChatbotBillingBridge $billingBridge;

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
        $this->billingBridge = app(ChatbotBillingBridge::class);

        // Đăng ký route kiểm thử cho việc trigger InsufficientCreditsException qua HTTP (P2.5.8.4)
        Route::middleware(['auth:sanctum', 'active_account'])->prefix('api/v1/chatbot/billing-test')->group(function () {
            Route::post('require-credits', function (Request $request) {
                $workspaceId = $request->header('X-Workspace-Id')
                    ?: $request->input('workspace_id')
                    ?: $request->user()?->default_workspace_id;

                $workspace = Workspace::findOrFail((int) $workspaceId);
                $required = (int) $request->input('required_credits', 20);

                app(ChatbotBillingBridge::class)->checkCredits($workspace, $required);

                return response()->json([
                    'success' => true,
                    'message' => 'Số dư credit hợp lệ.',
                    'required_credits' => $required,
                ]);
            });
        });

        // 1. Khởi tạo Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Smile Dental Tenant',
            'slug' => 'smile-dental-tenant',
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

        $this->conversationA = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'user_id' => $this->userA->id,
            'title' => 'Tư vấn SEO Nha Khoa Smile',
        ]);

        $this->messageA = ConversationMessage::create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $this->conversationA->id,
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'role' => 'user',
            'content' => 'Viết bài SEO về bọc răng sứ thẩm mỹ',
            'status' => 'completed',
        ]);

        // 2. Khởi tạo Workspace B (cô lập multi-tenant)
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
            'name' => 'Competitor Website',
            'domain' => 'competitor.com',
            'url' => 'https://competitor.com',
        ]);

        $this->conversationB = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceB->id,
            'website_id' => $this->websiteB->id,
            'user_id' => $this->userB->id,
            'title' => 'Chiến dịch Workspace B',
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
     * P2.5.8: Kiểm tra bảng định mức chi phí chuẩn cho toàn bộ 10+ công cụ SEO.
     */
    public function test_get_tool_cost_returns_correct_pricing_table(): void
    {
        $this->assertSame(2, $this->billingBridge->getToolCost('keyword_research'));
        $this->assertSame(3, $this->billingBridge->getToolCost('competitor_analysis'));
        $this->assertSame(2, $this->billingBridge->getToolCost('topic_cluster'));
        $this->assertSame(2, $this->billingBridge->getToolCost('content_gap'));
        $this->assertSame(20, $this->billingBridge->getToolCost('content_studio_write'));
        $this->assertSame(20, $this->billingBridge->getToolCost('article_write'));
        $this->assertSame(1, $this->billingBridge->getToolCost('article_rewrite'));
        $this->assertSame(5, $this->billingBridge->getToolCost('ai_image'));
        $this->assertSame(0, $this->billingBridge->getToolCost('seo_score'));
        $this->assertSame(0, $this->billingBridge->getToolCost('internal_links'));
        $this->assertSame(0, $this->billingBridge->getToolCost('stock_image_free'));
        $this->assertSame(0, $this->billingBridge->getToolCost('wordpress_publish'));

        // Bulk Generation: tính theo số lượng từ khóa (18 credits/bài)
        $this->assertSame(54, $this->billingBridge->getToolCost('bulk_generation', [
            'keywords' => ['từ khóa 1', 'từ khóa 2', 'từ khóa 3'],
        ]));
    }

    /**
     * P2.5.8.1: reserveCredits tạm giữ credit trước khi gọi tool (chuyển available -> reserved).
     */
    public function test_reserve_credits_holds_credits_and_updates_account_balances(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial balance'
        );

        $balanceBefore = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(100, $balanceBefore->availableCredits);
        $this->assertSame(0, $balanceBefore->reservedCredits);

        // Reserve 20 credits cho tool content_studio_write
        $tx = $this->billingBridge->reserveCredits(
            workspace: $this->workspaceA,
            toolName: 'content_studio_write',
            amount: 20,
            conversation: $this->conversationA,
            message: $this->messageA,
            actor: $this->userA
        );

        $this->assertInstanceOf(CreditTransaction::class, $tx);
        $this->assertSame(CreditTransactionType::RESERVE, $tx->type);
        $this->assertSame(-20, (int) $tx->amount);
        $this->assertSame(100, (int) $tx->balance_before);
        $this->assertSame(80, (int) $tx->balance_after);

        $balanceAfter = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(80, $balanceAfter->availableCredits);
        $this->assertSame(20, $balanceAfter->reservedCredits);
        $this->assertSame(100, $balanceAfter->totalCredits);
    }

    /**
     * P2.5.8.1: commitReservation trừ từ phần đã reserve sau khi tool thành công và tăng cycle_used_credits.
     */
    public function test_commit_reservation_finalizes_deduction_from_reserved_pool(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial balance'
        );

        // Bước 1: Reserve 20 credits
        $this->billingBridge->reserveCredits(
            workspace: $this->workspaceA,
            toolName: 'content_studio_write',
            amount: 20,
            conversation: $this->conversationA,
            message: $this->messageA,
            actor: $this->userA
        );

        // Bước 2: Commit 20 credits
        $commitTx = $this->billingBridge->commitReservation(
            workspace: $this->workspaceA,
            toolName: 'content_studio_write',
            amount: 20,
            conversation: $this->conversationA,
            message: $this->messageA,
            actor: $this->userA
        );

        $this->assertInstanceOf(CreditTransaction::class, $commitTx);
        $this->assertSame(CreditTransactionType::DEDUCT, $commitTx->type);
        $this->assertSame(-20, (int) $commitTx->amount);

        // Số dư khả dụng giữ nguyên 80, số dư tạm giữ giảm về 0, credit đã dùng trong chu kỳ tăng lên 20
        $balanceFinal = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(80, $balanceFinal->availableCredits);
        $this->assertSame(0, $balanceFinal->reservedCredits);
        $this->assertSame(20, $balanceFinal->cycleUsedCredits);
        $this->assertSame(80, $balanceFinal->totalCredits);
    }

    /**
     * P2.5.8.2: Zero Loss Guarantee — Giải phóng credit tạm giữ khi tác vụ thất bại hoặc bị hủy.
     */
    public function test_zero_loss_guarantee_releases_reserved_credits_on_failure(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE,
            note: 'Initial balance'
        );

        // Tạm giữ 20 credits
        $this->billingBridge->reserveCredits(
            workspace: $this->workspaceA,
            toolName: 'content_studio_write',
            amount: 20,
            conversation: $this->conversationA,
            message: $this->messageA,
            actor: $this->userA
        );

        $this->assertSame(80, $this->billingBridge->getBalance($this->workspaceA)->availableCredits);
        $this->assertSame(20, $this->billingBridge->getBalance($this->workspaceA)->reservedCredits);

        // Giả lập tác vụ gặp sự cố hoặc người dùng hủy -> Giải phóng 100%
        $releaseTx = $this->billingBridge->releaseReservation(
            workspace: $this->workspaceA,
            toolName: 'content_studio_write',
            amount: 20,
            conversation: $this->conversationA,
            message: $this->messageA,
            reason: 'Tác vụ AI tạo bài bị timeout',
            actor: $this->userA
        );

        $this->assertInstanceOf(CreditTransaction::class, $releaseTx);
        $this->assertSame(CreditTransactionType::RELEASE_REFUND, $releaseTx->type);
        $this->assertSame(20, (int) $releaseTx->amount);

        // Số dư khả dụng được khôi phục trọn vẹn 100 credit (Zero Loss)
        $balance = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(100, $balance->availableCredits);
        $this->assertSame(0, $balance->reservedCredits);
        $this->assertSame(0, $balance->cycleUsedCredits);
    }

    /**
     * P2.5.8.2: Hoàn trả credit (refund) khi đã commit nhưng cần hoàn trả do lỗi hệ thống.
     */
    public function test_refund_credits_reimburses_committed_credits_accurately(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 50,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        // Reserve và commit 5 credits cho ai_image
        $this->billingBridge->reserveCredits($this->workspaceA, 'ai_image', 5, $this->conversationA);
        $this->billingBridge->commitReservation($this->workspaceA, 'ai_image', 5, $this->conversationA);

        $this->assertSame(45, $this->billingBridge->getBalance($this->workspaceA)->availableCredits);
        $this->assertSame(5, $this->billingBridge->getBalance($this->workspaceA)->cycleUsedCredits);

        // Thực hiện hoàn tiền
        $refundTx = $this->billingBridge->refundCredits(
            workspace: $this->workspaceA,
            toolName: 'ai_image',
            amount: 5,
            conversation: $this->conversationA,
            reason: 'Ảnh sinh ra không đúng yêu cầu của người dùng'
        );

        $this->assertSame(5, (int) $refundTx->amount);
        $this->assertSame(CreditTransactionType::RELEASE_REFUND, $refundTx->type);

        $balance = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(50, $balance->availableCredits);
        $this->assertSame(0, $balance->cycleUsedCredits);
    }

    /**
     * P2.5.8.4: Ném ngoại lệ InsufficientCreditsException khi số dư khả dụng không đủ.
     */
    public function test_check_credits_throws_insufficient_credits_exception(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 10,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $this->expectException(InsufficientCreditsException::class);

        // Yêu cầu 20 credits trong khi chỉ có 10 credits
        $this->billingBridge->checkCredits($this->workspaceA, 20);
    }

    /**
     * P2.5.8.4: Khi gọi qua API, InsufficientCreditsException tự động trả về phản hồi JSON mã HTTP 402.
     */
    public function test_insufficient_credits_exception_renders_http_402_via_api(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 10,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/chatbot/billing-test/require-credits', [
                'required_credits' => 50,
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('error_code', 'INSUFFICIENT_CREDITS')
            ->assertJsonPath('required_credits', 50)
            ->assertJsonPath('available_credits', 10);
    }

    /**
     * P2.5.8.1: Idempotency Key bảo vệ giao dịch chống trừ credit 2 lần khi gửi trùng lặp hoặc thử lại.
     */
    public function test_idempotency_prevents_duplicate_reservations_and_commits(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 100,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $customKey = 'idemp_key_unique_test_123';

        // Gọi reserve lần 1
        $tx1 = $this->billingBridge->reserveCredits(
            workspace: $this->workspaceA,
            toolName: 'keyword_research',
            amount: 2,
            conversation: $this->conversationA,
            idempotencyKey: $customKey
        );

        // Gọi reserve lần 2 với cùng key
        $tx2 = $this->billingBridge->reserveCredits(
            workspace: $this->workspaceA,
            toolName: 'keyword_research',
            amount: 2,
            conversation: $this->conversationA,
            idempotencyKey: $customKey
        );

        $this->assertSame($tx1->id, $tx2->id);

        // Số dư chỉ bị trừ đúng 2 credits (100 - 2 = 98), không bị trừ 4 credits
        $balance = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(98, $balance->availableCredits);
        $this->assertSame(2, $balance->reservedCredits);
    }

    /**
     * P2.5.8.1 & P2.5.8.2: executeWithBilling tự động commit khi callback thành công.
     */
    public function test_execute_with_billing_commits_on_successful_execution(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 50,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $executed = false;

        $result = $this->billingBridge->executeWithBilling(
            workspace: $this->workspaceA,
            toolName: 'competitor_analysis', // 3 credits
            conversation: $this->conversationA,
            toolAction: function () use (&$executed) {
                $executed = true;

                return ['top_competitors' => ['https://nhakhoaa.com', 'https://nhakhoab.com', 'https://nhakhoac.com']];
            },
            message: $this->messageA,
            actor: $this->userA
        );

        $this->assertTrue($executed);
        $this->assertIsArray($result);
        $this->assertCount(3, $result['top_competitors']);

        // Số dư khả dụng giảm đúng 3 credits (50 - 3 = 47), số dư tạm giữ về 0
        $balance = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(47, $balance->availableCredits);
        $this->assertSame(0, $balance->reservedCredits);
        $this->assertSame(3, $balance->cycleUsedCredits);
    }

    /**
     * P2.5.8.2: executeWithBilling thực thi Zero Loss Guarantee — hoàn lại 100% credit tạm giữ khi gặp ngoại lệ.
     */
    public function test_execute_with_billing_enforces_zero_loss_guarantee_on_exception(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 50,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $this->assertSame(50, $this->billingBridge->getBalance($this->workspaceA)->availableCredits);

        $exceptionCaught = false;

        try {
            $this->billingBridge->executeWithBilling(
                workspace: $this->workspaceA,
                toolName: 'content_studio_write', // 20 credits
                conversation: $this->conversationA,
                toolAction: function () {
                    throw new RuntimeException('Gemini LLM Provider Rate Limited');
                },
                message: $this->messageA,
                actor: $this->userA
            );
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame('Gemini LLM Provider Rate Limited', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught);

        // Số dư khả dụng được hoàn trả 100% về lại 50 credits, không mất dù chỉ 1 credit
        $balance = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(50, $balance->availableCredits);
        $this->assertSame(0, $balance->reservedCredits);
        $this->assertSame(0, $balance->cycleUsedCredits);
    }

    /**
     * Các công cụ 0 credit (seo_score, internal_links, stock_image_free, wordpress_publish)
     * được thực thi trực tiếp mà không cần giữ hay trừ credit.
     */
    public function test_execute_with_billing_runs_zero_credit_tools_without_touching_balance(): void
    {
        $this->creditLedger->grantCredits(
            workspace: $this->workspaceA,
            amount: 50,
            source: CreditGrantSource::SUBSCRIPTION_CYCLE
        );

        $initialTxCount = CreditTransaction::where('workspace_id', $this->workspaceA->id)->count();

        $result = $this->billingBridge->executeWithBilling(
            workspace: $this->workspaceA,
            toolName: 'seo_score', // 0 credit
            conversation: $this->conversationA,
            toolAction: function () {
                return ['score' => 92, 'passed' => true];
            }
        );

        $this->assertSame(['score' => 92, 'passed' => true], $result);

        // Không phát sinh thêm bất kỳ transaction trừ credit nào
        $currentTxCount = CreditTransaction::where('workspace_id', $this->workspaceA->id)->count();
        $this->assertSame($initialTxCount, $currentTxCount);

        $balance = $this->billingBridge->getBalance($this->workspaceA);
        $this->assertSame(50, $balance->availableCredits);
    }

    /**
     * P2.5.8.3: Ghi nhận nhật ký AI chi tiết gồm token, latency, chi phí USD vào ai_usage_logs và usage_records.
     */
    public function test_log_ai_usage_persists_logs_and_calculates_usd_cost(): void
    {
        $promptTokens = 2500;
        $completionTokens = 1200;
        $durationMs = 480;

        $usageLog = $this->billingBridge->logAiUsage(
            conversation: $this->conversationA,
            provider: 'gemini',
            model: 'gemini-1.5-flash',
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            durationMs: $durationMs,
            jobType: 'chatbot_writing',
            creditsCharged: 20,
            status: 'success',
            message: $this->messageA
        );

        $this->assertInstanceOf(AiUsageLog::class, $usageLog);
        $this->assertSame($this->workspaceA->id, $usageLog->workspace_id);
        $this->assertSame($this->websiteA->id, $usageLog->website_id);
        $this->assertSame('gemini', $usageLog->provider);
        $this->assertSame('gemini-1.5-flash', $usageLog->model);
        $this->assertSame(2500, $usageLog->prompt_tokens);
        $this->assertSame(1200, $usageLog->completion_tokens);
        $this->assertSame(3700, $usageLog->total_tokens);
        $this->assertSame(20, $usageLog->credits_charged);
        $this->assertSame(480, $usageLog->duration_ms);
        $this->assertSame('success', $usageLog->status);
        $this->assertGreaterThan(0, $usageLog->cost_usd);

        // Kiểm tra bản ghi đồng bộ trên bảng usage_records cho SaaS Workspace
        $this->assertDatabaseHas('usage_records', [
            'workspace_id' => $this->workspaceA->id,
            'website_id' => $this->websiteA->id,
            'action_type' => 'chatbot_writing',
            'tokens_used' => 3700,
            'credits_charged' => 20,
            'status' => UsageRecordStatus::SETTLED->value,
        ]);

        // Kiểm tra ConversationMessage được gắn metadata usage
        $this->messageA->refresh();
        $this->assertIsArray($this->messageA->metadata);
        $this->assertArrayHasKey('ai_usage', $this->messageA->metadata);
        $this->assertSame('gemini-1.5-flash', $this->messageA->metadata['ai_usage']['model']);
        $this->assertSame(3700, $this->messageA->metadata['ai_usage']['total_tokens']);
    }

    /**
     * P2.5.8.3: getConversationCostSummary tổng hợp chính xác toàn bộ chi phí token, USD và credit của phiên chat.
     */
    public function test_get_conversation_cost_summary_aggregates_session_costs(): void
    {
        // Tin nhắn 1: 1000 prompt + 500 completion = 1500 tokens
        $this->billingBridge->logAiUsage(
            conversation: $this->conversationA,
            provider: 'gemini',
            model: 'gemini-1.5-flash',
            promptTokens: 1000,
            completionTokens: 500,
            durationMs: 300,
            creditsCharged: 2,
            message: $this->messageA
        );

        // Tin nhắn 2
        $message2 = ConversationMessage::create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $this->conversationA->id,
            'workspace_id' => $this->workspaceA->id,
            'role' => 'assistant',
            'content' => 'Phản hồi chi tiết dàn ý SEO...',
        ]);

        $this->billingBridge->logAiUsage(
            conversation: $this->conversationA,
            provider: 'gemini',
            model: 'gemini-1.5-flash',
            promptTokens: 2000,
            completionTokens: 1000,
            durationMs: 400,
            creditsCharged: 3,
            message: $message2
        );

        $summary = $this->billingBridge->getConversationCostSummary($this->conversationA);

        $this->assertSame($this->conversationA->id, $summary['conversation_id']);
        $this->assertSame(2, $summary['logs_count']);
        $this->assertSame(3000, $summary['prompt_tokens']);
        $this->assertSame(1500, $summary['completion_tokens']);
        $this->assertSame(4500, $summary['total_tokens']);
        $this->assertSame(5, $summary['credits_charged']);
        $this->assertSame(700, $summary['total_duration_ms']);
        $this->assertGreaterThan(0, $summary['cost_usd']);
    }

    /**
     * Multi-Tenancy Isolation: Workspace A tuyệt đối không ảnh hưởng đến số dư ví và chi phí của Workspace B.
     */
    public function test_multi_tenancy_isolation_ensures_credit_and_logs_belong_strictly_to_workspace(): void
    {
        // Cấp 100 credits cho Workspace A và 50 credits cho Workspace B
        $this->creditLedger->grantCredits($this->workspaceA, 100, CreditGrantSource::SUBSCRIPTION_CYCLE);
        $this->creditLedger->grantCredits($this->workspaceB, 50, CreditGrantSource::SUBSCRIPTION_CYCLE);

        // Thực hiện giao dịch trên Workspace A
        $this->billingBridge->executeWithBilling(
            workspace: $this->workspaceA,
            toolName: 'content_studio_write', // 20 credits
            conversation: $this->conversationA,
            toolAction: fn () => ['status' => 'article_created']
        );

        // Số dư Workspace A giảm xuống 80
        $this->assertSame(80, $this->billingBridge->getBalance($this->workspaceA)->availableCredits);

        // Số dư Workspace B vẫn giữ nguyên tuyệt đối 50 credits
        $this->assertSame(50, $this->billingBridge->getBalance($this->workspaceB)->availableCredits);

        // Mọi transaction của A không xuất hiện trong B
        $txCountB = CreditTransaction::where('workspace_id', $this->workspaceB->id)
            ->where('type', CreditTransactionType::DEDUCT)
            ->count();
        $this->assertSame(0, $txCountB);
    }
}
