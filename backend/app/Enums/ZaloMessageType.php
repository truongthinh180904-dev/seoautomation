<?php

namespace App\Enums;

enum ZaloMessageType: string
{
    case REVIEW_REQUEST = 'review_request';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match($this) {
            self::REVIEW_REQUEST => 'Yêu cầu duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Từ chối',
            self::PUBLISHED => 'Đã đăng',
        };
    }
}
