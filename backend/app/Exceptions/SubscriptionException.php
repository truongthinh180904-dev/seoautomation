<?php

namespace App\Exceptions;

use RuntimeException;

class SubscriptionException extends RuntimeException
{
    public function __construct(
        string $message = 'Thao tác trên gói dịch vụ không hợp lệ.',
        public ?int $workspaceId = null,
        public ?int $planId = null
    ) {
        parent::__construct($message);
    }
}
