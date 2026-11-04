<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Media\AiImageService;
use App\Services\SEO\InternalLinkingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class InternalLinkController extends Controller
{
    public function __construct(
        protected InternalLinkingEngine $linkEngine,
        protected AiImageService $aiImageService,
    ) {}

    /**
     * GET /api/v1/internal-links
     * Danh sách đề xuất liên kết nội bộ theo Workspace.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $websiteId = $request->query('website_id') ? (int) $request->query('website_id') : null;
        $articleId = $request->query('article_id') ? (int) $request->query('article_id') : null;
        $status = $request->query('status');
        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = InternalLinkSuggestion::query()
            ->where('workspace_id', $workspace->id)
            ->when($websiteId, fn ($q) => $q->where('website_id', $websiteId))
            ->when($articleId, fn ($q) => $q->forArticle($articleId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with([
                'sourceArticle:id,title,slug,focus_keyword,status',
                'targetArticle:id,title,slug,focus_keyword,status',
            ])
            ->latest();

        $suggestions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách đề xuất liên kết thành công.',
            'data' => $suggestions,
        ]);
    }

    /**
     * POST /api/v1/internal-links/scan
     * Quét cơ hội liên kết nội bộ cho 1 bài viết hoặc toàn bộ website.
     */
    public function scan(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'website_id' => 'required_without:article_id|nullable|integer',
            'article_id' => 'nullable|integer',
        ], [
            'website_id.required_without' => 'Vui lòng cung cấp website_id hoặc article_id để thực hiện quét.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->filled('article_id')) {
                $article = Article::query()
                    ->where('workspace_id', $workspace->id)
                    ->findOrFail((int) $request->article_id);

                $suggestions = $this->linkEngine->scanArticle($article);

                return response()->json([
                    'success' => true,
                    'message' => 'Quét cơ hội liên kết bài viết thành công.',
                    'data' => [
                        'article_id' => $article->id,
                        'suggestions_count' => count($suggestions),
                        'suggestions' => $suggestions,
                    ],
                ]);
            }

            $website = Website::query()
                ->where('workspace_id', $workspace->id)
                ->findOrFail((int) $request->website_id);

            $result = $this->linkEngine->scanWebsite($website->id, $workspace->id);

            return response()->json([
                'success' => true,
                'message' => 'Quét cơ hội liên kết website thành công.',
                'data' => $result,
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 402);
        } catch (Throwable $e) {
            Log::error('Scan internal links failed: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi quét liên kết nội bộ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/internal-links/{id}/accept
     * Chấp nhận đề xuất liên kết.
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $suggestion = InternalLinkSuggestion::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($id);

        $suggestion->update(['status' => 'accepted']);

        return response()->json([
            'success' => true,
            'message' => 'Đã chấp nhận đề xuất liên kết.',
            'data' => $suggestion,
        ]);
    }

    /**
     * POST /api/v1/internal-links/{id}/reject
     * Từ chối đề xuất liên kết.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $suggestion = InternalLinkSuggestion::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($id);

        $suggestion->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối đề xuất liên kết.',
            'data' => $suggestion,
        ]);
    }

    /**
     * POST /api/v1/internal-links/{id}/apply
     * Áp dụng chèn liên kết vào nội dung HTML của bài viết nguồn.
     */
    public function apply(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $suggestion = InternalLinkSuggestion::query()
            ->where('workspace_id', $workspace->id)
            ->with(['sourceArticle', 'targetArticle'])
            ->findOrFail($id);

        try {
            $applied = $this->linkEngine->applySuggestion($suggestion);

            if (! $applied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể áp dụng đề xuất liên kết do thiếu dữ liệu bài viết.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã áp dụng và chèn liên kết vào bài viết thành công.',
                'data' => $suggestion->fresh(['sourceArticle', 'targetArticle']),
            ]);
        } catch (Throwable $e) {
            Log::error('Apply internal link failed: '.$e->getMessage(), [
                'exception' => $e,
                'suggestion_id' => $id,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi áp dụng liên kết: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/internal-links/graph
     * Lấy dữ liệu đồ thị mạng lưới liên kết nội bộ theo Website.
     */
    public function graph(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'website_id là bắt buộc.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $website = Website::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $request->website_id);

        $graph = $this->linkEngine->getLinkGraph($website->id);

        return response()->json([
            'success' => true,
            'message' => 'Lấy dữ liệu đồ thị liên kết thành công.',
            'data' => $graph,
        ]);
    }

    /**
     * GET /api/v1/internal-links/orphans
     * Lấy danh sách bài viết mồ côi (chưa có liên kết trỏ về) trong Website.
     */
    public function orphans(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'website_id là bắt buộc.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $website = Website::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $request->website_id);

        $orphans = $this->linkEngine->getOrphanArticles($website->id);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách bài viết mồ côi thành công.',
            'data' => $orphans,
            'total' => count($orphans),
        ]);
    }

    /**
     * POST /api/v1/internal-links/generate-image
     * Sinh ảnh AI Imagen 3 theo tiêu đề mục H2.
     */
    public function generateImage(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer',
            'heading' => 'required|string|max:255',
            'prompt' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu tạo ảnh không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $article = Article::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $request->article_id);

        try {
            $asset = $this->aiImageService->generateImageForHeading(
                $article,
                (string) $request->heading,
                $request->filled('prompt') ? (string) $request->prompt : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Tạo ảnh AI theo tiêu đề H2 thành công.',
                'data' => $asset,
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 402);
        } catch (Throwable $e) {
            Log::error('Generate AI Image for H2 failed: '.$e->getMessage(), [
                'exception' => $e,
                'article_id' => $article->id,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo ảnh AI: '.$e->getMessage(),
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
