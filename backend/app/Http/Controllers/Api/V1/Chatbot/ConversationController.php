<?php

namespace App\Http\Controllers\Api\V1\Chatbot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbot\SendMessageRequest;
use App\Http\Requests\Chatbot\StoreConversationRequest;
use App\Models\Workspace;
use App\Services\Chatbot\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * GET /api/v1/conversations
     * Danh sách các cuộc trò chuyện theo Workspace hiện tại.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $websiteId = $request->query('website_id') ? (int) $request->query('website_id') : null;
        $search = $request->query('search');
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = $this->conversationService->list(
            workspaceId: $workspace->id,
            websiteId: $websiteId,
            search: $search ? (string) $search : null,
            perPage: $perPage
        );

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách cuộc trò chuyện thành công.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/conversations
     * Khởi tạo cuộc trò chuyện mới.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        try {
            $conversation = $this->conversationService->createConversation(
                workspaceId: $workspace->id,
                userId: (int) $request->user()->id,
                data: $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Khởi tạo cuộc trò chuyện thành công.',
                'data' => $conversation->load(['website:id,name,url']),
            ], 201);
        } catch (Throwable $e) {
            Log::error('Conversation store error: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi tạo cuộc trò chuyện.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/conversations/{uuid}
     * Chi tiết cuộc trò chuyện và các tin nhắn gần nhất.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc trò chuyện.',
            ], 404);
        }

        $history = $this->conversationService->getRecentHistory($conversation, 20);

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết cuộc trò chuyện thành công.',
            'data' => [
                'conversation' => $conversation,
                'context_summary' => $history['context_summary'],
                'current_mode' => $history['current_mode'],
                'recent_messages' => $history['messages'],
            ],
        ]);
    }

    /**
     * PUT /api/v1/conversations/{uuid}
     * Cập nhật thông tin cuộc trò chuyện.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc trò chuyện để cập nhật.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:255'],
            'pinned' => ['nullable', 'boolean'],
            'current_mode' => ['nullable', 'string', 'in:general,research,writing,audit,bulk'],
            'context_summary' => ['nullable', 'string'],
            'website_id' => [
                'nullable',
                'integer',
                Rule::exists('websites', 'id')->where('workspace_id', $workspace->id),
            ],
        ], [
            'website_id.exists' => 'Website được chọn không tồn tại hoặc không thuộc Không gian làm việc này.',
            'current_mode.in' => 'Chế độ hội thoại không hợp lệ.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu cập nhật không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updated = $this->conversationService->updateConversation(
                conversation: $conversation,
                data: $validator->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật cuộc trò chuyện thành công.',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            Log::error('Conversation update error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi cập nhật cuộc trò chuyện.',
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/conversations/{uuid}
     * Xóa cuộc trò chuyện và toàn bộ tin nhắn.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc trò chuyện để xóa.',
            ], 404);
        }

        try {
            $this->conversationService->deleteConversation($conversation);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa cuộc trò chuyện thành công.',
            ]);
        } catch (Throwable $e) {
            Log::error('Conversation delete error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi xóa cuộc trò chuyện.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/conversations/{uuid}/messages
     * Lấy danh sách tin nhắn của cuộc trò chuyện.
     */
    public function messages(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc trò chuyện.',
            ], 404);
        }

        $limit = min(max((int) $request->query('limit', 50), 1), 200);
        $history = $this->conversationService->getRecentHistory($conversation, $limit);

        return response()->json([
            'success' => true,
            'message' => 'Lấy lịch sử tin nhắn thành công.',
            'data' => $history['messages'],
            'meta' => [
                'context_summary' => $history['context_summary'],
                'current_mode' => $history['current_mode'],
                'count' => $history['messages']->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/conversations/{uuid}/messages
     * Gửi tin nhắn mới vào cuộc trò chuyện.
     */
    public function sendMessage(SendMessageRequest $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc trò chuyện.',
            ], 404);
        }

        $validated = $request->validated();
        $role = $validated['role'] ?? 'user';
        $content = $validated['content'];

        $metadata = $validated['metadata'] ?? [];
        if (! empty($validated['images'])) {
            $metadata['images'] = $validated['images'];
        }

        try {
            $message = $this->conversationService->addMessage(
                conversation: $conversation,
                role: $role,
                content: $content,
                options: [
                    'user_id' => $role === 'user' ? $request->user()?->id : null,
                    'tool_calls' => $validated['tool_calls'] ?? null,
                    'tool_results' => $validated['tool_results'] ?? null,
                    'metadata' => ! empty($metadata) ? $metadata : null,
                    'action_type' => $validated['action_type'] ?? null,
                    'status' => $validated['status'] ?? 'completed',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Tin nhắn đã được gửi thành công.',
                'data' => $message->load('user:id,name,email'),
            ], 201);
        } catch (Throwable $e) {
            Log::error('Send message error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi gửi tin nhắn.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/conversations/{uuid}/clear
     * Làm trống toàn bộ tin nhắn trong cuộc trò chuyện.
     */
    public function clear(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc trò chuyện.',
            ], 404);
        }

        try {
            $this->conversationService->clearMessages($conversation);

            return response()->json([
                'success' => true,
                'message' => 'Đã làm trống lịch sử tin nhắn.',
            ]);
        } catch (Throwable $e) {
            Log::error('Clear messages error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi làm trống tin nhắn.',
            ], 500);
        }
    }

    /**
     * Xác định Workspace từ Request context hoặc Header/User.
     */
    protected function resolveWorkspace(Request $request): Workspace
    {
        /** @var Workspace|null $workspace */
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace) {
            $workspaceId = $request->header('X-Workspace-Id')
                ?: $request->query('workspace_id')
                ?: $request->user()?->default_workspace_id;

            if ($workspaceId) {
                $workspace = Workspace::find((int) $workspaceId);
            }
        }

        if (! $workspace && $request->user()) {
            $workspace = $request->user()->workspaces()->wherePivot('is_active', true)->first();
        }

        if (! $workspace) {
            abort(404, 'Không tìm thấy Không gian làm việc (Workspace) hợp lệ.');
        }

        return $workspace;
    }
}
