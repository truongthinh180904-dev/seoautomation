<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    /**
     * Lấy danh sách các cuộc trò chuyện thuộc Workspace với eager loading chống N+1.
     */
    public function list(
        int $workspaceId,
        ?int $websiteId = null,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->with(['website:id,name,url', 'user:id,name,email'])
            ->withCount('messages')
            ->orderByDesc('pinned')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($websiteId !== null) {
            $query->where('website_id', $websiteId);
        }

        if (! empty($search)) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return $query->paginate($perPage);
    }

    /**
     * Tìm cuộc trò chuyện theo UUID trong phạm vi Workspace (cô lập Multi-Tenant).
     */
    public function getConversation(int $workspaceId, string $uuid): ?Conversation
    {
        return Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('uuid', $uuid)
            ->with(['website:id,name,url', 'user:id,name,email'])
            ->withCount('messages')
            ->first();
    }

    /**
     * Khởi tạo một phiên trò chuyện mới bọc trong DB transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function createConversation(int $workspaceId, int $userId, array $data): Conversation
    {
        return DB::transaction(function () use ($workspaceId, $userId, $data) {
            return Conversation::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'website_id' => $data['website_id'] ?? null,
                'title' => ! empty($data['title']) ? trim((string) $data['title']) : 'Cuộc trò chuyện mới',
                'current_mode' => $data['current_mode'] ?? 'general',
                'context_summary' => $data['context_summary'] ?? null,
                'pinned' => (bool) ($data['pinned'] ?? false),
                'last_message_at' => now(),
            ]);
        });
    }

    /**
     * Cập nhật thông tin cuộc trò chuyện (tiêu đề, ghim, chế độ, website, tóm tắt ngữ cảnh).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateConversation(Conversation $conversation, array $data): Conversation
    {
        return DB::transaction(function () use ($conversation, $data) {
            $payload = [];

            if (array_key_exists('title', $data) && $data['title'] !== null) {
                $payload['title'] = trim((string) $data['title']);
            }

            if (array_key_exists('pinned', $data)) {
                $payload['pinned'] = (bool) $data['pinned'];
            }

            if (array_key_exists('current_mode', $data) && $data['current_mode'] !== null) {
                $payload['current_mode'] = (string) $data['current_mode'];
            }

            if (array_key_exists('context_summary', $data)) {
                $payload['context_summary'] = $data['context_summary'];
            }

            if (array_key_exists('website_id', $data)) {
                $payload['website_id'] = $data['website_id'];
            }

            if (! empty($payload)) {
                $conversation->update($payload);
            }

            return $conversation->fresh(['website:id,name,url', 'user:id,name,email']);
        });
    }

    /**
     * Xóa cuộc trò chuyện (tự động xóa cascade các messages đi kèm).
     */
    public function deleteConversation(Conversation $conversation): bool
    {
        return DB::transaction(function () use ($conversation) {
            return (bool) $conversation->delete();
        });
    }

    /**
     * Lưu tin nhắn mới vào cuộc trò chuyện và cập nhật last_message_at.
     *
     * @param  array<string, mixed>  $options
     */
    public function addMessage(
        Conversation $conversation,
        string $role,
        string $content,
        array $options = []
    ): ConversationMessage {
        return DB::transaction(function () use ($conversation, $role, $content, $options) {
            $message = ConversationMessage::create([
                'uuid' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
                'user_id' => $options['user_id'] ?? null,
                'role' => $role,
                'content' => $content,
                'tool_calls' => $options['tool_calls'] ?? null,
                'tool_results' => $options['tool_results'] ?? null,
                'metadata' => $options['metadata'] ?? null,
                'action_type' => $options['action_type'] ?? null,
                'status' => $options['status'] ?? 'completed',
            ]);

            $updates = [
                'last_message_at' => now(),
            ];

            // Nếu tiêu đề vẫn là mặc định và người dùng gửi tin nhắn đầu tiên, tự động cập nhật tiêu đề theo nội dung
            if ($role === 'user' && ($conversation->title === 'Cuộc trò chuyện mới' || empty($conversation->title))) {
                $cleanText = trim((string) preg_replace('/\s+/', ' ', $content));
                if (! empty($cleanText)) {
                    $updates['title'] = Str::limit($cleanText, 50);
                }
            }

            $conversation->update($updates);

            return $message;
        });
    }

    /**
     * Lấy lịch sử $limit tin nhắn gần nhất kèm theo tóm tắt ngữ cảnh Context Memory.
     *
     * @return array{
     *     conversation: Conversation,
     *     context_summary: string|null,
     *     current_mode: string,
     *     messages: Collection<int, ConversationMessage>
     * }
     */
    public function getRecentHistory(Conversation $conversation, int $limit = 10): array
    {
        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return [
            'conversation' => $conversation,
            'context_summary' => $conversation->context_summary,
            'current_mode' => $conversation->current_mode,
            'messages' => $messages,
        ];
    }

    /**
     * Cập nhật tóm tắt ngữ cảnh dài hạn để phòng ngừa tràn token LLM.
     */
    public function updateContextSummary(Conversation $conversation, string $summary): Conversation
    {
        return DB::transaction(function () use ($conversation, $summary) {
            $conversation->update([
                'context_summary' => $summary,
            ]);

            return $conversation->fresh();
        });
    }

    /**
     * Làm trống toàn bộ tin nhắn trong phiên trò chuyện.
     */
    public function clearMessages(Conversation $conversation): bool
    {
        return DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->update([
                'context_summary' => null,
            ]);

            return true;
        });
    }
}
