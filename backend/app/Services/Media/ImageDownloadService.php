<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageDownloadService
{
    protected array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    public function download(MediaAsset $asset): MediaAsset
    {
        if (!$asset->source_url) {
            throw new RuntimeException('Media asset source URL is empty.');
        }

        $response = Http::timeout(60)->get($asset->source_url);
        if (!$response->successful()) {
            throw new RuntimeException('Image download failed: HTTP ' . $response->status());
        }

        $mimeType = $response->header('Content-Type', 'image/jpeg');
        $mimeType = explode(';', $mimeType)[0];
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new RuntimeException("Unsupported image type: {$mimeType}");
        }

        $contents = $response->body();
        if (strlen($contents) > 10 * 1024 * 1024) {
            throw new RuntimeException('Image is larger than 10MB.');
        }

        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $path = sprintf(
            'media/%d/%d-%s.%s',
            $asset->tenant_id,
            $asset->id,
            bin2hex(random_bytes(4)),
            $extension
        );

        Storage::put($path, $contents);

        $asset->update([
            'local_path' => $path,
            'status' => 'downloaded',
            'error_message' => null,
            'metadata' => array_merge($asset->metadata ?? [], [
                'mime_type' => $mimeType,
                'size_bytes' => strlen($contents),
            ]),
        ]);

        return $asset->fresh();
    }
}
