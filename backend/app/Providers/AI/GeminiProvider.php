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
        $contentModel = config('ai.providers.gemini.content_model', 'gemini-3.1-pro-preview');
        $defaultModel = config('ai.providers.gemini.default_model', 'gemini-2.5-flash');
        $modelMap = [
            'gpt-4o' => $contentModel,
            'gpt-4' => $contentModel,
            'gpt-4o-mini' => $defaultModel,
            'gpt-3.5-turbo' => $defaultModel,
            'claude-3-opus-20240229' => $contentModel,
            'claude-3-opus' => $contentModel,
            'claude-3-sonnet-20240229' => $defaultModel,
            'claude-3-haiku-20240307' => $defaultModel,
            'claude-3-5-sonnet' => $contentModel,
        ];

        $rawModel = $request->model;
        if (! $rawModel || isset($modelMap[$rawModel]) || str_starts_with((string) $rawModel, 'gpt-') || str_starts_with((string) $rawModel, 'claude-')) {
            $model = $modelMap[$rawModel] ?? $defaultModel;
        } else {
            $model = $rawModel;
        }

        $apiKey = config('ai.providers.gemini.api_key');

        try {
            $response = $this->sendGenerateRequest($model, $request, $apiKey);

            if ($response->status() === 404) {
                foreach (config('ai.providers.gemini.fallback_models', []) as $fallbackModel) {
                    if ($fallbackModel === $model) {
                        continue;
                    }

                    $fallbackResponse = $this->sendGenerateRequest($fallbackModel, $request, $apiKey);
                    $response = $fallbackResponse;

                    if ($fallbackResponse->successful()) {
                        $model = $fallbackModel;
                        break;
                    }

                    if ($fallbackResponse->status() === 429) {
                        break;
                    }
                }
            }

            if ($response->status() === 429) {
                throw new RateLimitException('Gemini Rate Limit exceeded', 429);
            }

            if (! $response->successful()) {
                throw new ProviderException(
                    'Gemini Error: '.$response->body(),
                    $this->getProvider(),
                    $response->status()
                );
            }

            $data = $response->json();
            $parts = data_get($data, 'candidates.0.content.parts', []);
            $nonThoughtTexts = [];
            $allTexts = [];
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                        $allTexts[] = $part['text'];
                        if (empty($part['thought'])) {
                            $nonThoughtTexts[] = $part['text'];
                        }
                    }
                }
            }
            $content = ! empty($nonThoughtTexts)
                ? implode("\n", $nonThoughtTexts)
                : (! empty($allTexts) ? implode("\n", $allTexts) : (string) data_get($data, 'candidates.0.content.parts.0.text', ''));
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
            throw new ProviderException('Gemini Error: '.$this->sanitizeError($e->getMessage()), $this->getProvider(), (int) $e->getCode(), $e);
        }
    }

    public function getProvider(): AIProvider
    {
        return AIProvider::GEMINI;
    }

    public function isAvailable(): bool
    {
        return ! empty(config('ai.providers.gemini.api_key'));
    }

    protected function sendGenerateRequest(string $model, AIRequestDTO $request, string $apiKey)
    {
        return Http::timeout(120)
            ->withOptions(['verify' => config('ai.http.verify_ssl', true)])
            ->retry(2, 500, null, false)
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
