<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $email = Str::lower((string) $request->email);
        $throttleKey = 'login:'.sha1($request->ip().'|'.$email);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Quá nhiều lần thử đăng nhập thất bại. Vui lòng thử lại sau {$seconds} giây.",
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Thông tin đăng nhập không chính xác',
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị vô hiệu hóa.',
                'error_code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        $user->load(['tenant', 'defaultWorkspace', 'workspaces']);

        $activeWorkspace = $user->defaultWorkspace ?: $user->workspaces()->first();
        if ($activeWorkspace && $activeWorkspace->status === WorkspaceStatus::SUSPENDED && ! $user->is_platform_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Không gian làm việc đã bị tạm ngưng.',
                'error_code' => 'WORKSPACE_SUSPENDED',
            ], 403);
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $this->auditLogService->log(
            action: 'auth.login',
            auditable: $user,
            workspaceId: $activeWorkspace?->id,
            actor: $user
        );

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
