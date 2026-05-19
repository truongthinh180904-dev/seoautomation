<?php

namespace App\Providers;

use App\Providers\AI\AnthropicProvider;
use App\Providers\AI\GeminiProvider;
use App\Providers\AI\OpenAIProvider;
use App\Services\AI\AIProviderService;
use App\Services\AI\TokenUsageService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIProviderService::class, function ($app) {
            $providers = [];

            if (config('openai.api_key') || config('ai.providers.openai.api_key')) {
                $providers[] = $app->make(OpenAIProvider::class);
            }

            if (config('ai.providers.anthropic.api_key')) {
                $providers[] = $app->make(AnthropicProvider::class);
            }

            if (config('ai.providers.gemini.api_key')) {
                $providers[] = $app->make(GeminiProvider::class);
            }

            return new AIProviderService(
                $providers,
                $app->make(TokenUsageService::class)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
