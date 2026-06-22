<?php

namespace App\Jobs\Media;

use App\Models\MediaAsset;
use App\Services\Article\ArticlePipelineService;
use App\Services\Media\ImageDownloadService;
use App\Enums\ArticleStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DownloadImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 180, 300];

    public function __construct(public int $mediaAssetId)
    {
        $this->onQueue('imports');
    }

    public function handle(ImageDownloadService $downloader, ArticlePipelineService $pipeline): void
    {
        $asset = MediaAsset::find($this->mediaAssetId);
        if (!$asset || $asset->status->value !== 'pending') {
            return;
        }

        try {
            if ($asset->article) {
                $pipeline->start($asset->article, 'media_download', 'Đang tải ảnh từ URL trong Excel.');
            }
            $downloader->download($asset);
            $asset->refresh();
            if ($asset->article) {
                $article = $asset->article;
                $pipeline->complete($article, 'media_download', 'Đã tải ảnh từ URL trong Excel.');

                if ($article->wordpress_site_id && $article->status === ArticleStatus::APPROVED) {
                    UploadToWordPressMediaJob::dispatch($asset->id, $article->wordpress_site_id)->onQueue('publishing');
                }
            }
        } catch (Throwable $exception) {
            if ($asset->article) {
                $pipeline->fail($asset->article, 'media_download', $exception->getMessage());
            }
            $asset->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $asset = MediaAsset::with('article')->find($this->mediaAssetId);
        if ($asset?->article) {
            app(ArticlePipelineService::class)->fail($asset->article, 'media_download', $exception->getMessage());
        }

        MediaAsset::whereKey($this->mediaAssetId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
