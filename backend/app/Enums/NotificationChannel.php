<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case DASHBOARD = 'dashboard';
    case EMAIL = 'email';
    case TELEGRAM = 'telegram';
}
