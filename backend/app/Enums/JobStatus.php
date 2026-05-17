<?php

namespace App\Enums;

enum JobStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case RETRYING = 'retrying';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Chờ xử lý',
            self::PROCESSING => 'Đang xử lý',
            self::COMPLETED => 'Hoàn thành',
            self::FAILED => 'Thất bại',
            self::RETRYING => 'Đang thử lại',
        };
    }
}
