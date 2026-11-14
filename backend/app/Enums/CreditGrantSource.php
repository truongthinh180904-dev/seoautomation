<?php

namespace App\Enums;

enum CreditGrantSource: string
{
    case FREE_DAILY = 'free_daily';
    case SUBSCRIPTION_CYCLE = 'subscription_cycle';
    case MANUAL_ADMIN = 'manual_admin';
    case PROMO = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::FREE_DAILY => 'Cấp gói Free hàng ngày',
            self::SUBSCRIPTION_CYCLE => 'Chu kỳ gói trả phí',
            self::MANUAL_ADMIN => 'Admin cấp thủ công',
            self::PROMO => 'Khuyến mãi / Bonus',
        };
    }
}
