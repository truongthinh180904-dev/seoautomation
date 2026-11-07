<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\RankTrackedKeyword;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\RankTracking\RankTrackingService;
use InvalidArgumentException;

class RankTrackerToolAdapter implements ChatToolInterface
{
    public function __construct(
        protected RankTrackingService $rankTrackingService
    ) {}

    public function getName(): string
    {
        return 'rank_tracker';
    }

    public function getTitle(): string
    {
        return 'Kiểm tra & Theo dõi Thứ hạng Từ khóa (Rank Tracker)';
    }

    public function getDescription(): string
    {
        return 'Tra cứu thứ hạng từ khóa mục tiêu trong Top 100 Google SERP, biến động tăng/giảm vị trí và lịch sử thứ hạng theo ngày (0 credit, tức thì).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0; // Tra cứu dữ liệu đã theo dõi hoàn toàn miễn phí
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
                    'description' => 'ID website cần kiểm tra thứ hạng (tùy chọn, mặc định lấy website đầu tiên).',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['summary', 'list', 'check_keyword', 'history'],
                    'default' => 'summary',
                    'description' => 'Hành động tra cứu: summary (tổng quan phân bố Top 3, Top 10, tăng/giảm), list (danh sách từ khóa đang theo dõi), check_keyword (tra cứu thứ hạng của 1 từ khóa cụ thể), history (lịch sử vị trí 30 ngày qua).',
                ],
                'keyword' => [
                    'type' => 'string',
                    'description' => 'Từ khóa cụ thể cần kiểm tra (bắt buộc đối với action check_keyword và history).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'description' => 'Số lượng từ khóa hiển thị (áp dụng cho action list).',
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 30,
                    'description' => 'Số ngày xem lịch sử (mặc định 30 ngày).',
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
                'message' => 'Chưa có website nào trong Không gian làm việc. Vui lòng thêm website trước khi theo dõi thứ hạng từ khóa.',
                'credits_deducted' => 0,
            ];
        }

        $action = (string) ($params['action'] ?? 'summary');
        $keywordText = ! empty($params['keyword']) ? trim((string) $params['keyword']) : '';

        switch ($action) {
            case 'check_keyword':
                if ($keywordText === '') {
                    throw new InvalidArgumentException('Cần cung cấp từ khóa để kiểm tra thứ hạng.');
                }

                /** @var RankTrackedKeyword|null $tracked */
                $tracked = RankTrackedKeyword::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('website_id', $website->id)
                    ->where(function ($q) use ($keywordText) {
                        $q->where('keyword', $keywordText)
                            ->orWhere('keyword', 'like', '%'.$keywordText.'%');
                    })
                    ->first();

                if (! $tracked) {
                    return [
                        'tool' => $this->getName(),
                        'action' => 'check_keyword',
                        'keyword' => $keywordText,
                        'website_id' => $website->id,
                        'website_name' => $website->name,
                        'is_tracked' => false,
                        'current_position' => null,
                        'summary_text' => sprintf(
                            'Từ khóa "%s" hiện chưa nằm trong danh sách theo dõi của website %s. Bạn có thể thêm từ khóa này vào danh mục Rank Tracking để tự động cào thứ hạng hàng ngày.',
                            $keywordText,
                            $website->name
                        ),
                        'credits_deducted' => 0,
                    ];
                }

                $delta = null;
                $changeLabel = 'Không đổi';
                if ($tracked->current_position !== null && $tracked->previous_position !== null) {
                    $delta = $tracked->previous_position - $tracked->current_position; // Số dương nghĩa là tăng bậc (tốt hơn)
                    if ($delta > 0) {
                        $changeLabel = sprintf('Tăng +%d bậc', $delta);
                    } elseif ($delta < 0) {
                        $changeLabel = sprintf('Tụt %d bậc', abs($delta));
                    }
                } elseif ($tracked->current_position !== null && $tracked->previous_position === null) {
                    $changeLabel = 'Mới lọt Top';
                } elseif ($tracked->current_position === null) {
                    $changeLabel = 'Chưa vào Top 100';
                }

                $summaryText = sprintf(
                    'Từ khóa "%s" trên %s đang xếp hạng #%s (%s, vị trí tốt nhất: #%s).',
                    $tracked->keyword,
                    $website->name,
                    $tracked->current_position !== null ? (string) $tracked->current_position : '> 100',
                    $changeLabel,
                    $tracked->best_position !== null ? (string) $tracked->best_position : 'Chưa có'
                );

                return [
                    'tool' => $this->getName(),
                    'action' => 'check_keyword',
                    'keyword_id' => $tracked->id,
                    'keyword' => $tracked->keyword,
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'is_tracked' => true,
                    'current_position' => $tracked->current_position,
                    'previous_position' => $tracked->previous_position,
                    'best_position' => $tracked->best_position,
                    'target_url' => $tracked->target_url,
                    'device' => $tracked->device,
                    'country' => $tracked->country,
                    'change_label' => $changeLabel,
                    'last_checked_at' => $tracked->last_checked_at?->toIso8601String(),
                    'summary_text' => $summaryText,
                    'credits_deducted' => 0,
                ];

            case 'history':
                if ($keywordText === '') {
                    throw new InvalidArgumentException('Cần cung cấp từ khóa để xem lịch sử thứ hạng.');
                }

                /** @var RankTrackedKeyword|null $tracked */
                $tracked = RankTrackedKeyword::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('website_id', $website->id)
                    ->where('keyword', 'like', '%'.$keywordText.'%')
                    ->first();

                if (! $tracked) {
                    return [
                        'tool' => $this->getName(),
                        'action' => 'history',
                        'keyword' => $keywordText,
                        'website_id' => $website->id,
                        'is_tracked' => false,
                        'items' => [],
                        'summary_text' => "Chưa có dữ liệu lịch sử cho từ khóa \"{$keywordText}\".",
                        'credits_deducted' => 0,
                    ];
                }

                $days = max(7, min(90, (int) ($params['days'] ?? 30)));
                $history = $this->rankTrackingService->getKeywordHistory($tracked, $days);

                return [
                    'tool' => $this->getName(),
                    'action' => 'history',
                    'keyword_id' => $tracked->id,
                    'keyword' => $tracked->keyword,
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'days' => $days,
                    'total_points' => $history->count(),
                    'items' => $history->map(fn ($h) => [
                        'check_date' => $h->check_date->toDateString(),
                        'position' => $h->position,
                        'found_url' => $h->found_url,
                    ])->all(),
                    'summary_text' => sprintf(
                        'Lịch sử %d ngày qua của từ khóa "%s" ghi nhận %d mốc kiểm tra thứ hạng.',
                        $days,
                        $tracked->keyword,
                        $history->count()
                    ),
                    'credits_deducted' => 0,
                ];

            case 'list':
                $limit = max(1, min(50, (int) ($params['limit'] ?? 10)));
                $paginator = $this->rankTrackingService->getTrackedKeywords($website->id, [
                    'per_page' => $limit,
                    'search' => $keywordText ?: null,
                ]);

                $items = collect($paginator->items())->map(fn ($k) => [
                    'id' => $k->id,
                    'keyword' => $k->keyword,
                    'current_position' => $k->current_position,
                    'previous_position' => $k->previous_position,
                    'best_position' => $k->best_position,
                    'device' => $k->device,
                    'country' => $k->country,
                    'last_checked_at' => $k->last_checked_at?->toIso8601String(),
                ])->all();

                return [
                    'tool' => $this->getName(),
                    'action' => 'list',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'total_tracked' => $paginator->total(),
                    'items' => $items,
                    'summary_text' => sprintf(
                        'Danh sách %d từ khóa đang theo dõi trên website %s (Tổng số: %d từ khóa).',
                        count($items),
                        $website->name,
                        $paginator->total()
                    ),
                    'credits_deducted' => 0,
                ];

            case 'summary':
            default:
                $summary = $this->rankTrackingService->getWebsiteSummary($website->id);

                $summaryText = sprintf(
                    'Tổng quan thứ hạng website %s: Theo dõi %d từ khóa (%d trong Top 3, %d trong Top 10, %d trong Top 100). Biến động: %d tăng, %d giảm, %d giữ nguyên.',
                    $website->name,
                    $summary['total_keywords'],
                    $summary['top_3'],
                    $summary['top_10'],
                    $summary['top_100'],
                    $summary['improved'],
                    $summary['declined'],
                    $summary['unchanged']
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
