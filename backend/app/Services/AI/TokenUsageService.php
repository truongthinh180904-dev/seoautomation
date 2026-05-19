<?php

namespace App\Services\AI;

class TokenUsageService
{
    public function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $rates = config('ai_costs.models', []);
        $rate = $rates[$model] ?? config('ai_costs.fallback_model_rate', ['input' => 5.0, 'output' => 15.0]);

        $inputCost = ($promptTokens / 1000000) * $rate['input'];
        $outputCost = ($completionTokens / 1000000) * $rate['output'];

        return $inputCost + $outputCost;
    }
}
