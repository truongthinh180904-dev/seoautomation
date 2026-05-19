<?php

namespace App\Jobs\Media;

use App\Models\MediaAsset;
use App\Services\Media\ImageDownloadService;
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

    public function handle(ImageDownloadService $downloader): void
    {
        $asset = MediaAsset::find($this->mediaAssetId);
        if (!$asset || $asset->status->value !== 'pending') {
            return;
        }

        $downloader->download($asset);
    }

    public function failed(Throwable $exception): void
    {
        MediaAsset::whereKey($this->mediaAssetId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
