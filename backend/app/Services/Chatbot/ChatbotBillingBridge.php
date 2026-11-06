<?php

namespace App\Services\Chatbot;

use App\DTOs\CreditBalanceDTO;
use App\Enums\UsageRecordStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CreditTransaction;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use App\Services\Chatbot\Tools\SeoToolRegistry;
use App\Services\Cost\CostTrackingService;
use Illuminate\Support\Str;
use Throwable;

class ChatbotBillingBridge
{
    /**
     * Bảng định mức chi phí Credit mặc định cho các công cụ SEO và tương tác Chatbot.
     *
     * @var array<string, int>
     */
    public const TOOL_COSTS = [
        'keyword_research' => 2,
        'competitor_analysis' => 3,
        'topic_cluster' => 2,
        'content_gap' => 2,
        'content_studio_write' => 20,
        'article_write' => 20,
        'article_rewrite' => 1,
        'ai_image' => 5,
        'bulk_generation' => 18,
        'seo_score' => 0,
        'internal_links' => 0,
        'stock_image_free' => 0,
        'wordpress_publish' => 0,
    ];

    public function __construct(
        protected CreditLedgerService $creditLedgerService,
        protected ?CostTrackingService $costTrackingService = null,
        protected ?SeoToolRegistry $toolRegistry = null
    ) {
        $this->costTrackingService = $this->costTrackingService ?? (app()->bound(CostTrackingService::class) ? app(CostTrackingService::class) : null);
        $this->toolRegistry = $this->toolRegistry ?? (app()->bound(SeoToolRegistry::class) ? app(SeoToolRegistry::class) : null);
    }

    /**
     * Lấy định mức credit dự kiến cho một công cụ hoặc hành động.
     *
     * @param  array<string, mixed>  $params
     */
    public function getToolCost(string $toolName, array $params = []): int
    {
        if (in_array($toolName, ['bulk_generation', 'start_bulk_generation'], true)) {
            $keywords = (array) ($params['keywords'] ?? []);
            $count = max(1, count($keywords));

            return $count * 18;
        }

        if ($this->toolRegistry && $this->toolRegistry->has($toolName)) {
            $tool = $this->toolRegistry->get($toolName);
            if ($tool) {
                return $tool->getEstimatedCredits($params);
            }
        }

        return self::TOOL_COSTS[$toolName] ?? 0;
    }

    /**
     * Lấy số dư credit hiện tại của Workspace.
     */
    public function getBalance(Workspace|int $workspace): CreditBalanceDTO
    {
        return $this->creditLedgerService->getBalance($workspace);
    }

    /**
     * Kiểm tra nhanh xem Workspace có đủ credit hay không.
     */
    public function hasSufficientCredits(Workspace|int $workspace, int $amount): bool
    {
        return $this->creditLedgerService->hasSufficientCredits($workspace, $amount);
    }

    /**
     * Kiểm tra số dư khả dụng và ném ngoại lệ HTTP 402 nếu không đủ (P2.5.8.4).
     *
     * @throws InsufficientCreditsException
     */
    public function checkCredits(Workspace|int $workspace, int $amount): void
    {
        $this->creditLedgerService->checkCredits($workspace, $amount);
    }

