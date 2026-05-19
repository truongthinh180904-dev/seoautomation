<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class QuotaCheckResultDTO extends Data
{
    public function __construct(
        public bool $canProceed,
        public bool $articlesExceeded,
        public bool $costExceeded,
        public bool $warningThreshold,
        public ?int $articlesLimit,
        public int $articlesUsed,
        public ?float $costLimitUsd,
        public float $costUsedUsd,
        public ?string $message = null,
    ) {}
}
