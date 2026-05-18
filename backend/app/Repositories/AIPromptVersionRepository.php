<?php

namespace App\Repositories;

use App\Models\AIPromptVersion;
use App\Repositories\Contracts\AIPromptVersionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AIPromptVersionRepository implements AIPromptVersionRepositoryInterface
{
    public function paginateVisibleForTenant(int $tenantId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AIPromptVersion::query()
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->orderByDesc('created_at');

        if (!empty($filters['agent_type'])) {
            $query->where('agent_type', $filters['agent_type']);
        }

        return $query->paginate($perPage);
    }

    public function findVisibleForTenant(int $id, int $tenantId): ?AIPromptVersion
    {
        return AIPromptVersion::query()
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->find($id);
    }

    public function create(array $data): AIPromptVersion
    {
        return AIPromptVersion::create($data);
    }

    public function update(AIPromptVersion $prompt, array $data): AIPromptVersion
    {
        $prompt->update($data);

        return $prompt->fresh();
    }

    public function deactivateSiblings(AIPromptVersion $prompt): int
    {
        return AIPromptVersion::where('agent_type', $prompt->agent_type)
            ->where(function ($q) use ($prompt) {
                if ($prompt->tenant_id) {
                    $q->where('tenant_id', $prompt->tenant_id);
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->where('id', '!=', $prompt->id)
            ->update(['is_active' => false]);
    }

    public function updatePerformance(int $id, float $seoScore): bool
    {
        $prompt = AIPromptVersion::find($id);

        if (!$prompt) {
            return false;
        }

        $current = (float) ($prompt->performance_score ?? 0);
        $prompt->performance_score = round(($current + $seoScore) / 2, 2);

        return $prompt->save();
    }

    public function delete(AIPromptVersion $prompt): bool
    {
        return (bool) $prompt->delete();
    }
}
