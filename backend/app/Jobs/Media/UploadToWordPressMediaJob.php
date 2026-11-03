<?php

namespace App\Jobs\Media;

use App\Enums\ArticleStatus;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\MediaAsset;
use App\Models\WordPressSite;
use App\Services\Article\ArticlePipelineService;
use App\Services\WordPress\WordPressMediaService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadToWordPressMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return "upload_wp_media_{$this->mediaAssetId}_{$this->wordpressSiteId}";
    }

    public function __construct(
        public int $mediaAssetId,
        public int $wordpressSiteId
    ) {
        $this->onQueue('publishing');
    }

    public function handle(WordPressMediaService $mediaService, ArticlePipelineService $pipeline): void
    {
        $asset = MediaAsset::find($this->mediaAssetId);
        $site = WordPressSite::find($this->wordpressSiteId);

        if (! $asset || ! $site || $asset->status->value !== 'downloaded') {
            return;
        }

        // Multi-tenant & workspace ownership verification
        if ($asset->tenant_id !== $site->tenant_id) {
            Log::warning('Cross-tenant media upload attempt blocked: tenant mismatch', [
                'asset_id' => $asset->id,
                'site_id' => $site->id,
            ]);

            return;
        }

        if ($asset->workspace_id && $site->workspace_id && $asset->workspace_id !== $site->workspace_id) {
            Log::warning('Cross-workspace media upload attempt blocked: workspace mismatch', [
                'asset_id' => $asset->id,
                'site_id' => $site->id,
            ]);

            return;
        }

        try {
            BelongsToTenant::setExecutionContext($asset->tenant_id);
            if ($asset->workspace_id) {
                BelongsToWorkspace::setExecutionContext($asset->workspace_id);
            }

            if ($asset->article) {
                $pipeline->start($asset->article, 'media_upload', 'Đang upload ảnh lên WordPress.');
            }

            $asset = $mediaService->upload($asset, $site);

            if ($asset->article_id && ($asset->metadata['role'] ?? null) === 'featured') {
                $article = $asset->article;
                if ($article) {
                    $pipeline->complete($article, 'media_upload', 'Đã upload ảnh đại diện lên WordPress.');
                }
                $article?->update([
                    'featured_image_url' => $asset->wordpress_media_url ?: $article->featured_image_url,
                    'image_assets' => array_merge($article->image_assets ?? [], [
                        'featured' => [
                            'media_asset_id' => $asset->id,
                            'wordpress_media_id' => $asset->wordpress_media_id,
                            'wordpress_media_url' => $asset->wordpress_media_url,
                        ],
                    ]),
                ]);

                if ($article && $article->wordpress_site_id && $article->status === ArticleStatus::APPROVED) {
                    $targetSite = $article->wordpressSite ?: WordPressSite::find($article->wordpress_site_id);
                    if ($targetSite && $targetSite->tenant_id === $article->tenant_id && (! $article->workspace_id || ! $targetSite->workspace_id || $article->workspace_id === $targetSite->workspace_id)) {
                        PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id)
                            ->onQueue('publishing');
                    }
                }
            } elseif ($asset->article_id && ($asset->metadata['role'] ?? null) === 'inline') {
                $article = $asset->article;

                if ($article) {
                    $pipeline->complete($article, 'media_upload', 'Đã upload ảnh trong bài lên WordPress.');
                }

                if ($article && $article->wordpress_site_id && $article->status === ArticleStatus::APPROVED) {
                    $targetSite = $article->wordpressSite ?: WordPressSite::find($article->wordpress_site_id);
                    if ($targetSite && $targetSite->tenant_id === $article->tenant_id && (! $article->workspace_id || ! $targetSite->workspace_id || $article->workspace_id === $targetSite->workspace_id)) {
                        PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id)
                            ->onQueue('publishing');
                    }
                }
            }
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    public function failed(Throwable $exception): void
    {
        $asset = MediaAsset::with('article')->find($this->mediaAssetId);
        if ($asset?->article) {
            app(ArticlePipelineService::class)->fail($asset->article, 'media_upload', $exception->getMessage());
        }

        MediaAsset::whereKey($this->mediaAssetId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
