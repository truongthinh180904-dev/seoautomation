<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\TopicClusterService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TopicClusterAdapter implements ChatToolInterface
{
    public function __construct(
        protected TopicClusterService $topicClusterService
    ) {}

    public function getName(): string
    {
        return 'topic_cluster';
    }

    public function getTitle(): string
    {
        return 'Thiết lập Topic Cluster';
    }

    public function getDescription(): string
    {
        return 'Xây dựng cấu trúc cụm chủ đề đa tầng (Pillar - Cluster), phân bổ bài vệ tinh chống cannibalization.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return (int) config('ai_costs.credits.topic_cluster_generation', 2);
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pillar_keyword'],
            'properties' => [
                'pillar_keyword' => [
                    'type' => 'string',
                    'description' => 'Từ khóa hoặc chủ đề trụ cột (Pillar Topic) để lập cụm.',
                ],
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID của Website thuộc Workspace (tùy chọn, tự động nhận diện nếu có).',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['generate', 'list'],
                    'default' => 'generate',
                    'description' => 'Hành động: sinh cụm chủ đề mới hoặc liệt kê các cụm hiện có.',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $pillar = trim((string) ($params['pillar_keyword'] ?? $params['seed_topic'] ?? $params['topic'] ?? ''));
        $action = (string) ($params['action'] ?? 'generate');

        // Tìm website hợp lệ thuộc Workspace
        $websiteId = ! empty($params['website_id']) ? (int) $params['website_id'] : null;
        $website = null;
        if ($websiteId) {
            $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
        }
        if (! $website) {
            $website = Website::where('workspace_id', $workspace->id)->first();
        }

        if ($action === 'list') {
            $clusters = $this->topicClusterService->listClusters($workspace, $website?->id, $params);

            return [
                'tool' => $this->getName(),
                'action' => 'list',
                'total_clusters' => $clusters->count(),
                'clusters' => $clusters->toArray(),
            ];
        }

        if ($pillar === '') {
            throw new InvalidArgumentException('Từ khóa trụ cột (pillar_keyword) không được để trống.');
        }

        if ($website) {
            $result = $this->topicClusterService->generateAiClusters(
                workspace: $workspace,
                websiteId: $website->id,
                seedTopic: $pillar,
                options: $params,
                actor: $actor
            );

            return [
                'tool' => $this->getName(),
                'action' => 'generate',
                'website_id' => $website->id,
                'website_name' => $website->name,
                'pillar_keyword' => $pillar,
                'clusters' => $result['clusters'] ?? [],
                'total_clusters' => count($result['clusters'] ?? []),
                'credits_deducted' => $result['credits_deducted'] ?? 2,
            ];
        }

        // Trường hợp workspace chưa cấu hình website nào
        $syntheticClusters = [
            [
                'name' => "Cụm chủ đề: {$pillar}",
                'slug' => Str::slug($pillar),
                'pillar_keyword' => $pillar,
                'sub_topics' => [
                    "Hướng dẫn cơ bản về {$pillar}",
                    "Bảng giá và chi phí {$pillar}",
                    "So sánh các phương án {$pillar}",
                    "Những lưu ý và sai lầm khi chọn {$pillar}",
                ],
            ],
        ];

        return [
            'tool' => $this->getName(),
            'action' => 'generate',
            'pillar_keyword' => $pillar,
            'clusters' => $syntheticClusters,
            'total_clusters' => 1,
            'credits_deducted' => 0,
        ];
    }
}
