<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Workspace;
use App\Services\SEO\SeoScoringEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeoScoringController extends Controller
{
    public function __construct(
        protected ?SeoScoringEngine $scoringEngine = null
    ) {}

    /**
     * Resolve SeoScoringEngine (hỗ trợ dynamic mocking qua container trong testing).
     */
    protected function engine(): SeoScoringEngine
    {
        return app()->bound(SeoScoringEngine::class)
            ? app(SeoScoringEngine::class)
            : ($this->scoringEngine ?? app(SeoScoringEngine::class));
    }

    /**
     * POST /api/v1/articles/{id}/score-audit
     * Kiểm toán điểm chất lượng và 9 tiêu chí SEO cho bài viết trong Workspace.
     * Thuật toán thuần Deterministic PHP, thực thi < 50ms, hoàn toàn 0 Credit.
     */
    public function audit(Request $request, int $id): JsonResponse
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

        $validated = $request->validate([
            'content' => 'nullable|string',
            'title' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:500',
            'seo_description' => 'nullable|string|max:1000',
            'focus_keyword' => 'nullable|string|max:255',
            'faqs' => 'nullable|array',
            'internal_links' => 'nullable|array',
            'persist' => 'nullable|boolean',
        ]);

        try {
            $persist = $request->boolean('persist', true);

            $result = $this->engine()->auditArticle(
                article: $article,
                overrides: $validated,
                persist: $persist
            );

            return response()->json([
                'success' => true,
                'message' => 'Kiểm toán điểm SEO bài viết thành công.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('SeoScoring audit failed', [
                'workspace_id' => $workspace->id,
                'article_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm toán SEO bài viết: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/articles/score-audit
     * Kiểm toán tức thời dữ liệu thô (Real-time live audit) trước khi lưu bài viết.
     */
    public function auditLive(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:500',
            'seo_description' => 'nullable|string|max:1000',
            'focus_keyword' => 'nullable|string|max:255',
            'faqs' => 'nullable|array',
            'internal_links' => 'nullable|array',
            'extra' => 'nullable|array',
        ]);

        try {
            $extra = $validated['extra'] ?? [];
            if (! empty($validated['faqs'])) {
                $extra['faqs'] = $validated['faqs'];
            }
            if (! empty($validated['internal_links'])) {
                $extra['internal_links'] = $validated['internal_links'];
            }

            $result = $this->engine()->audit(
                content: $validated['content'],
                title: $validated['title'] ?? null,
                seoTitle: $validated['seo_title'] ?? null,
                seoDescription: $validated['seo_description'] ?? null,
                keyword: $validated['focus_keyword'] ?? null,
                extra: $extra
            );

            return response()->json([
                'success' => true,
                'message' => 'Kiểm toán SEO trực tiếp thành công.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('SeoScoring auditLive failed', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm toán SEO: '.$e->getMessage(),
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
