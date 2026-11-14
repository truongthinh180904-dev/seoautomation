<?php

namespace App\Enums;

enum PlanCode: string
{
    case FREE = 'free';
    case BASIC = 'basic';
    case PRO = 'pro';
    case BUSINESS = 'business';

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Gói Free',
            self::BASIC => 'Gói Basic',
            self::PRO => 'Gói Pro',
            self::BUSINESS => 'Gói Business',
        };
    }
}
