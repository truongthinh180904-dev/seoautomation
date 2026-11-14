<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));
        $user = User::where('email', $email)->first();

        $token = null;

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $this->auditLogService->log(
                action: 'auth.password_reset_requested',
                auditable: $user,
                actor: $user
            );
        }

        $responseData = [
            'success' => true,
            'message' => 'Nếu email tồn tại trong hệ thống, liên kết đặt lại mật khẩu đã được gửi.',
        ];

        // Expose token directly only in the testing environment to facilitate automated tests
        if (app()->environment('testing') && $token !== null) {
            $responseData['reset_token'] = $token;
        }

        return response()->json($responseData);
    }
}
