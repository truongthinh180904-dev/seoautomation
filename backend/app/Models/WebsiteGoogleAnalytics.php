<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteGoogleAnalytics extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'website_google_analytics';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'google_account_id',
        'property_id',
        'property_name',
        'account_id',
        'account_name',
        'measurement_id',
        'time_zone',
        'currency_code',
        'is_verified',
        'last_synced_at',
        'sync_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function googleAccount(): BelongsTo
    {
        return $this->belongsTo(GoogleAccount::class);
    }

    /**
     * Returns standard GA4 resource name format: 'properties/{id}'.
     */
    public function getFormattedPropertyId(): string
    {
        if (str_starts_with($this->property_id, 'properties/')) {
            return $this->property_id;
        }

        return 'properties/'.$this->property_id;
    }

    /**
     * Returns clean numeric property ID without 'properties/' prefix.
     */
    public function getCleanPropertyId(): string
    {
        if (str_starts_with($this->property_id, 'properties/')) {
            return substr($this->property_id, strlen('properties/'));
        }

        return $this->property_id;
    }

    public function markAsSynced(?\DateTimeInterface $syncedAt = null): void
    {
        $this->update([
            'last_synced_at' => $syncedAt ?? Carbon::now(),
            'sync_status' => 'idle',
        ]);
    }

    public function markSyncStatus(string $status): void
    {
        $this->update([
            'sync_status' => $status,
        ]);
    }
}
