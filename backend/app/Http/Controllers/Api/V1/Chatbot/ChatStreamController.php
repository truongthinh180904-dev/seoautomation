<?php

namespace App\Http\Controllers\Api\V1\Chatbot;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\Queue\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatStreamController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * GET /api/v1/chatbot/tasks/{uuid}/status
     * Polling endpoint lấy thông tin trạng thái và tiến độ thời gian thực của tác vụ qua UUID.
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Task|null $task */
        $task = Task::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->with(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name'])
            ->first();

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tác vụ hoặc tác vụ không thuộc Không gian làm việc này.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin tiến độ tác vụ thành công.',
            'data' => $this->formatTaskData($task),
        ]);
    }

    /**
     * GET /api/v1/chatbot/tasks/{uuid}/stream
     * Server-Sent Events (SSE) stream endpoint đẩy sự kiện tiến độ thời gian thực (0% -> 100%).
     */
    public function stream(Request $request, string $uuid): StreamedResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Task|null $task */
        $task = Task::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->with(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name'])
            ->first();

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tác vụ hoặc tác vụ không thuộc Không gian làm việc này.',
            ], 404);
        }

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($workspace, $uuid, $request) {
            if (! app()->runningUnitTests() && ! app()->environment('testing')) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
            }

            /** @var Task|null $currentTask */
            $currentTask = Task::where('workspace_id', $workspace->id)
                ->where('uuid', $uuid)
                ->with(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name'])
                ->first();

            if (! $currentTask) {
                echo "event: error\n";
                echo 'data: '.json_encode(['message' => 'Task not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (! app()->runningUnitTests() && ! app()->environment('testing') && function_exists('flush')) {
                    @flush();
                }

                return;
            }

            $statusValue = $currentTask->status instanceof TaskStatus ? $currentTask->status->value : (string) $currentTask->status;
            $event = match ($statusValue) {
                TaskStatus::SUCCESS->value, 'success' => 'complete',
                TaskStatus::FAILED->value, 'failed' => 'error',
                TaskStatus::CANCELLED->value, 'cancelled' => 'cancelled',
                default => 'progress',
            };

            echo "event: {$event}\n";
            echo 'data: '.json_encode($this->formatTaskData($currentTask), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
            if (! app()->runningUnitTests() && ! app()->environment('testing') && function_exists('flush')) {
                @flush();
            }

            // Trong môi trường testing hoặc tham số once=1 hoặc tác vụ đã kết thúc: trả về 1 frame và dừng
            if (
                app()->runningUnitTests() ||
                app()->environment('testing') ||
                $request->boolean('once') ||
                in_array($statusValue, ['success', 'failed', 'cancelled'], true)
            ) {
                return;
            }

            // Vòng lặp stream trực tiếp cho kết nối SSE thực tế
            $maxIterations = 60;
            $iteration = 0;

            while ($iteration < $maxIterations) {
                if (connection_aborted()) {
                    break;
                }

                usleep(500000); // 0.5s
                $iteration++;

                $currentTask = Task::where('workspace_id', $workspace->id)
                    ->where('uuid', $uuid)
                    ->with(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name'])
                    ->first();

                if (! $currentTask) {
                    break;
                }

                $statusValue = $currentTask->status instanceof TaskStatus ? $currentTask->status->value : (string) $currentTask->status;
                $event = match ($statusValue) {
                    TaskStatus::SUCCESS->value, 'success' => 'complete',
                    TaskStatus::FAILED->value, 'failed' => 'error',
                    TaskStatus::CANCELLED->value, 'cancelled' => 'cancelled',
                    default => 'progress',
                };

                echo "event: {$event}\n";
                echo 'data: '.json_encode($this->formatTaskData($currentTask), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (! app()->runningUnitTests() && ! app()->environment('testing') && function_exists('flush')) {
                    @flush();
                }

                if (in_array($statusValue, ['success', 'failed', 'cancelled'], true)) {
                    break;
                }
            }
        }, 200, $headers);
    }

    /**
     * POST /api/v1/chatbot/tasks/{uuid}/resume
     * Tự động tiếp tục hoặc thử lại (Resume / Retry) một tác vụ đã bị sự cố hoặc gián đoạn.
     */
    public function resume(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Task|null $task */
        $task = Task::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tác vụ trong Không gian làm việc hiện tại.',
            ], 404);
        }

        $taskStatusVal = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;

        if (! in_array($task->status, [TaskStatus::FAILED, TaskStatus::CANCELLED], true) && ! in_array($taskStatusVal, ['failed', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể tiếp tục tác vụ đã thất bại hoặc bị hủy.',
            ], 422);
        }

        try {
            $task->update([
                'status' => TaskStatus::RUNNING,
                'current_step' => 'Đang tiếp tục thực thi tác vụ...',
                'error_code' => null,
                'error_message' => null,
                'cancelled_at' => null,
                'started_at' => $task->started_at ?: now(),
                'attempts' => $task->attempts + 1,
            ]);

            $refreshed = $task->fresh(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Đã kích hoạt tiếp tục tác vụ thành công.',
                'data' => $this->formatTaskData($refreshed),
            ]);
        } catch (Throwable $e) {
            Log::error('ChatStreamController resume error: '.$e->getMessage(), [
                'task_uuid' => $uuid,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi khôi phục tác vụ.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/chatbot/tasks/{uuid}/cancel
     * Hủy bỏ an toàn một tác vụ đang chạy và giải phóng credit đã giữ trước nếu có.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        /** @var Task|null $task */
        $task = Task::where('workspace_id', $workspace->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tác vụ trong Không gian làm việc hiện tại.',
            ], 404);
        }

        $taskStatusVal = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;

        if ($task->status === TaskStatus::SUCCESS || $taskStatusVal === 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy tác vụ đã hoàn thành thành công.',
            ], 422);
        }

        if ($task->status === TaskStatus::CANCELLED || $taskStatusVal === 'cancelled') {
            return response()->json([
                'success' => true,
                'message' => 'Tác vụ đã được hủy trước đó.',
                'data' => $this->formatTaskData($task->fresh(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name'])),
            ]);
        }

        try {
            $reason = (string) $request->input('reason', 'Đã hủy từ khung trò chuyện Aura Assistant');
            $cancelledTask = $this->taskService->cancelTask($task, $request->user(), $reason);

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy tác vụ thành công.',
                'data' => $this->formatTaskData($cancelledTask->fresh(['article:id,title,status,slug', 'keyword:id,keyword', 'campaign:id,name'])),
            ]);
        } catch (Throwable $e) {
            Log::error('ChatStreamController cancel error: '.$e->getMessage(), [
                'task_uuid' => $uuid,
                'workspace_id' => $workspace->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi hủy tác vụ.',
            ], 500);
        }
    }

    /**
     * Định dạng dữ liệu Task chuẩn hóa cho frontend Chatbot widget.
     */
    protected function formatTaskData(Task $task): array
    {
        $statusValue = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
        $statusLabel = $task->status instanceof TaskStatus ? $task->status->label() : $statusValue;

        return [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'type' => $task->type,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'progress' => (int) $task->progress,
            'current_step' => $task->current_step ?: 'Đang chuẩn bị...',
            'credits_reserved' => (int) $task->credits_reserved,
            'credits_settled' => (int) $task->credits_settled,
            'attempts' => (int) $task->attempts,
            'max_attempts' => (int) $task->max_attempts,
            'unique_lock_key' => $task->unique_lock_key,
            'error_code' => $task->error_code,
            'error_message' => $task->error_message,
            'payload' => $task->payload ?? [],
            'can_resume' => in_array($task->status, [TaskStatus::FAILED, TaskStatus::CANCELLED], true) || in_array($statusValue, ['failed', 'cancelled'], true),
            'can_retry' => $task->status === TaskStatus::FAILED || $statusValue === 'failed',
            'is_finished' => in_array($task->status, [TaskStatus::SUCCESS, TaskStatus::FAILED, TaskStatus::CANCELLED], true) || in_array($statusValue, ['success', 'failed', 'cancelled'], true),
            'started_at' => $task->started_at?->toIso8601String(),
            'finished_at' => $task->finished_at?->toIso8601String(),
            'cancelled_at' => $task->cancelled_at?->toIso8601String(),
            'article' => $task->article ? [
                'id' => $task->article->id,
                'title' => $task->article->title,
                'status' => $task->article->status instanceof \BackedEnum ? $task->article->status->value : (string) $task->article->status,
                'slug' => $task->article->slug,
            ] : null,
            'keyword' => $task->keyword ? [
                'id' => $task->keyword->id,
                'keyword' => $task->keyword->keyword,
            ] : null,
            'campaign' => $task->campaign ? [
                'id' => $task->campaign->id,
                'name' => $task->campaign->name,
            ] : null,
        ];
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
            throw new ModelNotFoundException('Không tìm thấy Không gian làm việc (Workspace) hợp lệ.');
        }

        return $workspace;
    }
}
