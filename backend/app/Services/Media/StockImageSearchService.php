<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;

class StockImageSearchService
{
    public function search(string $query, int $limit): array
    {
        if (!config('ai.image_generation.stock.enabled', true) || $limit <= 0) {
            return [];
        }

        $images = [];
        $images = array_merge($images, $this->searchPexels($query, $limit));

        if (count($images) < $limit) {
            $images = array_merge($images, $this->searchUnsplash($query, $limit - count($images)));
        }

        return array_slice($this->uniqueByUrl($images), 0, $limit);
    }

    protected function searchPexels(string $query, int $limit): array
    {
        $key = config('ai.image_generation.stock.pexels_api_key');
        if (!$key) {
            return [];
        }

        $response = Http::withOptions([
            'verify' => (bool) config('ai.http.verify_ssl', true),
        ])->timeout(30)
            ->withHeaders(['Authorization' => $key])
            ->get('https://api.pexels.com/v1/search', [
                'query' => $query,
                'per_page' => min(30, max(1, $limit)),
                'orientation' => 'landscape',
            ]);

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json('photos') ?? [])
            ->map(fn (array $photo) => [
                'url' => data_get($photo, 'src.landscape') ?: data_get($photo, 'src.large') ?: data_get($photo, 'src.large2x'),
                'alt' => data_get($photo, 'alt') ?: $query,
                'caption' => trim('Photo by ' . (data_get($photo, 'photographer') ?: 'Pexels')),
                'provider' => 'pexels',
                'credit' => data_get($photo, 'photographer_url'),
            ])
            ->filter(fn (array $image) => !empty($image['url']))
            ->values()
            ->all();
    }

    protected function searchUnsplash(string $query, int $limit): array
    {
        $key = config('ai.image_generation.stock.unsplash_access_key');
        if (!$key) {
            return [];
        }

        $response = Http::withOptions([
            'verify' => (bool) config('ai.http.verify_ssl', true),
        ])->timeout(30)
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'per_page' => min(30, max(1, $limit)),
                'orientation' => 'landscape',
                'client_id' => $key,
            ]);

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json('results') ?? [])
            ->map(fn (array $photo) => [
                'url' => $this->optimizedUnsplashUrl(data_get($photo, 'urls.regular') ?: data_get($photo, 'urls.full')),
                'alt' => data_get($photo, 'alt_description') ?: data_get($photo, 'description') ?: $query,
                'caption' => trim('Photo by ' . (data_get($photo, 'user.name') ?: 'Unsplash')),
                'provider' => 'unsplash',
                'credit' => data_get($photo, 'links.html'),
            ])
            ->filter(fn (array $image) => !empty($image['url']))
            ->values()
            ->all();
    }

    protected function optimizedUnsplashUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $query = array_merge($query, [
            'fm' => 'jpg',
            'w' => (int) config('media.optimization.max_width', 1200),
            'q' => (int) config('media.optimization.jpeg_quality', 82),
            'fit' => 'max',
        ]);

        return ($parts['scheme'] ?? 'https') . '://'
            . ($parts['host'] ?? 'images.unsplash.com')
            . ($parts['path'] ?? '')
            . '?' . http_build_query($query);
    }

    protected function uniqueByUrl(array $images): array
    {
        $seen = [];

        return array_values(array_filter($images, function (array $image) use (&$seen) {
            $url = $image['url'] ?? null;
            if (!$url || isset($seen[$url])) {
                return false;
            }

            $seen[$url] = true;
            return true;
        }));
    }
}
