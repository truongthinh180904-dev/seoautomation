<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\WorkspaceStatus;
use App\Models\Workspace;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa xác thực danh tính.',
            ], 401);
        }

        // 1. Identify requested workspace ID
        $explicitWorkspaceId = $request->header('X-Workspace-Id') ?: $request->query('workspace_id');
        $workspace = null;

        if ($explicitWorkspaceId !== null && $explicitWorkspaceId !== '') {
            $workspace = Workspace::find((int) $explicitWorkspaceId);
            if (! $workspace) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy Không gian làm việc (Workspace) hợp lệ.',
                    'error_code' => 'WORKSPACE_NOT_FOUND',
                ], 404);
            }
        } else {
            if ($user->default_workspace_id) {
                $workspace = Workspace::find((int) $user->default_workspace_id);
            }

            if (! $workspace) {
                $workspace = $user->workspaces()->wherePivot('is_active', true)->first();
            }
        }

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Không gian làm việc (Workspace) hợp lệ.',
                'error_code' => 'WORKSPACE_NOT_FOUND',
            ], 404);
        }

        $isSuperAdmin = ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN);

        // 2. Check workspace status (e.g. suspended)
        if ($workspace->status === WorkspaceStatus::SUSPENDED && ! $isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Không gian làm việc này đã bị tạm ngưng.',
                'error_code' => 'WORKSPACE_SUSPENDED',
            ], 403);
        }

        // 3. Check access rights
        if (! $isSuperAdmin) {
            $isMember = $workspace->members()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();

            if (! $isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập Không gian làm việc này.',
                    'error_code' => 'FORBIDDEN_WORKSPACE_ACCESS',
                ], 403);
            }
        }

        // 4. Bind context for request and tenant bridge
        $request->attributes->set('current_workspace', $workspace);
        app()->instance('current_workspace_id', $workspace->id);
        BelongsToWorkspace::setExecutionContext($workspace->id);

        if ($workspace->legacy_tenant_id) {
            $user->tenant_id = $workspace->legacy_tenant_id;
            app()->instance('current_tenant_id', $workspace->legacy_tenant_id);
            BelongsToTenant::setExecutionContext($workspace->legacy_tenant_id);
        }

        return $next($request);
    }
}
