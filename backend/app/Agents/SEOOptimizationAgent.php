<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;

class SEOOptimizationAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::SEO_OPTIMIZATION;
    }

    protected function run(array $context): AgentResultDTO
    {
        $keyword = $context['keyword'] ?? null;
        $articleContent = $context['article_content'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        if (!$keyword || !$articleContent || !$tenantId) {
            throw new \InvalidArgumentException("Missing required context fields.");
        }

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'keyword' => $keyword,
            'article_content' => mb_substr($articleContent, 0, 20000)
        ], $tenantId);

        $systemPrompt = $promptData['system_prompt'] . "\n\nYou MUST return the response ONLY as a JSON object with this exact structure: {\"seo_title\": \"string (max 60 chars)\", \"seo_description\": \"string (max 160 chars)\", \"faqs\": [{\"question\": \"string\", \"answer\": \"string\"}]}. Do not include markdown formatting or other text.";

        $request = new AIRequestDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $promptData['user_prompt'],
            model: 'gpt-4o-mini',
            agentType: $this->getType(),
            tenantId: $tenantId,
            articleId: $context['article_id'] ?? null,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
        );

        $response = $this->callAI($request);
        $content = $this->cleanJsonResponse($response->content);
        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return AgentResultDTO::failure($this->getType(), "Failed to parse JSON SEO data: " . json_last_error_msg());
        }

        $seoTitle = $parsed['seo_title'] ?? '';
        $seoDescription = $parsed['seo_description'] ?? '';
        $faqs = $parsed['faqs'] ?? [];

        if (empty($seoTitle) || empty($seoDescription)) {
            return AgentResultDTO::failure($this->getType(), "SEO validation failed: Missing seo_title or seo_description");
        }

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: [
                'seo_title' => mb_substr($seoTitle, 0, 60),
                'seo_description' => mb_substr($seoDescription, 0, 160),
                'faqs' => is_array($faqs) ? $faqs : [],
            ],
            tokens: $response->totalTokens,
            latency: $response->latencyMs
        );
    }

    protected function cleanJsonResponse(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
        } elseif (str_starts_with($content, '```')) {
            $content = substr($content, 3);
        }
        if (str_ends_with($content, '```')) {
            $content = substr($content, 0, -3);
        }
        return trim($content);
    }
}
