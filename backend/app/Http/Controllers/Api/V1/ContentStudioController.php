<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Workspace;
use App\Services\Article\ContentStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ContentStudioController extends Controller
{
    public function __construct(
        protected ?ContentStudioService $studioService = null
    ) {}

    /**
     * Resolve ContentStudioService (hỗ trợ dynamic mocking qua container trong testing).
     */
    protected function studio(): ContentStudioService
    {
        return app()->bound(ContentStudioService::class)
            ? app(ContentStudioService::class)
            : ($this->studioService ?? app(ContentStudioService::class));
    }

    /**
     * GET /api/v1/content-studio/{id}
     * Tải dữ liệu bài viết cho phiên làm việc Content Studio.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($id);
        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết không tồn tại hoặc không thuộc Workspace hiện tại.',
            ], 404);
        }

        try {
            $data = $this->studio()->getStudioData($article, $workspace);

            return response()->json([
                'success' => true,
                'message' => 'Tải dữ liệu Content Studio thành công.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('ContentStudio show failed', [
                'workspace_id' => $workspace->id,
                'article_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải dữ liệu bài viết: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/content-studio/assist
     * Trợ lý AI Copilot tức thì (Rewrite, Expand, Shorten, Change Tone).
     * Trừ 1 credit cho mỗi lượt gọi thành công.
     */
    public function assist(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'article_id' => 'required|integer',
            'action' => 'required|string|in:rewrite,expand,shorten,change_tone,custom,add_example,fix_grammar',
            'selected_text' => 'required|string|min:1',
            'tone' => 'nullable|string|max:100',
            'instruction' => 'nullable|string|max:1000',
            'context_before' => 'nullable|string|max:2000',
            'context_after' => 'nullable|string|max:2000',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find((int) $validated['article_id']);
        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết không tồn tại hoặc không thuộc Workspace hiện tại.',
            ], 404);
        }

        try {
            $result = $this->studio()->assist(
                article: $article,
                workspace: $workspace,
                action: $validated['action'],
                selectedText: $validated['selected_text'],
                options: $validated,
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'AI Copilot xử lý thành công.',
                'data' => $result,
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'INSUFFICIENT_CREDITS',
                'message' => $e->getMessage() ?: 'Không đủ credit để sử dụng AI Copilot. Cần 1 credit.',
                'required_credits' => 1,
                'available_credits' => $e->availableCredits ?? 0,
            ], 402);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('ContentStudio assist failed', [
                'workspace_id' => $workspace->id,
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gọi AI Copilot: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/content-studio/autosave
     * Tự động lưu nháp nội dung bài viết và quản lý lịch sử phiên bản.
     */
    public function autosave(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'article_id' => 'required|integer',
            'content' => 'required|string',
            'title' => 'nullable|string|max:255',
            'outline' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'create_version' => 'nullable|boolean',
            'version_reason' => 'nullable|string|max:255',
        ]);

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find((int) $validated['article_id']);
        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết không tồn tại hoặc không thuộc Workspace hiện tại.',
            ], 404);
        }

        try {
            $result = $this->studio()->autosave(
                article: $article,
                workspace: $workspace,
                data: $validated,
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu nháp tự động thành công.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('ContentStudio autosave failed', [
                'workspace_id' => $workspace->id,
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lưu nháp bài viết: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/content-studio/{id}/versions
     * Lấy danh sách các phiên bản đã lưu của bài viết.
     */
    public function versions(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Article|null $article */
        $article = Article::where('workspace_id', $workspace->id)->find($id);
        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết không tồn tại hoặc không thuộc Workspace hiện tại.',
            ], 404);
        }

        try {
            $versions = $this->studio()->getVersions($article, $workspace);

            return response()->json([
                'success' => true,
                'message' => 'Lấy lịch sử phiên bản thành công.',
                'data' => $versions,
            ]);
        } catch (Throwable $e) {
            Log::error('ContentStudio versions failed', [
                'workspace_id' => $workspace->id,
                'article_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải lịch sử phiên bản: '.$e->getMessage(),
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
