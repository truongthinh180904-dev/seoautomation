<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\ContentGapService;
use InvalidArgumentException;

class ContentGapAdapter implements ChatToolInterface
{
    public function __construct(
        protected ContentGapService $contentGapService
    ) {}

    public function getName(): string
    {
        return 'content_gap';
    }

    public function getTitle(): string
    {
        return 'Phát hiện Lỗ hổng Nội dung (Content Gap)';
    }

    public function getDescription(): string
    {
        return 'So sánh ma trận từ khóa của website với đối thủ để tìm các chủ đề đối thủ đang xếp hạng cao mà website chưa có.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return (int) config('ai_costs.credits.content_gap_analysis', 3);
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['competitor_domain'],
            'properties' => [
                'competitor_domain' => [
                    'type' => 'string',
                    'description' => 'Tên miền của website đối thủ cần phân tích (ví dụ: nhakhoasmile.vn).',
                ],
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID Website mục tiêu của bạn để đối chiếu khoảng trống nội dung.',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $domain = trim((string) ($params['competitor_domain'] ?? $params['domain'] ?? ''));
        if ($domain === '') {
            throw new InvalidArgumentException('Tên miền đối thủ không được để trống.');
        }

        $websiteId = ! empty($params['website_id']) ? (int) $params['website_id'] : null;
        $website = null;
        if ($websiteId) {
            $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
        }
        if (! $website) {
            $website = Website::where('workspace_id', $workspace->id)->first();
        }

        if (! $website) {
            throw new InvalidArgumentException('Workspace chưa có website nào để phân tích đối chiếu Content Gap. Hãy tạo website trước.');
        }

        $result = $this->contentGapService->analyzeGap(
            workspace: $workspace,
            websiteId: $website->id,
            competitorDomain: $domain,
            options: $params,
            actor: $actor
        );

        $gaps = $result['gaps'] ?? [];

        return [
            'tool' => $this->getName(),
            'competitor_domain' => $domain,
            'website_id' => $website->id,
            'website_name' => $website->name,
            'total_gaps_found' => count($gaps),
            'high_opportunity_count' => $result['high_opportunity_count'] ?? 0,
            'average_opportunity_score' => $result['average_opportunity_score'] ?? 0,
            'gaps' => array_slice($gaps, 0, 15),
            'credits_deducted' => $result['credits_deducted'] ?? 3,
        ];
    }
}
