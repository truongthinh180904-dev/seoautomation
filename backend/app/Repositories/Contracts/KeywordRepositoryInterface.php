<?php

namespace App\Repositories\Contracts;

use App\Models\Keyword;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface KeywordRepositoryInterface
{
    public function findById(int $id): ?Keyword;
    public function findByIdForTenant(int $id, int $tenantId): ?Keyword;
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator;
    public function paginateScheduledForTenant(int $tenantId, int $perPage = 20): LengthAwarePaginator;
    public function findPendingForProcessing(int $tenantId, int $limit = 50): Collection;
    public function countByStatus(int $tenantId): array;
    public function markAsProcessing(int $id): bool;
    public function markAsCompleted(int $id): bool;
    public function markAsFailed(int $id, string $reason): bool;
    public function findByBatchId(string $batchId): Collection;
    public function create(array $data): Keyword;
    public function bulkCreate(array $keywords): int;
    public function bulkMarkSkippedForTenant(int $tenantId, array $ids): int;
}
