<?php

namespace App\Http\Controllers\Api\V1\Chatbot;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\Chatbot\ActionApprovalService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ActionApprovalController extends Controller
{
    public function __construct(
        protected ActionApprovalService $approvalService
    ) {}

    /**
     * POST /api/v1/chatbot/actions/{uuid}/approve
     * Phê duyệt hành động nhạy cảm (Human-in-the-Loop Gate).
     */
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $actor = $request->user();

        if (! $actor) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa xác thực người dùng.',
            ], 401);
        }

        try {
            $result = $this->approvalService->approveAction($uuid, $workspace, $actor);

            return response()->json([
                'success' => true,
                'message' => 'Hành động đã được phê duyệt và kích hoạt thành công.',
                'data' => $result,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu xác nhận trong Không gian làm việc này.',
            ], 404);
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'INSUFFICIENT_CREDITS',
            ], 402);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Action approve error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ trong quá trình phê duyệt hành động.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/actions/{uuid}/reject
     * Từ chối / Hủy bỏ hành động chờ duyệt (Zero Loss).
     */
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $actor = $request->user();

        if (! $actor) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa xác thực người dùng.',
            ], 401);
        }

        $reason = $request->input('reason');

        try {
            $result = $this->approvalService->rejectAction(
                action: $uuid,
                workspace: $workspace,
                actor: $actor,
                reason: $reason ? (string) $reason : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy bỏ hành động thành công.',
                'data' => $result,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu xác nhận trong Không gian làm việc này.',
            ], 404);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Action reject error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ trong quá trình từ chối hành động.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/chatbot/actions/{uuid}
     * Lấy chi tiết hành động chờ duyệt.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        try {
            $details = $this->approvalService->getActionDetails($uuid, $workspace);

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết hành động chờ duyệt thành công.',
                'data' => $details,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu xác nhận trong Không gian làm việc này.',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Action show error: '.$e->getMessage(), [
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi lấy chi tiết hành động.',
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
