<?php

namespace App\Repositories\Contracts;

use App\Models\Schedule;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScheduleRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function findByIdForTenant(int $id, int $tenantId): ?Schedule;
    public function create(array $data): Schedule;
    public function update(Schedule $schedule, array $data): Schedule;
    public function delete(Schedule $schedule): bool;
}
