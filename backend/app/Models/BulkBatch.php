<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkBatch extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'bulk_batches';

    protected $fillable = [
        'uuid',
        'workspace_id',
        'website_id',
        'campaign_id',
        'name',
        'total_items',
        'completed_items',
        'failed_items',
        'credits_reserved',
        'credits_settled',
        'status',
        'generation_settings',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'completed_items' => 'integer',
        'failed_items' => 'integer',
        'credits_reserved' => 'integer',
        'credits_settled' => 'integer',
        'generation_settings' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->total_items <= 0) {
            return 0.0;
        }

        $processed = $this->completed_items + $this->failed_items;

        return min(100.0, round(($processed / $this->total_items) * 100, 1));
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'partially_failed', 'failed', 'cancelled'], true);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
