<?php

namespace App\Services\SEO;

use App\DTOs\SerpResultDTO;
use App\Exceptions\SEO\SerpApiException;
use App\Services\Cost\CostTrackingService;
use Illuminate\Support\Facades\Http;

class SerpResearchService
{
    public function __construct(
        protected SerpCacheService $cache,
        protected CostTrackingService $costTracking
    ) {}

    public function search(string $keyword, ?int $tenantId = null, string $language = 'vi'): array
    {
        $cached = $this->cache->get($keyword, $language);
        if ($cached !== null) {
            return $cached;
        }

        $results = (function () use ($keyword, $language, $tenantId) {
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

            if ($tenantId) {
                $this->costTracking->logUsage([
                    'tenant_id' => $tenantId,
                    'job_type' => 'serp_lookup',
                    'provider' => 'serper',
                    'model' => 'serpapi',
                    'cost_usd' => $this->costTracking->calculateCost('serper', 'serpapi'),
                    'status' => 'success',
                ]);
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
        })();

        $this->cache->put($keyword, $results, $language);

        return $results;
    }
}
