<?php

namespace App\DTOs;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class CreditBalanceDTO extends Data
{
    public function __construct(
        public int $workspaceId,
        public int $availableCredits,
        public int $reservedCredits,
        public int $totalCredits,
        public int $cycleGrantedCredits,
        public int $cycleUsedCredits,
        public ?CarbonInterface $periodStart = null,
        public ?CarbonInterface $periodEnd = null,
        public ?string $planCode = null,
        public ?string $planName = null,
    ) {}
}
