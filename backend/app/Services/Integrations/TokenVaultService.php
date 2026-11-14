<?php

namespace App\Services\Integrations;

use App\Models\GoogleAccount;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TokenVaultService
{
    /**
     * Encrypt a token string with AES-256.
     */
    public function encryptToken(string $token): string
    {
        return Crypt::encryptString($token);
    }

    /**
     * Decrypt an encrypted token payload.
     *
     * @throws DecryptException
     */
    public function decryptToken(string $payload): string
    {
        try {
            return Crypt::decryptString($payload);
        } catch (DecryptException $e) {
            Log::error('[TokenVaultService] Failed to decrypt token payload', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Store or update encrypted tokens on a GoogleAccount instance.
     */
    public function storeTokens(
        GoogleAccount $account,
        string $accessToken,
        ?string $refreshToken = null,
        ?DateTimeInterface $expiresAt = null
    ): GoogleAccount {
        $account->access_token = $accessToken;

        if ($refreshToken !== null) {
            $account->refresh_token = $refreshToken;
        }

        if ($expiresAt !== null) {
            $account->token_expires_at = Carbon::instance($expiresAt);
        }

        $account->status = 'active';
        $account->save();

        return $account;
    }

    /**
     * Get decrypted access token from a GoogleAccount model.
     * Since GoogleAccount casts 'access_token' => 'encrypted', accessing the property
     * automatically decrypts it via Eloquent. This method verifies non-emptiness.
     */
    public function getDecryptedAccessToken(GoogleAccount $account): string
    {
        $token = $account->access_token;

        if (empty($token)) {
            throw new \RuntimeException("GoogleAccount #{$account->id} has no valid access token.");
        }

        return $token;
    }

    /**
     * Get decrypted refresh token from a GoogleAccount model.
     */
    public function getDecryptedRefreshToken(GoogleAccount $account): ?string
    {
        return $account->refresh_token ?: null;
    }

    /**
     * Check if account token has expired.
     */
    public function isTokenExpired(GoogleAccount $account): bool
    {
        return $account->isExpired();
    }

    /**
     * Check if account token is expiring within the given threshold (default 300s).
     */
    public function isTokenExpiringSoon(GoogleAccount $account, int $thresholdSeconds = 300): bool
    {
        return $account->isExpiringSoon($thresholdSeconds);
    }
}
