<?php

namespace App\Listeners;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Events\SeoAnomalyDetectedEvent;
use App\Models\Notification;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

class SendSeoAlertNotification
{
    /**
     * Handle the event.
     */
    public function handle(SeoAnomalyDetectedEvent $event): void
    {
        $insight = $event->insight;
        $rule = $event->rule;

        // Resolve workspace to ensure multi-tenant boundary compliance
        $workspace = Workspace::with('owner')->find($insight->workspace_id);
        if (! $workspace) {
            Log::warning("[SendSeoAlertNotification] Workspace #{$insight->workspace_id} not found for insight #{$insight->id}");

            return;
        }

        $tenantId = $workspace->legacy_tenant_id ?? $workspace->owner?->tenant_id ?? 1;
        $userId = $workspace->owner_id;

        // Determine notification delivery channel based on website automation rules
        $channel = ($rule && $rule->email_notifications_enabled)
            ? NotificationChannel::EMAIL
            : NotificationChannel::DASHBOARD;

        $subject = "[Cảnh báo SEO] {$insight->title}";
        $message = $insight->description;

        Notification::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => NotificationType::SERPER_QUOTA_WARNING,
            'channel' => $channel,
            'subject' => $subject,
            'message' => $message,
            'data' => [
                'insight_id' => $insight->id,
                'website_id' => $insight->website_id,
                'workspace_id' => $insight->workspace_id,
                'severity' => $insight->severity,
                'anomaly_type' => $insight->type,
                'suggested_action' => $insight->suggested_action,
                'threshold_breached' => $event->thresholdBreached,
                'metrics_snapshot' => $insight->metrics_snapshot,
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Log::info("[SendSeoAlertNotification] Created alert notification for website #{$insight->website_id}, insight #{$insight->id} (severity: {$insight->severity})");
    }
}
