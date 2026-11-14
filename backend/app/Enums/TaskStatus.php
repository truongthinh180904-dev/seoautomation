<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xử lý',
            self::RUNNING => 'Đang chạy',
            self::SUCCESS => 'Thành công',
            self::FAILED => 'Thất bại',
            self::CANCELLED => 'Đã hủy',
        };
    }
}
