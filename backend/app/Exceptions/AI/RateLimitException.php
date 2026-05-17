<?php

namespace App\Exceptions\AI;

use RuntimeException;

class RateLimitException extends RuntimeException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 429, $previous);
    }
}
