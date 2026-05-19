<?php

namespace App\Services\Keyword;

use App\Models\Keyword;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Media\MediaParserService;

class KeywordImportService
{
    public function __construct(
        protected KeywordRepositoryInterface $repository,
        protected MediaParserService $mediaParser
    ) {}

    public function import(array $rows, int $tenantId, int $userId, ?int $campaignId = null): array
    {
        $batchId = uniqid('batch_', true);
        $total = count($rows);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $validRows = [];
        $seenKeywords = [];
        $existingKeywords = Keyword::where('tenant_id', $tenantId)
            ->whereIn('keyword', array_column($rows, 'keyword'))
            ->pluck('keyword')
            ->toArray();

        foreach ($rows as $row) {
            $keyword = trim((string) ($row['keyword'] ?? ''));

            if ($keyword === '') {
                $skipped++;
                continue;
            }

            $keywordKey = mb_strtolower($keyword);

            if (in_array($keyword, $existingKeywords, true) || isset($seenKeywords[$keywordKey])) {
                $skipped++;
                continue;
            }

            $seenKeywords[$keywordKey] = true;

            $validRows[] = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'campaign_id' => $row['campaign_id'] ?? $campaignId,
                'batch_id' => $batchId,
                'keyword' => $keyword,
                'language' => $row['language'] ?? 'vi',
                'search_volume' => $row['search_volume'] ?? null,
                'difficulty' => $row['difficulty'] ?? null,
                'priority' => $row['priority'] ?? 5,
                'search_intent' => $row['search_intent'] ?? null,
                'wordpress_site_id' => $row['wordpress_site_id'] ?? null,
                'scheduled_at' => $row['scheduled_at'] ?? null,
                'status' => 'pending',
                'pillar_topic' => $row['pillar_topic'] ?? null,
                'content_cluster' => $row['content_cluster'] ?? null,
                'funnel_stage' => $row['funnel_stage'] ?? null,
                'target_word_count' => $row['target_word_count'] ?? null,
                'target_url' => $row['target_url'] ?? null,
                'canonical_url' => $row['canonical_url'] ?? null,
                'brief_notes' => $row['brief_notes'] ?? null,
                'must_include_points' => $row['must_include_points'] ?? null,
                'avoid_topics' => $row['avoid_topics'] ?? null,
                'reference_urls' => $row['reference_urls'] ?? null,
                'competitor_urls_override' => $row['competitor_urls_override'] ?? null,
                'raw_import_row' => $row['raw_import_row'] ?? null,
                'template_version' => $row['template_version'] ?? null,
                'meta' => $row['meta'] ?? null,
            ];
        }

        if (!empty($validRows)) {
            $imported = $this->repository->bulkCreate($validRows);
        }

        $mediaAssetsCreated = $this->mediaParser->createAssetsFromKeywordRows($validRows, $tenantId, $campaignId);

        return [
            'batch_id' => $batchId,
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'media_assets_created' => $mediaAssetsCreated,
            'errors' => $errors,
        ];
    }
}
