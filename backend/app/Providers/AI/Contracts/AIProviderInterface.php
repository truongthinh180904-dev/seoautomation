<?php

namespace App\Providers\AI\Contracts;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;

interface AIProviderInterface
{
    public function complete(AIRequestDTO $request): AIResponseDTO;
    public function getProvider(): AIProvider;
    public function isAvailable(): bool;
}