    /**
     * Tạm giữ credit trước khi gọi tool (P2.5.8.1).
     * Áp dụng lock và trừ available_credits -> reserved_credits.
     * Chống trừ 2 lần qua Idempotency Key (Idempotency Guarantee).
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws InsufficientCreditsException
     */
    public function reserveCredits(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?array $metadata = null
    ): ?CreditTransaction {
        if ($amount <= 0) {
            return null;
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;
        $messageId = $message instanceof ConversationMessage ? $message->id : $message;

        $key = $idempotencyKey ?? sprintf(
            'chatbot:reserve:ws_%d:conv_%d:msg_%s:tool_%s',
            $workspaceId,
            $conversationId,
            $messageId ?? 'action',
            $toolName
        );

        $mergedMetadata = array_merge([
            'channel' => 'chatbot',
            'tool_name' => $toolName,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'operation' => 'reserve',
        ], $metadata ?? []);

        return $this->creditLedgerService->reserveCredits(
            workspace: $workspaceId,
            amount: $amount,
            referenceType: 'chatbot_tool',
            referenceId: $messageId ?: $conversationId,
            idempotencyKey: $key,
            metadata: $mergedMetadata,
            actor: $actor
        );
    }

    /**
     * Khấu trừ credit từ phần đã reserve sau khi tool hoàn tất thành công (P2.5.8.1).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function commitReservation(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?array $metadata = null
    ): ?CreditTransaction {
        if ($amount <= 0) {
            return null;
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;
        $messageId = $message instanceof ConversationMessage ? $message->id : $message;

        $key = $idempotencyKey ?? sprintf(
            'chatbot:commit:ws_%d:conv_%d:msg_%s:tool_%s',
            $workspaceId,
            $conversationId,
            $messageId ?? 'action',
            $toolName
        );

        $mergedMetadata = array_merge([
            'channel' => 'chatbot',
            'tool_name' => $toolName,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'operation' => 'commit',
        ], $metadata ?? []);

        return $this->creditLedgerService->deductCredits(
            workspace: $workspaceId,
            amount: $amount,
            referenceType: 'chatbot_tool',
            referenceId: $messageId ?: $conversationId,
            idempotencyKey: $key,
            metadata: $mergedMetadata,
            fromReserved: true,
            actor: $actor
        );
    }

    /**
     * Giải phóng credit tạm giữ trở lại số dư khả dụng (P2.5.8.2: Zero Loss Guarantee).
     * Kích hoạt khi tool lỗi, timeout hoặc người dùng từ chối/hủy bỏ hành động.
     */
    public function releaseReservation(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): ?CreditTransaction {
        if ($amount <= 0) {
            return null;
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;
        $messageId = $message instanceof ConversationMessage ? $message->id : $message;

        $key = $idempotencyKey ?? sprintf(
            'chatbot:release:ws_%d:conv_%d:msg_%s:tool_%s',
            $workspaceId,
            $conversationId,
            $messageId ?? 'action',
            $toolName
        );

        $releaseReason = $reason ?: sprintf('Chatbot tool %s bị hủy hoặc gặp sự cố (Zero Loss Guarantee)', $toolName);

        return $this->creditLedgerService->releaseCredits(
            workspace: $workspaceId,
            amount: $amount,
            referenceType: 'chatbot_tool',
            referenceId: $messageId ?: $conversationId,
            idempotencyKey: $key,
            reason: $releaseReason,
            actor: $actor
        );
    }

    /**
     * Bồi hoàn credit nếu đã commit nhưng xảy ra sự cố cần hoàn lại.
     */
    public function refundCredits(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): ?CreditTransaction {
        if ($amount <= 0) {
            return null;
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;
        $messageId = $message instanceof ConversationMessage ? $message->id : $message;

        $key = $idempotencyKey ?? sprintf(
            'chatbot:refund:ws_%d:conv_%d:msg_%s:tool_%s',
            $workspaceId,
            $conversationId,
            $messageId ?? 'action',
            $toolName
        );

        return $this->creditLedgerService->refundCredits(
            workspace: $workspaceId,
            amount: $amount,
            referenceType: 'chatbot_tool',
            referenceId: $messageId ?: $conversationId,
            idempotencyKey: $key,
            reason: $reason ?: sprintf('Hoàn trả credit cho chatbot tool %s', $toolName),
            actor: $actor
        );
    }

    /**
     * Alias method cho reserveCredits:
     */
    public function reserveToolCredits(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): ?CreditTransaction {
        return $this->reserveCredits($workspace, $toolName, $amount, $conversation, $message, $actor, $idempotencyKey);
    }

    /**
     * Alias method cho commitReservation:
     */
    public function commitToolCredits(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): ?CreditTransaction {
        return $this->commitReservation($workspace, $toolName, $amount, $conversation, $message, $actor, $idempotencyKey);
    }

    /**
     * Alias method cho releaseReservation:
     */
    public function releaseToolCredits(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): ?CreditTransaction {
        return $this->releaseReservation($workspace, $toolName, $amount, $conversation, $message, $reason, $actor, $idempotencyKey);
    }

    /**
     * Alias method cho refundCredits:
     */
    public function refundToolCredits(
        Workspace|int $workspace,
        string $toolName,
        int $amount,
        Conversation|int $conversation,
        null|ConversationMessage|int $message = null,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): ?CreditTransaction {
        return $this->refundCredits($workspace, $toolName, $amount, $conversation, $message, $reason, $actor, $idempotencyKey);
    }

    /**
     * Higher-Order Executor: Bọc toàn bộ chu trình gọi Tool của Chatbot trong cơ chế Billing tự động.
     * Quy trình:
     * 1. Xác định chi phí credit.
     * 2. Nếu credit > 0: Tạm giữ (reserve).
     * 3. Thực thi callback.
     * 4. Thành công: Khấu trừ (commit).
     * 5. Gặp ngoại lệ / Lỗi: Tự động giải phóng 100% (Zero Loss Guarantee) và ném tiếp ngoại lệ.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws Throwable
     */
    public function executeWithBilling(
        Workspace $workspace,
        string $toolName,
        Conversation $conversation,
        callable $toolAction,
        null|ConversationMessage|int $message = null,
        ?User $actor = null,
        ?int $costCredits = null,
        array $params = [],
        ?string $idempotencyKey = null
    ): mixed {
        $amount = $costCredits ?? $this->getToolCost($toolName, $params);

        if ($amount <= 0) {
            return $toolAction();
        }

        // 1. Reserve credit (sẽ ném InsufficientCreditsException nếu không đủ)
        $this->reserveCredits(
            workspace: $workspace,
            toolName: $toolName,
            amount: $amount,
            conversation: $conversation,
            message: $message,
            actor: $actor,
            idempotencyKey: $idempotencyKey ? 'res:'.$idempotencyKey : null
        );

        // 2. Chạy tác vụ
        try {
            $result = $toolAction();

            // 3. Commit reservation
            $this->commitReservation(
                workspace: $workspace,
                toolName: $toolName,
                amount: $amount,
                conversation: $conversation,
                message: $message,
                actor: $actor,
                idempotencyKey: $idempotencyKey ? 'com:'.$idempotencyKey : null
            );

            return $result;
        } catch (Throwable $e) {
            // 4. Zero Loss Guarantee: Hoàn lại 100% credit tạm giữ
            $this->releaseReservation(
                workspace: $workspace,
                toolName: $toolName,
                amount: $amount,
                conversation: $conversation,
                message: $message,
                reason: sprintf('Tool %s gặp sự cố: %s', $toolName, $e->getMessage()),
                actor: $actor,
                idempotencyKey: $idempotencyKey ? 'rel:'.$idempotencyKey : null
            );

            throw $e;
        }
    }

    /**
     * Tính toán chi phí USD dựa trên số token và model AI (P2.5.8.3).
     */
    public function calculateCost(string $provider, ?string $model, int $promptTokens = 0, int $completionTokens = 0): float
    {
        if ($this->costTrackingService) {
            return $this->costTrackingService->calculateCost($provider, $model, $promptTokens, $completionTokens);
        }

        if ($provider === 'serper') {
            return (float) config('ai_costs.serper_cost_per_call_usd', 0.001);
        }

        $rates = config('ai_costs.models', []);
        $rate = $rates[$model] ?? config('ai_costs.fallback_model_rate', ['input' => 5.0, 'output' => 15.0]);

        return round(
            (($promptTokens / 1000000) * $rate['input']) + (($completionTokens / 1000000) * $rate['output']),
            8
        );
    }

    /**
     * Ghi nhận nhật ký sử dụng AI, số token prompt/completion, latency và chi phí USD vào ai_usage_logs (P2.5.8.3).
     *
     * @param  array<string, mixed>  $additionalMetadata
     */
    public function logAiUsage(
        Conversation $conversation,
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        int $durationMs = 0,
        string $jobType = 'chatbot_conversation',
        int $creditsCharged = 0,
        string $status = 'success',
        ?string $errorMessage = null,
        ?ConversationMessage $message = null,
        array $additionalMetadata = []
    ): AiUsageLog {
        $costUsd = $this->calculateCost($provider, $model, $promptTokens, $completionTokens);
        $totalTokens = $promptTokens + $completionTokens;

        // Resolve Tenant ID để bảo đảm tính tương thích với bảng ai_usage_logs legacy
        $workspace = $conversation->workspace ?: Workspace::find($conversation->workspace_id);
        $tenantId = $workspace?->legacy_tenant_id ?? Tenant::query()->value('id') ?? 1;

        $requestHash = $message?->uuid
            ?? sprintf('conv_%s_msg_%d_%s', $conversation->uuid, $message?->id ?? 0, Str::random(8));
        $requestHash = substr($requestHash, 0, 64);

        $usageLog = AiUsageLog::create([
            'tenant_id' => $tenantId,
            'workspace_id' => $conversation->workspace_id,
            'website_id' => $conversation->website_id,
            'job_type' => $jobType,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => $costUsd,
            'credits_charged' => max(0, $creditsCharged),
            'duration_ms' => $durationMs,
            'status' => in_array($status, ['success', 'failed', 'timeout', 'rate_limited'], true) ? $status : 'success',
            'error_message' => $errorMessage,
            'request_hash' => $requestHash,
        ]);

        // Ghi song song vào UsageRecord cho SaaS Workspace
        UsageRecord::create([
            'workspace_id' => $conversation->workspace_id,
            'website_id' => $conversation->website_id,
            'action_type' => $jobType,
            'credits_charged' => max(0, $creditsCharged),
            'tokens_used' => $totalTokens,
            'provider_cost_usd' => $costUsd,
            'status' => $status === 'success' ? UsageRecordStatus::SETTLED : UsageRecordStatus::REFUNDED,
            'usage_date' => now()->toDateString(),
        ]);

        // Ghi metadata trực tiếp vào ConversationMessage nếu có
        if ($message) {
            $currentMetadata = $message->metadata ?? [];
            $currentMetadata['ai_usage'] = array_merge([
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'cost_usd' => $costUsd,
                'duration_ms' => $durationMs,
                'credits_charged' => $creditsCharged,
                'status' => $status,
            ], $additionalMetadata);

            $message->update(['metadata' => $currentMetadata]);
        }

        return $usageLog;
    }

    /**
     * Tổng hợp chi phí Token, latency và Credit đã tiêu thụ của toàn bộ phiên trò chuyện.
     *
     * @return array{
     *     conversation_id: int,
     *     logs_count: int,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     cost_usd: float,
     *     credits_charged: int,
     *     total_duration_ms: int
     * }
     */
    public function getConversationCostSummary(Conversation|int $conversation): array
    {
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;

        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->get();

        $totalPrompt = 0;
        $totalCompletion = 0;
        $totalCost = 0.0;
        $totalCredits = 0;
        $totalDuration = 0;
        $logsCount = 0;

        foreach ($messages as $msg) {
            if (! empty($msg->metadata['ai_usage']) && is_array($msg->metadata['ai_usage'])) {
                $usage = $msg->metadata['ai_usage'];
                $totalPrompt += (int) ($usage['prompt_tokens'] ?? 0);
                $totalCompletion += (int) ($usage['completion_tokens'] ?? 0);
                $totalCost += (float) ($usage['cost_usd'] ?? 0);
                $totalCredits += (int) ($usage['credits_charged'] ?? 0);
                $totalDuration += (int) ($usage['duration_ms'] ?? 0);
                $logsCount++;
            }
        }

        return [
            'conversation_id' => $conversationId,
            'logs_count' => $logsCount,
            'prompt_tokens' => $totalPrompt,
            'completion_tokens' => $totalCompletion,
            'total_tokens' => $totalPrompt + $totalCompletion,
            'cost_usd' => round($totalCost, 8),
            'credits_charged' => $totalCredits,
            'total_duration_ms' => $totalDuration,
        ];
    }
}
