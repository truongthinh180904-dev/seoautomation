<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));
        $rawToken = $request->validated('token');
        $newPassword = $request->validated('password');

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu đặt lại mật khẩu không tồn tại hoặc đã hết hạn.',
            ], 422);
        }

        // Token validity: 60 minutes
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Mã đặt lại mật khẩu đã hết hạn. Vui lòng gửi lại yêu cầu mới.',
            ], 422);
        }

        if (! Hash::check($rawToken, $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Mã đặt lại mật khẩu không hợp lệ.',
            ], 422);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Người dùng không tồn tại.',
            ], 404);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        // Invalidate old tokens for security
        $user->tokens()->delete();

        // Delete used reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $this->auditLogService->log(
            action: 'auth.password_reset_completed',
            auditable: $user,
            actor: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.',
        ]);
    }
}
