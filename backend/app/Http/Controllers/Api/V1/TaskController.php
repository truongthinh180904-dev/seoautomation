<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Queue\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,running,success,failed,cancelled',
            'type' => 'nullable|string|max:50',
            'campaign_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workspace = $request->attributes->get('current_workspace');
        $workspaceId = $workspace?->id ?: $request->user()->default_workspace_id;

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $tasks = $this->taskService->paginateForWorkspace(
            $workspaceId,
            $request->only(['status', 'type', 'campaign_id']),
            $perPage
        );

        return response()->json($tasks);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $workspace = $request->attributes->get('current_workspace');
        $workspaceId = $workspace?->id ?: $request->user()->default_workspace_id;

        $task = $this->taskService->findForWorkspace($id, $workspaceId);

        if (! $task) {
            abort(404, 'Task not found.');
        }

        return response()->json([
            'task' => $task,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workspace = $request->attributes->get('current_workspace');
        $workspaceId = $workspace?->id ?: $request->user()->default_workspace_id;

        $task = $this->taskService->findForWorkspace($id, $workspaceId);

        if (! $task) {
            abort(404, 'Task not found.');
        }

        $reason = (string) $request->input('reason', 'Cancelled by user');
        $cancelledTask = $this->taskService->cancelTask($task, $request->user(), $reason);

        return response()->json([
            'message' => 'Tác vụ đã được hủy thành công.',
            'task' => $cancelledTask,
        ]);
    }
}
