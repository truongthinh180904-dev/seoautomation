<?php

namespace App\Repositories\Contracts;

use App\Models\WordPressSite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface WordPressSiteRepositoryInterface
{
    public function findById(int $id): ?WordPressSite;
    public function paginateForTenant(int $tenantId, int $perPage = 20): LengthAwarePaginator;
    public function findActiveForTenant(int $tenantId): Collection;
    public function findByIdForTenant(int $id, int $tenantId): ?WordPressSite;
    public function create(array $data): WordPressSite;
    public function update(int $id, array $data): bool;
    public function updateConnectionStatus(int $id, string $status): bool;
    public function delete(int $id): bool;
}
