<?php

namespace App\Agents;

use App\Agents\Contracts\AgentInterface;
use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Models\AILog;
use App\Services\AI\AIProviderService;
use App\Services\AI\PromptBuilderService;
use Throwable;

abstract class BaseAgent implements AgentInterface
{
    public function __construct(
        protected AIProviderService $aiProviderService,
        protected PromptBuilderService $promptBuilderService
    ) {}

    abstract protected function run(array $context): AgentResultDTO;

    public function execute(array $context): AgentResultDTO
    {
        $startTime = microtime(true);

        try {
            $result = $this->run($context);
            
            if ($result->success) {
                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                // Update latency if it was not set by the AI provider explicitly
                if ($result->latencyMs === 0) {
                    $result->latencyMs = $latencyMs;
                }
            }
            
            return $result;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            
            if (isset($context['article_id']) || isset($context['keyword_id'])) {
                AILog::create([
                    'tenant_id' => $context['tenant_id'] ?? null,
                    'article_id' => $context['article_id'] ?? null,
                    'keyword_id' => $context['keyword_id'] ?? null,
                    'agent_type' => $this->getType()->value,
                    'provider' => 'system',
                    'model' => 'none',
                    'status' => 'failed',
                    'error_message' => "Agent Execution Error: " . $error,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'cost_usd' => 0,
                    'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                ]);
            }

            return AgentResultDTO::failure($this->getType(), $error);
        }
    }

    protected function callAI(AIRequestDTO $request): AIResponseDTO
    {
        return $this->aiProviderService->complete($request);
    }
}
