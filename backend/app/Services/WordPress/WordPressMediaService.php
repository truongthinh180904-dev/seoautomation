<?php

namespace App\Services\WordPress;

use App\Exceptions\WordPress\WordPressPublishException;
use App\Models\MediaAsset;
use App\Models\WordPressSite;
use App\Services\WordPress\Concerns\ConfiguresWordPressHttp;
use Illuminate\Support\Facades\Storage;

class WordPressMediaService
{
    use ConfiguresWordPressHttp;

    public function upload(MediaAsset $asset, WordPressSite $site): MediaAsset
    {
        if (!$asset->local_path || !Storage::exists($asset->local_path)) {
            throw new WordPressPublishException('Media asset local file is missing.');
        }

        $contents = Storage::get($asset->local_path);
        $fileName = basename($asset->local_path);
        $apiUrl = rtrim($site->api_url, '/');

        $response = $this->wordpressHttp()->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->app_password),
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Type' => $this->guessMimeType($fileName),
        ])->withBody($contents, $this->guessMimeType($fileName))
            ->post($apiUrl . '/wp/v2/media');

        if (!$response->successful()) {
            $asset->update([
                'status' => 'failed',
                'error_message' => $response->body(),
            ]);
            throw new WordPressPublishException('WordPress media upload failed: ' . $response->body());
        }

        $data = $response->json();
        $asset->update([
            'wordpress_media_id' => data_get($data, 'id'),
            'wordpress_media_url' => data_get($data, 'source_url'),
            'status' => 'uploaded',
        ]);

        $this->updateMetadata($asset, $site);

        return $asset->fresh();
    }

    protected function updateMetadata(MediaAsset $asset, WordPressSite $site): void
    {
        if (!$asset->wordpress_media_id) {
            return;
        }

        $this->wordpressHttp()->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->app_password),
            'Content-Type' => 'application/json',
        ])->post(rtrim($site->api_url, '/') . '/wp/v2/media/' . $asset->wordpress_media_id, array_filter([
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'description' => $asset->description,
        ]));
    }

    protected function guessMimeType(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
