<?php

namespace App\Services\WordPress;

use App\Exceptions\WordPress\WordPressPublishException;
use App\Models\WordPressSite;
use App\Services\WordPress\Concerns\ConfiguresWordPressHttp;

class WordPressTagService
{
    use ConfiguresWordPressHttp;

    public function resolveTagNames(array $tagNames, WordPressSite $site): array
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tagName = trim((string) $tagName);
            if ($tagName !== '') {
                $tagIds[] = $this->findOrCreate($tagName, $site);
            }
        }

        return array_values(array_unique($tagIds));
    }

    public function findOrCreate(string $tagName, WordPressSite $site): int
    {
        $apiUrl = rtrim($site->api_url, '/');
        $auth = $this->authHeader($site);

        $search = $this->wordpressHttp()->withHeaders($auth)->get($apiUrl . '/wp/v2/tags', [
            'search' => $tagName,
            'per_page' => 20,
        ]);

        if ($search->successful()) {
            foreach ($search->json() ?? [] as $tag) {
                if (mb_strtolower($tag['name'] ?? '') === mb_strtolower($tagName)) {
                    return (int) $tag['id'];
                }
            }
        }

        $create = $this->wordpressHttp()->withHeaders($auth)->post($apiUrl . '/wp/v2/tags', [
            'name' => $tagName,
        ]);

        if ($create->successful()) {
            return (int) data_get($create->json(), 'id');
        }

        throw new WordPressPublishException("Cannot create WordPress tag '{$tagName}': " . $create->body());
    }

    protected function authHeader(WordPressSite $site): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->app_password),
            'Content-Type' => 'application/json',
        ];
    }
}
