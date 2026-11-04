<?php

namespace App\Services\SEO;

use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Models\Campaign;
use App\Models\Keyword;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIProviderService;
use App\Services\AI\JsonResponseParser;
use App\Services\Billing\CreditLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Throwable;

class KeywordResearchService
{
    public function __construct(
        protected CreditLedgerService $creditLedger,
        protected ?SerpResearchService $serpResearch = null,
        protected ?AIProviderService $aiProvider = null,
        protected ?JsonResponseParser $jsonParser = null
    ) {
        $this->serpResearch = $this->serpResearch ?? (app()->bound(SerpResearchService::class) ? app(SerpResearchService::class) : null);
        $this->aiProvider = $this->aiProvider ?? (app()->bound(AIProviderService::class) ? app(AIProviderService::class) : null);
        $this->jsonParser = $this->jsonParser ?? app(JsonResponseParser::class);
    }

    /**
     * Khám phá từ khóa liên quan từ từ khóa hạt giống (Seed Keyword),
     * tự động phân loại Search Intent, Volume, Difficulty và LSI keywords.
     * Trừ 2 credits cho mỗi lượt nghiên cứu batch (50 từ khóa).
     */
    public function research(
        Workspace|int $workspace,
        string $query,
        array $options = [],
        ?User $actor = null
    ): array {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);
        $query = trim($query);
        $language = (string) ($options['language'] ?? 'vi');
        $country = (string) ($options['country'] ?? 'vn');
        $limit = max(5, min(100, (int) ($options['limit'] ?? 50)));

        // 1. Trừ credit: 2 credits / lượt tìm kiếm batch (theo chuẩn P2.2.4 & config ai_costs)
        $creditCost = (int) config('ai_costs.credits.keyword_research_batch', 2);
        $referenceId = (int) (abs(crc32($query.'_'.microtime(true))) % 2147483647);
        $idempotencyKey = $options['idempotency_key'] ?? ('kw_research:'.$workspaceModel->id.':'.Str::uuid()->toString());

        $this->creditLedger->deductCredits(
            workspace: $workspaceModel,
            amount: $creditCost,
            referenceType: 'keyword_research',
            referenceId: $referenceId,
            idempotencyKey: $idempotencyKey,
            metadata: [
                'query' => $query,
                'language' => $language,
                'country' => $country,
                'limit' => $limit,
            ],
            fromReserved: false,
            actor: $actor
        );

        // 2. Thu thập danh sách gợi ý từ Google Suggest & Modifiers
        $suggestedKeywords = $this->fetchSuggestions($query, $language, $country);

