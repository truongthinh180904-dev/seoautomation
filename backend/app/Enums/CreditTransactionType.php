<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case GRANT = 'grant';
    case RESERVE = 'reserve';
    case DEDUCT = 'deduct';
    case RELEASE_REFUND = 'release_refund';
    case EXPIRE = 'expire';
    case ADMIN_ADJUST = 'admin_adjust';

    public function label(): string
    {
        return match ($this) {
            self::GRANT => 'Cấp credit',
            self::RESERVE => 'Tạm giữ credit',
            self::DEDUCT => 'Trừ credit',
            self::RELEASE_REFUND => 'Hoàn credit',
            self::EXPIRE => 'Hết hạn chu kỳ',
            self::ADMIN_ADJUST => 'Điều chỉnh Admin',
        };
    }
}
