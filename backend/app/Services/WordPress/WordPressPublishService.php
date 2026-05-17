<?php

namespace App\Services\WordPress;

use App\DTOs\PublishingDTO;
use App\Exceptions\WordPress\WordPressAuthException;
use App\Exceptions\WordPress\WordPressPublishException;
use App\Exceptions\WordPress\WordPressValidationException;
use App\Models\WordPressSite;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class WordPressPublishService
{
    public function publish(PublishingDTO $dto, WordPressSite $site): array
    {
        $decryptedPassword = Crypt::decryptString($site->app_password);
        $auth = base64_encode($site->username . ':' . $decryptedPassword);

        $body = [
            'title' => $dto->title,
            'content' => $dto->content,
            'status' => $dto->publishStatus,
            'excerpt' => mb_substr(strip_tags($dto->content), 0, 160),
            'categories' => $dto->categoryIds,
            'tags' => $dto->tagIds,
            'meta' => [
                '_yoast_wpseo_title' => $dto->seoTitle,
                '_yoast_wpseo_metadesc' => $dto->seoDescription,
            ],
        ];

        if ($dto->scheduledAt) {
            $body['date'] = $dto->scheduledAt;
        }

        $apiUrl = rtrim($site->api_url, '/');
        
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/json',
        ])->post($apiUrl . '/wp/v2/posts', $body);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'post_id' => $data['id'] ?? null,
                'url' => $data['link'] ?? null,
            ];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new WordPressAuthException("Authentication failed for {$site->url}");
        }

        if ($response->status() === 400 || $response->status() === 422) {
            throw new WordPressValidationException("Validation error: " . $response->body(), $response->json() ?? []);
        }

        throw new WordPressPublishException("Publishing failed: " . $response->body());
    }

    public function testConnection(WordPressSite $site): bool
    {
        try {
            $decryptedPassword = Crypt::decryptString($site->app_password);
            $auth = base64_encode($site->username . ':' . $decryptedPassword);

            $apiUrl = rtrim($site->api_url, '/');
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $auth,
            ])->get($apiUrl . '/wp/v2/users/me');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
