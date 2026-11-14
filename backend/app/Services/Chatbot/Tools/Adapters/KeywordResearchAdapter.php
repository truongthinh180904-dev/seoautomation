<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use App\Services\SEO\KeywordResearchService;
use InvalidArgumentException;

class KeywordResearchAdapter implements ChatToolInterface
{
    public function __construct(
        protected KeywordResearchService $keywordResearchService
    ) {}

    public function getName(): string
    {
        return 'keyword_research';
    }

    public function getTitle(): string
    {
        return 'Nghiên cứu & Khám phá Từ khóa';
    }

    public function getDescription(): string
    {
        return 'Tìm kiếm ý tưởng từ khóa, volume, KD, search intent và gom cụm ngữ nghĩa tự động.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        return (int) config('ai_costs.credits.keyword_research_batch', 2);
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Từ khóa hạt giống (Seed Keyword) để nghiên cứu và mở rộng.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Số lượng từ khóa mong muốn (từ 5 đến 100, mặc định 20).',
                    'default' => 20,
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
        $query = trim((string) ($params['query'] ?? $params['seed_keyword'] ?? $params['keyword'] ?? ''));
        if ($query === '') {
            throw new InvalidArgumentException('Từ khóa hạt giống không được để trống.');
        }

        $options = array_merge([
            'limit' => 20,
            'language' => 'vi',
            'country' => 'vn',
        ], $params);

        $result = $this->keywordResearchService->research(
            workspace: $workspace,
            query: $query,
            options: $options,
            actor: $actor
        );

        $keywords = $result['keywords'] ?? [];

        return [
            'tool' => $this->getName(),
            'query' => $query,
            'total_keywords' => count($keywords),
            'keywords' => $keywords,
            'clusters' => $result['clusters'] ?? [],
            'lsi_keywords' => $result['lsi_keywords'] ?? [],
            'credits_deducted' => $result['credits_deducted'] ?? 2,
        ];
    }
}
