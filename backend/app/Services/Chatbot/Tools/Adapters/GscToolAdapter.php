<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\Analytics\SeoAnalyticsAggregatorService;
use Carbon\Carbon;
use InvalidArgumentException;

class GscToolAdapter implements ChatToolInterface
{
    public function __construct(
        protected SeoAnalyticsAggregatorService $analyticsAggregator
    ) {}

    public function getName(): string
    {
        return 'gsc_query';
    }

    public function getTitle(): string
    {
        return 'Tra cứu Hiệu suất Tìm kiếm Google Search Console';
    }

    public function getDescription(): string
    {
        return 'Tra cứu số liệu Clicks, Impressions, CTR, Vị trí trung bình, Top Queries và Top Pages từ Google Search Console (0 credit, tức thì).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0; // Tra cứu dữ liệu đã đồng bộ hoàn toàn miễn phí
    }

    public function isSensitive(): bool
    {
        return false; // Tra cứu số liệu chỉ đọc
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID của website cần tra cứu (tùy chọn, mặc định lấy website đầu tiên của Không gian làm việc).',
                ],
                'date_from' => [
                    'type' => 'string',
                    'description' => 'Ngày bắt đầu tra cứu (YYYY-MM-DD, mặc định 28 ngày trước).',
                ],
                'date_to' => [
                    'type' => 'string',
                    'description' => 'Ngày kết thúc tra cứu (YYYY-MM-DD, mặc định hôm nay).',
                ],
                'query_type' => [
                    'type' => 'string',
                    'enum' => ['overview', 'top_queries', 'top_pages', 'declining_pages', 'low_ctr'],
                    'default' => 'overview',
                    'description' => 'Loại báo cáo SEO cần lấy: overview (tổng quan & so sánh kỳ trước), top_queries (từ khóa nhiều clicks nhất), top_pages (trang nhận nhiều click nhất), declining_pages (trang bị tụt clicks), low_ctr (từ khóa impressions cao nhưng CTR thấp).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 5,
                    'description' => 'Số lượng kết quả cần lấy (mặc định 5).',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Từ khóa tìm kiếm lọc theo truy vấn hoặc URL.',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        // 1. Xác định Website thuộc Workspace (Cô lập Multi-Tenant)
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
                'message' => 'Chưa có website nào được kết nối trong Không gian làm việc này. Vui lòng thêm website và kết nối Google Search Console.',
                'credits_deducted' => 0,
            ];
        }

        $dateTo = ! empty($params['date_to']) ? (string) $params['date_to'] : Carbon::today()->toDateString();
        $dateFrom = ! empty($params['date_from']) ? (string) $params['date_from'] : Carbon::parse($dateTo)->subDays(27)->toDateString();
        $queryType = (string) ($params['query_type'] ?? $params['action'] ?? 'overview');
        $limit = max(1, min(50, (int) ($params['limit'] ?? 5)));
        $search = ! empty($params['search']) ? trim((string) $params['search']) : null;

        switch ($queryType) {
            case 'top_queries':
                $queries = $this->analyticsAggregator->getTopQueries(
                    workspaceId: $workspace->id,
                    websiteId: $website->id,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    limit: $limit,
                    search: $search
                );

                return [
                    'tool' => $this->getName(),
                    'query_type' => 'top_queries',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'total_items' => count($queries),
                    'items' => $queries,
                    'summary_text' => sprintf(
                        'Đã tìm thấy %d từ khóa hàng đầu dẫn traffic về website %s từ %s đến %s.',
                        count($queries),
                        $website->name,
                        $dateFrom,
                        $dateTo
                    ),
                    'credits_deducted' => 0,
                ];

            case 'top_pages':
                $pages = $this->analyticsAggregator->getTopPages(
                    workspaceId: $workspace->id,
                    websiteId: $website->id,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    limit: $limit,
                    search: $search
                );

                return [
                    'tool' => $this->getName(),
                    'query_type' => 'top_pages',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'total_items' => count($pages),
                    'items' => $pages,
                    'summary_text' => sprintf(
                        'Đã tìm thấy %d trang nhận nhiều lượt clicks nhất trên %s.',
                        count($pages),
                        $website->name
                    ),
                    'credits_deducted' => 0,
                ];

            case 'declining_pages':
                $declining = $this->analyticsAggregator->getDecliningPages(
                    workspaceId: $workspace->id,
                    websiteId: $website->id,
                    days: 28,
                    limit: $limit
                );

                return [
                    'tool' => $this->getName(),
                    'query_type' => 'declining_pages',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'total_items' => count($declining),
                    'items' => $declining,
                    'summary_text' => sprintf(
                        'Phát hiện %d trang có lưu lượng clicks sụt giảm so với chu kỳ trước.',
                        count($declining)
                    ),
                    'credits_deducted' => 0,
                ];

            case 'low_ctr':
                $lowCtr = $this->analyticsAggregator->getLowCtrOpportunities(
                    workspaceId: $workspace->id,
                    websiteId: $website->id,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    minImpressions: 200,
                    maxCtr: 0.02,
                    limit: $limit
                );

                return [
                    'tool' => $this->getName(),
                    'query_type' => 'low_ctr',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'total_items' => count($lowCtr),
                    'items' => $lowCtr,
                    'summary_text' => sprintf(
                        'Phát hiện %d từ khóa có lượt hiển thị cao nhưng CTR dưới 2%% (cơ hội tối ưu Title/Meta).',
                        count($lowCtr)
                    ),
                    'credits_deducted' => 0,
                ];

            case 'overview':
            default:
                $overview = $this->analyticsAggregator->getPerformanceOverview(
                    workspaceId: $workspace->id,
                    websiteId: $website->id,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    compare: true
                );

                $summary = $overview['summary'] ?? [];
                $clicks = $summary['clicks'] ?? [];
                $impressions = $summary['impressions'] ?? [];
                $ctr = $summary['ctr'] ?? [];
                $position = $summary['position'] ?? [];

                $summaryText = sprintf(
                    'Hiệu suất tìm kiếm website %s (%s đến %s): %s clicks (%s%s%%), %s lượt hiển thị (%s%s%%), CTR trung bình %s%%, vị trí trung bình %s.',
                    $website->name,
                    $dateFrom,
                    $dateTo,
                    number_format((float) ($clicks['current'] ?? 0)),
                    ((float) ($clicks['delta_percent'] ?? 0) >= 0 ? '+' : ''),
                    number_format((float) ($clicks['delta_percent'] ?? 0), 1),
                    number_format((float) ($impressions['current'] ?? 0)),
                    ((float) ($impressions['delta_percent'] ?? 0) >= 0 ? '+' : ''),
                    number_format((float) ($impressions['delta_percent'] ?? 0), 1),
                    number_format(((float) ($ctr['current'] ?? 0)) * 100, 2),
                    number_format((float) ($position['current'] ?? 0), 1)
                );

                return [
                    'tool' => $this->getName(),
                    'query_type' => 'overview',
                    'website_id' => $website->id,
                    'website_name' => $website->name,
                    'website_domain' => $website->domain,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'summary' => $summary,
                    'summary_text' => $summaryText,
                    'credits_deducted' => 0,
                ];
        }
    }
}
