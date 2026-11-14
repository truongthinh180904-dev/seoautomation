<?php

namespace App\Jobs\Media;

use App\Enums\ArticleStatus;
use App\Models\MediaAsset;
use App\Models\WordPressSite;
use App\Services\Article\ArticlePipelineService;
use App\Services\Media\ImageDownloadService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DownloadImageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return "download_image_job_{$this->mediaAssetId}";
    }

    public function __construct(public int $mediaAssetId)
    {
        $this->onQueue('imports');
    }

    public function handle(ImageDownloadService $downloader, ArticlePipelineService $pipeline): void
    {
        $asset = MediaAsset::find($this->mediaAssetId);
        if (! $asset || $asset->status->value !== 'pending') {
            return;
        }

        try {
            BelongsToTenant::setExecutionContext($asset->tenant_id);
            if ($asset->workspace_id) {
                BelongsToWorkspace::setExecutionContext($asset->workspace_id);
            }

            if ($asset->article) {
                $pipeline->start($asset->article, 'media_download', 'Đang tải ảnh từ URL trong Excel.');
            }
            $downloader->download($asset);
            $asset->refresh();
            if ($asset->article) {
                $article = $asset->article;
                $pipeline->complete($article, 'media_download', 'Đã tải ảnh từ URL trong Excel.');

                if ($article->wordpress_site_id && $article->status === ArticleStatus::APPROVED) {
                    $site = $article->wordpressSite ?: WordPressSite::find($article->wordpress_site_id);
                    if ($site && $site->tenant_id === $article->tenant_id && (! $article->workspace_id || ! $site->workspace_id || $article->workspace_id === $site->workspace_id)) {
                        UploadToWordPressMediaJob::dispatch($asset->id, $article->wordpress_site_id)->onQueue('publishing');
                    }
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
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);
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
