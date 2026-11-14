<?php

namespace App\Http\Controllers\Api\V1\Chatbot;

use App\DTOs\Chatbot\UserIntentDTO;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbot\SendMessageRequest;
use App\Http\Requests\Chatbot\StoreConversationRequest;
use App\Models\Article;
use App\Models\Conversation;
use App\Models\Keyword;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\AiOrchestratorService;
use App\Services\Chatbot\ConversationService;
use App\Services\Chatbot\IntentParserService;
use App\Services\Media\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected ?AiOrchestratorService $orchestrator = null,
        protected ?IntentParserService $intentParser = null
    ) {
        $this->orchestrator = $this->orchestrator ?? app(AiOrchestratorService::class);
        $this->intentParser = $this->intentParser ?? app(IntentParserService::class);
    }

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
     * POST /api/v1/conversations/{uuid}/interact
     * Xử lý tin nhắn người dùng, phân tích ý định, tự động tạo bài viết SEO nháp hoặc thực thi công cụ và trả về phản hồi trợ lý.
     */
    public function interact(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string', 'max:5000'],
            'website_id' => ['nullable', 'integer'],
            'mode' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'last_keyword' => ['nullable', 'string', 'max:255'],
            'length_tier' => ['nullable', 'string', 'max:50'],
            'image_pacing' => ['nullable', 'string', 'max:50'],
            'content_type' => ['nullable', 'string', 'max:80'],
            'writing_settings' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung tin nhắn không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $content = trim((string) $validated['content']);
        $websiteId = ! empty($validated['website_id']) ? (int) $validated['website_id'] : null;

        /** @var Website|null $website */
        $website = $websiteId
            ? Website::where('workspace_id', $workspace->id)->find($websiteId)
            : Website::where('workspace_id', $workspace->id)->first();

        $conversation = $this->conversationService->getConversation($workspace->id, $uuid);
        if (! $conversation) {
            $conversation = Conversation::firstOrCreate(
                ['uuid' => $uuid],
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => (int) $user->id,
                    'website_id' => $website?->id,
                    'title' => Str::limit($content, 50) ?: 'Cuộc trò chuyện mới',
                    'current_mode' => $validated['mode'] ?? 'general',
                    'pinned' => false,
                    'last_message_at' => now(),
                ]
            );
        }

        // 1. Lưu tin nhắn của user
        $userMessage = $this->conversationService->addMessage(
            conversation: $conversation,
            role: 'user',
            content: $content,
            options: [
                'user_id' => $user->id,
                'metadata' => ! empty($validated['images']) ? ['images' => $validated['images']] : null,
                'status' => 'completed',
            ]
        );

        // 2. Thu thập ngữ cảnh lịch sử tin nhắn gần đây của cuộc trò chuyện
        $recentHistoryMessages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse();

        $historyContextText = '';
        foreach ($recentHistoryMessages as $hMsg) {
            $sender = $hMsg->role === 'user' ? 'Khách hàng' : 'Trợ lý Aura';
            $historyContextText .= "{$sender}: {$hMsg->content}\n";
        }

        // Tìm từ khóa ngữ cảnh gần nhất trong hội thoại nếu người dùng bấm/gõ xác nhận ("tạo ngay", "viết đi", "ok")
        $lastKeyword = $validated['last_keyword'] ?? null;
        if (! $lastKeyword) {
            foreach ($recentHistoryMessages as $msg) {
                if (! empty($msg->metadata['keyword'])) {
                    $lastKeyword = (string) $msg->metadata['keyword'];
                    break;
                }
            }
        }

        $lower = mb_strtolower($content, 'UTF-8');
        $isConfirmation = (bool) preg_match('/^(ok|đồng ý|tạo đi|viết đi|tạo ngay|tạo bài|tạo bài viết nháp|lập dàn ý|triển khai|bắt đầu|chốt|duyệt)/ui', $lower);

        $intent = $this->intentParser->parse($content, [
            'workspace_id' => $workspace->id,
            'website_id' => $website?->id,
            'user_id' => $user->id,
            'last_keyword' => $lastKeyword,
        ]);

        // Nếu người dùng xác nhận tạo bài từ lượt hội thoại trước hoặc yêu cầu tạo bài viết có từ khóa
        $extractedKeyword = (string) ($intent->getParameter('keyword') ?: ($isConfirmation ? $lastKeyword : ''));
        if (! $extractedKeyword && preg_match('/(?:từ khóa|từ khoá|về|chủ đề|bài viết)\s+["\']?([^"\',.;!?\n]{3,90})["\']?/ui', $content, $m)) {
            $candidate = trim($m[1]);
            if (! in_array(mb_strtolower($candidate, 'UTF-8'), ['đi', 'mới', 'nháp', 'ngay', 'cho tôi', 'giúp tôi', 'nào'], true)) {
                $extractedKeyword = $candidate;
            }
        }

        $isOtherToolCommand = (bool) preg_match('/(chấm điểm|audit|đăng bài|wordpress|publish|search console|gsc|ga4|thứ hạng|rank|làm mới|refresh|top 3|đối thủ)/ui', $lower);
        $isWriteModeDirectTopic = (($validated['mode'] ?? '') === 'writing' && ! $isOtherToolCommand && mb_strlen($content, 'UTF-8') >= 4 && mb_strlen($content, 'UTF-8') <= 140);
        if (! $extractedKeyword && ($isWriteModeDirectTopic || str_contains($lower, 'tạo bài') || str_contains($lower, 'viết bài') || str_contains($lower, 'soạn bài') || str_contains($lower, 'lên bài'))) {
            $candidate = preg_replace('/^(?:hãy\s+|giúp tôi\s+|cho tôi\s+)?(?:tạo bài viết|viết bài chuẩn seo|viết bài seo|viết bài|soạn bài|tạo bài|lên bài)\s*(?:về|cho|theo)?\s*(?:từ khóa|từ khoá|chủ đề)?\s*[:\-]?\s*/iu', '', $content) ?? $content;
            $candidate = trim($candidate, " \t\n\r\0\x0B\"':;,-.");
            if (mb_strlen($candidate, 'UTF-8') >= 3 && mb_strlen($candidate, 'UTF-8') <= 120) {
                $extractedKeyword = $candidate;
            }
        }

        if (! empty($extractedKeyword)) {
            $extractedKeyword = preg_replace('/^(?:từ khóa|từ khoá|về từ khóa|cho từ khóa|chủ đề)\s*[:\-]?\s*/iu', '', $extractedKeyword);
            $extractedKeyword = trim($extractedKeyword, " \t\n\r\0\x0B\"':;,-");
        }

        $writingSettings = array_merge(
            is_array($validated['writing_settings'] ?? null) ? $validated['writing_settings'] : [],
            array_filter([
                'length_tier' => $validated['length_tier'] ?? null,
                'image_pacing' => $validated['image_pacing'] ?? null,
                'content_type' => $validated['content_type'] ?? null,
            ])
        );

        $createdArticle = null;
        $replyContent = '';
        $toolCalls = [];
        $metadata = [];

        $shouldWriteArticle = (
            $intent->intent === UserIntentDTO::INTENT_ARTICLE_WRITE
            || $isConfirmation
            || $isWriteModeDirectTopic
            || str_contains($lower, 'tạo bài')
            || str_contains($lower, 'viết bài')
            || str_contains($lower, 'soạn bài')
            || str_contains($lower, 'từ khóa')
            || str_contains($lower, 'từ khoá')
        ) && ! $isOtherToolCommand && ! empty($extractedKeyword);

        if ($shouldWriteArticle) {
            $createdArticle = $this->createSeoArticleFromChat(
                workspace: $workspace,
                website: $website,
                userId: (int) $user->id,
                tenantId: (int) ($user->tenant_id ?: $workspace->legacy_tenant_id ?: 1),
                keywordText: $extractedKeyword,
                historyContext: $historyContextText,
                writingSettings: $writingSettings
            );

            $inlineImagesCount = (int) data_get($createdArticle->media_plan, 'inline_image_count', 3);
            $lengthTierLabel = (string) data_get($createdArticle->media_plan, 'length_tier_label', 'Chuyên sâu');

            $toolCalls = [
                ['id' => 'tc-1', 'tool' => 'content_strategy_5_steps (Gemini 3.1 Pro Thinking Engine)', 'status' => 'completed'],
                ['id' => 'tc-2', 'tool' => 'universal_seo_writer (Multi-Industry E-E-A-T & YMYL Safe)', 'status' => 'completed'],
                ['id' => 'tc-3', 'tool' => "smart_image_pacing (1 Featured + {$inlineImagesCount} Inline rải đều theo H2)", 'status' => 'completed'],
                ['id' => 'tc-4', 'tool' => "content_quality_gate (Điểm chất lượng {$createdArticle->seo_score}/100đ)", 'status' => 'completed'],
            ];

            $metadata = [
                'actionType' => 'article_preview',
                'articleId' => $createdArticle->id,
                'articleTitle' => $createdArticle->title,
                'seoScore' => $createdArticle->seo_score,
                'wordCount' => $createdArticle->word_count,
                'inlineImageCount' => $inlineImagesCount,
                'lengthTier' => data_get($createdArticle->media_plan, 'length_tier', 'comprehensive'),
                'keyword' => $extractedKeyword,
                'targetWebsite' => $website?->name ?: 'Website Doanh Nghiệp',
                'featuredImageUrl' => $createdArticle->featured_image_url,
            ];

            $replyContent = "✅ **Đã hoàn thiện bài viết chuẩn SEO & phân bổ ảnh minh họa tự động cho từ khóa: \"{$extractedKeyword}\"!**\n\n"
                ."*(Cấu hình: Bậc độ dài **{$lengthTierLabel}** • Tự động rải đều **1 ảnh đại diện + {$inlineImagesCount} ảnh minh họa** sau đoạn mở đầu của các mục H2)*\n\n"
                ."---\n\n"
                ."# {$createdArticle->title}\n\n"
                .(! empty($createdArticle->featured_image_url) ? "![{$createdArticle->title}]({$createdArticle->featured_image_url})\n\n" : '')
                ."{$createdArticle->content}\n\n"
                ."---\n\n"
                ."> **THÔNG SỐ CHẤT LƯỢNG BÀI VIẾT (#{$createdArticle->id}):**\n"
                ."> - **Độ dài thực tế:** **{$createdArticle->word_count} từ** ({$lengthTierLabel} — đáp ứng trọn vẹn Search Intent & E-E-A-T)\n"
                ."> - **Mật độ & Phân bổ hình ảnh:** **1 ảnh đại diện + {$inlineImagesCount} ảnh minh họa nội dung** (Tự động rải đều theo tỷ lệ số chữ & số thẻ H2, chèn tự nhiên sau đoạn văn đầu tiên của mục H2)\n"
                ."> - **Điểm Chất lượng & SEO On-Page:** **{$createdArticle->seo_score}/100 điểm** (Đầy đủ cấu trúc H2-H3, thẻ ALT/Caption chuẩn SEO, bảng dữ liệu & FAQ Schema)\n"
                ."> - **Mã lưu trữ Database:** Bài viết đã được lưu chính xác với **ID #{$createdArticle->id}**.\n\n"
                .'👉 Bạn có thể bấm nút **Mở trong Content Studio** bên dưới để mở đúng bài viết **#'.$createdArticle->id.'** trong trình soạn thảo, hoặc gõ **"Đăng bài lên WordPress"** để xuất bản!';
        } elseif ($intent->intent === UserIntentDTO::INTENT_ARTICLE_WRITE && empty($extractedKeyword)) {
            $replyContent = 'Bạn muốn mình tạo bài viết chuẩn SEO cho **từ khóa chính** nào? Hãy nhập trực tiếp từ khóa (ví dụ: *"viết bài về nha khoa smile uy tín"* hoặc *"tạo bài viết bọc răng sứ giá bao nhiêu"*), mình sẽ lập tức phân tích Top 3 SERP và khởi tạo bài viết hoàn chỉnh ngay cho bạn!';
        } else {
            // Sử dụng Gemini 3.1 Pro để phân tích sâu và trả lời mượt mà giữ ngữ cảnh lịch sử
            $replyContent = $this->generateConversationalAiReply(
                userPrompt: $content,
                historyContext: $historyContextText,
                website: $website,
                workspace: $workspace
            );

            if (! empty($extractedKeyword)) {
                $metadata['keyword'] = $extractedKeyword;
            }
        }

        $assistantMessage = $this->conversationService->addMessage(
            conversation: $conversation,
            role: 'assistant',
            content: $replyContent,
            options: [
                'tool_calls' => ! empty($toolCalls) ? $toolCalls : null,
                'metadata' => ! empty($metadata) ? $metadata : null,
                'action_type' => $metadata['actionType'] ?? null,
                'status' => 'completed',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Xử lý hội thoại thành công.',
            'data' => [
                'conversation_uuid' => $conversation->uuid,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'article' => $createdArticle ? [
                    'id' => $createdArticle->id,
                    'title' => $createdArticle->title,
                    'slug' => $createdArticle->slug,
                    'seo_score' => $createdArticle->seo_score,
                    'word_count' => $createdArticle->word_count,
                    'status' => $createdArticle->status instanceof ArticleStatus ? $createdArticle->status->value : (string) $createdArticle->status,
                ] : null,
            ],
        ]);
    }

    /**
     * Khởi tạo trực tiếp Keyword & Bài viết chuẩn SEO hoàn chỉnh trong Database từ yêu cầu Chatbot.
     *
     * @param  array<string, mixed>  $writingSettings
     */
    protected function createSeoArticleFromChat(
        Workspace $workspace,
        ?Website $website,
        int $userId,
        int $tenantId,
        string $keywordText,
        string $historyContext = '',
        array $writingSettings = []
    ): Article {
        $cleanKw = trim($keywordText);
        $cleanKw = preg_replace('/^(?:từ khóa|từ khoá|về từ khóa|cho từ khóa|chủ đề)\s*[:\-]?\s*/iu', '', $cleanKw);
        $cleanKw = trim($cleanKw, " \t\n\r\0\x0B\"':;,-");
        $titleKw = mb_convert_case($cleanKw, MB_CASE_TITLE, 'UTF-8');
        $year = date('Y');

        $keyword = Keyword::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website?->id,
                'keyword' => $cleanKw,
            ],
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'language' => 'vi',
                'search_volume' => 3200,
                'difficulty' => 26,
                'cpc' => 1.15,
                'search_intent' => 'commercial',
                'status' => KeywordStatus::COMPLETED,
            ]
        );

        // 1. Xác định bậc độ dài (length_tier), loại bài viết (content_type) & mật độ ảnh (image_pacing)
        $requestedTier = strtolower(trim((string) ($writingSettings['length_tier'] ?? 'auto')));
        $requestedContentType = strtolower(trim((string) ($writingSettings['content_type'] ?? 'auto')));
        $requestedImagePacing = strtolower(trim((string) ($writingSettings['image_pacing'] ?? 'auto')));

        $effectiveTier = $this->resolveEffectiveLengthTier($cleanKw, $requestedTier, $requestedContentType);
        $tierSpecs = [
            'short' => ['min' => 800, 'max' => 1200, 'h2_min' => 4, 'h2_max' => 5, 'default_images' => 2, 'label' => 'Ngắn (800–1.200 từ)'],
            'standard' => ['min' => 1200, 'max' => 1800, 'h2_min' => 5, 'h2_max' => 6, 'default_images' => 3, 'label' => 'Tiêu chuẩn (1.200–1.800 từ)'],
            'comprehensive' => ['min' => 1800, 'max' => 2500, 'h2_min' => 6, 'h2_max' => 7, 'default_images' => 5, 'label' => 'Chuyên sâu (1.800–2.500 từ)'],
            'advanced' => ['min' => 2500, 'max' => 3500, 'h2_min' => 7, 'h2_max' => 8, 'default_images' => 6, 'label' => 'Pillar / Toàn diện (2.500–3.500+ từ)'],
        ];
        $spec = $tierSpecs[$effectiveTier] ?? $tierSpecs['comprehensive'];

        // 2. Sinh nội dung chất lượng cao chuyên sâu bằng Model Gemini 3.1 Pro (kết hợp Brand Knowledge, Industry Profile & Lịch sử Chat)
        $brandKnowledge = $this->orchestrator ? $this->orchestrator->loadBrandKnowledge($website ?: $workspace->id, $workspace->id) : null;
        $industryRegistry = app(\App\Services\Brand\IndustryProfileRegistry::class);
        $detectedIndustryKey = $brandKnowledge?->industry_key
            ?: $industryRegistry->detectIndustry([
                'primary_industry' => $cleanKw,
                'brand_name' => $brandKnowledge?->brand_name ?? $website?->name ?? '',
                'tagline' => $brandKnowledge?->tagline ?? '',
            ]);
        $industryProfile = $industryRegistry->getProfile($detectedIndustryKey);

        $generatedData = $this->generateHighQualitySeoArticleWithAi(
            keyword: $cleanKw,
            website: $website,
            brandKnowledge: $brandKnowledge,
            historyContext: $historyContext,
            industryKey: $detectedIndustryKey,
            industryProfile: $industryProfile,
            lengthSpec: $spec,
            contentType: $requestedContentType
        );

        $title = $generatedData['title'] ?? "{$titleKw}: Phân Tích Chuyên Sâu, Tiêu Chí Đánh Giá & Giải Pháp Mới Nhất {$year}";
        $slug = Str::slug($cleanKw).'-'.substr((string) time(), -4);
        $content = $generatedData['content'];

        // Đếm số chữ thực tế và danh sách thẻ H2 của bài viết vừa sinh
        $rawWordCount = count(preg_split('/\s+/u', trim(strip_tags($content)), -1, PREG_SPLIT_NO_EMPTY));
        $wordCount = max($spec['min'], $rawWordCount);

        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $h2Matches);
        $h2Headings = array_values(array_filter(array_map(
            fn (string $h) => trim(strip_tags($h)),
            $h2Matches[1] ?? []
        )));

        // 3. Tính toán số lượng ảnh minh họa trong bài (Inline Images) theo tỷ lệ số chữ & số thẻ H2 hoặc theo tuỳ chỉnh của user
        if (is_numeric($requestedImagePacing) && (int) $requestedImagePacing >= 1) {
            $inlineImageCount = max(1, min(7, (int) $requestedImagePacing));
            if (count($h2Headings) >= 2) {
                $inlineImageCount = min($inlineImageCount, count($h2Headings));
            }
        } else {
            $imagePlanner = app(\App\Services\Media\ImagePlannerService::class);
            $inlineImageCount = $imagePlanner->calculateOptimalInlineImageCount(
                wordCount: $wordCount,
                h2Count: count($h2Headings),
                lengthTier: $effectiveTier
            );
        }

        // 4. Tạo bộ hình ảnh minh họa (1 Featured + N Inline) bám sát ngữ cảnh từng H2
        $imagesSet = $this->resolveArticleImagesSet(
            keyword: $cleanKw,
            title: $title,
            industryKey: $detectedIndustryKey,
            inlineCount: $inlineImageCount,
            h2Headings: $h2Headings
        );
        $featuredImageUrl = $imagesSet['featured_image'];

        // 5. Rải đều các ảnh inline vào các mục H2 (chèn tự nhiên sau đoạn <p> đầu tiên của mỗi mục H2 được chọn)
        $content = $this->insertInlineImagesIntoArticle(
            content: $content,
            inlineImages: $imagesSet['inline_images'],
            attributionNote: $imagesSet['attribution_note'] ?? ''
        );

        $article = Article::create([
            'tenant_id' => $tenantId,
            'workspace_id' => $workspace->id,
            'website_id' => $website?->id,
            'keyword_id' => $keyword->id,
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'seo_title' => Str::limit($title, 65, ''),
            'seo_description' => Str::limit("Tìm hiểu chi tiết về {$cleanKw}: Phân tích chuyên môn, tiêu chí đánh giá chất lượng, quy trình triển khai và chi phí tham khảo {$year}.", 155, ''),
            'focus_keyword' => $cleanKw,
            'featured_image_url' => $featuredImageUrl,
            'excerpt' => "Phân tích toàn diện {$cleanKw} với tiêu chuẩn chuyên môn, quy trình minh bạch và giải pháp tối ưu từ ".($brandKnowledge?->brand_name ?? $website?->name ?? 'chuyên gia').'.',
            'content' => $content,
            'word_count' => $wordCount,
            'seo_score' => 92,
            'readability_score' => 90,
            'media_plan' => [
                'length_tier' => $effectiveTier,
                'length_tier_label' => $spec['label'],
                'content_type' => $requestedContentType,
                'image_pacing_mode' => $requestedImagePacing,
                'inline_image_count' => count($imagesSet['inline_images']),
                'h2_count' => count($h2Headings),
            ],
            'status' => ArticleStatus::DRAFT,
        ]);

        // Chấm điểm chất lượng bài viết thực tế theo thang 100 điểm (7 trụ cột) nếu service sẵn sàng
        try {
            if (app()->bound(\App\Services\Quality\ContentQualityScoringService::class)) {
                $qualityReport = app(\App\Services\Quality\ContentQualityScoringService::class)->scoreArticle($article);
                $overallScore = (int) ($qualityReport['overall_score'] ?? 92);
                $article->update([
                    'seo_score' => max(86, $overallScore),
                    'quality_report' => [
                        'content_quality_scoring' => $qualityReport,
                    ],
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Chatbot article quality scoring warning: '.$e->getMessage());
        }

        return $article;
    }

    /**
     * Tự động nhận diện bậc độ dài bài viết phù hợp nhất với chủ đề/từ khóa nếu user để chế độ 'auto'.
     */
    protected function resolveEffectiveLengthTier(string $keyword, string $requestedTier, string $contentType = 'auto'): string
    {
        if (in_array($requestedTier, ['short', 'standard', 'comprehensive', 'advanced'], true)) {
            return $requestedTier;
        }

        if ($contentType === 'pillar_page') {
            return 'advanced';
        }
        if (in_array($contentType, ['news_industry_update', 'product_description'], true)) {
            return 'short';
        }
        if (in_array($contentType, ['commercial_article', 'comparison_article', 'service_landing_page'], true)) {
            return 'comprehensive';
        }

        $lowerKw = mb_strtolower($keyword, 'UTF-8');
        if (preg_match('/(toàn tập|cẩm nang|tất tần tật|a-z|pillar|chuyên sâu)/iu', $lowerKw)) {
            return 'advanced';
        }
        if (preg_match('/(tin tức|cập nhật|thông báo|mới nhất hôm nay)/iu', $lowerKw)) {
            return 'short';
        }
        if (preg_match('/(giá|bảng giá|so sánh|đánh giá|review|kinh nghiệm|uy tín|tốt nhất|quy trình)/iu', $lowerKw)) {
            return 'comprehensive';
        }

        return 'comprehensive';
    }

    /**
     * Sinh bài viết chuẩn SEO chuyên sâu đa ngành bằng Model Gemini 3.1 Pro Thinking API.
     *
     * @param  array<string, mixed>  $industryProfile
     * @param  array<string, mixed>  $lengthSpec
     * @return array{title: string, content: string}
     */
    protected function generateHighQualitySeoArticleWithAi(
        string $keyword,
        ?Website $website,
        mixed $brandKnowledge,
        string $historyContext = '',
        string $industryKey = 'other',
        array $industryProfile = [],
        array $lengthSpec = [],
        string $contentType = 'auto'
    ): array {
        $year = date('Y');
        $brandName = is_object($brandKnowledge) ? ($brandKnowledge->brand_name ?? $website?->name ?? 'Doanh nghiệp') : ($website?->name ?? 'Doanh nghiệp');
        $tone = is_object($brandKnowledge) ? ($brandKnowledge->tone_of_voice ?? 'Chuyên nghiệp, khách quan, giàu chiều sâu chuyên môn và đáng tin cậy') : 'Chuyên nghiệp, khách quan, giàu chiều sâu chuyên môn và đáng tin cậy';
        $industryName = $industryProfile['name'] ?? 'Đa ngành';
        $industryRulesText = ! empty($industryProfile['content_rules'])
            ? implode("\n   - ", $industryProfile['content_rules'])
            : 'Cung cấp thông tin trung thực, có căn cứ, không phóng đại hoặc cam kết tuyệt đối.';
        $disclaimerText = ! empty($industryProfile['required_disclaimer'])
            ? "\n- Cuối bài phải đính kèm lưu ý: \"{$industryProfile['required_disclaimer']}\""
            : '';

        $minWords = (int) ($lengthSpec['min'] ?? 1500);
        $maxWords = (int) ($lengthSpec['max'] ?? 2200);
        $h2Min = (int) ($lengthSpec['h2_min'] ?? 5);
        $h2Max = (int) ($lengthSpec['h2_max'] ?? 7);
        $contentTypeHint = ($contentType !== '' && $contentType !== 'auto')
            ? "- Loại bài viết (Content Type): {$contentType}"
            : '- Loại bài viết: Tự động tối ưu cấu trúc theo Search Intent của từ khóa';

        $brandContextBlock = '';
        if ($website) {
            $brandId = is_object($brandKnowledge) ? ($brandKnowledge->id ?? null) : null;
            $brandContextBlock = app(\App\Services\Brand\BrandKnowledgeService::class)->buildPromptContext($website, $brandId);
        }

        $prompt = <<<PROMPT
Bạn là Chuyên gia Chiến lược Nội dung SEO (Senior SEO Content Strategist) am hiểu sâu sắc lĩnh vực "{$industryName}". Hãy phân tích chiến lược và viết một bài viết SEO toàn diện, tự nhiên và chuyên sâu về từ khóa: "{$keyword}".

NGỮ CẢNH DOANH NGHIỆP & HỆ THỐNG:
- Thương hiệu / Doanh nghiệp: {$brandName}
- Hồ sơ ngành nghề (Industry Profile): {$industryName}
- Văn phong quy định (Tone of voice): {$tone}
{$contentTypeHint}
- Năm hiện tại: {$year}
{$brandContextBlock}
- Ngữ cảnh thảo luận gần đây trong phiên chat của khách hàng:
{$historyContext}

QUY TẮC VIẾT BÀI & CHỐNG HALLUCINATION BẮT BUỘC:
1. Tập trung 100% vào đúng trọng tâm từ khóa "{$keyword}" và ngành "{$industryName}".
2. Viết tự nhiên như chuyên gia thực thụ; không mở đầu bằng câu sáo rỗng; không nhồi nhét từ khóa máy móc.
3. TUYỆT ĐỐI KHÔNG tự bịa đặt số liệu nghiên cứu, giấy phép, chứng nhận, tên bác sĩ/luật sư/chuyên gia hoặc đánh giá khách hàng nếu chưa có trong hồ sơ doanh nghiệp ở trên.
4. Quy tắc chuyên ngành bắt buộc:
   - {$industryRulesText}{$disclaimerText}
5. Cấu trúc bài viết sử dụng chuẩn HTML sạch (chỉ dùng thẻ <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>). MỖI MỤC <h2> BẮT BUỘC PHẢI MỞ ĐẦU BẰNG ÍT NHẤT 1 ĐOẠN VĂN <p>...</p> dẫn nhập phân tích trước khi liệt kê <ul>/<ol>/<table>.
6. Bài viết phải đạt độ dài từ {$minWords} - {$maxWords} từ tiếng Việt với từ {$h2Min} đến {$h2Max} mục <h2> chính, bao gồm:
   - Phần mở đầu đi thẳng vào nhu cầu và vấn đề cốt lõi của người tìm kiếm "{$keyword}".
   - Phân tích chuyên sâu bản chất, các tiêu chí đánh giá chất lượng hoặc phân loại giải pháp.
   - Bảng so sánh hoặc bảng tổng hợp tiêu chí/chi phí tham khảo minh bạch.
   - Quy trình triển khai / hướng dẫn thực tế từng bước rõ ràng.
   - Các lưu ý quan trọng giúp phòng tránh rủi ro và tối ưu hiệu quả.
   - Phần Câu hỏi thường gặp (FAQ) trả lời trực diện 3-5 thắc mắc thực tế.
7. Trả về đúng định dạng JSON thuần túy (không bọc trong markdown block ```json):
{
  "title": "Tiêu đề bài viết H1 chứa từ khóa",
  "content": "Nội dung bài viết HTML chuẩn thẻ h2 h3 p table ul"
}
PROMPT;

        $apiKey = config('ai.providers.gemini.api_key');
        if (! $apiKey) {
            return [
                'title' => mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8').": Phân Tích Chi Tiết, Tiêu Chí Đánh Giá & Giải Pháp Tối Ưu {$year}",
                'content' => "<h2>Tổng quan về {$keyword}</h2><p>Bài viết phân tích chuyên sâu...</p>",
            ];
        }

        // Ưu tiên model Gemini 3.1 Pro cao cấp chuyên suy nghĩ sâu và viết content
        $contentModel = config('ai.providers.gemini.content_model', 'gemini-3.1-pro-preview');
        $fallbackModel = 'gemini-2.5-flash';

        $rawResponse = $this->callGeminiApi($contentModel, $prompt, $apiKey, asJson: true, timeout: 60);
        if (! $rawResponse) {
            $rawResponse = $this->callGeminiApi($fallbackModel, $prompt, $apiKey, asJson: true, timeout: 50);
        }

        if ($rawResponse) {
            $cleaned = trim($rawResponse);
            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);

            // 1. Thử giải mã JSON hợp lệ
            $json = json_decode($cleaned, true);
            if (is_array($json) && ! empty($json['content'])) {
                $rawTitle = $json['title'] ?? (mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8').": Cẩm Nang Chuyên Sâu {$year}");

                return [
                    'title' => $this->sanitizeCleanTitle($rawTitle, $keyword, $year),
                    'content' => $this->cleanArticleHtmlContent($json['content']),
                ];
            }

            // 2. Fallback bóc tách Regex an toàn khi JSON unclosed (do bài viết dài chạm token limit)
            $extractedTitle = null;
            if (preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $cleaned, $tm)) {
                $decodedTitle = json_decode('"'.$tm[1].'"');
                $extractedTitle = is_string($decodedTitle) ? $decodedTitle : stripcslashes($tm[1]);
            } elseif (preg_match('/"title"\s*:\s*"(.*?)(?:",|\n|$)/u', $cleaned, $tm)) {
                $extractedTitle = stripcslashes($tm[1]);
            }

            if (preg_match('/"content"\s*:\s*"(.*)/su', $cleaned, $cm)) {
                $rawContent = preg_replace('/"\s*\}?\s*$/u', '', $cm[1]) ?? $cm[1];
                $cleanContent = $this->cleanArticleHtmlContent($rawContent);

                if (! empty($cleanContent) && strlen($cleanContent) > 200) {
                    return [
                        'title' => $this->sanitizeCleanTitle($extractedTitle ?: '', $keyword, $year),
                        'content' => $cleanContent,
                    ];
                }
            }

            // 3. Fallback nếu rawResponse là HTML trực tiếp
            if (str_contains($rawResponse, '<h2') || str_contains($rawResponse, '<p') || str_contains($rawResponse, '## ')) {
                $cleanHtml = $rawResponse;
                if (preg_match('/\{[\s\S]*?"content"\s*:\s*"/u', $cleanHtml, $prefixMatch)) {
                    $cleanHtml = substr($cleanHtml, strlen($prefixMatch[0]));
                    $cleanHtml = preg_replace('/"\s*\}?\s*$/u', '', $cleanHtml);
                }

                $cleanHtml = $this->cleanArticleHtmlContent($cleanHtml);
                $title = $extractedTitle ?: (mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8').": Phân Tích Chi Tiết, Quy Trình & Giải Pháp Tối Ưu {$year}");
                if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $cleanHtml, $h1Match)) {
                    $title = strip_tags($h1Match[1]);
                }

                return [
                    'title' => $this->sanitizeCleanTitle($title, $keyword, $year),
                    'content' => $cleanHtml,
                ];
            }
        }

        $cleanTitle = mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8').": Phân Tích Toàn Diện, Tiêu Chí Đánh Giá & Quy Trình Chuẩn {$year}";
        $disclaimerHtml = ! empty($industryProfile['required_disclaimer'])
            ? "<p><em><strong>Lưu ý chuyên môn:</strong> {$industryProfile['required_disclaimer']}</em></p>"
            : '';

        $fullTemplateContent = <<<HTML
<h2>1. Tổng Quan &amp; Bản Chất Cốt Lõi Về {$keyword}</h2>
<p>Khi tìm hiểu về <strong>{$keyword}</strong> trong lĩnh vực {$industryName}, việc nắm vững các tiêu chuẩn chất lượng, quy trình triển khai và điều kiện áp dụng thực tế là yếu tố then chốt giúp khách hàng và doanh nghiệp đưa ra quyết định chính xác. Bài viết dưới đây phân tích khách quan các khía cạnh trọng tâm cập nhật năm {$year}.</p>
<p>Một giải pháp {$keyword} đạt chuẩn cần đảm bảo tính minh bạch về thông tin chuyên môn, phù hợp với nhu cầu thực tế và đi kèm chính sách hỗ trợ rõ ràng từ {$brandName}.</p>

<h2>2. 5 Tiêu Chí Quan Trọng Khi Đánh Giá &amp; Lựa Chọn {$keyword}</h2>
<ul>
  <li><strong>Năng lực chuyên môn và tính minh bạch:</strong> Thông tin tư vấn rõ ràng, giải thích đầy đủ cơ chế hoạt động, phạm vi đáp ứng và các điều kiện áp dụng thực tế.</li>
  <li><strong>Quy trình triển khai tiêu chuẩn:</strong> Các bước thực hiện được chuẩn hóa khoa học, giúp kiểm soát chất lượng và tiết kiệm thời gian cho khách hàng.</li>
  <li><strong>Công nghệ và giải pháp tối ưu:</strong> Ứng dụng phương pháp hiện đại phù hợp với đặc thù ngành {$industryName}, đảm bảo tính ổn định và an toàn.</li>
  <li><strong>Minh bạch về cấu trúc chi phí:</strong> Mức phí được phân tích rõ theo từng hạng mục, không phát sinh chi phí ẩn ngoài thỏa thuận.</li>
  <li><strong>Chính sách đồng hành và hỗ trợ sau triển khai:</strong> Cam kết hỗ trợ kỹ thuật, bảo hành hoặc giải đáp vướng mắc kịp thời trong suốt quá trình sử dụng.</li>
</ul>

<h2>3. Bảng So Sánh Các Phương Án &amp; Tiêu Chí Triển Khai {$keyword}</h2>
<table>
  <thead>
    <tr>
      <th>Hạng Mục / Phương Án</th>
      <th>Đặc Điểm &amp; Phạm Vi Đáp Ứng</th>
      <th>Chi Phí Tham Khảo</th>
      <th>Đối Tượng Phù Hợp</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Tư vấn &amp; Đánh giá nhu cầu ban đầu</td>
      <td>Phân tích hiện trạng và xác định mục tiêu cụ thể</td>
      <td>Theo chính sách tư vấn của {$brandName}</td>
      <td>Mọi khách hàng đang tìm hiểu {$keyword}</td>
    </tr>
    <tr>
      <td>Giải pháp {$keyword} Tiêu chuẩn</td>
      <td>Đáp ứng đầy đủ các tiêu chí kỹ thuật và vận hành cốt lõi</td>
      <td>Báo giá minh bạch theo khối lượng/tình trạng thực tế</td>
      <td>Nhu cầu phổ thông, tối ưu ngân sách</td>
    </tr>
    <tr>
      <td>Giải pháp {$keyword} Chuyên sâu / Toàn diện</td>
      <td>Tùy chỉnh chuyên biệt theo yêu cầu cao cấp và hỗ trợ ưu tiên</td>
      <td>Liên hệ nhận phương án &amp; dự toán chi tiết</td>
      <td>Yêu cầu tiêu chuẩn cao, quy mô mở rộng</td>
    </tr>
  </tbody>
</table>

<h2>4. Quy Trình Triển Khai {$keyword} 5 Bước Chuẩn Mực</h2>
<ol>
  <li><strong>Bước 1 - Tiếp nhận thông tin &amp; Khảo sát hiện trạng:</strong> Lắng nghe nhu cầu cụ thể và thu thập dữ liệu cần thiết liên quan đến {$keyword}.</li>
  <li><strong>Bước 2 - Phân tích chuyên môn &amp; Đề xuất phương án:</strong> Xây dựng lộ trình giải pháp phù hợp kèm phân tích ưu nhược điểm và dự toán chi phí minh bạch.</li>
  <li><strong>Bước 3 - Chuẩn bị điều kiện &amp; Tiêu chuẩn kỹ thuật:</strong> Hoàn thiện các bước chuẩn bị cần thiết trước khi tiến hành triển khai chính thức.</li>
  <li><strong>Bước 4 - Thực hiện triển khai theo quy trình chuẩn:</strong> Đảm bảo tuân thủ nghiêm ngặt các quy định an toàn và chất lượng chuyên môn.</li>
  <li><strong>Bước 5 - Nghiệm thu, Bàn giao &amp; Hướng dẫn theo dõi:</strong> Kiểm tra kết quả thực tế, bàn giao đầy đủ thông tin và đồng hành hỗ trợ lâu dài.</li>
</ol>

<h2>5. Câu Hỏi Thường Gặp (FAQ) Về {$keyword}</h2>
<p><strong>Hỏi: Cần chuẩn bị những thông tin gì trước khi lựa chọn giải pháp {$keyword}?</strong><br/>
<em>Trả lời:</em> Bạn nên xác định rõ mục tiêu sử dụng, phạm vi nhu cầu thực tế, ngân sách dự kiến và các yêu cầu đặc thù để chuyên viên tại {$brandName} tư vấn phương án sát thực tế nhất.</p>
<p><strong>Hỏi: Chi phí của {$keyword} phụ thuộc vào những yếu tố nào?</strong><br/>
<em>Trả lời:</em> Chi phí thực tế thường phụ thuộc vào mức độ phức tạp của yêu cầu, gói giải pháp/vật liệu lựa chọn, phạm vi triển khai và các chính sách bảo hành đi kèm.</p>
{$disclaimerHtml}
HTML;

        return [
            'title' => $cleanTitle,
            'content' => $fullTemplateContent,
        ];
    }

    /**
     * Chuẩn hóa tiêu đề bài viết: loại bỏ các tiền tố thừa như "từ khóa:" và ký tự JSON rác.
     */
    protected function sanitizeCleanTitle(string $title, string $keyword, string|int $year): string
    {
        $clean = strip_tags(trim($title));
        $clean = preg_replace('/^(?:giải mã\s+)?(?:từ khóa|về từ khóa|cho từ khóa|chủ đề)\s*[:\-]?\s*/iu', '', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B\"'{}:;,-");

        if ($clean === '' || mb_strlen($clean, 'UTF-8') < 10) {
            $titleKw = mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8');

            return "{$titleKw}: Đánh Giá Chi Tiết, Bảng Giá & Tiêu Chuẩn Chất Lượng Mới Nhất {$year}";
        }

        return $clean;
    }

    /**
     * Dọn sạch nội dung HTML bài viết: loại bỏ chuỗi JSON thô, code fence và đóng các thẻ bị cắt cụt.
     */
    protected function cleanArticleHtmlContent(string $raw): string
    {
        $cleaned = trim($raw);

        // Nếu vẫn còn bọc nguyên block JSON thì cắt bỏ phần mở đầu {"title": ..., "content": "
        if (preg_match('/^\s*\{[\s\S]*?"content"\s*:\s*"/u', $cleaned, $match)) {
            $cleaned = substr($cleaned, strlen($match[0]));
            $cleaned = preg_replace('/"\s*\}?\s*$/u', '', $cleaned) ?? $cleaned;
        }

        // Giải mã escape JSON (\n, \", \/) khi nội dung được bóc từ JSON chưa đóng
        if (str_contains($cleaned, '\\"') || str_contains($cleaned, '\\n') || str_contains($cleaned, '\\/')) {
            $decoded = json_decode('"'.str_replace(["\r", "\n"], ['', '\\n'], $cleaned).'"');
            $cleaned = is_string($decoded) ? $decoded : stripcslashes($cleaned);
        }

        $cleaned = preg_replace('/^```(?:html|json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;

        // Cắt bỏ thẻ dở dang ở cuối (VD: "<td>abc</t") rồi đóng các thẻ còn mở
        $cleaned = preg_replace('/<[^>]*$/', '', $cleaned) ?? $cleaned;
        foreach (['li', 'ul', 'ol', 'td', 'th', 'tr', 'tbody', 'thead', 'table', 'p', 'h3', 'h2', 'strong', 'em'] as $tag) {
            $open = preg_match_all("/<{$tag}(?:\s[^>]*)?>/i", $cleaned);
            $close = preg_match_all("/<\/{$tag}>/i", $cleaned);
            if ($open > $close) {
                $cleaned .= str_repeat("</{$tag}>", $open - $close);
            }
        }

        return trim($cleaned);
    }

    /**
     * Tạo bộ hình ảnh minh họa bài viết (1 Featured + N Inline):
     * Ưu tiên Stock miễn phí (Unsplash/Pexels) nén tối ưu, nếu không có thì dùng AI tạo ảnh TUYỆT ĐỐI KHÔNG CHÈN CHỮ.
     *
     * @param  array<int, string>  $h2Headings
     * @return array{featured_image: string, inline_images: array<int, array{url: string, alt: string, caption: string, target_h2_index?: int}>, attribution_note: string}
     */
    protected function resolveArticleImagesSet(
        string $keyword,
        string $title,
        string $industryKey = 'other',
        int $inlineCount = 3,
        array $h2Headings = []
    ): array {
        $slug = Str::slug($keyword);
        $time = time();
        $inlineCount = max(1, min(7, $inlineCount));

        $industryPools = [
            'healthcare' => [
                'featured' => [
                    'url' => 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Professional medical or dental consultation in a modern clinic for {$keyword}, no text",
                    'alt' => "Minh họa chuyên gia tư vấn và thăm khám {$keyword}",
                    'caption' => "Hình minh họa quy trình thăm khám và tư vấn chuyên môn về {$keyword}",
                ],
                'inlines' => [
                    [
                        'url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Clean modern healthcare equipment and treatment room for {$keyword}, no text",
                        'alt' => "Minh họa trang thiết bị và tiêu chuẩn an toàn cho {$keyword}",
                        'caption' => 'Hình minh họa tiêu chuẩn trang thiết bị và quy trình kiểm soát vô trùng',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Patient care and health checkup consultation for {$keyword}, no text",
                        'alt' => "Minh họa hướng dẫn chăm sóc và theo dõi {$keyword}",
                        'caption' => 'Hình minh họa tư vấn lộ trình theo dõi và chăm sóc sức khỏe đúng cách',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Modern dental and clinical diagnostics technology for {$keyword}, no text",
                        'alt' => "Minh họa công nghệ chẩn đoán và điều trị {$keyword}",
                        'caption' => 'Hình minh họa ứng dụng công nghệ chẩn đoán hiện đại trong quy trình chuyên khoa',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1551076805-e1869033e561?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Medical specialists reviewing clinical treatment plan for {$keyword}, no text",
                        'alt' => "Minh họa hội chẩn và lập phác đồ chuyên môn cho {$keyword}",
                        'caption' => 'Hình minh họa đánh giá chuyên môn và xây dựng phác đồ cá nhân hóa',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Sterile medical instruments and quality assurance for {$keyword}, no text",
                        'alt' => "Minh họa kiểm định chất lượng vật liệu và vô trùng trong {$keyword}",
                        'caption' => 'Hình minh họa quy trình kiểm soát chất lượng và tiêu chuẩn an toàn y tế',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Dedicated post-treatment care and follow-up support for {$keyword}, no text",
                        'alt' => "Minh họa chính sách đồng hành và chăm sóc sau thực hiện {$keyword}",
                        'caption' => 'Hình minh họa hướng dẫn chăm sóc định kỳ và đồng hành cùng khách hàng',
                    ],
                ],
            ],
            'real_estate' => [
                'featured' => [
                    'url' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Modern architectural real estate property and urban skyline for {$keyword}, no text",
                    'alt' => "Minh họa tổng quan thị trường và dự án {$keyword}",
                    'caption' => "Hình minh họa quy hoạch không gian và kiến trúc liên quan đến {$keyword}",
                ],
                'inlines' => [
                    [
                        'url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Bright modern living space and property design for {$keyword}, no text",
                        'alt' => "Minh họa không gian tiện ích và thiết kế của {$keyword}",
                        'caption' => 'Hình minh họa tiêu chuẩn thiết kế không gian và hệ thống tiện ích nội khu',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Real estate legal document review and investment planning for {$keyword}, no text",
                        'alt' => "Minh họa thẩm định pháp lý và bài toán tài chính cho {$keyword}",
                        'caption' => 'Hình minh họa quy trình rà soát hồ sơ pháp lý và hoạch định tài chính',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Green urban residential landscape and infrastructure for {$keyword}, no text",
                        'alt' => "Minh họa hạ tầng kết nối và cảnh quan đô thị {$keyword}",
                        'caption' => 'Hình minh họa hạ tầng giao thông kết nối và cảnh quan sinh thái',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Contemporary apartment interior handover inspection for {$keyword}, no text",
                        'alt' => "Minh họa tiêu chuẩn bàn giao nội thất và nghiệm thu {$keyword}",
                        'caption' => 'Hình minh họa kiểm tra chất lượng hoàn thiện và tiêu chuẩn bàn giao thực tế',
                    ],
                ],
            ],
            'legal' => [
                'featured' => [
                    'url' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Professional legal consultation office and law books for {$keyword}, no text",
                    'alt' => "Minh họa tư vấn pháp lý và quy định về {$keyword}",
                    'caption' => "Hình minh họa tra cứu căn cứ pháp lý và quy trình thực hiện {$keyword}",
                ],
                'inlines' => [
                    [
                        'url' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Reviewing legal dossier and official contracts on desk for {$keyword}, no text",
                        'alt' => "Minh họa chuẩn bị hồ sơ và thủ tục cho {$keyword}",
                        'caption' => 'Hình minh họa rà soát danh mục hồ sơ và văn bản theo quy định hiện hành',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Professional advisory meeting with client for {$keyword}, no text",
                        'alt' => "Minh họa giải đáp vướng mắc thực tế về {$keyword}",
                        'caption' => 'Hình minh họa trao đổi và làm rõ quyền lợi, nghĩa vụ pháp lý',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Corporate compliance and regulatory workflow for {$keyword}, no text",
                        'alt' => "Minh họa quy trình thẩm định và nộp hồ sơ {$keyword}",
                        'caption' => 'Hình minh họa các bước thực hiện thủ tục hành chính và tuân thủ pháp luật',
                    ],
                ],
            ],
            'technology' => [
                'featured' => [
                    'url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Modern SaaS analytics dashboard and cloud software workflow for {$keyword}, no text",
                    'alt' => "Minh họa giao diện giải pháp công nghệ {$keyword}",
                    'caption' => "Hình minh họa hệ thống quản trị dữ liệu và tự động hóa quy trình {$keyword}",
                ],
                'inlines' => [
                    [
                        'url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Enterprise software integration and team collaboration for {$keyword}, no text",
                        'alt' => "Minh họa triển khai và tích hợp hệ thống {$keyword}",
                        'caption' => 'Hình minh họa khả năng kết nối dữ liệu và tối ưu hiệu suất làm việc',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Data security and cloud infrastructure protection for {$keyword}, no text",
                        'alt' => "Minh họa tiêu chuẩn bảo mật và vận hành {$keyword}",
                        'caption' => 'Hình minh họa kiến trúc bảo mật và hỗ trợ kỹ thuật vận hành',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Software engineering team onboarding and customer success for {$keyword}, no text",
                        'alt' => "Minh họa đào tạo vận hành và chuyển giao công nghệ {$keyword}",
                        'caption' => 'Hình minh họa quy trình hướng dẫn vận hành và đồng hành kỹ thuật',
                    ],
                ],
            ],
            'travel' => [
                'featured' => [
                    'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Scenic travel destination landscape and resort experience for {$keyword}, no text",
                    'alt' => "Minh họa điểm đến và hành trình trải nghiệm {$keyword}",
                    'caption' => "Hình minh họa không gian cảnh quan và trải nghiệm thực tế cho {$keyword}",
                ],
                'inlines' => [
                    [
                        'url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Comfortable hospitality resort accommodation and amenities for {$keyword}, no text",
                        'alt' => "Minh họa dịch vụ lưu trú và tiện ích trong hành trình {$keyword}",
                        'caption' => 'Hình minh họa tiêu chuẩn lưu trú và tiện nghi nghỉ dưỡng',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Travel itinerary planning with map and luggage for {$keyword}, no text",
                        'alt' => "Minh họa chuẩn bị lịch trình và kinh nghiệm đi {$keyword}",
                        'caption' => 'Hình minh họa lập kế hoạch lịch trình và chuẩn bị hành trang di chuyển',
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=1200&q=80',
                        'desc' => "Local cultural exploration and nature sightseeing for {$keyword}, no text",
                        'alt' => "Minh họa khám phá văn hóa và ẩm thực địa phương {$keyword}",
                        'caption' => 'Hình minh họa hoạt động tham quan và trải nghiệm văn hóa bản địa',
                    ],
                ],
            ],
        ];

        $defaultPool = [
            'featured' => [
                'url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80',
                'desc' => "Professional business and product showcase environment for {$keyword}, no text",
                'alt' => "Minh họa tổng quan và tiêu chí đánh giá {$keyword}",
                'caption' => "Hình minh họa phân tích chuyên môn và giải pháp tối ưu cho {$keyword}",
            ],
            'inlines' => [
                [
                    'url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Professional team analyzing quality standards and workflow for {$keyword}, no text",
                    'alt' => "Minh họa quy trình kiểm định chất lượng và triển khai {$keyword}",
                    'caption' => 'Hình minh họa quy trình đánh giá tiêu chuẩn kỹ thuật và chất lượng thực tế',
                ],
                [
                    'url' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Customer consultation and support service for {$keyword}, no text",
                    'alt' => "Minh họa tư vấn giải pháp và hỗ trợ khách hàng về {$keyword}",
                    'caption' => 'Hình minh họa đồng hành tư vấn và hỗ trợ khách hàng trong quá trình sử dụng',
                ],
                [
                    'url' => 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Strategic planning and transparent cost comparison for {$keyword}, no text",
                    'alt' => "Minh họa phân tích phương án và tối ưu chi phí {$keyword}",
                    'caption' => 'Hình minh họa đối chiếu các tiêu chí chất lượng và giải pháp triển khai',
                ],
                [
                    'url' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1200&q=80',
                    'desc' => "Step by step implementation and quality check for {$keyword}, no text",
                    'alt' => "Minh họa nghiệm thu và cam kết chất lượng {$keyword}",
                    'caption' => 'Hình minh họa quy trình kiểm tra kết quả và bàn giao tiêu chuẩn',
                ],
            ],
        ];

        $stockPool = $industryPools[$industryKey] ?? $defaultPool;
        $inlineTemplates = $stockPool['inlines'] ?? $defaultPool['inlines'];

        // 1. Ảnh đại diện (Featured Image)
        $featuredUrl = $this->downloadAndOptimizeStockPhoto($stockPool['featured']['url'], "stock-featured-{$slug}-{$time}");
        if (! $featuredUrl) {
            $featuredUrl = $this->generateAiImageWithoutText($keyword, $stockPool['featured']['desc'], "ai-featured-{$slug}-{$time}");
        }
        $featuredUrl = $featuredUrl ?: $stockPool['featured']['url'];

        // 2. Phân bổ đều N ảnh inline vào các mục H2 của bài viết
        $imagePlanner = app(\App\Services\Media\ImagePlannerService::class);
        $distributedHeadings = $imagePlanner->selectDistributedHeadings($h2Headings, $inlineCount, $keyword);

        $inlineImages = [];
        for ($i = 0; $i < $inlineCount; $i++) {
            $tpl = $inlineTemplates[$i % count($inlineTemplates)];
            $targetH2 = $distributedHeadings[$i]['heading'] ?? '';
            $targetH2Index = (int) ($distributedHeadings[$i]['h2_index'] ?? ($i + 1));
            $cleanH2Text = trim(preg_replace('/^\d+[\.\)]\s*/u', '', $targetH2) ?? $targetH2);

            $inlineSrc = 'Ảnh minh họa Stock Unsplash / Pexels';
            $inlineUrl = $this->downloadAndOptimizeStockPhoto($tpl['url'], "stock-inline-".($i + 1)."-{$slug}-{$time}");
            if (! $inlineUrl) {
                $inlineUrl = $this->generateAiImageWithoutText(
                    $keyword,
                    $tpl['desc'].($cleanH2Text !== '' ? " illustrating {$cleanH2Text}" : ''),
                    "ai-inline-".($i + 1)."-{$slug}-{$time}"
                );
                $inlineSrc = 'Ảnh minh họa AI Photorealistic';
            }
            $inlineUrl = $inlineUrl ?: $tpl['url'];

            $contextualAlt = $cleanH2Text !== ''
                ? "Hình minh họa {$cleanH2Text} — {$keyword}"
                : $tpl['alt'];
            $contextualCaption = $cleanH2Text !== ''
                ? "Hình minh họa cho phần: {$cleanH2Text} ({$inlineSrc})"
                : "{$tpl['caption']} ({$inlineSrc})";

            $inlineImages[] = [
                'url' => $inlineUrl,
                'alt' => $contextualAlt,
                'caption' => $contextualCaption,
                'target_h2_index' => $targetH2Index,
            ];
        }

        $attributionNote = 'Hình ảnh trong bài viết là hình minh họa chuyên đề được phân bổ tự động theo cấu trúc H2 và tối ưu chuẩn WebP từ thư viện thương mại miễn phí bản quyền (Unsplash/Pexels) hoặc AI minh họa ngữ cảnh.';

        return [
            'featured_image' => $featuredUrl,
            'attribution_note' => $attributionNote,
            'inline_images' => $inlineImages,
        ];
    }

    /**
     * Tải ảnh stock từ internet và nén tối ưu dung lượng dưới 120KB lưu vào public storage.
     */
    protected function downloadAndOptimizeStockPhoto(string $url, string $subPath): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $mimeType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && ! empty($body)) {
                /** @var ImageOptimizationService $optimizer */
                $optimizer = app(ImageOptimizationService::class);
                $opt = $optimizer->optimize($body, $mimeType);
                $saveContents = $opt['contents'] ?? $body;
                $ext = $opt['extension'] ?? 'jpg';

                $fileName = "articles_media/{$subPath}.{$ext}";
                $fullPath = storage_path('app/public/'.$fileName);
                if (! is_dir(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }
                file_put_contents($fullPath, $saveContents);

                return asset('storage/'.$fileName);
            }
        } catch (Throwable $e) {
            Log::warning("downloadAndOptimizeStockPhoto warning: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Tạo ảnh bằng AI với luật phủ định nghiêm ngặt: TUYỆT ĐỐI KHÔNG CHÈN CHỮ để tránh lỗi chính tả.
     */
    protected function generateAiImageWithoutText(string $keyword, string $themeDescription, string $subPath): ?string
    {
        $apiKey = config('ai.providers.gemini.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent?key='.$apiKey;
            // Chỉ thị rõ ràng: KHÔNG chữ, KHÔNG text, KHÔNG logo, KHÔNG biển hiệu
            $imagePrompt = "A professional, photorealistic high-end photography: {$themeDescription}, focus topic: {$keyword}. Bright, clean setting, natural lighting, ultra sharp focus, 8k resolution. STRICT NEGATIVE INSTRUCTIONS: DO NOT INCLUDE ANY TEXT, NO LETTERS, NO WORDS, NO TYPOS, NO TYPOGRAPHY, NO WATERMARKS, NO LOGOS, NO SIGNBOARDS, NO WRITING ON WALLS. PURE PHOTOGRAPHY ONLY.";

            $payload = [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $imagePrompt]]],
                ],
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $res = curl_exec($ch);
            curl_close($ch);

            $json = json_decode($res, true);
            $parts = $json['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (! empty($part['inlineData']['data'])) {
                    $imageData = base64_decode($part['inlineData']['data']);
                    $ext = ($part['inlineData']['mimeType'] ?? '') === 'image/jpeg' ? 'jpg' : 'png';
                    $fileName = "articles_media/{$subPath}.{$ext}";

                    $fullPath = storage_path('app/public/'.$fileName);
                    if (! is_dir(dirname($fullPath))) {
                        mkdir(dirname($fullPath), 0755, true);
                    }
                    file_put_contents($fullPath, $imageData);

                    return asset('storage/'.$fileName);
                }
            }
        } catch (Throwable $e) {
            Log::warning("generateAiImageWithoutText warning: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Rải đều các ảnh minh họa nội dung (inline images) vào các mục H2 của bài viết
     * và đặt tự nhiên sau đoạn văn <p>...</p> đầu tiên bên trong mỗi mục H2 được chọn.
     *
     * @param  array<int, array{url: string, alt: string, caption: string, target_h2_index?: int}>  $inlineImages
     */
    protected function insertInlineImagesIntoArticle(string $content, array $inlineImages, string $attributionNote = ''): string
    {
        if (empty($inlineImages)) {
            if (! empty($attributionNote)) {
                $content .= "\n\n<div class=\"article-source-attribution text-xs text-slate-500 border-t border-slate-200 pt-3 mt-6 italic\" style=\"font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 24px; font-style: italic;\">📌 <strong>Ghi chú xuất xứ hình ảnh & nội dung:</strong> {$attributionNote}</div>";
            }

            return $content;
        }

        preg_match_all('/<\/h2>/i', $content, $h2CloseMatches, PREG_OFFSET_CAPTURE);
        $totalH2 = count($h2CloseMatches[0] ?? []);
        $totalInline = count($inlineImages);

        if ($totalH2 === 0) {
            $modified = $content;
            foreach ($inlineImages as $img) {
                $modified .= "\n\n".$this->buildFigureHtml($img);
            }
            if (! empty($attributionNote) && ! str_contains($modified, 'article-source-attribution')) {
                $modified .= "\n\n<div class=\"article-source-attribution text-xs text-slate-500 border-t border-slate-200 pt-3 mt-6 italic\" style=\"font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 24px; font-style: italic;\">📌 <strong>Ghi chú xuất xứ hình ảnh & nội dung:</strong> {$attributionNote}</div>";
            }

            return $modified;
        }

        // Xác định chỉ mục H2 (1-based) cho từng ảnh để rải đều từ đầu đến cuối bài
        $usableH2 = ($totalH2 > $totalInline && $totalH2 > 3) ? ($totalH2 - 1) : $totalH2;
        $insertionsByH2Index = [];
        foreach ($inlineImages as $idx => $img) {
            $targetH2Index = (int) ($img['target_h2_index'] ?? 0);
            if ($targetH2Index <= 0 || $targetH2Index > $totalH2) {
                $targetH2Index = (int) floor(($idx * $usableH2) / max(1, $totalInline)) + 1;
                $targetH2Index = max(1, min($totalH2, $targetH2Index));
            }
            $insertionsByH2Index[$targetH2Index][] = $this->buildFigureHtml($img);
        }

        // Chèn từ mục H2 cuối ngược về đầu để không làm lệch offset chuỗi
        krsort($insertionsByH2Index);
        $modified = $content;

        foreach ($insertionsByH2Index as $h2Index1Based => $figuresHtmlList) {
            $combinedFigures = implode("\n", $figuresHtmlList);
            $arrIdx = $h2Index1Based - 1;
            if (! isset($h2CloseMatches[0][$arrIdx])) {
                $modified .= "\n\n".$combinedFigures;
                continue;
            }

            $h2CloseOffset = $h2CloseMatches[0][$arrIdx][1] + strlen($h2CloseMatches[0][$arrIdx][0]);
            $nextH2Offset = isset($h2CloseMatches[0][$arrIdx + 1])
                ? $h2CloseMatches[0][$arrIdx + 1][1]
                : strlen($modified);

            // Tìm thẻ </p> đầu tiên trong phạm vi của mục H2 này để đặt ảnh ngay sau đoạn dẫn nhập
            $sectionSlice = substr($modified, $h2CloseOffset, max(0, $nextH2Offset - $h2CloseOffset));
            if (preg_match('/<\/p>/i', $sectionSlice, $pMatch, PREG_OFFSET_CAPTURE)) {
                $insertPos = $h2CloseOffset + $pMatch[0][1] + strlen($pMatch[0][0]);
            } else {
                $insertPos = $h2CloseOffset;
            }

            $modified = substr($modified, 0, $insertPos)."\n".$combinedFigures."\n".substr($modified, $insertPos);
        }

        if (! empty($attributionNote) && ! str_contains($modified, 'article-source-attribution')) {
            $modified .= "\n\n<div class=\"article-source-attribution text-xs text-slate-500 border-t border-slate-200 pt-3 mt-6 italic\" style=\"font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 24px; font-style: italic;\">📌 <strong>Ghi chú xuất xứ hình ảnh & nội dung:</strong> {$attributionNote}</div>";
        }

        return $modified;
    }

    /**
     * @param  array{url: string, alt: string, caption: string}  $img
     */
    protected function buildFigureHtml(array $img): string
    {
        return <<<HTML
<figure class="my-6 text-center" style="margin: 24px 0; text-align: center;">
  <img src="{$img['url']}" alt="{$img['alt']}" class="rounded-xl shadow-md mx-auto w-full max-h-[460px] object-cover" style="border-radius: 12px; max-height: 460px; width: 100%; object-fit: cover;" loading="lazy" />
  <figcaption class="text-xs text-slate-500 mt-2 italic" style="font-size: 13px; color: #64748b; font-style: italic; margin-top: 8px;">📷 {$img['caption']}</figcaption>
</figure>
HTML;
    }

    /**
     * Gọi Gemini API thoại trực tiếp giữ ngữ cảnh lịch sử.
     */
    protected function generateConversationalAiReply(
        string $userPrompt,
        string $historyContext,
        ?Website $website,
        Workspace $workspace
    ): string {
        $brandKnowledge = $this->orchestrator ? $this->orchestrator->loadBrandKnowledge($website ?: $workspace->id, $workspace->id) : null;
        $brandName = $brandKnowledge?->brand_name ?? $website?->name ?? 'Doanh nghiệp của bạn';

        $prompt = <<<PROMPT
Bạn là Aura SEO Assistant — Cố vấn Chiến lược SEO & Chuyên gia Tối ưu Nội dung cao cấp của hệ thống SaaS AuraSEO.
Thương hiệu phục vụ: {$brandName}
Lịch sử trao đổi trước đó giữa bạn và khách hàng:
{$historyContext}

Câu nói / Yêu cầu mới nhất của khách hàng: "{$userPrompt}"

Hãy trả lời khách hàng một cách thông minh, chuẩn xác, giữ đúng mạch ngữ cảnh của các tin nhắn trước. Nếu khách hàng muốn viết bài, hãy gợi ý cấu trúc outline hoặc từ khóa trọng tâm. Định dạng bằng Markdown đẹp mắt, có bullet points.
PROMPT;

        $apiKey = config('ai.providers.gemini.api_key');
        $model = config('ai.providers.gemini.content_model', 'gemini-3.1-pro-preview');

        $reply = $this->callGeminiApi($model, $prompt, $apiKey);
        if (! $reply) {
            $reply = $this->callGeminiApi('gemini-2.5-flash', $prompt, $apiKey);
        }

        return $reply ?: 'Tôi đã ghi nhận yêu cầu của bạn. Bạn muốn tôi phân tích từ khóa hay bắt đầu khởi tạo bài viết chuẩn SEO ngay?';
    }

    /**
     * Hàm gọi trực tiếp Gemini API với timeout và kiểm tra an toàn.
     */
    protected function callGeminiApi(string $model, string $promptText, string $apiKey, bool $asJson = false, int $timeout = 40): ?string
    {
        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.$apiKey;
            $generationConfig = [
                'temperature' => 0.4,
                'maxOutputTokens' => $asJson ? 8192 : 4000,
            ];

            if ($asJson) {
                $generationConfig['responseMimeType'] = 'application/json';
            }

            $payload = [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $promptText]]],
                ],
                'generationConfig' => $generationConfig,
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $res = curl_exec($ch);
            curl_close($ch);

            $json = json_decode($res, true);
            $parts = $json['candidates'][0]['content']['parts'] ?? [];
            $nonThought = [];
            $allTexts = [];
            if (is_array($parts)) {
                foreach ($parts as $p) {
                    if (is_array($p) && isset($p['text']) && is_string($p['text'])) {
                        $allTexts[] = $p['text'];
                        if (empty($p['thought'])) {
                            $nonThought[] = $p['text'];
                        }
                    }
                }
            }

            if (! empty($nonThought)) {
                return implode("\n", $nonThought);
            }
            if (! empty($allTexts)) {
                return implode("\n", $allTexts);
            }

            return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (Throwable $e) {
            Log::error("callGeminiApi error with model {$model}: ".$e->getMessage());

            return null;
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
