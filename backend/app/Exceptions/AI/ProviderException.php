<?php

namespace App\Exceptions\AI;

use App\Enums\AIProvider;
use RuntimeException;
use Throwable;

class ProviderException extends RuntimeException
{
    public AIProvider $provider;

    public function __construct(string $message, AIProvider $provider, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->provider = $provider;
    }
}
