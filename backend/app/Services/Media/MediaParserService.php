<?php

namespace App\Services\Media;

use App\Jobs\Media\DownloadImageJob;
use App\Models\MediaAsset;

class MediaParserService
{
    public function createAssetsFromKeywordRows(array $rows, int $tenantId, ?int $campaignId = null): int
    {
        $created = 0;

        foreach ($rows as $row) {
            $rowCampaignId = $row['campaign_id'] ?? $campaignId;
            $meta = $row['meta'] ?? [];

            if (!empty($meta['featured_image_url'])) {
                $created += $this->createAsset(
                    tenantId: $tenantId,
                    campaignId: $rowCampaignId,
                    sourceUrl: $meta['featured_image_url'],
                    altText: $row['keyword'] ?? null,
                    metadata: ['role' => 'featured', 'keyword' => $row['keyword'] ?? null]
                );
            }

            foreach ($this->parseImageUrls((string) ($meta['image_urls'] ?? '')) as $image) {
                $created += $this->createAsset(
                    tenantId: $tenantId,
                    campaignId: $rowCampaignId,
                    sourceUrl: $image['url'],
                    altText: $image['alt'] ?: ($row['keyword'] ?? null),
                    caption: $image['caption'],
                    metadata: ['role' => 'inline', 'keyword' => $row['keyword'] ?? null]
                );
            }
        }

        return $created;
    }

    protected function createAsset(
        int $tenantId,
        ?int $campaignId,
        string $sourceUrl,
        ?string $altText = null,
        ?string $caption = null,
        array $metadata = []
    ): int {
        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return 0;
        }

        $asset = MediaAsset::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignId,
                'source_url' => $sourceUrl,
            ],
            [
                'source_type' => 'external_url',
                'alt_text' => $altText,
                'caption' => $caption,
                'status' => 'pending',
                'metadata' => $metadata,
            ]
        );

        if ($asset->wasRecentlyCreated) {
            DownloadImageJob::dispatch($asset->id)->onQueue('imports');
        }

        return 1;
    }

    protected function parseImageUrls(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $items = [];
        foreach (explode(';', $value) as $chunk) {
            $parts = array_map('trim', explode('|', $chunk));
            if (!empty($parts[0])) {
                $items[] = [
                    'url' => $parts[0],
                    'alt' => $parts[1] ?? null,
                    'caption' => $parts[2] ?? null,
                ];
            }
        }

        return $items;
    }
}