        // 3. Bổ sung từ khóa từ SERP nếu có SerpResearchService
        if ($this->serpResearch !== null) {
            try {
                $serpResults = $this->serpResearch->search(
                    keyword: $query,
                    tenantId: $workspaceModel->legacy_tenant_id,
                    language: $language,
                    workspaceId: $workspaceModel->id
                );
                foreach ($serpResults as $res) {
                    if (! empty($res->title)) {
                        $cleanedTitle = preg_replace('/[|\-–_].*$/u', '', (string) $res->title);
                        $cleanedTitle = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', '', (string) $cleanedTitle));
                        if (mb_strlen($cleanedTitle) >= 5 && mb_strlen($cleanedTitle) <= 60) {
                            $suggestedKeywords[] = mb_strtolower($cleanedTitle);
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('KeywordResearch: SerpResearch optional fetch failed: '.$e->getMessage());
            }
        }

        // Đảm bảo seed keyword luôn có mặt ở đầu danh sách
        array_unshift($suggestedKeywords, mb_strtolower($query));

        // Chuẩn hóa, lọc trùng lặp
        $uniqueKeywords = [];
        foreach ($suggestedKeywords as $kw) {
            $kw = trim(mb_strtolower($kw));
            if ($kw !== '' && ! in_array($kw, $uniqueKeywords, true)) {
                $uniqueKeywords[] = $kw;
            }
            if (count($uniqueKeywords) >= $limit) {
                break;
            }
        }

        // 4. Phân loại Search Intent, Volume, Difficulty và LSI keywords
        $enrichedKeywords = $this->enrichKeywords($uniqueKeywords, $query, $language);

        return [
            'seed_keyword' => $query,
            'language' => $language,
            'country' => $country,
            'credits_deducted' => $creditCost,
            'total_keywords' => count($enrichedKeywords),
            'keywords' => $enrichedKeywords,
        ];
    }

    /**
     * Gom cụm danh sách từ khóa theo chủ đề và ý định tìm kiếm (Semantic Clustering).
     */
    public function cluster(array $keywords, array $options = []): array
    {
        $language = (string) ($options['language'] ?? 'vi');
        $tenantId = (int) ($options['tenant_id'] ?? 0);
        $workspaceId = isset($options['workspace_id']) ? (int) $options['workspace_id'] : null;
        $normalizedList = [];

        foreach ($keywords as $item) {
            if (is_string($item)) {
                $normalizedList[] = [
                    'keyword' => trim($item),
                    'search_intent' => $this->detectIntent($item, $language),
                    'search_volume' => $this->estimateVolume($item, $item),
                    'difficulty' => $this->estimateDifficulty($item),
                    'cpc' => 0.35,
                    'lsi_keywords' => [],
                ];
            } elseif (is_array($item) && ! empty($item['keyword'])) {
                $kw = trim((string) $item['keyword']);
                $normalizedList[] = [
                    'keyword' => $kw,
                    'search_intent' => $item['search_intent'] ?? $this->detectIntent($kw, $language),
                    'search_volume' => isset($item['search_volume']) ? (int) $item['search_volume'] : $this->estimateVolume($kw, $kw),
                    'difficulty' => isset($item['difficulty']) ? (int) $item['difficulty'] : $this->estimateDifficulty($kw),
                    'cpc' => isset($item['cpc']) ? (float) $item['cpc'] : 0.35,
                    'lsi_keywords' => $item['lsi_keywords'] ?? [],
                ];
            }
        }

        if (empty($normalizedList)) {
            return [
                'total_clusters' => 0,
                'total_keywords' => 0,
                'clusters' => [],
            ];
        }

        // Cố gắng dùng AI để phân nhóm thông minh nếu có AIProvider
        $clusters = $this->clusterWithAi($normalizedList, $language, $tenantId, $workspaceId);

        // Fallback sang gom cụm Rule-based nếu AI không trả về kết quả
        if (empty($clusters)) {
            $clusters = $this->clusterWithRules($normalizedList);
        }

        return [
            'total_clusters' => count($clusters),
            'total_keywords' => count($normalizedList),
            'clusters' => $clusters,
        ];
    }

    /**
     * Lưu danh sách từ khóa nghiên cứu vào bảng `keywords` và liên kết với Campaign.
     */
    public function saveResearchedKeywords(
        Workspace|int $workspace,
        array $keywords,
        ?int $campaignId = null,
        ?int $websiteId = null,
        ?User $actor = null
    ): array {
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::findOrFail($workspace);
        $saved = [];

        if ($campaignId !== null) {
            $campaign = Campaign::where('id', $campaignId)
                ->where('workspace_id', $workspaceModel->id)
                ->first();
            if (! $campaign) {
                $campaignId = null;
            }
        }

        DB::transaction(function () use ($workspaceModel, $keywords, $campaignId, $websiteId, $actor, &$saved) {
            foreach ($keywords as $item) {
                $keywordText = is_array($item) ? trim((string) ($item['keyword'] ?? '')) : trim((string) $item);
                if ($keywordText === '') {
                    continue;
                }

                $intent = is_array($item) ? ($item['search_intent'] ?? null) : null;
                $volume = is_array($item) && isset($item['search_volume']) ? (int) $item['search_volume'] : null;
                $diff = is_array($item) && isset($item['difficulty']) ? (int) $item['difficulty'] : null;
                $cpc = is_array($item) && isset($item['cpc']) ? (float) $item['cpc'] : null;
                $lsi = is_array($item) && isset($item['lsi_keywords']) && is_array($item['lsi_keywords']) ? $item['lsi_keywords'] : null;
                $pillar = is_array($item) ? ($item['pillar_topic'] ?? null) : null;
                $cluster = is_array($item) ? ($item['content_cluster'] ?? null) : null;

                $model = Keyword::updateOrCreate(
                    [
                        'workspace_id' => $workspaceModel->id,
                        'keyword' => $keywordText,
                    ],
                    [
                        'tenant_id' => $workspaceModel->legacy_tenant_id ?? 1,
                        'website_id' => $websiteId,
                        'campaign_id' => $campaignId,
                        'user_id' => $actor?->id ?? $workspaceModel->owner_id,
                        'search_intent' => $intent ?? $this->detectIntent($keywordText, 'vi'),
                        'search_volume' => $volume ?? $this->estimateVolume($keywordText, $keywordText),
                        'difficulty' => $diff ?? $this->estimateDifficulty($keywordText),
                        'cpc' => $cpc,
                        'lsi_keywords' => $lsi,
                        'pillar_topic' => $pillar,
                        'content_cluster' => $cluster,
                        'status' => 'pending',
                        'priority' => 5,
                    ]
                );

                $saved[] = $model;
            }
        });

        return [
            'saved_count' => count($saved),
            'keywords' => $saved,
        ];
    }

    /**
     * Gọi Google Suggest API kết hợp tiền tố/hậu tố để sinh hàng chục từ khóa.
     */
    protected function fetchSuggestions(string $query, string $language, string $country): array
    {
        $suggestions = [];
        $patterns = [
            $query,
            $query.' là gì',
            $query.' tốt nhất',
            $query.' giá rẻ',
            $query.' giá bao nhiêu',
            'cách chọn '.$query,
            'hướng dẫn '.$query,
            'đánh giá '.$query,
            'so sánh '.$query,
            'top '.$query,
            'mua '.$query.' ở đâu',
        ];

        if ($language === 'en') {
            $patterns = [
                $query,
                'what is '.$query,
                'best '.$query,
                'cheap '.$query,
                'how to '.$query,
                $query.' review',
                $query.' vs',
                'top 10 '.$query,
                'buy '.$query,
            ];
        }

        foreach ($patterns as $pattern) {
            try {
                $response = Http::timeout(3)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    ])
                    ->get('https://suggestqueries.google.com/complete/search', [
                        'client' => 'chrome',
                        'hl' => $language,
                        'gl' => $country,
                        'q' => $pattern,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json) && isset($json[1]) && is_array($json[1])) {
                        foreach ($json[1] as $item) {
                            if (is_string($item) && trim($item) !== '') {
                                $suggestions[] = trim($item);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Tiếp tục thử pattern kế tiếp nếu có timeout
                continue;
            }
        }

        // Bổ sung heuristic variations nếu Google Suggest bị rate-limit hoặc offline
        if (count($suggestions) < 15) {
            $modifiers = [
                'uy tín', 'chính hãng', 'chuyên nghiệp', 'cao cấp',
                'tại tphcm', 'tại hà nội', 'mới nhất', 'kinh nghiệm',
                'xu hướng', 'ưu nhược điểm', 'lưu ý khi dùng',
            ];
            foreach ($modifiers as $mod) {
                $suggestions[] = $query.' '.$mod;
            }
        }

        return $suggestions;
    }

    /**
     * Bổ sung thuộc tính Search Intent, Volume, Difficulty và LSI keywords cho danh sách từ khóa.
     */
    protected function enrichKeywords(array $keywords, string $seedKeyword, string $language): array
    {
        $result = [];

        foreach ($keywords as $kw) {
            $intent = $this->detectIntent($kw, $language);
            $volume = $this->estimateVolume($kw, $seedKeyword);
            $difficulty = $this->estimateDifficulty($kw);
            $cpc = $this->estimateCpc($intent, $volume);
            $lsi = $this->generateLsiKeywords($kw, $keywords);

            $result[] = [
                'keyword' => $kw,
                'search_intent' => $intent,
                'search_volume' => $volume,
                'difficulty' => $difficulty,
                'cpc' => $cpc,
                'lsi_keywords' => $lsi,
            ];
        }

        return $result;
    }

    /**
     * Phân loại ý định tìm kiếm (Search Intent) theo quy luật xác định (Rule-based Regex).
     */
    public function detectIntent(string $keyword, string $language = 'vi'): string
    {
        $kw = mb_strtolower($keyword);

        // Transactional: ý định mua sắm, giao dịch
        if (preg_match('/\b(mua|bán|giá|báo giá|khuyến mãi|chi phí|order|đặt hàng|buy|price|cost|pricing|discount|coupon|deal|shop|store)\b/ui', $kw)) {
            return 'transactional';
        }

        // Commercial: so sánh, đánh giá trước khi mua
        if (preg_match('/\b(top|review|đánh giá|so sánh|tốt nhất|ưu nhược điểm|nên mua|lựa chọn|best|vs|versus|compare|comparison|guide)\b/ui', $kw)) {
            return 'commercial';
        }

        // Navigational: tìm tên thương hiệu, đăng nhập, trang chủ, tải app
        if (preg_match('/\b(đăng nhập|login|sign in|trang chủ|homepage|tải|download|website|cổng|portal|app)\b/ui', $kw)) {
            return 'navigational';
        }

        // Default: Informational (là gì, cách, hướng dẫn, tại sao, kinh nghiệm...)
        return 'informational';
    }

    /**
     * Ước lượng search volume theo độ phổ biến tương đối.
     */
    protected function estimateVolume(string $keyword, string $seedKeyword): int
    {
        $kw = mb_strtolower(trim($keyword));
        $seed = mb_strtolower(trim($seedKeyword));
        $wordCount = count(explode(' ', $kw));

        if ($kw === $seed) {
            return 8500;
        }

        // Short-tail (2-3 từ)
        if ($wordCount <= 3) {
            return (int) (1800 + (abs(crc32($kw)) % 4200));
        }

        // Mid-tail (4-5 từ)
        if ($wordCount <= 5) {
            return (int) (450 + (abs(crc32($kw)) % 1550));
        }

        // Long-tail (6+ từ)
        return (int) (90 + (abs(crc32($kw)) % 410));
    }

    /**
     * Ước lượng độ khó từ khóa (Keyword Difficulty 0–100).
     */
    protected function estimateDifficulty(string $keyword): int
    {
        $kw = mb_strtolower(trim($keyword));
        $wordCount = count(explode(' ', $kw));

        // Từ ngắn có mức cạnh tranh cao hơn từ khóa dài
        $base = match (true) {
            $wordCount <= 2 => 70,
            $wordCount === 3 => 52,
            $wordCount === 4 => 38,
            default => 24,
        };

        $jitter = (int) (abs(crc32($kw)) % 18) - 9;

        return max(5, min(95, $base + $jitter));
    }

    /**
     * Ước lượng giá thầu CPC tương đối theo Intent.
     */
    protected function estimateCpc(string $intent, int $volume): float
    {
        $base = match ($intent) {
            'transactional' => 1.25,
            'commercial' => 0.85,
            'navigational' => 0.45,
            default => 0.25,
        };

        $factor = min(1.5, max(0.8, $volume / 2000));

        return round($base * $factor, 2);
    }

    /**
     * Trích xuất các từ khóa ngữ nghĩa LSI từ danh sách liên quan.
     */
    protected function generateLsiKeywords(string $currentKeyword, array $allKeywords): array
    {
        $currentTokens = array_filter(explode(' ', mb_strtolower($currentKeyword)));
        $lsi = [];

        foreach ($allKeywords as $other) {
            $otherStr = is_array($other) ? ($other['keyword'] ?? '') : (string) $other;
            $otherStr = mb_strtolower(trim($otherStr));
            if ($otherStr === '' || $otherStr === mb_strtolower($currentKeyword)) {
                continue;
            }

            $otherTokens = array_filter(explode(' ', $otherStr));
            $common = array_intersect($currentTokens, $otherTokens);

            // Có chung từ khóa hạt giống nhưng bổ sung ngữ cảnh
            if (count($common) >= 1 && count($otherTokens) !== count($currentTokens)) {
                $lsi[] = $otherStr;
                if (count($lsi) >= 3) {
                    break;
                }
            }
        }

        return $lsi;
    }

    /**
     * Gom cụm từ khóa bằng AI nếu có cấu hình AIProvider.
     */
    protected function clusterWithAi(array $keywords, string $language, int $tenantId = 0, ?int $workspaceId = null): array
    {
        if ($this->aiProvider === null || (app()->environment('testing') && ! ($this->aiProvider instanceof MockInterface))) {
            return [];
        }

        try {
            $kwList = array_map(fn ($k) => $k['keyword'], array_slice($keywords, 0, 50));
            $prompt = "Bạn là chuyên gia SEO chiến lược. Hãy nhóm danh sách từ khóa sau thành các cụm chủ đề ngữ nghĩa (Semantic Topic Clusters) cho chiến lược nội dung website.\n\n"
                ."Danh sách từ khóa:\n".json_encode($kwList, JSON_UNESCAPED_UNICODE)."\n\n"
                ."Yêu cầu trả về đúng định dạng JSON Array chứa các object:\n"
                ."[\n"
                ."  {\n"
                ."    \"cluster_name\": \"Tên cụm chủ đề ngắn gọn\",\n"
                ."    \"pillar_topic\": \"Chủ đề trụ cột\",\n"
                ."    \"primary_keyword\": \"Từ khóa chính đại diện trong cụm\",\n"
                ."    \"search_intent\": \"informational|commercial|transactional|navigational\",\n"
                ."    \"keywords\": [\"từ 1\", \"từ 2\"]\n"
                ."  }\n"
                ."]\n"
                .'Chỉ trả về JSON thuần, không kèm văn bản giải thích.';

            $requestDto = new AIRequestDTO(
                systemPrompt: 'You are an elite SEO strategist specializing in keyword semantic clustering.',
                userPrompt: $prompt,
                model: (string) config('ai.providers.gemini.default_model', 'gemini-2.5-flash'),
                agentType: AgentType::CONTENT_ANALYSIS,
                tenantId: $tenantId,
                maxTokens: 2000,
                temperature: 0.2,
                workspaceId: $workspaceId,
            );

            $response = $this->aiProvider->complete($requestDto);
            $parsed = $this->jsonParser->parse($response->content);

            if (! is_array($parsed) || empty($parsed)) {
                return [];
            }

            // Map lại với metadata chi tiết
            $indexed = [];
            foreach ($keywords as $item) {
                $indexed[$item['keyword']] = $item;
            }

            $clusters = [];
            foreach ($parsed as $c) {
                if (empty($c['cluster_name']) || empty($c['keywords']) || ! is_array($c['keywords'])) {
                    continue;
                }

                $clusterKeywords = [];
                $totalVol = 0;
                $sumDiff = 0;

                foreach ($c['keywords'] as $kwName) {
                    if (isset($indexed[$kwName])) {
                        $meta = $indexed[$kwName];
                        $clusterKeywords[] = $meta;
                        $totalVol += $meta['search_volume'];
                        $sumDiff += $meta['difficulty'];
                    } else {
                        $clusterKeywords[] = [
                            'keyword' => $kwName,
                            'search_intent' => $c['search_intent'] ?? 'informational',
                            'search_volume' => 500,
                            'difficulty' => 35,
                            'cpc' => 0.35,
                            'lsi_keywords' => [],
                        ];
                        $totalVol += 500;
                        $sumDiff += 35;
                    }
                }

                $count = max(1, count($clusterKeywords));
                $clusters[] = [
                    'cluster_name' => (string) $c['cluster_name'],
                    'pillar_topic' => (string) ($c['pillar_topic'] ?? $c['cluster_name']),
                    'primary_keyword' => (string) ($c['primary_keyword'] ?? ($clusterKeywords[0]['keyword'] ?? '')),
                    'search_intent' => (string) ($c['search_intent'] ?? 'informational'),
                    'total_search_volume' => $totalVol,
                    'avg_difficulty' => (int) round($sumDiff / $count),
                    'keywords' => $clusterKeywords,
                ];
            }

            return $clusters;
        } catch (Throwable $e) {
            Log::info('KeywordResearch: AI clustering fallback to rule-based: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Gom cụm Rule-based theo Search Intent và Token Similarity.
     */
    protected function clusterWithRules(array $keywords): array
    {
        $byIntent = [
            'informational' => [],
            'commercial' => [],
            'transactional' => [],
            'navigational' => [],
        ];

        foreach ($keywords as $kw) {
            $intent = $kw['search_intent'] ?? 'informational';
            if (! isset($byIntent[$intent])) {
                $intent = 'informational';
            }
            $byIntent[$intent][] = $kw;
        }

        $clusterTitles = [
            'informational' => 'Cụm Hướng Dẫn & Kiến Thức Cốt Lõi',
            'commercial' => 'Cụm Đánh Giá & So Sánh Sản Phẩm',
            'transactional' => 'Cụm Báo Giá & Mua Hàng Trực Tiếp',
            'navigational' => 'Cụm Tìm Kiếm Thương Hiệu & Ứng Dụng',
        ];

        $clusters = [];
        foreach ($byIntent as $intent => $items) {
            if (empty($items)) {
                continue;
            }

            // Sắp xếp từ khóa theo volume giảm dần để chọn primary keyword
            usort($items, fn ($a, $b) => $b['search_volume'] <=> $a['search_volume']);

            $primary = $items[0]['keyword'];
            $totalVol = array_sum(array_column($items, 'search_volume'));
            $avgDiff = (int) round(array_sum(array_column($items, 'difficulty')) / count($items));

            $clusters[] = [
                'cluster_name' => $clusterTitles[$intent],
                'pillar_topic' => Str::title(str_replace(['cụm ', ' & '], ['', ' - '], mb_strtolower($clusterTitles[$intent]))),
                'primary_keyword' => $primary,
                'search_intent' => $intent,
                'total_search_volume' => $totalVol,
                'avg_difficulty' => $avgDiff,
                'keywords' => $items,
            ];
        }

        return $clusters;
    }
}
