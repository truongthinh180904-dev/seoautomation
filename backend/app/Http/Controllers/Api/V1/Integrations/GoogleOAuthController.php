<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Models\GoogleAccount;
use App\Services\Integrations\GoogleOAuthService;
use App\Traits\BelongsToWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    public function __construct(
        protected GoogleOAuthService $googleOAuthService
    ) {}

    /**
     * Generate Google OAuth consent URL and store state parameter.
     */
    public function authUrl(Request $request): JsonResponse
    {
        $workspaceId = BelongsToWorkspace::resolveCurrentWorkspaceId();

        if (! $workspaceId) {
            return response()->json(['message' => 'No active workspace selected.'], 400);
        }

        $user = $request->user();
        $state = Str::random(40);

        Cache::put("google_oauth_state:{$state}", [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
        ], now()->addMinutes(10));

        $authUrl = $this->googleOAuthService->getAuthUrl($state);

        return response()->json([
            'auth_url' => $authUrl,
            'state' => $state,
        ]);
    }

    /**
     * Handle OAuth callback from Google.
     */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $cacheKey = "google_oauth_state:{$validated['state']}";
        $cached = Cache::get($cacheKey);

        if (! $cached || empty($cached['workspace_id'])) {
            return response()->json([
                'message' => 'Invalid, expired, or missing OAuth state parameter.',
            ], 422);
        }

        Cache::forget($cacheKey);

        $workspaceId = (int) $cached['workspace_id'];
        $userId = (int) $cached['user_id'];

        BelongsToWorkspace::setExecutionContext($workspaceId);

        try {
            $account = $this->googleOAuthService->handleCallback(
                $validated['code'],
                $workspaceId,
                $userId
            );

            return response()->json([
                'message' => 'Google account connected successfully.',
                'account' => [
                    'id' => $account->id,
                    'email' => $account->email,
                    'google_id' => $account->google_id,
                    'scopes' => $account->scopes,
                    'status' => $account->status,
                    'token_expires_at' => $account->token_expires_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to connect Google account: '.$e->getMessage(),
            ], 400);
        } finally {
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    /**
     * Check Google integration status for the active workspace.
     */
    public function status(Request $request): JsonResponse
    {
        $workspaceId = BelongsToWorkspace::resolveCurrentWorkspaceId();

        if (! $workspaceId) {
            return response()->json(['message' => 'No active workspace selected.'], 400);
        }

        $status = $this->googleOAuthService->getConnectionStatus($workspaceId);

        return response()->json($status);
    }

    /**
     * Disconnect Google account.
     */
    public function disconnect(Request $request, int $id): JsonResponse
    {
        $account = GoogleAccount::findOrFail($id);

        $this->googleOAuthService->disconnect($account);

        return response()->json([
            'message' => 'Google account disconnected successfully.',
        ]);
    }
}
