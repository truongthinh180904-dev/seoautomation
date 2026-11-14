<?php

namespace App\DTOs\Integrations;

use Carbon\Carbon;

class GoogleAuthDTO
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $googleId,
        public readonly string $email,
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?Carbon $tokenExpiresAt = null,
        public readonly array $scopes = [],
        public readonly string $status = 'active',
        public readonly ?string $avatarUrl = null,
    ) {}

    /**
     * Create DTO from an associative array.
     */
    public static function fromArray(array $data): self
    {
        $tokenExpiresAt = null;
        if (! empty($data['token_expires_at'])) {
            $tokenExpiresAt = $data['token_expires_at'] instanceof Carbon
                ? $data['token_expires_at']
                : Carbon::parse($data['token_expires_at']);
        } elseif (! empty($data['expires_in'])) {
            $tokenExpiresAt = Carbon::now()->addSeconds((int) $data['expires_in']);
        }

        $scopes = $data['scopes'] ?? [];
        if (is_string($scopes)) {
            $scopes = array_filter(explode(' ', $scopes));
        }

        return new self(
            googleId: (string) ($data['google_id'] ?? $data['id'] ?? $data['sub'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            accessToken: (string) ($data['access_token'] ?? ''),
            refreshToken: ! empty($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            tokenExpiresAt: $tokenExpiresAt,
            scopes: array_values($scopes),
            status: (string) ($data['status'] ?? 'active'),
            avatarUrl: ! empty($data['picture']) ? (string) $data['picture'] : ($data['avatar_url'] ?? null),
        );
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'google_id' => $this->googleId,
            'email' => $this->email,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_expires_at' => $this->tokenExpiresAt?->toIso8601String(),
            'scopes' => $this->scopes,
            'status' => $this->status,
            'avatar_url' => $this->avatarUrl,
        ];
    }

    /**
     * Convert DTO to a safe array for API output (secrets stripped).
     */
    public function toSafeArray(): array
    {
        return [
            'google_id' => $this->googleId,
            'email' => $this->email,
            'token_expires_at' => $this->tokenExpiresAt?->toIso8601String(),
            'scopes' => $this->scopes,
            'status' => $this->status,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
