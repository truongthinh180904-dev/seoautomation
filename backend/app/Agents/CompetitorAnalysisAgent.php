<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\Enums\AgentType;
use App\Models\CompetitorAnalysis;
use App\Services\AI\AIProviderService;
use App\Services\AI\PromptBuilderService;
use App\Services\SEO\CompetitorCrawlService;

class CompetitorAnalysisAgent extends BaseAgent
{
    public function __construct(
        AIProviderService $aiProviderService,
        PromptBuilderService $promptBuilderService,
        protected CompetitorCrawlService $crawlService,
        protected ContentAnalysisAgent $contentAnalysisAgent
    ) {
        parent::__construct($aiProviderService, $promptBuilderService);
    }

    public function getType(): AgentType
    {
        return AgentType::COMPETITOR_ANALYSIS;
    }

    protected function run(array $context): AgentResultDTO
    {
        $url = $context['url'] ?? null;
        $keyword = $context['keyword'] ?? null;
        $keywordId = $context['keyword_id'] ?? null;
        $serpResultId = $context['serp_result_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        if (!$url || !$keyword || !$keywordId || !$serpResultId || !$tenantId) {
            throw new \InvalidArgumentException("Missing required context fields.");
        }

        // 1. Crawl URL
        try {
            $crawlData = $this->crawlService->crawl($url);
        } catch (\Exception $e) {
            return AgentResultDTO::failure($this->getType(), "Crawl Failed: " . $e->getMessage());
        }

        // 2. Extract Semantics using ContentAnalysisAgent
        $analysisResult = $this->contentAnalysisAgent->execute([
            'keyword' => $keyword,
            'keyword_id' => $keywordId,
            'content' => $crawlData['raw_text'],
            'tenant_id' => $tenantId,
        ]);

        $semanticData = $analysisResult->success ? $analysisResult->data : [];

        // 3. Store Results
        $analysisRecord = CompetitorAnalysis::updateOrCreate(
            ['serp_result_id' => $serpResultId, 'keyword_id' => $keywordId],
            [
                'tenant_id' => $tenantId,
                'url' => $url,
                'word_count' => $crawlData['word_count'] ?? null,
                'heading_structure' => $crawlData['heading_structure'] ?? null,
                'faqs' => $crawlData['faqs'] ?? null,
                'semantic_entities' => $semanticData['semantic_entities'] ?? null,
                'semantic_keywords' => $semanticData['semantic_keywords'] ?? null,
                'search_intent' => $semanticData['search_intent'] ?? null,
                'raw_html_hash' => md5($crawlData['raw_text'] ?? ''),
                'analysis_completed' => true,
                'analyzed_at' => now(),
            ]
        );

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: ['competitor_analysis_id' => $analysisRecord->id],
            tokens: $analysisResult->tokensUsed ?? 0,
            latency: $analysisResult->latencyMs ?? 0
        );
    }
}
