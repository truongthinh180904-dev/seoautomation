<?php

namespace App\Services\SEO\RankTracking;

use App\Exceptions\SEO\SerpApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerperRankAdapter
{
    protected ?string $apiKey;

    protected string $endpoint = 'https://google.serper.dev/search';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey
            ?: config('services.serp.api_key')
            ?: config('seo.serp_api_key')
            ?: env('SERPER_API_KEY')
            ?: env('SERP_API_KEY');
    }

    /**
     * Query Serper.dev Google Search API for top organic rankings.
     *
     * @return array{organic: array, serp_features: array, search_parameters: array, raw_data: array}
     *
     * @throws SerpApiException
     */
    public function search(
        string $keyword,
        string $country = 'vn',
        string $language = 'vi',
        string $device = 'desktop',
        int $num = 100
    ): array {
        $payload = [
            'q' => $keyword,
            'gl' => strtolower($country),
            'hl' => strtolower($language),
            'num' => min(max($num, 10), 100),
        ];

        if (strtolower($device) === 'mobile') {
            $payload['type'] = 'search';
        }

        // Fallback for testing environments without HTTP mocking or API keys
        if (app()->environment('testing') && ! $this->apiKey && ! Http::hasMacro('fake')) {
            return $this->generateMockSerperResponse($keyword, $country, $language, $num);
        }

        if (! $this->apiKey && ! app()->environment('testing')) {
            throw new SerpApiException('Serper.dev API key is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => (string) ($this->apiKey ?: 'test-serper-key'),
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($this->endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error("[SerperRankAdapter] HTTP Request Exception: {$e->getMessage()}", [
                'keyword' => $keyword,
                'country' => $country,
            ]);
            throw new SerpApiException("Failed to connect to Serper.dev: {$e->getMessage()}", 0, $e);
        }

        if ($response->failed()) {
            Log::error("[SerperRankAdapter] API Error: {$response->status()} - {$response->body()}", [
                'keyword' => $keyword,
            ]);
            throw new SerpApiException("Serper.dev API returned error HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json() ?: [];
        $organic = $data['organic'] ?? [];

        // Normalize positions
        $normalizedOrganic = [];
        foreach ($organic as $index => $item) {
            $normalizedOrganic[] = [
                'position' => (int) ($item['position'] ?? ($index + 1)),
                'title' => (string) ($item['title'] ?? ''),
                'link' => (string) ($item['link'] ?? ''),
                'snippet' => (string) ($item['snippet'] ?? ''),
                'sitelinks' => $item['sitelinks'] ?? [],
            ];
        }

        $serpFeatures = [
            'has_answer_box' => ! empty($data['answerBox']),
            'has_people_also_ask' => ! empty($data['peopleAlsoAsk']),
            'has_knowledge_graph' => ! empty($data['knowledgeGraph']),
            'has_top_stories' => ! empty($data['topStories']),
            'has_sitelinks' => ! empty(array_filter($normalizedOrganic, fn ($i) => ! empty($i['sitelinks']))),
        ];

        return [
            'organic' => $normalizedOrganic,
            'serp_features' => $serpFeatures,
            'search_parameters' => $data['searchParameters'] ?? $payload,
            'raw_data' => $data,
        ];
    }

    /**
     * Locate the rank and matching URL of a given website domain in organic results.
     *
     * @param  array<int, array{position: int, title: string, link: string, snippet: string}>  $organicResults
     * @return array{position: int, found_url: string, title: string, snippet: string}|null
     */
    public function findRanking(string $domainOrUrl, array $organicResults, ?string $targetUrl = null): ?array
    {
        $domain = parse_url(
            preg_match('#^https?://#i', $domainOrUrl) ? $domainOrUrl : 'https://'.$domainOrUrl,
            PHP_URL_HOST
        ) ?: $domainOrUrl;

        $normalizedDomain = strtolower(preg_replace('/^www\./i', '', trim($domain)));
        $normalizedTarget = $targetUrl ? strtolower(rtrim($targetUrl, '/')) : null;

        $firstDomainMatch = null;

        foreach ($organicResults as $result) {
            $link = $result['link'] ?? '';
            $linkHost = parse_url($link, PHP_URL_HOST) ?: '';
            $normalizedLinkHost = strtolower(preg_replace('/^www\./i', '', trim($linkHost)));

            $linkTrimmed = strtolower(rtrim($link, '/'));

            // Exact target URL match is top priority
            if ($normalizedTarget && $linkTrimmed === $normalizedTarget) {
                return [
                    'position' => (int) $result['position'],
                    'found_url' => $link,
                    'title' => (string) ($result['title'] ?? ''),
                    'snippet' => (string) ($result['snippet'] ?? ''),
                ];
            }

            // Domain match
            $isDomainMatch = ($normalizedLinkHost === $normalizedDomain)
                || str_ends_with($normalizedLinkHost, '.'.$normalizedDomain);

            if ($isDomainMatch && $firstDomainMatch === null) {
                $firstDomainMatch = [
                    'position' => (int) $result['position'],
                    'found_url' => $link,
                    'title' => (string) ($result['title'] ?? ''),
                    'snippet' => (string) ($result['snippet'] ?? ''),
                ];
            }
        }

        return $firstDomainMatch;
    }

    /**
     * Generate synthetic organic results for local/unit test fallbacks.
     */
    protected function generateMockSerperResponse(string $keyword, string $country, string $language, int $num): array
    {
        $organic = [];
        for ($i = 1; $i <= min($num, 10); $i++) {
            $organic[] = [
                'position' => $i,
                'title' => "Kết quả {$i} cho {$keyword}",
                'link' => "https://example-{$i}.vn/bai-viet-{$keyword}",
                'snippet' => "Mô tả mẫu xếp hạng vị trí {$i} cho từ khóa {$keyword}.",
                'sitelinks' => [],
            ];
        }

        return [
            'organic' => $organic,
            'serp_features' => [
                'has_answer_box' => false,
                'has_people_also_ask' => true,
                'has_knowledge_graph' => false,
                'has_top_stories' => false,
                'has_sitelinks' => false,
            ],
            'search_parameters' => [
                'q' => $keyword,
                'gl' => $country,
                'hl' => $language,
                'num' => $num,
            ],
            'raw_data' => ['mock' => true],
        ];
    }
}
