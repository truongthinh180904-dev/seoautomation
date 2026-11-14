<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\Analytics\SeoAnalyticsAggregatorService;
use Carbon\Carbon;
use InvalidArgumentException;

class Ga4ToolAdapter implements ChatToolInterface
{
    public function __construct(
        protected SeoAnalyticsAggregatorService $analyticsAggregator
    ) {}

    public function getName(): string
    {
        return 'ga4_query';
    }

    public function getTitle(): string
    {
        return 'Tra cứu Lượt truy cập & Tương tác Google Analytics 4';
    }

    public function getDescription(): string
    {
        return 'Tra cứu số liệu Sessions, Active Users, Engagement Rate, Thời lượng phiên trung bình và Conversions theo Landing Page từ GA4 (0 credit, tức thì).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return 0; // Tra cứu dữ liệu GA4 đã đồng bộ hoàn toàn miễn phí
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
                    'description' => 'ID của website cần tra cứu (tùy chọn, mặc định lấy website đầu tiên).',
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
                    'enum' => ['overview', 'top_landing_pages'],
                    'default' => 'overview',
                    'description' => 'Loại báo cáo GA4: overview (tổng quan sessions, active users, engagement rate), top_landing_pages (các trang đích có nhiều lượt xem và phiên truy cập nhất).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 5,
                    'description' => 'Số lượng trang đích hiển thị (áp dụng cho top_landing_pages).',
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
                'message' => 'Chưa có website nào được kết nối trong Không gian làm việc. Vui lòng thêm website và kết nối Google Analytics 4.',
                'credits_deducted' => 0,
            ];
        }

        $dateTo = ! empty($params['date_to']) ? (string) $params['date_to'] : Carbon::today()->toDateString();
        $dateFrom = ! empty($params['date_from']) ? (string) $params['date_from'] : Carbon::parse($dateTo)->subDays(27)->toDateString();
        $queryType = (string) ($params['query_type'] ?? $params['action'] ?? 'overview');
        $limit = max(1, min(50, (int) ($params['limit'] ?? 5)));

        if ($queryType === 'top_landing_pages') {
            $pages = $this->analyticsAggregator->getTopPages(
                workspaceId: $workspace->id,
                websiteId: $website->id,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                limit: $limit,
                sortBy: 'sessions',
                sortDir: 'desc'
            );

            return [
                'tool' => $this->getName(),
                'query_type' => 'top_landing_pages',
                'website_id' => $website->id,
                'website_name' => $website->name,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_items' => count($pages),
                'items' => $pages,
                'summary_text' => sprintf(
                    'Đã tìm thấy %d trang đích có lượt truy cập (sessions) cao nhất trên website %s.',
                    count($pages),
                    $website->name
                ),
                'credits_deducted' => 0,
            ];
        }

        // Mặc định: overview
        $overview = $this->analyticsAggregator->getPerformanceOverview(
            workspaceId: $workspace->id,
            websiteId: $website->id,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            compare: true
        );

        $summary = $overview['summary'] ?? [];
        $sessions = $summary['sessions'] ?? [];
        $users = $summary['active_users'] ?? [];
        $engagement = $summary['engagement_rate'] ?? [];
        $conversions = $summary['conversions'] ?? [];

        $summaryText = sprintf(
            'Lưu lượng người dùng GA4 website %s (%s đến %s): %s sessions (%s%s%%), %s người dùng hoạt động (%s%s%%), tỷ lệ tương tác %s%%, %s lượt chuyển đổi.',
            $website->name,
            $dateFrom,
            $dateTo,
            number_format((float) ($sessions['current'] ?? 0)),
            ((float) ($sessions['delta_percent'] ?? 0) >= 0 ? '+' : ''),
            number_format((float) ($sessions['delta_percent'] ?? 0), 1),
            number_format((float) ($users['current'] ?? 0)),
            ((float) ($users['delta_percent'] ?? 0) >= 0 ? '+' : ''),
            number_format((float) ($users['delta_percent'] ?? 0), 1),
            number_format(((float) ($engagement['current'] ?? 0)) * 100, 2),
            number_format((float) ($conversions['current'] ?? 0))
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
            'sessions' => $sessions,
            'active_users' => $users,
            'engagement_rate' => $engagement,
            'conversions' => $conversions,
            'summary_text' => $summaryText,
            'credits_deducted' => 0,
        ];
    }
}
