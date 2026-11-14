<?php

namespace App\Services\Integrations;

use App\Models\GoogleAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService extends AbstractExternalApiClient
{
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    protected const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public const DEFAULT_SCOPES = [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    public function __construct(
        protected TokenVaultService $tokenVault
    ) {}

    /**
     * Get Google OAuth client ID from config.
     */
    public function getClientId(): string
    {
        return (string) config('services.google.client_id', '');
    }

    /**
     * Get Google OAuth client secret from config.
     */
    public function getClientSecret(): string
    {
        return (string) config('services.google.client_secret', '');
    }

    /**
     * Get Google OAuth redirect URI from config.
     */
    public function getRedirectUri(): string
    {
        return (string) config('services.google.redirect_uri', '');
    }

    /**
     * Build Google OAuth 2.0 authorization consent URL.
     *
     * @param  array<int, string>  $additionalScopes
     */
    public function getAuthUrl(string $state, array $additionalScopes = []): string
    {
        $scopes = array_unique(array_merge(self::DEFAULT_SCOPES, $additionalScopes));

        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true',
        ];

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens and store account.
     *
     * @throws \RuntimeException
     */
    public function handleCallback(string $code, int $workspaceId, int $userId): GoogleAccount
    {
        $response = $this->postForm(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri' => $this->getRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            Log::error('[GoogleOAuthService] Token exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            throw new \RuntimeException('Failed to exchange authorization code: '.($response->json('error_description') ?? $response->body()));
        }

        $tokenData = $response->json();
        $accessToken = (string) ($tokenData['access_token'] ?? '');
        $refreshToken = ! empty($tokenData['refresh_token']) ? (string) $tokenData['refresh_token'] : null;
        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : 3600;

        $rawScopes = $tokenData['scope'] ?? self::DEFAULT_SCOPES;
        $grantedScopes = is_string($rawScopes) ? array_values(array_filter(explode(' ', $rawScopes))) : (array) $rawScopes;

        // Fetch user profile from Google UserInfo
        $userInfoResponse = $this->get(self::USERINFO_URL, [], [
            'Authorization' => "Bearer {$accessToken}",
        ]);

        if ($userInfoResponse->failed()) {
            Log::error('[GoogleOAuthService] Failed to fetch Google userinfo', [
                'status' => $userInfoResponse->status(),
            ]);

            throw new \RuntimeException('Failed to fetch Google user profile: '.$userInfoResponse->body());
        }

        $userData = $userInfoResponse->json();
        $googleId = (string) ($userData['sub'] ?? $userData['id'] ?? '');
        $email = (string) ($userData['email'] ?? '');

        if (empty($googleId) || empty($email)) {
            throw new \RuntimeException('Google UserInfo response missing id or email.');
        }

        return DB::transaction(function () use ($workspaceId, $userId, $googleId, $email, $accessToken, $refreshToken, $expiresIn, $grantedScopes) {
            $account = GoogleAccount::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('google_id', $googleId)
                ->first();

            if (! $account) {
                $account = new GoogleAccount;
                $account->workspace_id = $workspaceId;
                $account->google_id = $googleId;
            }

            $account->user_id = $userId;
            $account->email = $email;
            $account->access_token = $accessToken;

            if ($refreshToken !== null) {
                $account->refresh_token = $refreshToken;
            }

            $account->token_expires_at = Carbon::now()->addSeconds($expiresIn);
            $account->scopes = $grantedScopes;
            $account->status = 'active';
            $account->save();

            Log::info("[GoogleOAuthService] Successfully connected Google account #{$account->id} for workspace #{$workspaceId}");

            return $account;
        });
    }

    /**
     * Refresh OAuth access token using refresh token.
     *
     * @throws \RuntimeException
     */
    public function refreshToken(GoogleAccount $account): GoogleAccount
    {
        $refreshToken = $account->refresh_token;

        if (empty($refreshToken)) {
            $account->status = 'expired';
            $account->save();

            throw new \RuntimeException("GoogleAccount #{$account->id} cannot be refreshed: no refresh token available.");
        }

        $response = $this->postForm(self::TOKEN_URL, [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::warning("[GoogleOAuthService] Token refresh failed for account #{$account->id}", [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            $account->status = 'expired';
            $account->save();

            throw new \RuntimeException('Failed to refresh Google token: '.($response->json('error_description') ?? $response->body()));
        }

        $tokenData = $response->json();
        $account->access_token = (string) $tokenData['access_token'];

        if (! empty($tokenData['refresh_token'])) {
            $account->refresh_token = (string) $tokenData['refresh_token'];
        }

        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : 3600;
        $account->token_expires_at = Carbon::now()->addSeconds($expiresIn);
        $account->status = 'active';
        $account->save();

        Log::info("[GoogleOAuthService] Refreshed token for Google account #{$account->id}");

        return $account;
    }

    /**
     * Auto-refresh access token if it is expiring soon (within 300 seconds) or already expired.
     */
    public function refreshTokenIfNeeded(GoogleAccount $account): GoogleAccount
    {
        if ($account->isExpiringSoon(300)) {
            return $this->refreshToken($account);
        }

        return $account;
    }

    /**
     * Disconnect Google account and optionally revoke token with Google.
     */
    public function disconnect(GoogleAccount $account): bool
    {
        try {
            $token = $account->access_token ?: $account->refresh_token;
            if (! empty($token)) {
                $this->postForm(self::REVOKE_URL, [
                    'token' => $token,
                ]);
            }
        } catch (\Throwable $e) {
            Log::notice("[GoogleOAuthService] Revoke token call ignored error for account #{$account->id}: {$e->getMessage()}");
        }

        $account->status = 'revoked';
        $account->save();

        return (bool) $account->delete();
    }

    /**
     * Get integration status for a specific workspace.
     */
    public function getConnectionStatus(int $workspaceId): array
    {
        $account = GoogleAccount::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('status', '!=', 'revoked')
            ->latest()
            ->first();

        if (! $account) {
            return [
                'connected' => false,
                'account' => null,
            ];
        }

        return [
            'connected' => $account->isActive(),
            'account' => [
                'id' => $account->id,
                'email' => $account->email,
                'google_id' => $account->google_id,
                'scopes' => $account->scopes,
                'status' => $account->status,
                'is_expired' => $account->isExpired(),
                'token_expires_at' => $account->token_expires_at?->toIso8601String(),
                'created_at' => $account->created_at?->toIso8601String(),
                'updated_at' => $account->updated_at?->toIso8601String(),
            ],
        ];
    }
}
