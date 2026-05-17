<?php

namespace App\Providers\AI;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Exceptions\AI\ProviderException;
use App\Exceptions\AI\RateLimitException;
use App\Providers\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AIProviderInterface
{
    public function complete(AIRequestDTO $request): AIResponseDTO
    {
        $startTime = microtime(true);
        $apiKey = config('services.anthropic.api_key');
        
        if (!$apiKey) {
            throw new ProviderException("Anthropic API key is missing", $this->getProvider());
        }

        $modelMap = [
            'gpt-4o' => 'claude-3-opus-20240229',
            'gpt-4o-mini' => 'claude-3-haiku-20240307',
            'gpt-3.5-turbo' => 'claude-3-haiku-20240307',
        ];
        
        $model = $modelMap[$request->model] ?? 'claude-3-sonnet-20240229';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'system' => $request->systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $request->userPrompt]
            ],
            'max_tokens' => 4096,
        ]);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            throw new RateLimitException("Anthropic Rate Limit Exceeded");
        }

        if (!$response->successful()) {
            Log::error("Anthropic API Error: " . $response->body());
            throw new ProviderException("Anthropic API Error: " . $response->status(), $this->getProvider());
        }

        $data = $response->json();
        
        return new AIResponseDTO(
            content: $data['content'][0]['text'] ?? '',
            promptTokens: $data['usage']['input_tokens'] ?? 0,
            completionTokens: $data['usage']['output_tokens'] ?? 0,
            totalTokens: ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            provider: $this->getProvider(),
            model: $model,
            latencyMs: $latencyMs
        );
    }

    public function getProvider(): AIProvider
    {
        return AIProvider::ANTHROPIC;
    }

    public function isAvailable(): bool
    {
        return !empty(config('services.anthropic.api_key'));
    }
}
