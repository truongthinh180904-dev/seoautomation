<?php

namespace App\Agents\Contracts;

use App\DTOs\AgentResultDTO;
use App\Enums\AgentType;

interface AgentInterface
{
    public function execute(array $context): AgentResultDTO;
    public function getType(): AgentType;
}
