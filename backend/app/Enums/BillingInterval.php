<?php

namespace App\Enums;

enum BillingInterval: string
{
    case DAILY = 'daily';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Hàng ngày',
            self::MONTHLY => 'Hàng tháng',
        };
    }
}
