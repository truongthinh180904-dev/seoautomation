<?php

return [
    'default_channel' => env('NOTIFICATION_DEFAULT_CHANNEL', 'dashboard'),
    'email_enabled' => env('NOTIFICATION_EMAIL_ENABLED', true),
    'telegram_enabled' => env('TELEGRAM_ENABLED', false),
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
];
