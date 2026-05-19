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
        return WordPressSite::where('tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage);
    }

    public function findActiveForTenant(int $tenantId): Collection
    {
        return WordPressSite::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();
    }

    public function findByIdForTenant(int $id, int $tenantId): ?WordPressSite
    {
        return WordPressSite::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function create(array $data): WordPressSite
    {
        return WordPressSite::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return WordPressSite::where('id', $id)->update($data) > 0;
    }

    public function updateConnectionStatus(int $id, string $status): bool
    {
        return WordPressSite::where('id', $id)->update([
            'connection_status' => $status,
            'last_connected_at' => now(),
        ]) > 0;
    }

    public function delete(int $id): bool
    {
        return WordPressSite::where('id', $id)->delete() > 0;
    }
}
