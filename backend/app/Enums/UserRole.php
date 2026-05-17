<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Quản trị viên',
            self::EDITOR => 'Biên tập viên',
            self::VIEWER => 'Người xem',
        };
    }

    public function canManageTenants(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN]);
    }

    public function canPublish(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN]);
    }

    public function canApprove(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN, self::EDITOR]);
    }
}
