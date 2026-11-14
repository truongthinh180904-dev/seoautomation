<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'Đã vượt quá hạn mức sử dụng của gói dịch vụ.',
        public ?string $quotaType = null,
        public ?int $limit = null,
        public ?int $used = null,
        public ?int $workspaceId = null
    ) {
        parent::__construct($message);
    }
}
