<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\CompetitorAnalysisService;
use InvalidArgumentException;

class CompetitorAnalysisAdapter implements ChatToolInterface
{
    public function __construct(
        protected CompetitorAnalysisService $competitorAnalysisService
    ) {}

    public function getName(): string
    {
        return 'competitor_analysis';
    }

    public function getTitle(): string
    {
        return 'Phân tích Top 3 Đối thủ Google SERP';
    }

    public function getDescription(): string
    {
        return 'Phân tích chuyên sâu Top 3 đối thủ Google SERP, lấy đối thủ #1 làm mỏ neo chuẩn xác (Anchor Benchmark) về số từ, mật độ LSI Entities và cấu trúc Headings để vượt trội.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return (int) config('ai_costs.credits.competitor_deep_audit', 3);
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword'],
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => 'Từ khóa SEO chính cần phân tích Top 3 đối thủ trên Google SERP.',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Tên miền website mục tiêu để đối chiếu (tùy chọn).',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Mã ngôn ngữ tìm kiếm (mặc định: vi).',
                    'default' => 'vi',
                ],
                'country' => [
                    'type' => 'string',
                    'description' => 'Mã quốc gia tìm kiếm (mặc định: vn).',
                    'default' => 'vn',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $keyword = trim((string) ($params['keyword'] ?? $params['seed_keyword'] ?? ''));
        if ($keyword === '') {
            throw new InvalidArgumentException('Từ khóa phân tích đối thủ không được để trống.');
        }

        // Bắt buộc cấu hình chỉ lấy đúng Top 3 đối thủ Google SERP
        $options = array_merge($params, [
            'limit' => 3,
            'force_refresh' => (bool) ($params['force_refresh'] ?? false),
        ]);

        $analysis = $this->competitorAnalysisService->analyzeSerpAndCompetitors(
            workspace: $workspace,
            keyword: $keyword,
            options: $options,
            actor: $actor
        );

        $competitors = $analysis['competitors'] ?? [];
        $top3 = array_slice($competitors, 0, 3);

        // Lấy đối thủ #1 làm Mỏ Neo Chuẩn Xác (Anchor Benchmark)
        $anchor = ! empty($top3) ? $top3[0] : null;

        $anchorWordCount = (int) ($anchor['word_count'] ?? 1800);
        $anchorHeadings = $anchor['headings_count'] ?? ['h1' => 1, 'h2' => 5, 'h3' => 3, 'h4' => 0, 'total' => 9];
        $anchorH2Count = (int) ($anchorHeadings['h2'] ?? 5);
        $anchorLsi = $anchor['lsi_keywords'] ?? [];

        // Khuyến nghị chiến lược để nội dung của website vượt trội chính xác đối thủ đứng đầu #1
        $targetWordCount = max((int) ceil($anchorWordCount * 1.15), 1800);
        $targetH2Count = max($anchorH2Count + 2, 6);

        $outrankRecommendations = [
            'anchor_domain' => $anchor['domain'] ?? 'N/A',
            'anchor_word_count' => $anchorWordCount,
            'target_word_count' => $targetWordCount,
            'word_count_advantage_percent' => 15,
            'anchor_h2_count' => $anchorH2Count,
            'target_h2_count' => $targetH2Count,
            'target_keyword_density' => '1.2% - 2.0%',
            'recommended_lsi_entities' => array_slice($anchorLsi, 0, 10),
            'recommended_headings' => array_slice($anchor['heading_structure'] ?? [], 0, 6),
            'strategy_note' => "Để vượt vị trí #1 của đối thủ ({$anchorWordCount} từ, {$anchorH2Count} thẻ H2), bài viết cần đạt tối thiểu {$targetWordCount} từ, ít nhất {$targetH2Count} thẻ H2 chuyên sâu và bao phủ đầy đủ các thực thể LSI.",
        ];

        return [
            'tool' => $this->getName(),
            'keyword' => $keyword,
            'language' => $analysis['language'] ?? 'vi',
            'total_analyzed' => count($top3),
            'anchor_competitor' => $anchor,
            'outrank_recommendations' => $outrankRecommendations,
            'top_3_competitors' => $top3,
            'benchmark' => $analysis['benchmark'] ?? [],
            'related_searches' => array_slice($analysis['related_searches'] ?? [], 0, 8),
            'people_also_ask' => array_slice($analysis['people_also_ask'] ?? [], 0, 5),
            'credits_deducted' => $analysis['credits_deducted'] ?? 3,
        ];
    }
}
