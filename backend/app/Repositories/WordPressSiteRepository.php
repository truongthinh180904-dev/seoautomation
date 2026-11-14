<?php

namespace App\Repositories;

use App\Models\WordPressSite;
use App\Repositories\Contracts\WordPressSiteRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WordPressSiteRepository implements WordPressSiteRepositoryInterface
{
    public function findById(int $id): ?WordPressSite
    {
        return WordPressSite::find($id);
    }

    public function paginateForTenant(int $tenantId, int $perPage = 20): LengthAwarePaginator
    {
        $query = WordPressSite::query();

        if (app()->bound('current_workspace_id')) {
            $workspaceId = app('current_workspace_id');
            $query->where(function ($q) use ($tenantId, $workspaceId) {
                $q->where('workspace_id', $workspaceId)
                    ->orWhere(function ($sub) use ($tenantId) {
                        $sub->whereNull('workspace_id')->where('tenant_id', $tenantId);
                    });
            });
        } else {
            $query->where('tenant_id', $tenantId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findActiveForTenant(int $tenantId): Collection
    {
        $query = WordPressSite::query()->where('is_active', true);

        if (app()->bound('current_workspace_id')) {
            $workspaceId = app('current_workspace_id');
            $query->where(function ($q) use ($tenantId, $workspaceId) {
                $q->where('workspace_id', $workspaceId)
                    ->orWhere(function ($sub) use ($tenantId) {
                        $sub->whereNull('workspace_id')->where('tenant_id', $tenantId);
                    });
            });
        } else {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    public function findByIdForTenant(int $id, int $tenantId): ?WordPressSite
    {
        return WordPressSite::where('id', $id)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
                if (app()->bound('current_workspace_id')) {
                    $q->orWhere('workspace_id', app('current_workspace_id'));
                }
            })
            ->first();
    }

    public function create(array $data): WordPressSite
    {
        return WordPressSite::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $site = WordPressSite::find($id);
        if (! $site) {
            return false;
        }

        return $site->update($data);
    }

    public function updateConnectionStatus(int $id, string $status): bool
    {
        $site = WordPressSite::find($id);
        if (! $site) {
            return false;
        }

        return $site->update([
            'connection_status' => $status,
            'last_connected_at' => now(),
        ]);
    }

    public function delete(int $id): bool
    {
        return WordPressSite::where('id', $id)->delete() > 0;
    }
}
