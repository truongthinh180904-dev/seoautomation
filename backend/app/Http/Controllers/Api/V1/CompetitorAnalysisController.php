<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\SEO\CompetitorAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class CompetitorAnalysisController extends Controller
{
    public function __construct(
        protected CompetitorAnalysisService $competitorAnalysisService
    ) {}

    /**
     * GET/POST /api/v1/competitor/serp-analysis
     * Phân tích chuyên sâu SERP Top 10 đối thủ và trích xuất bảng Benchmark.
     */
    public function serpAnalysis(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required_without:query|nullable|string|max:255',
            'query' => 'required_without:keyword|nullable|string|max:255',
            'language' => 'nullable|string|in:vi,en',
            'country' => 'nullable|string|max:10',
            'limit' => 'nullable|integer|min:1|max:20',
            'crawl' => 'nullable|boolean',
            'force_refresh' => 'nullable|boolean',
        ], [
            'keyword.required_without' => 'Vui lòng cung cấp từ khóa cần phân tích SERP đối thủ.',
            'query.required_without' => 'Vui lòng cung cấp từ khóa cần phân tích SERP đối thủ.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu yêu cầu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        $keyword = (string) ($request->input('keyword') ?: $request->input('query'));
        $options = [
            'language' => $request->input('language', 'vi'),
            'country' => $request->input('country', 'vn'),
            'limit' => (int) $request->input('limit', 10),
            'crawl' => filter_var($request->input('crawl', true), FILTER_VALIDATE_BOOLEAN),
            'force_refresh' => filter_var($request->input('force_refresh', false), FILTER_VALIDATE_BOOLEAN),
        ];

        try {
            $data = $this->competitorAnalysisService->analyzeSerpAndCompetitors(
                workspace: $workspace,
                keyword: $keyword,
                options: $options,
                actor: $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Phân tích SERP đối thủ thành công.',
                'data' => $data,
            ]);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Không đủ credit để thực hiện phân tích đối thủ (Cần %d credit, hiện có %d credit).',
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
                'error_code' => 'INVALID_ARGUMENT',
            ], 422);
        } catch (Throwable $e) {
            Log::error('Competitor SERP analysis failed: '.$e->getMessage(), [
                'exception' => $e,
                'keyword' => $keyword,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi phân tích SERP đối thủ: '.$e->getMessage(),
                'error_code' => 'SERP_ANALYSIS_FAILED',
            ], 500);
        }
    }

    /**
     * Xác định Workspace từ Request context hoặc Auth User.
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
