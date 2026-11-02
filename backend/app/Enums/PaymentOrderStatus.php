<?php

namespace App\Enums;

enum PaymentOrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PENDING_VERIFICATION = 'pending_verification';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Chờ thanh toán',
            self::PENDING_VERIFICATION => 'Chờ xác nhận',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Từ chối',
            self::CANCELLED => 'Đã hủy',
            self::EXPIRED => 'Hết hạn',
        };
    }
}
