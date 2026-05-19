<?php

namespace App\Repositories;

use App\Models\Schedule;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ScheduleRepository implements ScheduleRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Schedule::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->orderByRaw('next_run_at IS NULL')
            ->orderBy('next_run_at')
            ->orderByDesc('id');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function findByIdForTenant(int $id, int $tenantId): ?Schedule
    {
        return Schedule::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->find($id);
    }

    public function create(array $data): Schedule
    {
        return Schedule::create($data);
    }

    public function update(Schedule $schedule, array $data): Schedule
    {
        $schedule->update($data);

        return $schedule->fresh('creator:id,name');
    }

    public function delete(Schedule $schedule): bool
    {
        return (bool) $schedule->delete();
    }
}
