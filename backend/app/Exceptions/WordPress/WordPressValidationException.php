<?php

namespace App\Exceptions\WordPress;

use RuntimeException;

class WordPressValidationException extends RuntimeException
{
    public function __construct(string $message, public array $errors = [])
    {
        parent::__construct($message);
    }
}
