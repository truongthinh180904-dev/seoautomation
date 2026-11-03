<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'app_password',
        'token',
        'plainTextToken',
        'secret',
        'api_key',
        'apiKey',
        'remember_token',
        'authorization',
        'access_token',
    ];

    public function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $notes = null,
        ?int $workspaceId = null,
        ?User $actor = null
    ): AuditLog {
        $user = $actor ?? (auth()->check() ? auth()->user() : null);

        $resolvedWorkspaceId = $workspaceId;
        if ($resolvedWorkspaceId === null) {
            if ($auditable !== null && isset($auditable->workspace_id) && $auditable->workspace_id) {
                $resolvedWorkspaceId = (int) $auditable->workspace_id;
            } elseif ($user !== null && isset($user->default_workspace_id) && $user->default_workspace_id) {
                $resolvedWorkspaceId = (int) $user->default_workspace_id;
            } elseif (app()->bound('current_workspace_id')) {
                $boundId = app('current_workspace_id');
                if (is_numeric($boundId)) {
                    $resolvedWorkspaceId = (int) $boundId;
                }
            }
        }

        $actorRole = null;
        if ($user !== null) {
            $actorRole = is_object($user->role) ? $user->role->value : (string) $user->role;
        }

        $request = request();
        $ip = $request ? $request->ip() : null;
        $userAgent = $request ? substr((string) $request->userAgent(), 0, 500) : null;

        return AuditLog::create([
            'workspace_id' => $resolvedWorkspaceId,
            'actor_id' => $user?->id,
            'actor_role' => $actorRole,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    public function sanitizeValues(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($lowerKey, strtolower($sensitiveKey))) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeValues($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
