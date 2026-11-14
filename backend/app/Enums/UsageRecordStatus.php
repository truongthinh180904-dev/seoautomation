<?php

namespace App\Enums;

enum UsageRecordStatus: string
{
    case RESERVED = 'reserved';
    case SETTLED = 'settled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::RESERVED => 'Đang tạm giữ',
            self::SETTLED => 'Đã kết toán',
            self::REFUNDED => 'Đã hoàn tiền',
        };
    }
}
