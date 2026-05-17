<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\Enums\AgentType;
use App\Models\SerpResult;
use App\Services\AI\AIProviderService;
use App\Services\AI\PromptBuilderService;
use App\Services\SEO\SerpResearchService;

class SerpResearchAgent extends BaseAgent
{
    public function __construct(
        AIProviderService $aiProviderService,
        PromptBuilderService $promptBuilderService,
        protected SerpResearchService $serpResearchService
    ) {
        parent::__construct($aiProviderService, $promptBuilderService);
    }

    public function getType(): AgentType
    {
        return AgentType::SERP_RESEARCH;
    }

    protected function run(array $context): AgentResultDTO
    {
        $keyword = $context['keyword'] ?? null;
        $keywordId = $context['keyword_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        if (!$keyword || !$keywordId || !$tenantId) {
            throw new \InvalidArgumentException("Missing required context: keyword, keyword_id, tenant_id");
        }

        // 1. Call SerpResearchService
        $serpResults = $this->serpResearchService->search($keyword, $tenantId);

        // 2. For each result: create SerpResult model record
        foreach ($serpResults as $dto) {
            SerpResult::updateOrCreate(
                [
                    'keyword_id' => $keywordId,
                    'position' => $dto->position,
                ],
                [
                    'url' => $dto->url,
                    'title' => $dto->title,
                    'description' => $dto->description,
                    'domain' => $dto->domain,
                    'raw_data' => $dto->rawData,
                ]
            );
        }

        // 3. Return AgentResultDTO::success
        return AgentResultDTO::success(
            agent: $this->getType(),
            data: ['serp_results' => $serpResults]
        );
    }
}
