<?php

namespace App\Services\Media;

use App\Jobs\Media\DownloadImageJob;
use App\Models\Keyword;
use App\Models\MediaAsset;
use App\Models\Article;

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

    public function createAssetsForKeyword(Keyword $keyword): int
    {
        $meta = $keyword->meta ?? [];
        $created = 0;

        if (!empty($meta['featured_image_url'])) {
            $created += $this->createAsset(
                tenantId: $keyword->tenant_id,
                campaignId: $keyword->campaign_id,
                sourceUrl: $meta['featured_image_url'],
                altText: $keyword->keyword,
                metadata: ['role' => 'featured', 'keyword' => $keyword->keyword]
            );
        }

        foreach ($this->parseImageUrls((string) ($meta['image_urls'] ?? '')) as $image) {
            $created += $this->createAsset(
                tenantId: $keyword->tenant_id,
                campaignId: $keyword->campaign_id,
                sourceUrl: $image['url'],
                altText: $image['alt'] ?: $keyword->keyword,
                caption: $image['caption'],
                metadata: ['role' => 'inline', 'keyword' => $keyword->keyword]
            );
        }

        return $created;
    }

    public function createAssetsForArticle(Article $article): int
    {
        $plan = $article->media_plan ?? [];
        $created = 0;

        if (!empty($plan['featured_image_url'])) {
            $created += $this->createAsset(
                tenantId: $article->tenant_id,
                campaignId: $article->campaign_id,
                sourceUrl: $plan['featured_image_url'],
                altText: $article->focus_keyword ?: $article->title,
                metadata: ['role' => 'featured', 'keyword' => $article->focus_keyword],
                articleId: $article->id
            );
        }

        foreach ($this->parseImageUrls((string) ($plan['image_urls'] ?? '')) as $image) {
            $created += $this->createAsset(
                tenantId: $article->tenant_id,
                campaignId: $article->campaign_id,
                sourceUrl: $image['url'],
                altText: $image['alt'] ?: ($article->focus_keyword ?: $article->title),
                caption: $image['caption'],
                metadata: ['role' => 'inline', 'keyword' => $article->focus_keyword],
                articleId: $article->id
            );
        }

        return $created;
    }

    protected function createAsset(
        int $tenantId,
        ?int $campaignId,
        string $sourceUrl,
        ?string $altText = null,
        ?string $caption = null,
        array $metadata = [],
        ?int $articleId = null
    ): int {
        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return 0;
        }

        $asset = MediaAsset::query()
            ->where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId)
            ->where('source_url', $sourceUrl)
            ->where('metadata->role', $metadata['role'] ?? null)
            ->where('metadata->keyword', $metadata['keyword'] ?? null)
            ->first();

        if (!$asset) {
            $asset = MediaAsset::create([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignId,
                'article_id' => $articleId,
                'source_url' => $sourceUrl,
                'source_type' => 'external_url',
                'alt_text' => $altText,
                'caption' => $caption,
                'status' => 'pending',
                'metadata' => $metadata,
            ]);
        }

        if ($articleId && !$asset->article_id) {
            $asset->update(['article_id' => $articleId]);
        }

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
