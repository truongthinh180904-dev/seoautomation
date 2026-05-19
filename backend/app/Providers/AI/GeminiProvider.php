<?php

namespace App\Providers\AI;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Exceptions\AI\ProviderException;
use App\Exceptions\AI\RateLimitException;
use App\Providers\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiProvider implements AIProviderInterface
{
    public function complete(AIRequestDTO $request): AIResponseDTO
    {
        $startTime = microtime(true);
        $model = $request->model ?: config('ai.providers.gemini.default_model', 'gemini-2.5-flash');
        $apiKey = config('ai.providers.gemini.api_key');

        try {
            $response = $this->sendGenerateRequest($model, $request, $apiKey);

            if ($response->status() === 404) {
                foreach (config('ai.providers.gemini.fallback_models', []) as $fallbackModel) {
                    if ($fallbackModel === $model) {
                        continue;
                    }

                    $fallbackResponse = $this->sendGenerateRequest($fallbackModel, $request, $apiKey);

                    if ($fallbackResponse->successful()) {
                        $model = $fallbackModel;
                        $response = $fallbackResponse;
                        break;
                    }
                }
            }

            if ($response->status() === 429) {
                throw new RateLimitException('Gemini Rate Limit exceeded', 429);
            }

            if (!$response->successful()) {
                throw new ProviderException(
                    'Gemini Error: ' . $response->body(),
                    $this->getProvider(),
                    $response->status()
                );
            }

            $data = $response->json();
            $content = data_get($data, 'candidates.0.content.parts.0.text', '');
            $promptTokens = (int) data_get($data, 'usageMetadata.promptTokenCount', 0);
            $completionTokens = (int) data_get($data, 'usageMetadata.candidatesTokenCount', 0);
            $totalTokens = (int) data_get($data, 'usageMetadata.totalTokenCount', $promptTokens + $completionTokens);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return new AIResponseDTO(
                content: $content,
                provider: $this->getProvider(),
                model: $model,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $totalTokens,
                latencyMs: $latencyMs,
                rawResponse: $data
            );
        } catch (RateLimitException|ProviderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ProviderException('Gemini Error: ' . $this->sanitizeError($e->getMessage()), $this->getProvider(), (int) $e->getCode(), $e);
        }
    }

    public function getProvider(): AIProvider
    {
        return AIProvider::GEMINI;
    }

    public function isAvailable(): bool
    {
        return !empty(config('ai.providers.gemini.api_key'));
    }

    protected function sendGenerateRequest(string $model, AIRequestDTO $request, string $apiKey)
    {
        return Http::timeout(120)
            ->withOptions(['verify' => config('ai.http.verify_ssl', true)])
            ->retry(2, 500)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $request->systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $request->userPrompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $request->temperature,
                    'maxOutputTokens' => $request->maxTokens,
                ],
            ]);
    }

    protected function sanitizeError(string $message): string
    {
        return preg_replace('/key=[^\\s&]+/', 'key=[redacted]', $message) ?? $message;
    }
}
