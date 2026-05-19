<?php

namespace App\Jobs\Notification;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(public int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);
        if (!$notification) {
            return;
        }

        try {
            match ($notification->channel) {
                NotificationChannel::EMAIL => $this->sendEmail($notification),
                NotificationChannel::TELEGRAM => $this->sendTelegram($notification),
                NotificationChannel::DASHBOARD => null,
            };

            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $notification->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function sendEmail(Notification $notification): void
    {
        if (!config('notifications.email_enabled', true)) {
            return;
        }

        $user = $notification->user ?: User::where('tenant_id', $notification->tenant_id)->orderBy('id')->first();
        if (!$user?->email) {
            return;
        }

        Mail::raw($notification->message ?? '', function ($message) use ($user, $notification) {
            $message->to($user->email)->subject($notification->subject ?? 'SaaS Auto SEO Notification');
        });
    }

    protected function sendTelegram(Notification $notification): void
    {
        if (!config('notifications.telegram_enabled')) {
            return;
        }

        Http::post('https://api.telegram.org/bot' . config('notifications.telegram_bot_token') . '/sendMessage', [
            'chat_id' => config('notifications.telegram_chat_id'),
            'text' => trim(($notification->subject ?? '') . "\n\n" . ($notification->message ?? '')),
            'parse_mode' => 'Markdown',
        ]);
    }
}
