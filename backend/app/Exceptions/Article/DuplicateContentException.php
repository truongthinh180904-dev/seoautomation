<?php

namespace App\Exceptions\Article;

use RuntimeException;

class DuplicateContentException extends RuntimeException
{
    public function __construct(string $message = 'Duplicate content detected.')
    {
        parent::__construct($message, 409);
    }
}
