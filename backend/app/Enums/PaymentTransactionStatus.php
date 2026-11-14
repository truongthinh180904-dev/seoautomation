<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case RECORDED = 'recorded';
    case VERIFIED = 'verified';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::RECORDED => 'Đã ghi nhận',
            self::VERIFIED => 'Đã xác minh',
            self::VOIDED => 'Đã hủy',
        };
    }
}
