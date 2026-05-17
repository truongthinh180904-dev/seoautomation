<?php

namespace App\Providers\AI;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Exceptions\AI\ProviderException;
use App\Exceptions\AI\RateLimitException;
use App\Providers\AI\Contracts\AIProviderInterface;
use OpenAI\Client;
use Throwable;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        protected Client $client
    ) {}

    public function complete(AIRequestDTO $request): AIResponseDTO
    {
        $startTime = microtime(true);
        $model = $request->model ?: 'gpt-4o-mini';
        
        try {
            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $request->systemPrompt],
                    ['role' => 'user', 'content' => $request->userPrompt],
                ],
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
            ]);

            $content = $response->choices[0]->message->content ?? '';
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $totalTokens = $response->usage->totalTokens ?? 0;
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return new AIResponseDTO(
                content: $content,
                provider: $this->getProvider(),
                model: $model,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $totalTokens,
                latencyMs: $latencyMs,
                rawResponse: $response->toArray()
            );

        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'rate limit') || $e->getCode() === 429) {
                throw new RateLimitException("OpenAI Rate Limit exceeded: " . $e->getMessage(), 429, $e);
            }
            throw new ProviderException("OpenAI Error: " . $e->getMessage(), $this->getProvider(), $e->getCode(), $e);
        }
    }

    public function getProvider(): AIProvider
    {
        return AIProvider::OPENAI;
    }

    public function isAvailable(): bool
    {
        return !empty(config('openai.api_key'));
    }
}
