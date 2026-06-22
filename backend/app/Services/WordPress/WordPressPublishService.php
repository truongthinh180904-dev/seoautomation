<?php

namespace App\Services\WordPress;

use App\DTOs\PublishingDTO;
use App\Exceptions\WordPress\WordPressAuthException;
use App\Exceptions\WordPress\WordPressPublishException;
use App\Exceptions\WordPress\WordPressValidationException;
use App\Models\WordPressSite;
use App\Services\WordPress\Concerns\ConfiguresWordPressHttp;

class WordPressPublishService
{
    use ConfiguresWordPressHttp;

    public function __construct(
        protected WordPressTagService $tagService
    ) {}

    public function publish(PublishingDTO $dto, WordPressSite $site): array
    {
        $auth = base64_encode($site->username . ':' . $site->app_password);
        $tagIds = array_values(array_unique(array_merge(
            $dto->tagIds,
            $this->tagService->resolveTagNames($dto->tagNames, $site)
        )));

        $body = [
            'title' => $dto->title,
            'content' => $dto->content,
            'status' => $dto->publishStatus,
            'excerpt' => $dto->excerpt ?: mb_substr(strip_tags($dto->content), 0, 160),
            'categories' => $dto->categoryIds,
            'tags' => $tagIds,
            'comment_status' => $dto->commentStatus,
            'ping_status' => $dto->pingStatus,
            'meta' => array_filter(array_merge([
                '_yoast_wpseo_title' => $dto->seoTitle,
                '_yoast_wpseo_metadesc' => $dto->seoDescription,
                'rank_math_title' => $dto->seoTitle,
                'rank_math_description' => $dto->seoDescription,
                'rank_math_canonical_url' => $dto->canonicalUrl,
            ], $dto->meta), fn ($value) => $value !== null && $value !== ''),
        ];

        if ($dto->slug) {
            $body['slug'] = $dto->slug;
        }

        if ($dto->authorId) {
            $body['author'] = $dto->authorId;
        }

        if ($dto->featuredMediaId) {
            $body['featured_media'] = $dto->featuredMediaId;
        }

        if ($dto->scheduledAt) {
            $body['date'] = $dto->scheduledAt;
        }

        $apiUrl = rtrim($site->api_url, '/');
        $postType = trim($dto->postType ?: 'post', '/');
        
        $response = $this->wordpressHttp()->withHeaders([
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/json',
        ])->post($apiUrl . "/wp/v2/{$postType}s", $body);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'post_id' => $data['id'] ?? null,
                'url' => $data['link'] ?? null,
                'edit_url' => isset($data['id']) ? rtrim($site->url, '/') . "/wp-admin/post.php?post={$data['id']}&action=edit" : null,
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
            $auth = base64_encode($site->username . ':' . $site->app_password);

            $apiUrl = rtrim($site->api_url, '/');
            
            $response = $this->wordpressHttp()->withHeaders([
                'Authorization' => 'Basic ' . $auth,
            ])->get($apiUrl . '/wp/v2/users/me');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
