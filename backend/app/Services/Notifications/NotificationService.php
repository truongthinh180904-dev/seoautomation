<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Jobs\Notification\SendNotificationJob;
use App\Models\Notification;

class NotificationService
{
    public function send(
        int $tenantId,
        NotificationType $type,
        string $subject,
        string $message,
        array $data = [],
        ?int $userId = null,
        ?NotificationChannel $channel = null
    ): Notification {
        $channel ??= NotificationChannel::tryFrom((string) config('notifications.default_channel', 'email'))
            ?? NotificationChannel::EMAIL;

        $notification = Notification::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'channel' => $channel,
            'subject' => $subject,
            'message' => $message,
            'data' => $data,
        ]);

        SendNotificationJob::dispatch($notification->id)->onQueue('notifications');

        return $notification;
    }
}
