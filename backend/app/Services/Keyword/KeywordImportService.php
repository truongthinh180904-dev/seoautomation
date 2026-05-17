<?php

namespace App\Services\Keyword;

use App\Models\Keyword;
use App\Repositories\Contracts\KeywordRepositoryInterface;

class KeywordImportService
{
    public function __construct(
        protected KeywordRepositoryInterface $repository
    ) {}

    public function import(array $rows, int $tenantId, int $userId): array
    {
        $batchId = uniqid('batch_', true);
        $total = count($rows);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $validRows = [];
        $existingKeywords = Keyword::where('tenant_id', $tenantId)
            ->whereIn('keyword', array_column($rows, 'keyword'))
            ->pluck('keyword')
            ->toArray();

        foreach ($rows as $row) {
            if (in_array($row['keyword'], $existingKeywords)) {
                $skipped++;
                continue;
            }

            $validRows[] = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'batch_id' => $batchId,
                'keyword' => $row['keyword'],
                'search_volume' => $row['search_volume'] ?? 0,
                'difficulty' => $row['difficulty'] ?? 0,
                'priority' => $row['priority'] ?? 'medium',
                'wordpress_site_id' => $row['wordpress_site_id'] ?? null,
                'scheduled_at' => $row['scheduled_at'] ?? null,
                'status' => 'pending',
                'language' => 'vi',
            ];
        }

        if (!empty($validRows)) {
            $imported = $this->repository->bulkCreate($validRows);
        }

        return [
            'batch_id' => $batchId,
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
