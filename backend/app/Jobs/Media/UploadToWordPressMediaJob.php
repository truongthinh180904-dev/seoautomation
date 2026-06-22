<?php

namespace App\Jobs\Media;

use App\Enums\ArticleStatus;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\MediaAsset;
use App\Models\WordPressSite;
use App\Services\Article\ArticlePipelineService;
use App\Services\WordPress\WordPressMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UploadToWordPressMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 180, 300];

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

        if (!$asset || !$site || $asset->status->value !== 'downloaded') {
            return;
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
                PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id)
                    ->onQueue('publishing');
            }
        } elseif ($asset->article_id && ($asset->metadata['role'] ?? null) === 'inline') {
            $article = $asset->article;

            if ($article) {
                $pipeline->complete($article, 'media_upload', 'Đã upload ảnh trong bài lên WordPress.');
            }

            if ($article && $article->wordpress_site_id && $article->status === ArticleStatus::APPROVED) {
                PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id)
                    ->onQueue('publishing');
            }
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
