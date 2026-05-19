<?php

namespace App\Repositories\Contracts;

use App\Models\Campaign;
use Illuminate\Pagination\LengthAwarePaginator;

interface CampaignRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator;

    public function findByIdForTenant(int $id, int $tenantId): ?Campaign;

    public function create(array $data): Campaign;

    public function update(int $id, array $data): bool;

    public function deleteForTenant(int $id, int $tenantId): bool;
}
