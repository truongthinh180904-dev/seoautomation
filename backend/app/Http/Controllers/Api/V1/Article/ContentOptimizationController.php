<?php

namespace App\Http\Controllers\Api\V1\Article;

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\WordPress\WordPressAuthException;
use App\Exceptions\WordPress\WordPressPublishException;
use App\Exceptions\WordPress\WordPressValidationException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Workspace;
use App\Services\SEO\Optimization\ContentRefreshService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ContentOptimizationController extends Controller
{
    public function __construct(
        protected ContentRefreshService $refreshService
    ) {}

    /**
     * POST /api/v1/articles/{id}/optimization-plan
     * Tạo kế hoạch tối ưu nội dung và khấu trừ 2 credit.
     */
    public function generatePlan(Request $request, int $id): JsonResponse
    {
        $article = Article::withoutGlobalScopes()->findOrFail($id);
        $workspace = $this->resolveWorkspace($request, $article);

        $options = [
            'insight_id' => $request->input('insight_id'),
            'target_queries' => $request->input('target_queries', []),
            'idempotency_key' => $request->input('idempotency_key'),
        ];

        try {
            $diffDto = $this->refreshService->generateOptimizationPlan(
                article: $article,
                workspace: $workspace,
                options: $options,
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã tạo kế hoạch làm mới nội dung thành công.',
                'data' => $diffDto->toArray(),
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Số dư credit không đủ để tạo kế hoạch tối ưu nội dung (cần 2 credits).',
                'required_credits' => $e->requiredCredits,
                'available_credits' => $e->availableCredits,
            ], 402);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/articles/{id}/diff-preview
     * Lấy bản xem trước thay đổi Diff Viewer không tính credit.
     */
    public function diffPreview(Request $request, int $id): JsonResponse
    {
        $article = Article::withoutGlobalScopes()->findOrFail($id);
        $workspace = $this->resolveWorkspace($request, $article);

        try {
            $diffDto = $this->refreshService->getDiffPreview($article, $workspace);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu so sánh trước/sau thành công.',
                'data' => $diffDto->toArray(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/articles/{id}/apply-optimization
     * Áp dụng bản tối ưu vào bài viết, lưu phiên bản cũ (HasVersions).
     * Tuyệt đối không tự xuất bản lên WordPress ở bước này.
     */
    public function applyOptimization(Request $request, int $id): JsonResponse
    {
        $article = Article::withoutGlobalScopes()->findOrFail($id);
        $workspace = $this->resolveWorkspace($request, $article);

        $payload = [
            'proposed_title' => $request->input('proposed_title'),
            'proposed_content' => $request->input('proposed_content'),
            'proposed_seo_title' => $request->input('proposed_seo_title'),
            'proposed_seo_description' => $request->input('proposed_seo_description'),
            'insight_id' => $request->input('insight_id'),
        ];

        try {
            $updatedArticle = $this->refreshService->applyOptimization(
                article: $article,
                workspace: $workspace,
                payload: $payload,
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã áp dụng bản tối ưu vào bài viết. Bản sao lưu phiên bản cũ đã được tạo an toàn.',
                'data' => [
                    'article' => $updatedArticle,
                    'latest_version' => $updatedArticle->latestVersion,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/articles/{id}/push-update-wordpress
     * Cổng Human-in-the-Loop cập nhật bài viết lên WordPress sau khi người dùng phê duyệt.
     */
    public function pushUpdateWordPress(Request $request, int $id): JsonResponse
    {
        $article = Article::withoutGlobalScopes()->findOrFail($id);
        $workspace = $this->resolveWorkspace($request, $article);

        $hasConfirmed = $request->boolean('confirm', true);
        if ($request->has('confirm') && ! $hasConfirmed) {
            return response()->json([
                'success' => false,
                'message' => 'Cần sự xác nhận của người dùng trước khi cập nhật bài viết lên WordPress.',
            ], 422);
        }

        $options = [
            'confirm' => true,
            'publish_status' => $request->input('publish_status', 'publish'),
            'insight_id' => $request->input('insight_id'),
        ];

        try {
            $result = $this->refreshService->pushUpdateToWordPress(
                article: $article,
                workspace: $workspace,
                options: $options,
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật bài viết lên WordPress thành công.',
                'data' => $result,
            ]);
        } catch (WordPressAuthException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xác thực tài khoản WordPress: '.$e->getMessage(),
            ], 401);
        } catch (WordPressValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu bài viết không hợp lệ trên WordPress: '.$e->getMessage(),
                'errors' => $e->getErrors(),
            ], 422);
        } catch (WordPressPublishException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi cập nhật bài viết WordPress: '.$e->getMessage(),
            ], 502);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ không mong muốn: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve workspace and enforce tenant isolation.
     */
    protected function resolveWorkspace(Request $request, Article $article): Workspace
    {
        $user = $request->user();

        $headerWs = $request->header('X-Workspace-Id') ?: $request->query('workspace_id');
        $workspaceId = $headerWs ? (int) $headerWs : (int) ($user?->default_workspace_id ?? 0);

        if ($workspaceId > 0) {
            $workspace = Workspace::findOrFail($workspaceId);
        } elseif ($article->workspace_id) {
            $workspace = Workspace::findOrFail($article->workspace_id);
        } else {
            $workspace = $user?->workspaces()->first() ?? Workspace::firstOrFail();
        }

        // Kiểm tra quyền sở hữu Tenant / Workspace
        if ($article->workspace_id && (int) $article->workspace_id !== (int) $workspace->id) {
            abort(403, 'Bạn không có quyền thao tác trên bài viết của workspace khác.');
        }

        if ($user && $article->tenant_id && (int) $article->tenant_id !== (int) $user->tenant_id && ! $user->is_platform_admin) {
            abort(403, 'Bạn không có quyền thao tác trên bài viết của tổ chức khác.');
        }

        if ($user && $article->workspace_id && ! $user->is_platform_admin) {
            $isMember = $user->workspaces()->where('workspaces.id', $article->workspace_id)->exists();
            if (! $isMember) {
                abort(403, 'Bạn không có quyền thao tác trên bài viết của workspace khác.');
            }
        }

        return $workspace;
    }
}
