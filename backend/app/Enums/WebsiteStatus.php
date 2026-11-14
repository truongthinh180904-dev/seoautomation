<?php

namespace App\Enums;

enum WebsiteStatus: string
{
    case ACTIVE = 'active';
    case DISCONNECTED = 'disconnected';
    case PAUSED = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Hoạt động',
            self::DISCONNECTED => 'Mất kết nối',
            self::PAUSED => 'Tạm dừng',
        };
    }
}
