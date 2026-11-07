<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSearchConsole extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'google_account_id',
        'site_url',
        'property_type',
        'permission_level',
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

    public function isDomainProperty(): bool
    {
        return $this->property_type === 'domain' || str_starts_with($this->site_url, 'sc-domain:');
    }

    public function isUrlPrefixProperty(): bool
    {
        return ! $this->isDomainProperty();
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
