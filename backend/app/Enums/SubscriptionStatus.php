<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Hoạt động',
            self::PAST_DUE => 'Quá hạn',
            self::CANCELLED => 'Đã hủy',
            self::EXPIRED => 'Đã hết hạn',
        };
    }
}
