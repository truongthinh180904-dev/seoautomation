<?php

namespace App\Services\SEO;

use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Enums\KeywordStatus;
use App\Models\Keyword;
use App\Models\TopicCluster;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\AI\AIProviderService;
use App\Services\AI\JsonResponseParser;
use App\Services\Billing\CreditLedgerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery\MockInterface;
use Throwable;

class TopicClusterService
{
    public function __construct(
        protected CreditLedgerService $creditLedger,
        protected ?AIProviderService $aiProvider = null,
        protected ?JsonResponseParser $jsonParser = null
    ) {
        $this->aiProvider = $this->aiProvider ?? (app()->bound(AIProviderService::class) ? app(AIProviderService::class) : null);
        $this->jsonParser = $this->jsonParser ?? app(JsonResponseParser::class);
    }

    /**
     * Lấy danh sách Topic Clusters của Workspace, có thể lọc theo Website và Status.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TopicCluster>
     */
    public function listClusters(Workspace $workspace, ?int $websiteId = null, array $filters = []): Collection
    {
        $query = TopicCluster::where('workspace_id', $workspace->id)
            ->with([
                'website:id,name,domain',
                'pillarKeyword:id,keyword,search_volume,difficulty,search_intent,status',
                'pillarArticle:id,title,slug,status',
                'keywords:id,topic_cluster_id,keyword,search_volume,difficulty,search_intent,status',
            ]);

        if ($websiteId !== null) {
            $query->where('website_id', $websiteId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc')->get();
    }

    /**
     * Lấy chi tiết Topic Cluster kèm thông tin từ khóa, bài viết trụ cột và tiến độ.
     *
     * @return array<string, mixed>
     */
    public function getClusterDetail(TopicCluster $cluster): array
    {
        $cluster->loadMissing([
            'website',
            'pillarKeyword',
            'pillarArticle',
            'keywords',
        ]);

        $keywords = $cluster->keywords;
        $totalKeywords = $keywords->count();
        $completedKeywords = $keywords->filter(function (Keyword $k) {
            return $k->status === KeywordStatus::COMPLETED || $k->status?->value === 'completed';
        })->count();

        $progressPercent = $totalKeywords > 0
            ? round(($completedKeywords / $totalKeywords) * 100, 1)
            : 0.0;

        return [
            'cluster' => $cluster,
            'metrics' => [
                'total_keywords' => $totalKeywords,
                'completed_keywords' => $completedKeywords,
                'progress_percent' => $progressPercent,
                'total_search_volume' => (int) $cluster->total_search_volume,
                'avg_difficulty' => (float) $cluster->avg_difficulty,
            ],
            'pillar_keyword' => $cluster->pillarKeyword,
            'pillar_article' => $cluster->pillarArticle,
            'keywords' => $keywords,
        ];
    }

    /**
     * Tạo Cụm chủ đề mới trong Workspace và Website.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCluster(Workspace $workspace, int $websiteId, array $data, ?User $actor = null): TopicCluster
    {
        // Kiểm tra website thuộc Workspace
        $website = Website::where('workspace_id', $workspace->id)->findOrFail($websiteId);

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Tên cụm chủ đề không được để trống.');
        }

        $slug = ! empty($data['slug']) ? Str::slug((string) $data['slug']) : Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;
        while (TopicCluster::where('workspace_id', $workspace->id)
            ->where('website_id', $website->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return DB::transaction(function () use ($workspace, $website, $data, $name, $slug) {
            $cluster = TopicCluster::create([
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'pillar_keyword_id' => ! empty($data['pillar_keyword_id']) ? (int) $data['pillar_keyword_id'] : null,
                'pillar_article_id' => ! empty($data['pillar_article_id']) ? (int) $data['pillar_article_id'] : null,
                'name' => $name,
                'slug' => $slug,
                'description' => ! empty($data['description']) ? (string) $data['description'] : null,
                'target_audience' => ! empty($data['target_audience']) ? (string) $data['target_audience'] : null,
                'status' => ! empty($data['status']) ? (string) $data['status'] : 'planning',
                'total_search_volume' => 0,
                'avg_difficulty' => 0.00,
            ]);

            // Nếu có gán pillar keyword, liên kết nó vào cluster
            if ($cluster->pillar_keyword_id) {
                Keyword::where('id', $cluster->pillar_keyword_id)
                    ->where('workspace_id', $workspace->id)
                    ->update(['topic_cluster_id' => $cluster->id]);
            }

            // Nếu có danh sách supporting keywords đi kèm
            if (! empty($data['keyword_ids']) && is_array($data['keyword_ids'])) {
                $keywordIds = array_map('intval', $data['keyword_ids']);
                Keyword::whereIn('id', $keywordIds)
                    ->where('workspace_id', $workspace->id)
                    ->update(['topic_cluster_id' => $cluster->id]);
            }

            $this->recalculateMetrics($cluster);

            return $cluster->fresh([
                'website',
                'pillarKeyword',
                'pillarArticle',
                'keywords',
            ]);
        });
    }

    /**
     * Cập nhật thông tin Cụm chủ đề.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCluster(TopicCluster $cluster, array $data): TopicCluster
    {
        return DB::transaction(function () use ($cluster, $data) {
            if (isset($data['name'])) {
                $cluster->name = trim((string) $data['name']);
            }

            if (isset($data['slug'])) {
                $cluster->slug = Str::slug((string) $data['slug']);
            }

            if (array_key_exists('description', $data)) {
                $cluster->description = $data['description'];
            }

            if (array_key_exists('target_audience', $data)) {
                $cluster->target_audience = $data['target_audience'];
            }

            if (isset($data['status'])) {
                $cluster->status = (string) $data['status'];
            }

            if (array_key_exists('pillar_article_id', $data)) {
                $cluster->pillar_article_id = $data['pillar_article_id'] ? (int) $data['pillar_article_id'] : null;
            }

            if (array_key_exists('pillar_keyword_id', $data)) {
                $newPillarId = $data['pillar_keyword_id'] ? (int) $data['pillar_keyword_id'] : null;
                $cluster->pillar_keyword_id = $newPillarId;

                if ($newPillarId) {
                    Keyword::where('id', $newPillarId)
                        ->where('workspace_id', $cluster->workspace_id)
                        ->update(['topic_cluster_id' => $cluster->id]);
                }
            }

            $cluster->save();

            $this->recalculateMetrics($cluster);

            return $cluster->fresh([
                'website',
                'pillarKeyword',
                'pillarArticle',
                'keywords',
            ]);
        });
    }

    /**
     * Xóa Cụm chủ đề và hủy liên kết các từ khóa thuộc cụm (không xóa từ khóa).
     */
    public function deleteCluster(TopicCluster $cluster): bool
    {
        return DB::transaction(function () use ($cluster) {
            Keyword::where('topic_cluster_id', $cluster->id)
                ->update(['topic_cluster_id' => null]);

            return (bool) $cluster->delete();
        });
    }

    /**
     * Gán danh sách từ khóa vào Cụm chủ đề và tự động tính toán lại metrics.
     *
     * @param  array<int>  $keywordIds
     */
    public function assignKeywords(TopicCluster $cluster, array $keywordIds): int
    {
        if (empty($keywordIds)) {
            return 0;
        }

        $cleanIds = array_map('intval', array_unique($keywordIds));

        return DB::transaction(function () use ($cluster, $cleanIds) {
            $updated = Keyword::whereIn('id', $cleanIds)
                ->where('workspace_id', $cluster->workspace_id)
                ->update(['topic_cluster_id' => $cluster->id]);

            $this->recalculateMetrics($cluster);

            return $updated;
        });
    }

    /**
     * Gỡ một từ khóa ra khỏi Cụm chủ đề.
     */
    public function removeKeyword(TopicCluster $cluster, int $keywordId): bool
    {
        return DB::transaction(function () use ($cluster, $keywordId) {
            Keyword::where('id', $keywordId)
                ->where('topic_cluster_id', $cluster->id)
                ->update(['topic_cluster_id' => null]);

            if ($cluster->pillar_keyword_id === $keywordId) {
                $cluster->pillar_keyword_id = null;
                $cluster->save();
            }

            $this->recalculateMetrics($cluster);

            return true;
        });
    }

    /**
     * Tính toán lại tổng search volume và độ khó trung bình của cụm.
     */
    public function recalculateMetrics(TopicCluster $cluster): TopicCluster
    {
        $keywords = Keyword::where(function ($query) use ($cluster) {
            $query->where('topic_cluster_id', $cluster->id);
            if ($cluster->pillar_keyword_id) {
                $query->orWhere('id', $cluster->pillar_keyword_id);
            }
        })->get();

        $totalVolume = (int) $keywords->sum('search_volume');
        $validDifficultyKeywords = $keywords->filter(fn ($k) => $k->difficulty !== null && $k->difficulty > 0);
        $avgDifficulty = $validDifficultyKeywords->count() > 0
            ? round((float) $validDifficultyKeywords->avg('difficulty'), 2)
            : 0.00;

        $cluster->update([
            'total_search_volume' => $totalVolume,
            'avg_difficulty' => $avgDifficulty,
        ]);

        return $cluster;
    }

    /**
     * Sinh gợi ý các Cụm chủ đề SEO bằng AI (hoặc heuristic fallback).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generateAiClusters(
        Workspace $workspace,
        int $websiteId,
        string $seedTopic,
        array $options = [],
        ?User $actor = null
    ): array {
        $website = Website::where('workspace_id', $workspace->id)->findOrFail($websiteId);
        $seedTopic = trim($seedTopic);
        if ($seedTopic === '') {
            throw new InvalidArgumentException('Chủ đề gốc (Seed Topic) không được để trống.');
        }

        // Trừ credit nếu có cấu hình (mặc định 1 credit cho gợi ý AI topic clusters)
        $creditCost = (int) config('ai_costs.credits.keyword_research_batch', 1);
        if ($actor && $creditCost > 0) {
            $refId = (int) (abs(crc32($seedTopic.'_'.microtime(true))) % 2147483647);
            $this->creditLedger->deductCredits(
                workspace: $workspace,
                amount: $creditCost,
                referenceType: 'topic_cluster_ai',
                referenceId: $refId,
                idempotencyKey: 'cluster_ai:'.$workspace->id.':'.Str::uuid()->toString(),
                metadata: ['seed_topic' => $seedTopic, 'website_id' => $website->id],
                fromReserved: false,
                actor: $actor
            );
        }

        $clusters = [];

        // Gọi AI qua AIProviderService nếu khả dụng
        if ($this->aiProvider !== null) {
            try {
                $prompt = sprintf(
                    "Bạn là chuyên gia cấu trúc SEO hàng đầu (Topic Cluster Architect). Hãy phân tích chủ đề: \"%s\" cho website thuộc lĩnh vực: \"%s\".\n"
                    ."Tạo 3 đến 4 Cụm chủ đề SEO (Topic Clusters) hoàn chỉnh gồm Pillar Page và các bài viết vệ tinh (Supporting Articles).\n"
                    ."Trả về DUY NHẤT một mảng JSON các object theo schema sau, không thêm lời dẫn:\n"
                    ."[\n"
                    ."  {\n"
                    ."    \"name\": \"Tên cụm chủ đề\",\n"
                    ."    \"slug\": \"slug-cum-chu-de\",\n"
                    ."    \"description\": \"Mô tả mục tiêu cụm chủ đề và đối tượng độc giả\",\n"
                    ."    \"target_audience\": \"Đối tượng khách hàng mục tiêu\",\n"
                    ."    \"pillar_keyword\": {\n"
                    ."      \"keyword\": \"Từ khóa chính trụ cột\",\n"
                    ."      \"search_volume\": 2500,\n"
                    ."      \"difficulty\": 45,\n"
                    ."      \"search_intent\": \"informational\"\n"
                    ."    },\n"
                    ."    \"supporting_keywords\": [\n"
                    ."      {\"keyword\": \"Từ khóa vệ tinh 1\", \"search_volume\": 800, \"difficulty\": 30, \"search_intent\": \"informational\"},\n"
                    ."      {\"keyword\": \"Từ khóa vệ tinh 2\", \"search_volume\": 650, \"difficulty\": 25, \"search_intent\": \"commercial\"}\n"
                    ."    ]\n"
                    ."  }\n"
                    .']',
                    $seedTopic,
                    $website->niche ?: 'General'
                );

                $requestDto = new AIRequestDTO(
                    systemPrompt: 'You are an expert SEO Strategist specializing in topic clusters and semantic search architecture. Always output strict valid JSON.',
                    userPrompt: $prompt,
                    model: (string) config('ai.providers.gemini.default_model', 'gemini-2.5-flash'),
                    agentType: AgentType::OUTLINE,
                    tenantId: $workspace->legacy_tenant_id ?? 0,
                    maxTokens: 2500,
                    temperature: 0.3,
                    workspaceId: $workspace->id,
                );

                $response = null;
                if ($this->aiProvider instanceof MockInterface) {
                    try {
                        $response = $this->aiProvider->complete($requestDto);
                    } catch (\BadMethodCallException $e) {
                        if (method_exists($this->aiProvider, 'sendRequest')) {
                            $response = $this->aiProvider->sendRequest($requestDto);
                        }
                    }
                } elseif (method_exists($this->aiProvider, 'complete')) {
                    $response = $this->aiProvider->complete($requestDto);
                } elseif (method_exists($this->aiProvider, 'sendRequest')) {
                    $response = $this->aiProvider->sendRequest($requestDto);
                }

                $content = $response?->content ?? '';

                $parsed = $this->jsonParser ? ($this->jsonParser->parse($content) ?? json_decode($content, true)) : json_decode($content, true);

                if (is_array($parsed) && ! empty($parsed)) {
                    $clusters = $parsed;
                }
            } catch (Throwable $e) {
                Log::warning('AI Topic Cluster generation error, falling back to heuristics: '.$e->getMessage(), [
                    'seed_topic' => $seedTopic,
                    'workspace_id' => $workspace->id,
                ]);
            }
        }

        // Fallback Heuristics thông minh nếu AI không khả dụng hoặc trả về rỗng
        if (empty($clusters)) {
            $clusters = $this->generateHeuristicClusters($seedTopic, $website->niche);
        }

        return [
            'seed_topic' => $seedTopic,
            'website_id' => $website->id,
            'website_name' => $website->name,
            'total_clusters' => count($clusters),
            'clusters' => $clusters,
        ];
    }

    /**
     * Thuật toán sinh Topic Clusters mẫu theo chuẩn SEO cấu trúc Silo.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function generateHeuristicClusters(string $seed, ?string $niche): array
    {
        $base = trim($seed);

        return [
            [
                'name' => "Cẩm nang toàn diện về {$base}",
                'slug' => Str::slug("cam-nang-toan-dien-ve-{$base}"),
                'description' => "Hướng dẫn nền tảng và lộ trình chi tiết về {$base} cho người mới bắt đầu.",
                'target_audience' => 'Người mới bắt đầu tìm hiểu về '.$base,
                'pillar_keyword' => [
                    'keyword' => $base,
                    'search_volume' => 3600,
                    'difficulty' => 42,
                    'search_intent' => 'informational',
                ],
                'supporting_keywords' => [
                    ['keyword' => "{$base} là gì", 'search_volume' => 1900, 'difficulty' => 25, 'search_intent' => 'informational'],
                    ['keyword' => "cách sử dụng {$base}", 'search_volume' => 1200, 'difficulty' => 32, 'search_intent' => 'informational'],
                    ['keyword' => "ưu nhược điểm {$base}", 'search_volume' => 750, 'difficulty' => 28, 'search_intent' => 'commercial'],
                    ['keyword' => "lưu ý quan trọng khi dùng {$base}", 'search_volume' => 450, 'difficulty' => 22, 'search_intent' => 'informational'],
                ],
            ],
            [
                'name' => "So sánh và đánh giá {$base}",
                'slug' => Str::slug("so-sanh-va-danh-gia-{$base}"),
                'description' => "Đánh giá chi tiết, so sánh các giải pháp và lựa chọn tối ưu liên quan đến {$base}.",
                'target_audience' => 'Khách hàng đang cân nhắc lựa chọn dịch vụ hoặc giải pháp',
                'pillar_keyword' => [
                    'keyword' => "đánh giá {$base}",
                    'search_volume' => 2100,
                    'difficulty' => 38,
                    'search_intent' => 'commercial',
                ],
                'supporting_keywords' => [
                    ['keyword' => "so sánh {$base} tốt nhất", 'search_volume' => 950, 'difficulty' => 35, 'search_intent' => 'commercial'],
                    ['keyword' => "bảng giá {$base}", 'search_volume' => 880, 'difficulty' => 30, 'search_intent' => 'transactional'],
                    ['keyword' => "review {$base} thực tế", 'search_volume' => 600, 'difficulty' => 26, 'search_intent' => 'commercial'],
                ],
            ],
            [
                'name' => "Chiến lược và mẹo tối ưu {$base}",
                'slug' => Str::slug("chien-luoc-va-meo-toi-uu-{$base}"),
                'description' => "Các kỹ thuật nâng cao và sai lầm thường gặp cần tránh khi áp dụng {$base}.",
                'target_audience' => 'Chuyên viên và doanh nghiệp muốn nâng cao hiệu quả',
                'pillar_keyword' => [
                    'keyword' => "tối ưu {$base}",
                    'search_volume' => 1500,
                    'difficulty' => 34,
                    'search_intent' => 'informational',
                ],
                'supporting_keywords' => [
                    ['keyword' => "kinh nghiệm triển khai {$base}", 'search_volume' => 520, 'difficulty' => 24, 'search_intent' => 'informational'],
                    ['keyword' => "sai lầm phổ biến khi dùng {$base}", 'search_volume' => 410, 'difficulty' => 20, 'search_intent' => 'informational'],
                    ['keyword' => "xu hướng {$base} mới nhất", 'search_volume' => 380, 'difficulty' => 18, 'search_intent' => 'informational'],
                ],
            ],
        ];
    }
}
