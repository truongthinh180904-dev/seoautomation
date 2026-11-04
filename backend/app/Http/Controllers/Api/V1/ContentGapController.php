<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\ContentGap;
use App\Models\Workspace;
use App\Services\SEO\ContentGapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class ContentGapController extends Controller
{
    public function __construct(
        protected ContentGapService $contentGapService
    ) {}

    /**
     * GET /api/v1/competitor/content-gaps
     * Danh sách khoảng trống nội dung đã phân tích.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $websiteId = $request->query('website_id') ? (int) $request->query('website_id') : null;
        $filters = [
            'competitor_domain' => $request->query('competitor_domain'),
            'status' => $request->query('status'),
            'min_opportunity' => $request->query('min_opportunity'),
        ];

        $gaps = $this->contentGapService->listGaps($workspace, $websiteId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách khoảng trống nội dung thành công.',
            'data' => $gaps,
        ]);
    }

    /**
     * POST /api/v1/competitor/content-gaps/analyze
     * Kích hoạt phân tích khoảng trống nội dung với đối thủ cạnh tranh.
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'website_id' => 'required|integer',
            'competitor_domain' => 'required|string|max:255',
            'campaign_id' => 'nullable|integer',
        ], [
            'website_id.required' => 'Vui lòng chọn Website cần đối chiếu.',
            'competitor_domain.required' => 'Vui lòng cung cấp tên miền đối thủ (VD: competitor.com).',
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
            $result = $this->contentGapService->analyzeGap(
                workspace: $workspace,
                websiteId: (int) $request->input('website_id'),
                competitorDomain: (string) $request->input('competitor_domain'),
                options: $request->all(),
                actor: $user
            );

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Phân tích thành công! Phát hiện %d khoảng trống nội dung từ đối thủ %s.',
                    $result['total_gaps'],
                    $result['competitor_domain']
                ),
                'data' => $result,
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Không đủ credit để phân tích khoảng trống đối thủ (Cần %d credit, hiện có %d credit).',
                    $e->requiredCredits,
                    $e->availableCredits
                ),
                'error_code' => 'INSUFFICIENT_CREDITS',
                'required_credits' => $e->requiredCredits,
                'available_credits' => $e->availableCredits,
            ], 402);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Content Gap analysis failed: '.$e->getMessage(), [
                'exception' => $e,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi phân tích khoảng trống: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/competitor/content-gaps/{id}/target
     * Chuyển một khoảng trống nội dung thành Từ khóa mục tiêu (Keyword).
     */
    public function target(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $gap = ContentGap::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $campaignId = $request->input('campaign_id') ? (int) $request->input('campaign_id') : null;

        try {
            $keyword = $this->contentGapService->targetGap($gap, $campaignId);

            return response()->json([
                'success' => true,
                'message' => sprintf('Đã thêm từ khóa "%s" vào danh sách mục tiêu thành công.', $keyword->keyword),
                'data' => [
                    'gap' => $gap->fresh(),
                    'keyword' => $keyword,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi chuyển đổi khoảng trống thành từ khóa: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/competitor/content-gaps/{id}/ignore
     * Đánh dấu bỏ qua khoảng trống nội dung.
     */
    public function ignore(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $gap = ContentGap::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $this->contentGapService->ignoreGap($gap);

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu bỏ qua khoảng trống nội dung.',
            'data' => $gap->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/competitor/content-gaps/{id}
     * Xóa một bản ghi khoảng trống nội dung.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $gap = ContentGap::where('workspace_id', $workspace->id)->findOrFail((int) $id);

        $this->contentGapService->deleteGap($gap);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bản ghi khoảng trống nội dung thành công.',
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
