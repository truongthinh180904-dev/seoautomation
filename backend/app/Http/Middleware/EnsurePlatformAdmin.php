<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
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

        $isSuperAdmin = ($user->role === UserRole::SUPER_ADMIN || $user->role?->value === 'super_admin' || $user->role === 'super_admin');
        if (! $user->is_platform_admin && ! $isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu quyền Quản trị viên hệ thống.',
                'error_code' => 'PLATFORM_ADMIN_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
