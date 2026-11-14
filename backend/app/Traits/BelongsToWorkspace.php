<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof User) {
            return;
        }

        $workspaceId = BelongsToWorkspace::resolveCurrentWorkspaceId();

        if ($workspaceId !== null) {
            $builder->where($model->getTable().'.workspace_id', $workspaceId);
        }
    }
}

trait BelongsToWorkspace
{
    protected static ?int $executionContextWorkspaceId = null;

    protected static bool $resolvingWorkspaceAuth = false;

    public static function setExecutionContext(?int $workspaceId): void
    {
        static::$executionContextWorkspaceId = $workspaceId;
    }

    public static function getExecutionContext(): ?int
    {
        return static::$executionContextWorkspaceId;
    }

    public static function resolveCurrentWorkspaceId(): ?int
    {
        if (static::$executionContextWorkspaceId !== null) {
            return static::$executionContextWorkspaceId;
        }

        if (app()->bound('current_workspace_id')) {
            $boundId = app('current_workspace_id');
            if (is_numeric($boundId)) {
                return (int) $boundId;
            }
        }

        if (static::$resolvingWorkspaceAuth) {
            return null;
        }

        static::$resolvingWorkspaceAuth = true;

        try {
            if (auth()->hasUser()) {
                $user = auth()->user();

                if (isset($user->default_workspace_id) && $user->default_workspace_id) {
                    return (int) $user->default_workspace_id;
                }
            } elseif (auth()->check()) {
                $user = auth()->user();

                if (isset($user->default_workspace_id) && $user->default_workspace_id) {
                    return (int) $user->default_workspace_id;
                }
            }
        } catch (\Throwable) {
            return null;
        } finally {
            static::$resolvingWorkspaceAuth = false;
        }

        return null;
    }

    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (Model $model): void {
            if (! isset($model->workspace_id) || $model->workspace_id === null) {
                $workspaceId = static::resolveCurrentWorkspaceId();
                if ($workspaceId !== null) {
                    $model->workspace_id = $workspaceId;
                }
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
