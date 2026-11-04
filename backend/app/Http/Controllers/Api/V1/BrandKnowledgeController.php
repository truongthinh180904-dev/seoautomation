<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandKnowledgeRequest;
use App\Models\Workspace;
use App\Services\Brand\BrandKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class BrandKnowledgeController extends Controller
{
    public function __construct(
        protected BrandKnowledgeService $brandKnowledgeService
    ) {}

    /**
     * GET /api/v1/brand-knowledge
     * Danh sách hồ sơ tri thức thương hiệu của Workspace.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $websiteId = $request->query('website_id') ? (int) $request->query('website_id') : null;
        $filters = [
            'search' => $request->query('search'),
            'tone_of_voice' => $request->query('tone_of_voice'),
        ];

        $items = $this->brandKnowledgeService->listForWebsite($workspace, $websiteId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách hồ sơ tri thức thương hiệu thành công.',
            'data' => $items,
        ]);
    }

    /**
     * POST /api/v1/brand-knowledge
     * Tạo hồ sơ tri thức thương hiệu mới.
     */
    public function store(BrandKnowledgeRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        try {
            $kb = $this->brandKnowledgeService->createBrandKnowledge(
                workspace: $workspace,
                websiteId: (int) $request->input('website_id'),
                data: $request->validated(),
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Tạo hồ sơ tri thức thương hiệu thành công.',
                'data' => $kb,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('BrandKnowledge store failed: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi tạo hồ sơ thương hiệu: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/brand-knowledge/{id}
     * Chi tiết một hồ sơ tri thức thương hiệu.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $kb = $this->brandKnowledgeService->getById($workspace, (int) $id);

        if (! $kb) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hồ sơ tri thức thương hiệu.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết hồ sơ thương hiệu thành công.',
            'data' => $kb,
        ]);
    }

    /**
     * PUT /api/v1/brand-knowledge/{id}
     * Cập nhật hồ sơ tri thức thương hiệu.
     */
    public function update(BrandKnowledgeRequest $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $kb = $this->brandKnowledgeService->getById($workspace, (int) $id);

        if (! $kb) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hồ sơ tri thức thương hiệu để cập nhật.',
            ], 404);
        }

        try {
            $updated = $this->brandKnowledgeService->updateBrandKnowledge(
                kb: $kb,
                data: $request->validated(),
                actor: $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật hồ sơ tri thức thương hiệu thành công.',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            Log::error('BrandKnowledge update failed: '.$e->getMessage(), [
                'exception' => $e,
                'id' => $id,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi cập nhật hồ sơ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/brand-knowledge/{id}
     * Xóa một hồ sơ tri thức thương hiệu.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $kb = $this->brandKnowledgeService->getById($workspace, (int) $id);

        if (! $kb) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hồ sơ tri thức thương hiệu để xóa.',
            ], 404);
        }

        try {
            $this->brandKnowledgeService->deleteBrandKnowledge($kb);

            return response()->json([
                'success' => true,
                'message' => 'Xóa hồ sơ tri thức thương hiệu thành công.',
            ]);
        } catch (Throwable $e) {
            Log::error('BrandKnowledge delete failed: '.$e->getMessage(), [
                'exception' => $e,
                'id' => $id,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi xóa hồ sơ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/brand-knowledge/{id}/set-default
     * Đặt hồ sơ làm mặc định cho Website.
     */
    public function setDefault(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $kb = $this->brandKnowledgeService->getById($workspace, (int) $id);

        if (! $kb) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hồ sơ tri thức thương hiệu.',
            ], 404);
        }

        try {
            $updated = $this->brandKnowledgeService->setDefault($kb);

            return response()->json([
                'success' => true,
                'message' => 'Đã đặt hồ sơ này làm mặc định thành công.',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            Log::error('BrandKnowledge setDefault failed: '.$e->getMessage(), [
                'exception' => $e,
                'id' => $id,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi đặt làm mặc định: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/brand-knowledge/inject-context
     * Sinh văn bản context hoặc inject vào Prompt sẵn có.
     */
    public function injectContext(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
            'brand_knowledge_id' => 'nullable|integer',
            'prompt' => 'nullable|string',
        ], [
            'website_id.required' => 'Mã định danh Website là bắt buộc.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $websiteId = (int) $request->input('website_id');
        $brandKnowledgeId = $request->input('brand_knowledge_id') ? (int) $request->input('brand_knowledge_id') : null;
        $prompt = $request->input('prompt');

        $contextText = $this->brandKnowledgeService->buildPromptContext($websiteId, $brandKnowledgeId);

        $finalPrompt = null;
        if (! empty($prompt)) {
            $finalPrompt = $this->brandKnowledgeService->injectIntoPrompt(
                prompt: $prompt,
                website: $websiteId,
                brandKnowledgeId: $brandKnowledgeId
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Tạo ngữ cảnh thương hiệu thành công.',
            'data' => [
                'context_text' => $contextText,
                'injected_prompt' => $finalPrompt ?? $contextText,
            ],
        ]);
    }

    /**
     * POST /api/v1/brand-knowledge/validate-taboo
     * Kiểm tra nội dung văn bản xem có chứa từ cấm kỵ (Taboo Words) của thương hiệu không.
     */
    public function validateTaboo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
            'content' => 'required|string',
            'brand_knowledge_id' => 'nullable|integer',
        ], [
            'website_id.required' => 'Mã định danh Website là bắt buộc.',
            'content.required' => 'Nội dung cần kiểm tra là bắt buộc.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $websiteId = (int) $request->input('website_id');
        $content = (string) $request->input('content');
        $brandKnowledgeId = $request->input('brand_knowledge_id') ? (int) $request->input('brand_knowledge_id') : null;

        $audit = $this->brandKnowledgeService->validateContentAgainstTaboo($content, $websiteId, $brandKnowledgeId);

        return response()->json([
            'success' => true,
            'message' => $audit['clean'] ? 'Nội dung an toàn, không chứa từ cấm kỵ.' : 'Phát hiện từ ngữ cấm kỵ trong nội dung.',
            'data' => $audit,
        ]);
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
