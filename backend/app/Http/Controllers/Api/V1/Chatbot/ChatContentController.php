<?php

namespace App\Http\Controllers\Api\V1\Chatbot;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Workspace;
use App\Services\Chatbot\ChatImageVisionService;
use App\Services\Chatbot\ImageFallbackPipeline;
use App\Services\Chatbot\InChatContentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class ChatContentController extends Controller
{
    public function __construct(
        protected InChatContentService $contentService,
        protected ChatImageVisionService $visionService,
        protected ImageFallbackPipeline $imagePipeline
    ) {}

    /**
     * GET /api/v1/chatbot/content/preview/{id}
     * Lấy dữ liệu chi tiết bài viết nháp và hình ảnh cho InChatArticleCard.
     */
    public function preview(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($id);

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết hoặc bài viết không thuộc Không gian làm việc này.',
            ], 404);
        }

        try {
            $previewData = $this->contentService->getArticlePreview($article, $workspace);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu xem trước bài viết thành công.',
                'data' => $previewData,
            ]);
        } catch (Throwable $e) {
            Log::error('ChatContentController preview error: '.$e->getMessage(), [
                'article_id' => $id,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi lấy bản xem trước bài viết.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/content/assist
     * Thực thi tác vụ AI Copilot trên một đoạn văn bản (Rewrite, Expand, Shorten...).
     */
    public function assist(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'article_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:rewrite,expand,shorten,change_tone,add_example,fix_grammar,custom'],
            'selected_text' => ['required', 'string', 'min:3'],
            'tone' => ['nullable', 'string', 'max:50'],
            'instruction' => ['nullable', 'string', 'max:500'],
            'context_before' => ['nullable', 'string', 'max:2000'],
            'context_after' => ['nullable', 'string', 'max:2000'],
        ], [
            'action.in' => 'Hành động biên tập AI không hợp lệ.',
            'selected_text.required' => 'Đoạn văn bản được chọn không được để trống.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu yêu cầu biên tập không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($validated['article_id']);

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết trong Không gian làm việc hiện tại.',
            ], 404);
        }

        try {
            $result = $this->contentService->assist(
                article: $article,
                workspace: $workspace,
                action: $validated['action'],
                selectedText: $validated['selected_text'],
                options: [
                    'tone' => $validated['tone'] ?? null,
                    'instruction' => $validated['instruction'] ?? null,
                    'context_before' => $validated['context_before'] ?? null,
                    'context_after' => $validated['context_after'] ?? null,
                ],
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Xử lý yêu cầu biên tập AI thành công.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('ChatContentController assist error: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi thực hiện tác vụ AI Copilot.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/content/apply-edit
     * Áp dụng đoạn văn đã được AI viết lại/chỉnh sửa vào bài viết nháp.
     */
    public function applyEdit(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'article_id' => ['required', 'integer'],
            'original_text' => ['nullable', 'string'],
            'replacement_text' => ['required', 'string', 'min:1'],
            'target_heading' => ['nullable', 'string', 'max:255'],
            'create_version' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu áp dụng chỉnh sửa không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($validated['article_id']);

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết trong Không gian làm việc hiện tại.',
            ], 404);
        }

        try {
            $appliedResult = $this->contentService->applySegmentEdit(
                article: $article,
                workspace: $workspace,
                originalText: (string) ($validated['original_text'] ?? ''),
                replacementText: $validated['replacement_text'],
                targetHeading: $validated['target_heading'] ?? null,
                options: [
                    'create_version' => $validated['create_version'] ?? true,
                ],
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật đoạn văn bản vào bài viết thành công.',
                'data' => $appliedResult,
            ]);
        } catch (Throwable $e) {
            Log::error('ChatContentController applyEdit error: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi áp dụng chỉnh sửa.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/content/insert-section
     * Chèn một mục H2/H3 mới kèm nội dung vào bài viết.
     */
    public function insertSection(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'article_id' => ['required', 'integer'],
            'heading' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'level' => ['nullable', 'string', 'in:h2,h3'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu chèn phân đoạn không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($validated['article_id']);

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
            ], 404);
        }

        try {
            $result = $this->contentService->insertSection(
                article: $article,
                workspace: $workspace,
                heading: $validated['heading'],
                content: $validated['content'],
                level: $validated['level'] ?? 'h2',
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã chèn phân đoạn mới vào bài viết.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('ChatContentController insertSection error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi chèn phân đoạn.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/content/attach-vision-image
     * AI Gemini Vision đọc hiểu ảnh người dùng tải lên và tự động ghim vào mục H2/H3 thích hợp nhất.
     */
    public function attachVisionImage(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'article_id' => ['required', 'integer'],
            'image' => ['required', 'array'],
            'target_heading' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Thông tin hình ảnh không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($validated['article_id']);

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
            ], 404);
        }

        try {
            $result = $this->visionService->attachUserImageToArticle(
                article: $article,
                workspace: $workspace,
                imageData: $validated['image'],
                targetHeading: $validated['target_heading'] ?? null,
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'AI Gemini Vision đã đọc hiểu và ghim ảnh vào bài viết thành công.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('ChatContentController attachVisionImage error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi xử lý ảnh Gemini Vision.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/content/run-image-pipeline
     * Kích hoạt Chiến lược xử lý hình ảnh 3 tầng chuẩn SEO (User Vision -> Stock Pexels/Unsplash -> AI Gemini Imagen).
     */
    public function runImagePipeline(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'article_id' => ['required', 'integer'],
            'heading' => ['nullable', 'string', 'max:255'],
            'user_images' => ['nullable', 'array'],
            'max_images' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Tham số chạy pipeline hình ảnh không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($validated['article_id']);

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
            ], 404);
        }

        $userImages = $validated['user_images'] ?? [];

        try {
            if (! empty($validated['heading'])) {
                $result = $this->imagePipeline->processImageForHeading(
                    article: $article,
                    workspace: $workspace,
                    heading: $validated['heading'],
                    userUploadedImages: $userImages,
                    actor: $request->user()
                );
            } else {
                $result = $this->imagePipeline->processAllHeadings(
                    article: $article,
                    workspace: $workspace,
                    userUploadedImages: $userImages,
                    maxImages: (int) ($validated['max_images'] ?? 4),
                    actor: $request->user()
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Thực thi xử lý hình ảnh 3 tầng chuẩn SEO thành công.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('ChatContentController runImagePipeline error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi chạy pipeline hình ảnh.',
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
            throw new ModelNotFoundException('Không tìm thấy Không gian làm việc (Workspace) hợp lệ.');
        }

        return $workspace;
    }
}
