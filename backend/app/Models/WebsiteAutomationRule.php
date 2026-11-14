<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteAutomationRule extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'website_automation_rules';

    protected $fillable = [
        'workspace_id',
        'website_id',
        'sync_gsc_enabled',
        'sync_ga4_enabled',
        'rank_tracking_enabled',
        'anomaly_scan_enabled',
        'alert_ranking_drop_threshold',
        'alert_traffic_drop_percentage',
        'email_notifications_enabled',
    ];

    protected $casts = [
        'sync_gsc_enabled' => 'boolean',
        'sync_ga4_enabled' => 'boolean',
        'rank_tracking_enabled' => 'boolean',
        'anomaly_scan_enabled' => 'boolean',
        'alert_ranking_drop_threshold' => 'integer',
        'alert_traffic_drop_percentage' => 'integer',
        'email_notifications_enabled' => 'boolean',
    ];

    /**
     * Get the website associated with these automation rules.
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * Get the workspace that owns the automation rules.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Helper to retrieve existing rules or initialize defaults for a website.
     */
    public static function getOrCreateForWebsite(int $workspaceId, int $websiteId): self
    {
        return static::firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'website_id' => $websiteId,
            ],
            [
                'sync_gsc_enabled' => true,
                'sync_ga4_enabled' => true,
                'rank_tracking_enabled' => true,
                'anomaly_scan_enabled' => true,
                'alert_ranking_drop_threshold' => 3,
                'alert_traffic_drop_percentage' => 20,
                'email_notifications_enabled' => true,
            ]
        );
    }
}
