<?php

namespace App\Enums;

enum WorkspaceMemberRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Chủ sở hữu',
            self::ADMIN => 'Quản trị viên',
            self::EDITOR => 'Biên tập viên',
            self::VIEWER => 'Người xem',
        };
    }
}
