<?php

namespace App\Services\Chatbot;

use App\Actions\Publishing\PushToWordPressAction;
use App\Enums\ArticleStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Article;
use App\Models\ChatPendingAction;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use App\Services\Bulk\BulkGenerationService;
use App\Services\Chatbot\Tools\SeoToolRegistry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ActionApprovalService
{
    public function __construct(
        protected CreditLedgerService $creditLedgerService,
        protected SeoToolRegistry $toolRegistry,
        protected ?BulkGenerationService $bulkService = null
    ) {
        $this->bulkService = $this->bulkService ?? (app()->bound(BulkGenerationService::class) ? app(BulkGenerationService::class) : null);
    }

    /**
     * Tạo một hành động nhạy cảm chờ người dùng xác nhận (Human-in-the-Loop Gate).
     *
     * @param  array<string, mixed>  $payload
     */
    public function createPendingAction(
        Conversation $conversation,
        string $actionName,
        array $payload,
        ?User $user = null,
        ?int $estimatedCredits = null,
        ?string $targetEntityType = null,
        ?int $targetEntityId = null,
        ?ConversationMessage $message = null,
        int $expiresInMinutes = 30
    ): ChatPendingAction {
        $actorUser = $user ?? $conversation->user;

        // Tự động suy luận số credit dự kiến nếu chưa được chỉ định
        if ($estimatedCredits === null) {
            $estimatedCredits = $this->calculateEstimatedCredits($actionName, $payload);
        }

        // Tự động suy luận đối tượng mục tiêu nếu chưa có
        if ($targetEntityType === null) {
            if (! empty($payload['article_id'])) {
                $targetEntityType = 'article';
                $targetEntityId = (int) $payload['article_id'];
            } elseif (! empty($payload['batch_id'])) {
                $targetEntityType = 'bulk_batch';
                $targetEntityId = (int) $payload['batch_id'];
            } elseif (! empty($payload['website_id'])) {
                $targetEntityType = 'website';
                $targetEntityId = (int) $payload['website_id'];
            }
        }

        return ChatPendingAction::create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'message_id' => $message?->id,
            'workspace_id' => $conversation->workspace_id,
            'user_id' => $actorUser ? $actorUser->id : $conversation->user_id,
            'action_name' => $actionName,
            'target_entity_type' => $targetEntityType,
            'target_entity_id' => $targetEntityId,
            'estimated_credits' => max(0, (int) $estimatedCredits),
            'action_payload' => $payload,
            'status' => 'pending',
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }

    /**
     * Phê duyệt và kích hoạt thực thi hành động nhạy cảm trong Database Transaction an toàn.
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException
     * @throws InsufficientCreditsException
     * @throws RuntimeException
     */
    public function approveAction(
        string|ChatPendingAction $action,
        Workspace $workspace,
        User $actor
    ): array {
        $pendingAction = $action instanceof ChatPendingAction
            ? $action
            : ChatPendingAction::where('workspace_id', $workspace->id)
                ->where('uuid', $action)
                ->firstOrFail();

        if ($pendingAction->workspace_id !== $workspace->id) {
            throw new ModelNotFoundException('Không tìm thấy yêu cầu xác nhận trong Không gian làm việc này.');
        }

        // Kiểm tra tính hợp lệ về trạng thái
        if ($pendingAction->status === 'approved') {
            throw new RuntimeException('Hành động này đã được phê duyệt trước đó.');
        }

        if ($pendingAction->status === 'rejected') {
            throw new RuntimeException('Hành động này đã bị hủy bỏ/từ chối.');
        }

        if ($pendingAction->status === 'expired' || $pendingAction->expires_at->isPast()) {
            $pendingAction->update(['status' => 'expired']);
            throw new RuntimeException('Yêu cầu xác nhận này đã hết hạn sau 30 phút.');
        }

        // Kiểm tra số dư credit nếu hành động có tính phí
        if ($pendingAction->estimated_credits > 0) {
            $this->creditLedgerService->checkCredits($workspace, $pendingAction->estimated_credits);
        }

        // Bọc toàn bộ quá trình trừ credit và kích hoạt tác vụ trong DB Transaction
        return DB::transaction(function () use ($pendingAction, $workspace, $actor) {
            // 1. Khấu trừ credit nếu có
            if ($pendingAction->estimated_credits > 0) {
                $this->creditLedgerService->deductCredits(
                    workspace: $workspace,
                    amount: $pendingAction->estimated_credits,
                    referenceType: 'chat_approval_action',
                    referenceId: $pendingAction->id,
                    metadata: ['note' => "Khấu trừ credit cho hành động được duyệt: {$pendingAction->action_name}"],
                    actor: $actor
                );
            }

            // 2. Đổi trạng thái sang approved
            $pendingAction->update([
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);

            // 3. Cập nhật trạng thái tin nhắn liên kết
            if ($pendingAction->message_id) {
                $pendingAction->message?->update(['status' => 'completed']);
            }

            // 4. Kích hoạt thực thi nghiệp vụ tương ứng
            $executionResult = $this->dispatchActionExecution($pendingAction, $workspace, $actor);

            return [
                'action_id' => $pendingAction->uuid,
                'action_name' => $pendingAction->action_name,
                'status' => 'approved',
                'reviewed_at' => $pendingAction->reviewed_at?->toIso8601String(),
                'credits_deducted' => $pendingAction->estimated_credits,
                'execution_result' => $executionResult,
            ];
        });
    }

    /**
     * Người dùng từ chối / hủy bỏ hành động chờ duyệt (Zero Loss - không tiêu tốn credit).
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function rejectAction(
        string|ChatPendingAction $action,
        Workspace $workspace,
        User $actor,
        ?string $reason = null
    ): array {
        $pendingAction = $action instanceof ChatPendingAction
            ? $action
            : ChatPendingAction::where('workspace_id', $workspace->id)
                ->where('uuid', $action)
                ->firstOrFail();

        if ($pendingAction->workspace_id !== $workspace->id) {
            throw new ModelNotFoundException('Không tìm thấy yêu cầu xác nhận trong Không gian làm việc này.');
        }

        if ($pendingAction->status === 'approved') {
            throw new RuntimeException('Hành động đã được phê duyệt, không thể từ chối.');
        }

        if ($pendingAction->status === 'rejected') {
            throw new RuntimeException('Hành động này đã bị từ chối/hủy bỏ trước đó.');
        }

        if ($pendingAction->status === 'expired' || $pendingAction->expires_at->isPast()) {
            $pendingAction->update(['status' => 'expired']);
            throw new RuntimeException('Hành động đã hết hạn hiệu lực.');
        }

        $pendingAction->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
        ]);

        if ($pendingAction->message_id) {
            $pendingAction->message?->update(['status' => 'cancelled']);
        }

        return [
            'action_id' => $pendingAction->uuid,
            'action_name' => $pendingAction->action_name,
            'status' => 'rejected',
            'reason' => $reason,
            'reviewed_at' => $pendingAction->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * Lấy thông tin chi tiết của hành động chờ duyệt kèm ngữ cảnh bài viết / website.
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException
     */
    public function getActionDetails(string $uuid, Workspace $workspace): array
    {
        /** @var ChatPendingAction $pendingAction */
        $pendingAction = ChatPendingAction::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->with(['conversation', 'user'])
            ->firstOrFail();

        if ($pendingAction->status === 'pending' && $pendingAction->expires_at->isPast()) {
            $pendingAction->update(['status' => 'expired']);
        }

        $payload = $pendingAction->action_payload ?? [];
        $targetWebsiteName = null;
        $targetWebsiteUrl = null;
        $targetArticleTitle = null;

        if (! empty($payload['site_id'])) {
            $site = WordPressSite::where('workspace_id', $workspace->id)->find($payload['site_id']);
            $targetWebsiteName = $site?->name ?? $site?->url;
            $targetWebsiteUrl = $site?->url;
        } elseif (! empty($payload['website_id'])) {
            $ws = Website::where('workspace_id', $workspace->id)->find($payload['website_id']);
            $targetWebsiteName = $ws?->name;
            $targetWebsiteUrl = $ws?->url;
        }

        if (! empty($payload['article_id'])) {
            $art = Article::where('workspace_id', $workspace->id)->find($payload['article_id']);
            $targetArticleTitle = $art?->title;
            if (! $targetWebsiteName && $art?->website_id) {
                $targetWebsiteName = $art->website?->name;
                $targetWebsiteUrl = $art->website?->url;
            }
        }

        $secondsLeft = max(0, (int) now()->diffInSeconds($pendingAction->expires_at, false));

        return [
            'uuid' => $pendingAction->uuid,
            'action_name' => $pendingAction->action_name,
            'status' => $pendingAction->status,
            'estimated_credits' => $pendingAction->estimated_credits,
            'target_entity_type' => $pendingAction->target_entity_type,
            'target_entity_id' => $pendingAction->target_entity_id,
            'target_website_name' => $targetWebsiteName,
            'target_website_url' => $targetWebsiteUrl,
            'target_article_title' => $targetArticleTitle,
            'payload' => $payload,
            'expires_at' => $pendingAction->expires_at->toIso8601String(),
            'reviewed_at' => $pendingAction->reviewed_at?->toIso8601String(),
            'seconds_remaining' => $secondsLeft,
            'is_expired' => $pendingAction->status === 'expired' || $secondsLeft <= 0,
        ];
    }

    /**
     * Quét và chuyển đổi các hành động quá hạn sang trạng thái expired.
     */
    public function expireStaleActions(): int
    {
        return ChatPendingAction::withoutGlobalScopes()
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Điều phối thực thi hành động tương ứng sau khi người dùng bấm Duyệt.
     *
     * @return array<string, mixed>
     */
    protected function dispatchActionExecution(
        ChatPendingAction $pendingAction,
        Workspace $workspace,
        User $actor
    ): array {
        $name = $pendingAction->action_name;
        $payload = $pendingAction->action_payload ?? [];

        $toolName = match ($name) {
            'publish_to_wordpress', 'wordpress_publish' => 'wordpress_publish',
            'start_bulk_generation', 'bulk_generation' => 'bulk_generation',
            default => $name,
        };

        if (in_array($toolName, ['bulk_generation', 'start_bulk_generation'], true)) {
            return [
                'tool' => 'bulk_generation',
                'status' => 'dispatched_to_queue',
                'credits_deducted' => $pendingAction->estimated_credits,
                'payload' => $payload,
            ];
        }

        if ($this->toolRegistry->has($toolName)) {
            return $this->toolRegistry->execute($toolName, $workspace, $payload, $actor);
        }

        // Dự phòng cho WordPress Publish nếu registry chưa nạp adapter
        if ($toolName === 'wordpress_publish') {
            $articleId = (int) ($payload['article_id'] ?? 0);
            /** @var Article $article */
            $article = Article::where('workspace_id', $workspace->id)->findOrFail($articleId);

            $siteId = ! empty($payload['site_id']) ? (int) $payload['site_id'] : null;
            $site = null;
            if ($siteId) {
                $site = WordPressSite::where('workspace_id', $workspace->id)->find($siteId);
            }
            if (! $site && $article->wordpress_site_id) {
                $site = WordPressSite::where('workspace_id', $workspace->id)->find($article->wordpress_site_id);
            }
            if (! $site && $article->website_id) {
                $site = WordPressSite::where('workspace_id', $workspace->id)
                    ->where('website_id', $article->website_id)
                    ->first();
            }
            if (! $site) {
                $site = WordPressSite::where('workspace_id', $workspace->id)->first();
            }

            if (! $site) {
                throw new RuntimeException('Chưa cấu hình tài khoản kết nối WordPress Site nào trong Workspace này.');
            }

            $article->update([
                'wordpress_site_id' => $site->id,
                'status' => ArticleStatus::APPROVED,
            ]);

            PushToWordPressAction::run($article);

            return [
                'tool' => 'wordpress_publish',
                'article_id' => $article->id,
                'article_title' => $article->title,
                'wordpress_site_id' => $site->id,
                'site_name' => $site->name ?? $site->url,
                'status' => 'dispatched_to_queue',
            ];
        }

        return [
            'action' => $name,
            'status' => 'dispatched',
            'payload' => $payload,
        ];
    }

    /**
     * Tính toán số lượng credit dự kiến cần tiêu hao.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function calculateEstimatedCredits(string $actionName, array $payload): int
    {
        if (in_array($actionName, ['publish_to_wordpress', 'wordpress_publish'], true)) {
            return 0; // Xuất bản WordPress thuần túy không tiêu tốn credit
        }

        if (in_array($actionName, ['bulk_generation', 'start_bulk_generation'], true)) {
            $keywords = (array) ($payload['keywords'] ?? []);
            $count = max(1, count($keywords));

            if ($this->bulkService) {
                return $this->bulkService->calculateTotalCredits($count);
            }

            return $count * 18; // Mặc định 18 credits/bài
        }

        if ($this->toolRegistry->has($actionName)) {
            $tool = $this->toolRegistry->get($actionName);

            return $tool ? $tool->getEstimatedCredits($payload) : 0;
        }

        return 0;
    }
}
