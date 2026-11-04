<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\BulkBatch;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Bulk\BulkGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class BulkGenerationController extends Controller
{
    public function __construct(
        protected ?BulkGenerationService $service = null
    ) {
        $this->service = $this->service ?? app(BulkGenerationService::class);
    }

    /**
     * GET /api/v1/bulk-batches
     * Danh sách các lô bài viết của Workspace hiện tại.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $perPage = min((int) $request->input('per_page', 15), 50);

        $batches = BulkBatch::where('workspace_id', $workspace->id)
            ->with([
                'website:id,name,domain',
                'campaign:id,name',
            ])
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách các lô bài viết thành công.',
            'data' => $batches->items(),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/bulk-batches
     * Khởi tạo lô sản xuất bài viết hàng loạt và giữ trước credit.
     */
    public function store(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'website_id' => 'required|integer',
            'campaign_id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'keywords' => 'required_without:keyword_ids',
            'keyword_ids' => 'required_without:keywords|array',
            'keyword_ids.*' => 'integer',
            'tone' => 'nullable|string|max:50',
            'target_word_count' => 'nullable|integer|min:300|max:10000',
            'include_images' => 'nullable|boolean',
            'auto_publish' => 'nullable|boolean',
            'delay_seconds' => 'nullable|integer|min:0|max:60',
            'simulate_failure_indexes' => 'nullable|array',
        ]);

        /** @var Website|null $website */
        $website = Website::where('workspace_id', $workspace->id)->find($validated['website_id']);
        if (! $website) {
            return response()->json([
                'success' => false,
                'message' => 'Website được chọn không tồn tại hoặc không thuộc Không gian làm việc hiện tại.',
            ], 404);
        }

        try {
            $batch = $this->service->createBatch($workspace, $website, $validated, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Lô bài viết hàng loạt đã được khởi tạo và đưa vào hàng đợi xử lý thành công.',
                'data' => [
                    'id' => $batch->id,
                    'uuid' => $batch->uuid,
                    'name' => $batch->name,
                    'status' => $batch->status,
                    'total_items' => (int) $batch->total_items,
                    'completed_items' => (int) $batch->completed_items,
                    'failed_items' => (int) $batch->failed_items,
                    'credits_reserved' => (int) $batch->credits_reserved,
                    'credits_settled' => (int) $batch->credits_settled,
                    'progress_percent' => $batch->progress_percent,
                    'generation_settings' => $batch->generation_settings,
                    'created_at' => $batch->created_at?->toIso8601String(),
                ],
            ], 201);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Số dư credit không đủ để khởi tạo lô bài viết hàng loạt.',
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
            Log::error('BulkGenerationController store failed', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi khởi tạo lô bài viết: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/bulk-batches/{uuid}
     * Chi tiết lô bài viết.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var BulkBatch|null $batch */
        $batch = BulkBatch::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->with(['website', 'campaign'])
            ->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lô bài viết yêu cầu.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tải chi tiết lô bài viết thành công.',
            'data' => $this->service->getProgress($batch),
        ]);
    }

    /**
     * GET /api/v1/bulk-batches/{uuid}/progress
     * Theo dõi tiến độ thời gian thực của lô bài viết.
     */
    public function progress(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var BulkBatch|null $batch */
        $batch = BulkBatch::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lô bài viết yêu cầu.',
            ], 404);
        }

        $progressData = $this->service->getProgress($batch);

        return response()->json([
            'success' => true,
            'message' => 'Lấy tiến độ lô bài viết thành công.',
            'data' => $progressData,
        ]);
    }

    /**
     * POST /api/v1/bulk-batches/{uuid}/cancel
     * Hủy lô bài viết và giải phóng credit đang tạm giữ.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var BulkBatch|null $batch */
        $batch = BulkBatch::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lô bài viết yêu cầu.',
            ], 404);
        }

        try {
            $reason = $request->input('reason', 'Hủy bởi người dùng');
            $updatedBatch = $this->service->cancelBatch($batch, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Lô bài viết đã được hủy và toàn bộ credit tạm giữ đã được hoàn trả thành công.',
                'data' => $this->service->getProgress($updatedBatch),
            ]);
        } catch (Throwable $e) {
            Log::error('BulkGenerationController cancel failed', [
                'batch_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi hủy lô bài viết: '.$e->getMessage(),
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
