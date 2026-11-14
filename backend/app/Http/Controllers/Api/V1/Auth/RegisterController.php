<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Security\AuditLogService;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
        protected AuditLogService $auditLogService
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ]);

            $workspaceName = $validated['workspace_name'] ?? ($validated['name'].' Workspace');
            $this->workspaceService->createWorkspaceForUser($user, $workspaceName);

            $this->auditLogService->log(
                action: 'auth.registered',
                auditable: $user,
                newValues: ['name' => $user->name, 'email' => $user->email],
                actor: $user
            );

            return $user;
        });

        $user->load(['tenant', 'defaultWorkspace', 'workspaces']);
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký tài khoản thành công.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }
}
