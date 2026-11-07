<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\SeoIntelligenceInsight;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\Intelligence\SeoIntelligenceService;
use InvalidArgumentException;

class SeoIntelligenceToolAdapter implements ChatToolInterface
{
    public function __construct(
        protected SeoIntelligenceService $intelligenceService
    ) {}

    public function getName(): string
    {
        return 'seo_intelligence';
    }

    public function getTitle(): string
    {
        return 'Phát hiện Cơ hội & Bất thường SEO (SEO Intelligence)';
    }

    public function getDescription(): string
    {
        return 'Tự động phân tích và giải thích 6 dạng bất thường & cơ hội SEO (Tụt hạng, Tụt traffic, CTR thấp, Striking Distance, Cannibalization) kèm điểm tiềm năng Opportunity Score và đề xuất hành động cụ thể (0 credit, tức thì).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0; // Thuần PHP rule-based heuristics, 0 credit
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID của website cần kiểm tra bất thường (tùy chọn, mặc định lấy website đầu tiên).',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['summary', 'list', 'explain_anomaly', 'scan_now'],
                    'default' => 'summary',
                    'description' => 'Hành động: summary (tổng quan số lượng cơ hội/cảnh báo), list (danh sách cảnh báo tích cực/nguy cấp), explain_anomaly (giải thích chi tiết 1 cảnh báo), scan_now (quét lại các heuristics ngay lập tức).',
                ],
                'severity' => [
                    'type' => 'string',
                    'enum' => ['info', 'warning', 'critical'],
                    'description' => 'Lọc theo mức độ nghiêm trọng (critical: nguy cấp, warning: cảnh báo, info: cơ hội).',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Lọc theo dạng bất thường: ranking_drop, traffic_decay, high_imp_low_ctr, striking_distance, cannibalization, high_traffic_low_engagement.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 5,
                    'description' => 'Số lượng cảnh báo cần lấy (mặc định 5).',
                ],
                'insight_id' => [
                    'type' => 'integer',
                    'description' => 'ID của insight cần giải thích chi tiết (áp dụng cho action explain_anomaly).',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $websiteId = ! empty($params['website_id']) ? (int) $params['website_id'] : null;
        $website = null;

        if ($websiteId) {
            $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
            if (! $website) {
                throw new InvalidArgumentException("Không tìm thấy website #{$websiteId} trong Không gian làm việc của bạn.");
            }
        } else {
            $website = Website::where('workspace_id', $workspace->id)->first();
        }

        if (! $website) {
            return [
                'tool' => $this->getName(),
                'status' => 'no_website',
                'message' => 'Chưa có website nào trong Không gian làm việc. Vui lòng thêm website để quét các cơ hội và bất thường SEO.',
                'credits_deducted' => 0,
            ];
        }

        $action = (string) ($params['action'] ?? 'summary');

        switch ($action) {
            case 'scan_now':
                $freshInsights = $this->intelligenceService->analyzeWebsite($website);

                return [
                    'tool' => $this->getName(),
                    'action' => 'scan_now',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'total_scanned' => $freshInsights->count(),
                    'summary_text' => sprintf(
                        'Đã hoàn tất quét toàn diện heuristics 0 credit cho %s. Phát hiện %d cảnh báo & cơ hội hoạt động.',
                        $website->name,
                        $freshInsights->count()
                    ),
                    'credits_deducted' => 0,
                ];

            case 'explain_anomaly':
                $insightId = ! empty($params['insight_id']) ? (int) $params['insight_id'] : null;
                $insight = null;

                if ($insightId) {
                    $insight = SeoIntelligenceInsight::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('website_id', $website->id)
                        ->find($insightId);
                } else {
                    // Lấy cảnh báo nghiêm trọng nhất nếu không truyền id
                    $insight = SeoIntelligenceInsight::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('website_id', $website->id)
                        ->active()
                        ->orderByDesc('opportunity_score')
                        ->first();
                }

                if (! $insight) {
                    return [
                        'tool' => $this->getName(),
                        'action' => 'explain_anomaly',
                        'website_id' => $website->id,
                        'message' => 'Không tìm thấy cảnh báo bất thường nào phù hợp.',
                        'credits_deducted' => 0,
                    ];
                }

                return [
                    'tool' => $this->getName(),
                    'action' => 'explain_anomaly',
                    'insight_id' => $insight->id,
                    'title' => $insight->title,
                    'type' => $insight->type,
                    'severity' => $insight->severity,
                    'opportunity_score' => $insight->opportunity_score,
                    'description' => $insight->description,
                    'suggested_action' => $insight->suggested_action,
                    'metrics_snapshot' => $insight->metrics_snapshot,
                    'article_id' => $insight->article_id,
                    'summary_text' => sprintf(
                        'Phân tích chi tiết: "%s" (Mức độ: %s, Điểm tiềm năng: %d/100). Đề xuất: %s.',
                        $insight->title,
                        strtoupper($insight->severity),
                        $insight->opportunity_score,
                        $insight->suggested_action
                    ),
                    'credits_deducted' => 0,
                ];

            case 'list':
                $limit = max(1, min(50, (int) ($params['limit'] ?? 5)));
                $filters = [
                    'status' => 'active',
                    'per_page' => $limit,
                ];
                if (! empty($params['severity'])) {
                    $filters['severity'] = (string) $params['severity'];
                }
                if (! empty($params['type'])) {
                    $filters['type'] = (string) $params['type'];
                }

                $paginator = $this->intelligenceService->getInsights($website->id, $filters);
                $items = collect($paginator->items())->map(fn ($ins) => [
                    'id' => $ins->id,
                    'type' => $ins->type,
                    'severity' => $ins->severity,
                    'opportunity_score' => $ins->opportunity_score,
                    'title' => $ins->title,
                    'suggested_action' => $ins->suggested_action,
                    'article_id' => $ins->article_id,
                ])->all();

                return [
                    'tool' => $this->getName(),
                    'action' => 'list',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'total_items' => count($items),
                    'items' => $items,
                    'summary_text' => sprintf(
                        'Danh sách %d cơ hội & cảnh báo SEO nổi bật nhất trên website %s.',
                        count($items),
                        $website->name
                    ),
                    'credits_deducted' => 0,
                ];

            case 'summary':
            default:
                $summary = $this->intelligenceService->getSummary($website->id);

                $summaryText = sprintf(
                    'Tổng quan SEO Intelligence website %s: %d cảnh báo đang kích hoạt (%d nguy cấp, %d cảnh báo, %d cơ hội tiềm năng). Điểm tiềm năng trung bình: %s/100.',
                    $website->name,
                    $summary['total_active'],
                    $summary['critical_count'],
                    $summary['warning_count'],
                    $summary['opportunity_count'],
                    $summary['avg_opportunity_score']
                );

                return [
                    'tool' => $this->getName(),
                    'action' => 'summary',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'website_domain' => $website->domain,
                    'summary' => $summary,
                    'summary_text' => $summaryText,
                    'credits_deducted' => 0,
                ];
        }
    }
}
