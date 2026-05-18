<?php

namespace App\Repositories\Contracts;

use App\Models\AIPromptVersion;
use Illuminate\Pagination\LengthAwarePaginator;

interface AIPromptVersionRepositoryInterface
{
    public function paginateVisibleForTenant(int $tenantId, array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function findVisibleForTenant(int $id, int $tenantId): ?AIPromptVersion;
    public function create(array $data): AIPromptVersion;
    public function update(AIPromptVersion $prompt, array $data): AIPromptVersion;
    public function deactivateSiblings(AIPromptVersion $prompt): int;
    public function updatePerformance(int $id, float $seoScore): bool;
    public function delete(AIPromptVersion $prompt): bool;
}
