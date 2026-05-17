<?php

namespace App\Services\SEO;

use App\DTOs\SerpResultDTO;
use App\Exceptions\SEO\SerpApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SerpResearchService
{
    public function search(string $keyword, ?int $tenantId = null, string $language = 'vi'): array
    {
        $tenantKey = $tenantId ?? 'default';
        $cacheKey = "serp:{$tenantKey}:" . md5($keyword);

        return Cache::remember($cacheKey, 86400, function () use ($keyword, $language) {
            $apiKey = config('seo.serp_api_key');

            // Optionally bypass if no API key is set for local development
            if (!$apiKey && !app()->isProduction()) {
                // Mock result
                return [
                    new SerpResultDTO(
                        position: 1,
                        url: 'https://example.com/mock-result',
                        title: 'Mock Result for ' . $keyword,
                        description: 'This is a mock result because API key is missing.',
                        domain: 'example.com',
                        rawData: ['mock' => true]
                    )
                ];
            }

            if (!$apiKey) {
                throw new SerpApiException("SERP API Key is not configured.");
            }

            $response = Http::get('https://serpapi.com/search', [
                'q' => $keyword,
                'hl' => $language,
                'gl' => 'vn',
                'num' => 10,
                'api_key' => $apiKey,
            ]);

            if ($response->failed()) {
                throw new SerpApiException("SerpAPI Error: " . $response->body());
            }

            $data = $response->json();
            $organicResults = $data['organic_results'] ?? [];

            $dtos = [];
            foreach ($organicResults as $result) {
                $domain = parse_url($result['link'] ?? '', PHP_URL_HOST);
                
                $dtos[] = new SerpResultDTO(
                    position: $result['position'] ?? 0,
                    url: $result['link'] ?? '',
                    title: $result['title'] ?? null,
                    description: $result['snippet'] ?? null,
                    domain: $domain,
                    rawData: $result,
                );
            }

            return $dtos;
        });
    }
}
