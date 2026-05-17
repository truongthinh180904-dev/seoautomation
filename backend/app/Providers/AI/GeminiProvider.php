<?php

namespace App\Providers\AI;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Exceptions\AI\ProviderException;
use App\Providers\AI\Contracts\AIProviderInterface;

class GeminiProvider implements AIProviderInterface
{
    public function complete(AIRequestDTO $request): AIResponseDTO
    {
        throw new ProviderException("Gemini provider not implemented", $this->getProvider());
    }

    public function getProvider(): AIProvider
    {
        return AIProvider::GEMINI;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
