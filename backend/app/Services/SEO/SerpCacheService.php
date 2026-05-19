<?php

namespace App\Services\SEO;

use App\DTOs\SerpResultDTO;
use App\Models\SerpCache;

class SerpCacheService
{
    public function get(string $keyword, string $language = 'vi', string $country = 'vn', string $provider = 'serper'): ?array
    {
        $cache = SerpCache::query()
            ->where('keyword', mb_strtolower(trim($keyword)))
            ->where('language', $language)
            ->where('country', $country)
            ->where('provider', $provider)
            ->where('expires_at', '>', now())
            ->first();

        if (!$cache) {
            return null;
        }

        return array_map(
            fn (array $row) => SerpResultDTO::fromArray($row),
            $cache->results ?? []
        );
    }

    public function put(string $keyword, array $results, string $language = 'vi', string $country = 'vn', string $provider = 'serper'): void
    {
        SerpCache::updateOrCreate(
            [
                'keyword' => mb_strtolower(trim($keyword)),
                'language' => $language,
                'country' => $country,
                'provider' => $provider,
            ],
            [
                'results' => array_map(fn (SerpResultDTO $dto) => [
                    'position' => $dto->position,
                    'url' => $dto->url,
                    'title' => $dto->title,
                    'description' => $dto->description,
                    'domain' => $dto->domain,
                    'raw_data' => $dto->rawData,
                ], $results),
                'expires_at' => now()->addDay(),
            ]
        );
    }
}
