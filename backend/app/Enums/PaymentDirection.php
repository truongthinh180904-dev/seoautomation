<?php

namespace App\Enums;

enum PaymentDirection: string
{
    case INBOUND = 'inbound';
    case REFUND = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::INBOUND => 'Thanh toán vào',
            self::REFUND => 'Hoàn tiền',
        };
    }
}
