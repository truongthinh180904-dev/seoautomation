<?php

namespace App\Repositories;

use App\Enums\KeywordStatus;
use App\Models\Keyword;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KeywordRepository implements KeywordRepositoryInterface
{
    public function findById(int $id): ?Keyword
    {
        return Keyword::find($id);
    }

    public function findByIdForTenant(int $id, int $tenantId): ?Keyword
    {
        return Keyword::where('tenant_id', $tenantId)->find($id);
    }

    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Keyword::where('tenant_id', $tenantId)
            ->with(['wordpressSite:id,name', 'user:id,name', 'article:id,keyword_id']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['wordpress_site_id'])) {
            $query->where('wordpress_site_id', $filters['wordpress_site_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('keyword', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        return $query->paginate($perPage);
    }

    public function paginateScheduledForTenant(int $tenantId, int $perPage = 20): LengthAwarePaginator
    {
        return Keyword::where('tenant_id', $tenantId)
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }

    public function findPendingForProcessing(int $tenantId, int $limit = 50): Collection
    {
        return Keyword::where('tenant_id', $tenantId)
            ->where('status', KeywordStatus::PENDING)
            ->limit($limit)
            ->get();
    }

    public function countByStatus(int $tenantId): array
    {
        $counts = Keyword::where('tenant_id', $tenantId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($counts as $count) {
            $result[$count->status->value] = $count->total;
        }

        return $result;
    }

    public function markAsProcessing(int $id): bool
    {
        return Keyword::where('id', $id)
            ->update(['status' => KeywordStatus::PROCESSING]);
    }

    public function markAsCompleted(int $id): bool
    {
        return Keyword::where('id', $id)
            ->update([
                'status' => KeywordStatus::COMPLETED,
                'processed_at' => now(),
            ]);
    }

    public function markAsFailed(int $id, string $reason): bool
    {
        $keyword = Keyword::find($id);
        if (!$keyword) return false;
        
        $meta = $keyword->meta ?? [];
        $meta['error_reason'] = $reason;
        
        $keyword->status = KeywordStatus::FAILED;
        $keyword->meta = $meta;
        return $keyword->save();
    }

    public function findByBatchId(string $batchId): Collection
    {
        return Keyword::where('batch_id', $batchId)->get();
    }

    public function create(array $data): Keyword
    {
        return Keyword::create($data);
    }

    public function bulkCreate(array $keywords): int
    {
        if (empty($keywords)) {
            return 0;
        }
        
        $now = now();
        $records = array_map(function ($keyword) use ($now) {
            $keyword['created_at'] = $keyword['created_at'] ?? $now;
            $keyword['updated_at'] = $keyword['updated_at'] ?? $now;
            if (isset($keyword['status']) && $keyword['status'] instanceof KeywordStatus) {
                $keyword['status'] = $keyword['status']->value;
            }
            if (isset($keyword['meta']) && is_array($keyword['meta'])) {
                $keyword['meta'] = json_encode($keyword['meta']);
            }
            return $keyword;
        }, $keywords);

        Keyword::insert($records);
        return count($records);
    }

    public function bulkMarkSkippedForTenant(int $tenantId, array $ids): int
    {
        return Keyword::where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->update(['status' => KeywordStatus::SKIPPED]);
    }
}
