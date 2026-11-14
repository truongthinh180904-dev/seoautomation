<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public int $requiredCredits = 0,
        public int $availableCredits = 0,
        public ?int $workspaceId = null,
        string $message = ''
    ) {
        if ($message === '') {
            $message = sprintf(
                'Số dư credit không đủ (yêu cầu %d credit, hiện có %d credit%s). Vui lòng nâng cấp gói hoặc nạp thêm.',
                $requiredCredits,
                $availableCredits,
                $workspaceId ? " cho Workspace #{$workspaceId}" : ''
            );
        }

        parent::__construct($message);
    }
}
