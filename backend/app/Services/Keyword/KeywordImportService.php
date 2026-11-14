<?php

namespace App\Services\Keyword;

use App\Enums\CampaignImportStatus;
use App\Models\Campaign;
use App\Models\CampaignImport;
use App\Models\Keyword;
use App\Models\WordPressSite;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Media\MediaParserService;
use Illuminate\Support\Facades\DB;

class KeywordImportService
{
    public function __construct(
        protected KeywordRepositoryInterface $repository,
        protected MediaParserService $mediaParser
    ) {}

    public function import(array $rows, int $tenantId, int $userId, ?int $campaignId = null, ?int $workspaceId = null): array
    {
        return DB::transaction(function () use ($rows, $tenantId, $userId, $campaignId, $workspaceId) {
            $batchId = uniqid('batch_', true);
            $total = count($rows);
            $imported = 0;
            $skipped = 0;
            $errors = [];

            // Anti-IDOR validation for campaign and WordPress site
            $allowedCampaignIds = Campaign::where('tenant_id', $tenantId)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->pluck('id')
                ->all();

            $allowedSiteIds = WordPressSite::where('tenant_id', $tenantId)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->pluck('id')
                ->all();

            if ($campaignId && ! in_array($campaignId, $allowedCampaignIds, true)) {
                $campaignId = null;
            }

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

                $rowCampaignId = $row['campaign_id'] ?? $campaignId;
                if ($rowCampaignId && ! in_array($rowCampaignId, $allowedCampaignIds, true)) {
                    $rowCampaignId = null;
                }

                $rowSiteId = $row['wordpress_site_id'] ?? null;
                if ($rowSiteId && ! in_array($rowSiteId, $allowedSiteIds, true)) {
                    $rowSiteId = null;
                }

                $validRows[] = [
                    'tenant_id' => $tenantId,
                    'workspace_id' => $workspaceId,
                    'user_id' => $userId,
                    'campaign_id' => $rowCampaignId,
                    'batch_id' => $batchId,
                    'keyword' => $keyword,
                    'language' => $row['language'] ?? 'vi',
                    'search_volume' => $row['search_volume'] ?? null,
                    'difficulty' => $row['difficulty'] ?? null,
                    'priority' => $row['priority'] ?? 5,
                    'search_intent' => $row['search_intent'] ?? null,
                    'wordpress_site_id' => $rowSiteId,
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

            if (! empty($validRows)) {
                $imported = $this->repository->bulkCreate($validRows);
            }

            $mediaAssetsCreated = $this->mediaParser->createAssetsFromKeywordRows($validRows, $tenantId, $campaignId, $workspaceId);

            // Ghi nhận lịch sử import vào campaign_imports
            CampaignImport::create([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignId,
                'uploaded_by' => $userId,
                'file_name' => $batchId,
                'template_version' => 'v2',
                'total_rows' => $total,
                'valid_rows' => count($validRows),
                'invalid_rows' => $skipped,
                'imported_rows' => $imported,
                'skipped_rows' => $skipped,
                'status' => CampaignImportStatus::IMPORTED,
            ]);

            return [
                'batch_id' => $batchId,
                'total' => $total,
                'imported' => $imported,
                'skipped' => $skipped,
                'media_assets_created' => $mediaAssetsCreated,
                'errors' => $errors,
            ];
        });
    }
}
