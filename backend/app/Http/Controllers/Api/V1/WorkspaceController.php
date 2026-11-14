<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceMemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AddWorkspaceMemberRequest;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService
    ) {}

    public function index(Request $request)
    {
        $workspaces = $this->workspaceService->getUserWorkspaces($request->user());

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $workspace = $this->workspaceService->createWorkspaceForUser(
            $request->user(),
            $validated['name'],
            $validated['slug'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Tạo Không gian làm việc mới thành công.',
            'data' => new WorkspaceResource($workspace),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::with(['owner:id,name,email', 'creditAccount', 'websites'])->findOrFail($id);

        if (! $request->user()->can('view', $workspace)) {
            abort(403, 'Bạn không có quyền truy cập Không gian làm việc này.');
        }

        return response()->json([
            'success' => true,
            'data' => new WorkspaceResource($workspace),
        ]);
    }

    public function switchWorkspace(Request $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->workspaceService->switchWorkspace($request->user(), $id);

            return response()->json([
                'success' => true,
                'message' => 'Đã chuyển sang không gian làm việc '.$workspace->name,
                'data' => new WorkspaceResource($workspace),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function members(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);

        if (! $request->user()->can('view', $workspace)) {
            abort(403, 'Bạn không có quyền truy cập danh sách thành viên của Không gian làm việc này.');
        }

        $members = $workspace->members()->with('user:id,name,email,avatar_url')->get();

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    public function addMember(AddWorkspaceMemberRequest $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        $currentUser = $request->user();

        $validated = $request->validated();

        $targetUser = User::findOrFail($validated['user_id']);
        $role = isset($validated['role']) ? WorkspaceMemberRole::from($validated['role']) : WorkspaceMemberRole::EDITOR;

        $member = $this->workspaceService->addMember($workspace, $targetUser, $role, $currentUser);

        return response()->json([
            'success' => true,
            'message' => 'Thêm thành viên thành công.',
            'data' => $member,
        ], 201);
    }

    public function removeMember(Request $request, int $id, int $userId): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);

        if (! $request->user()->can('manageMembers', $workspace)) {
            abort(403, 'Bạn không có quyền xóa thành viên khỏi Không gian làm việc này.');
        }

        $targetUser = User::findOrFail($userId);

        try {
            $this->workspaceService->removeMember($workspace, $targetUser, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa thành viên khỏi Không gian làm việc.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
