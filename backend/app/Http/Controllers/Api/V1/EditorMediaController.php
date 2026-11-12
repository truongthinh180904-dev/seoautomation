<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadEditorImageRequest;
use App\Services\Media\EditorImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditorMediaController extends Controller
{
    public function __construct(
        protected EditorImageUploadService $uploadService
    ) {}

    /**
     * POST /api/v1/editor/images
     * Tải ảnh từ máy tính lên để chèn/thay thế trong trình soạn thảo bài viết.
     */
    public function store(UploadEditorImageRequest $request): JsonResponse
    {
        $workspaceId = (int) ($request->attributes->get('current_workspace')?->id
            ?: $request->header('X-Workspace-Id')
            ?: $request->user()?->default_workspace_id
            ?: 0);

        try {
            $data = $this->uploadService->store(
                $request->file('image'),
                $workspaceId,
                $request->validated('alt')
            );

            return response()->json([
                'success' => true,
                'message' => 'Tải ảnh lên thành công.',
                'data' => $data,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Editor image upload failed', ['workspace_id' => $workspaceId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải ảnh lên. Vui lòng thử lại.',
            ], 500);
        }
    }
}
