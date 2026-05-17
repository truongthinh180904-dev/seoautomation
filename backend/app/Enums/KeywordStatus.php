<?php

namespace App\Enums;

enum KeywordStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Chờ xử lý',
            self::PROCESSING => 'Đang xử lý',
            self::COMPLETED => 'Hoàn thành',
            self::FAILED => 'Thất bại',
            self::SKIPPED => 'Bỏ qua',
        };
    }
}
