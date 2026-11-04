<?php

namespace App\Services\SEO;

use App\DTOs\SerpResultDTO;
use App\Exceptions\SEO\SerpApiException;
use App\Services\Cost\CostTrackingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SerpResearchService
{
    public function __construct(
        protected SerpCacheService $cache,
        protected CostTrackingService $costTracking
    ) {}

    /**
     * Search organic results from SERP (Google via SerpAPI).
     *
     * @return SerpResultDTO[]
     */
    public function search(
        string $keyword,
        ?int $tenantId = null,
        string $language = 'vi',
        ?int $keywordId = null,
        ?int $workspaceId = null,
        int $num = 10,
        bool $forceFresh = false
    ): array {
        $details = $this->searchWithDetails($keyword, $tenantId, $language, $keywordId, $workspaceId, $num, $forceFresh);

        return $details['results'];
    }

    /**
     * Search organic results with rich metadata (related searches, people also ask, raw data).
     *
     * @return array{results: SerpResultDTO[], related_searches: array, people_also_ask: array, raw_data: array}
     */
    public function searchWithDetails(
        string $keyword,
        ?int $tenantId = null,
        string $language = 'vi',
        ?int $keywordId = null,
        ?int $workspaceId = null,
        int $num = 10,
        bool $forceFresh = false
    ): array {
        if (! $forceFresh) {
            $cached = $this->cache->get($keyword, $language);
            if ($cached !== null && count($cached) > 0) {
                return [
                    'results' => $cached,
                    'related_searches' => $this->generateDefaultRelatedSearches($keyword, $language),
                    'people_also_ask' => $this->generateDefaultPeopleAlsoAsk($keyword, $language),
                    'raw_data' => ['cached' => true],
                ];
            }
        }

        $apiKey = config('seo.serp_api_key');

        // Local development & testing mock
        if (app()->environment('testing') || (! $apiKey && ! app()->isProduction())) {
            $mockResults = $this->generateMockSerpResults($keyword, $num);
            $relatedSearches = $this->generateDefaultRelatedSearches($keyword, $language);
            $peopleAlsoAsk = $this->generateDefaultPeopleAlsoAsk($keyword, $language);

            return [
                'results' => $mockResults,
                'related_searches' => $relatedSearches,
                'people_also_ask' => $peopleAlsoAsk,
                'raw_data' => ['mock' => true],
            ];
        }

        if (! $apiKey) {
            throw new SerpApiException('SERP API Key is not configured.');
        }

        $response = Http::timeout(15)->get('https://serpapi.com/search', [
            'q' => $keyword,
            'hl' => $language,
            'gl' => $language === 'vi' ? 'vn' : 'us',
            'num' => $num,
            'api_key' => $apiKey,
        ]);

        if ($response->failed()) {
            throw new SerpApiException('SerpAPI Error: '.$response->body());
        }

        if ($tenantId || $workspaceId) {
            $this->costTracking->logUsage([
                'tenant_id' => $tenantId,
                'workspace_id' => $workspaceId,
                'keyword_id' => $keywordId,
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
                position: (int) ($result['position'] ?? (count($dtos) + 1)),
                url: (string) ($result['link'] ?? ''),
                title: $result['title'] ?? null,
                description: $result['snippet'] ?? null,
                domain: $domain ?: 'unknown.com',
                rawData: $result,
            );
        }

        $relatedSearches = array_column($data['related_searches'] ?? [], 'query');
        if (empty($relatedSearches)) {
            $relatedSearches = $this->generateDefaultRelatedSearches($keyword, $language);
        }

        $peopleAlsoAsk = array_map(function ($item) {
            return [
                'question' => $item['question'] ?? '',
                'snippet' => $item['snippet'] ?? '',
                'title' => $item['title'] ?? '',
                'link' => $item['link'] ?? '',
            ];
        }, $data['related_questions'] ?? []);

        if (empty($peopleAlsoAsk)) {
            $peopleAlsoAsk = $this->generateDefaultPeopleAlsoAsk($keyword, $language);
        }

        if (config('seo.serp_api_key')) {
            $this->cache->put($keyword, $dtos, $language);
        }

        return [
            'results' => $dtos,
            'related_searches' => $relatedSearches,
            'people_also_ask' => $peopleAlsoAsk,
            'raw_data' => $data,
        ];
    }

    /**
     * Trích xuất các thực thể và từ khóa ngữ nghĩa LSI từ SERP.
     */
    public function extractLsiKeywords(string $keyword, array $serpResults, array $relatedSearches = []): array
    {
        $candidates = [];

        foreach ($relatedSearches as $rel) {
            if (is_string($rel) && trim($rel) !== '') {
                $candidates[] = trim(mb_strtolower($rel));
            }
        }

        foreach ($serpResults as $result) {
            $text = ($result instanceof SerpResultDTO)
                ? ($result->title.' '.$result->description)
                : (($result['title'] ?? '').' '.($result['description'] ?? ''));

            // Trích xuất các cụm 2-4 từ
            $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($text));
            $words = preg_split('/\s+/u', (string) $cleaned, -1, PREG_SPLIT_NO_EMPTY);

            if (is_array($words) && count($words) >= 2) {
                $count = count($words);
                for ($i = 0; $i < $count - 1; $i++) {
                    $bi = $words[$i].' '.$words[$i + 1];
                    if (mb_strlen($bi) > 5 && ! str_contains(mb_strtolower($keyword), $bi)) {
                        $candidates[] = $bi;
                    }
                    if ($i < $count - 2) {
                        $tri = $words[$i].' '.$words[$i + 1].' '.$words[$i + 2];
                        if (mb_strlen($tri) > 8 && ! str_contains(mb_strtolower($keyword), $tri)) {
                            $candidates[] = $tri;
                        }
                    }
                }
            }
        }

        $frequencies = array_count_values($candidates);
        arsort($frequencies);

        return array_slice(array_keys($frequencies), 0, 20);
    }

    /**
     * Generate mock SERP results for offline development / testing.
     *
     * @return SerpResultDTO[]
     */
    protected function generateMockSerpResults(string $keyword, int $count = 10): array
    {
        $mockDomains = [
            'thegioididong.com',
            'cellphones.com.vn',
            'fptshop.com.vn',
            'dienmayxanh.com',
            'tinhte.vn',
            'vnexpress.net',
            'vietnamnet.vn',
            'dantri.com.vn',
            'techz.vn',
            'genk.vn',
        ];

        $results = [];
        $limit = min($count, count($mockDomains));

        for ($i = 0; $i < $limit; $i++) {
            $pos = $i + 1;
            $domain = $mockDomains[$i];
            $slug = Str::slug($keyword);
            $url = "https://{$domain}/bai-viet/{$slug}-top-{$pos}";

            $results[] = new SerpResultDTO(
                position: $pos,
                url: $url,
                title: "Top đánh giá {$keyword} tốt nhất hiện nay - {$domain}",
                description: "Tổng hợp các mẫu {$keyword} chất lượng cao, độ bền tốt, bảo hành chính hãng. Hướng dẫn chọn mua {$keyword} chi tiết cho người mới.",
                domain: $domain,
                rawData: [
                    'position' => $pos,
                    'link' => $url,
                    'title' => "Top đánh giá {$keyword} tốt nhất hiện nay - {$domain}",
                    'snippet' => "Tổng hợp các mẫu {$keyword} chất lượng cao, độ bền tốt, bảo hành chính hãng.",
                    'domain' => $domain,
                ]
            );
        }

        return $results;
    }

    protected function generateDefaultRelatedSearches(string $keyword, string $language = 'vi'): array
    {
        if ($language === 'vi') {
            return [
                "{$keyword} giá rẻ",
                "{$keyword} chính hãng",
                "hướng dẫn chọn mua {$keyword}",
                "đánh giá {$keyword} tốt nhất",
                "so sánh các loại {$keyword}",
                "kinh nghiệm sử dụng {$keyword}",
            ];
        }

        return [
            "best {$keyword}",
            "cheap {$keyword}",
            "{$keyword} reviews",
            "how to choose {$keyword}",
            "{$keyword} guide",
        ];
    }

    protected function generateDefaultPeopleAlsoAsk(string $keyword, string $language = 'vi'): array
    {
        if ($language === 'vi') {
            return [
                [
                    'question' => "Có nên mua {$keyword} không?",
                    'snippet' => "Việc chọn mua {$keyword} phụ thuộc vào nhu cầu thực tế và ngân sách của bạn.",
                ],
                [
                    'question' => "Cách chọn {$keyword} tốt nhất như thế nào?",
                    'snippet' => 'Bạn nên lưu ý về chất liệu, thông số kỹ thuật, thương hiệu và chế độ bảo hành uy tín.',
                ],
                [
                    'question' => "Giá của {$keyword} hiện nay khoảng bao nhiêu?",
                    'snippet' => 'Mức giá dao động tùy thuộc phân khúc từ bình dân tới cao cấp.',
                ],
            ];
        }

        return [
            [
                'question' => "What is the best {$keyword}?",
                'snippet' => 'It depends on your budget and specific usage requirements.',
            ],
            [
                'question' => "How much does a good {$keyword} cost?",
                'snippet' => 'Prices vary widely across different brands and feature sets.',
            ],
        ];
    }
}
