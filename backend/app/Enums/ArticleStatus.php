<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case DRAFT = 'draft';
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Bản nháp',
            self::REVIEW => 'Chờ duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Từ chối',
            self::PUBLISHING => 'Đang đăng',
            self::PUBLISHED => 'Đã đăng',
            self::FAILED => 'Thất bại',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PUBLISHED, self::FAILED, self::REJECTED]);
    }

    public function canTransitionTo(self $status): bool
    {
        return match($this) {
            self::DRAFT => $status === self::REVIEW,
            self::REVIEW => in_array($status, [self::APPROVED, self::REJECTED]),
            self::APPROVED => $status === self::PUBLISHING,
            self::PUBLISHING => in_array($status, [self::PUBLISHED, self::FAILED]),
            default => false,
        };
    }
}
