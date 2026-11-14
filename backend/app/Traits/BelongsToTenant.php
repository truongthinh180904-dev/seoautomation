<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof User) {
            return;
        }

        $tenantId = BelongsToTenant::resolveCurrentTenantId();

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}

trait BelongsToTenant
{
    protected static ?int $executionContextTenantId = null;

    protected static bool $resolvingTenantAuth = false;

    public static function setExecutionContext(?int $tenantId): void
    {
        static::$executionContextTenantId = $tenantId;
    }

    public static function getExecutionContext(): ?int
    {
        return static::$executionContextTenantId;
    }

    public static function resolveCurrentTenantId(): ?int
    {
        if (static::$executionContextTenantId !== null) {
            return static::$executionContextTenantId;
        }

        if (app()->bound('current_tenant_id')) {
            $boundId = app('current_tenant_id');
            if (is_numeric($boundId)) {
                return (int) $boundId;
            }
        }

        if (static::$resolvingTenantAuth) {
            return null;
        }

        static::$resolvingTenantAuth = true;

        try {
            if (auth()->hasUser()) {
                $user = auth()->user();

                return $user?->tenant_id ? (int) $user->tenant_id : null;
            }

            if (auth()->check()) {
                $user = auth()->user();

                return $user?->tenant_id ? (int) $user->tenant_id : null;
            }
        } catch (\Throwable) {
            return null;
        } finally {
            static::$resolvingTenantAuth = false;
        }

        return null;
    }

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (! isset($model->tenant_id) || $model->tenant_id === null) {
                $tenantId = static::resolveCurrentTenantId();
                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
