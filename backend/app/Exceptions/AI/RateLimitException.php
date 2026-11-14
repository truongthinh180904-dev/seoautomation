<?php

namespace App\Exceptions\AI;

use RuntimeException;

class RateLimitException extends RuntimeException
{
    public function __construct(string $message = '', int|\Throwable|null $codeOrPrevious = 429, ?\Throwable $previous = null)
    {
        if ($codeOrPrevious instanceof \Throwable) {
            $previous = $codeOrPrevious;
            $code = 429;
        } else {
            $code = is_int($codeOrPrevious) ? $codeOrPrevious : 429;
        }

        parent::__construct($message, $code, $previous);
    }
}
