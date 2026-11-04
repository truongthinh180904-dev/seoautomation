<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\TopicCluster;
use App\Models\Workspace;
use App\Services\SEO\TopicClusterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class TopicClusterController extends Controller
{
    public function __construct(
        protected TopicClusterService $clusterService
    ) {}

    /**
     * GET /api/v1/topic-clusters
     * Danh sách Cụm chủ đề theo Workspace và Website.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $websiteId = $request->query('website_id') ? (int) $request->query('website_id') : null;
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        $clusters = $this->clusterService->listClusters($workspace, $websiteId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách Cụm chủ đề thành công.',
            'data' => $clusters,
        ]);
    }

    /**
     * POST /api/v1/topic-clusters
     * Tạo Cụm chủ đề mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:planning,in_progress,completed',
            'pillar_keyword_id' => 'nullable|integer',
            'pillar_article_id' => 'nullable|integer',
            'keyword_ids' => 'nullable|array',
            'keyword_ids.*' => 'integer',
        ], [
            'website_id.required' => 'Vui lòng chọn Website cho Cụm chủ đề.',
            'name.required' => 'Tên Cụm chủ đề là bắt buộc.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        try {
            $cluster = $this->clusterService->createCluster(
                workspace: $workspace,
                websiteId: (int) $request->input('website_id'),
                data: $request->all(),
                actor: $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Tạo Cụm chủ đề thành công.',
                'data' => $cluster,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('TopicCluster store failed: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi tạo Cụm chủ đề: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/topic-clusters/{id}
     * Chi tiết Cụm chủ đề kèm metrics và keywords.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $cluster = TopicCluster::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $detail = $this->clusterService->getClusterDetail($cluster);

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết Cụm chủ đề thành công.',
            'data' => $detail,
        ]);
    }

    /**
     * PUT/PATCH /api/v1/topic-clusters/{id}
     * Cập nhật Cụm chủ đề.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:planning,in_progress,completed',
            'pillar_keyword_id' => 'nullable|integer',
            'pillar_article_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = $this->resolveWorkspace($request);
        $cluster = TopicCluster::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        try {
            $updated = $this->clusterService->updateCluster($cluster, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật Cụm chủ đề thành công.',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật Cụm chủ đề: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/topic-clusters/{id}
     * Xóa Cụm chủ đề (hủy liên kết keywords, không xóa keyword).
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $cluster = TopicCluster::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $this->clusterService->deleteCluster($cluster);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa Cụm chủ đề thành công.',
        ]);
    }

    /**
     * POST /api/v1/topic-clusters/{id}/assign-keywords
     * Gán danh sách từ khóa vào Cụm chủ đề.
     */
    public function assignKeywords(Request $request, int|string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keyword_ids' => 'required|array|min:1',
            'keyword_ids.*' => 'integer',
        ], [
            'keyword_ids.required' => 'Vui lòng cung cấp danh sách ID từ khóa cần gán.',
            'keyword_ids.min' => 'Cần chọn ít nhất một từ khóa.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = $this->resolveWorkspace($request);
        $cluster = TopicCluster::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $assignedCount = $this->clusterService->assignKeywords($cluster, $request->input('keyword_ids', []));

        return response()->json([
            'success' => true,
            'message' => sprintf('Đã gán thành công %d từ khóa vào Cụm chủ đề.', $assignedCount),
            'data' => [
                'cluster' => $cluster->fresh(['keywords']),
                'assigned_count' => $assignedCount,
            ],
        ]);
    }

    /**
     * POST /api/v1/topic-clusters/{id}/remove-keyword
     * Gỡ một từ khóa khỏi Cụm chủ đề.
     */
    public function removeKeyword(Request $request, int|string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keyword_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Thiếu ID từ khóa cần gỡ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = $this->resolveWorkspace($request);
        $cluster = TopicCluster::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $this->clusterService->removeKeyword($cluster, (int) $request->input('keyword_id'));

        return response()->json([
            'success' => true,
            'message' => 'Đã gỡ từ khóa khỏi Cụm chủ đề thành công.',
            'data' => $cluster->fresh(['keywords']),
        ]);
    }

    /**
     * POST /api/v1/topic-clusters/generate-ai
     * Sinh gợi ý các Cụm chủ đề SEO bằng AI.
     */
    public function generateAi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
            'seed_topic' => 'required|string|max:255',
        ], [
            'website_id.required' => 'Vui lòng chọn Website.',
            'seed_topic.required' => 'Vui lòng nhập chủ đề gốc (Seed Topic).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        try {
            $result = $this->clusterService->generateAiClusters(
                workspace: $workspace,
                websiteId: (int) $request->input('website_id'),
                seedTopic: (string) $request->input('seed_topic'),
                options: $request->all(),
                actor: $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Gợi ý Cụm chủ đề bằng AI thành công.',
                'data' => $result,
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Số dư credit không đủ để sinh gợi ý AI.',
                'error_code' => 'INSUFFICIENT_CREDITS',
            ], 402);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Generate AI Clusters failed: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi sinh Cụm chủ đề: '.$e->getMessage(),
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
