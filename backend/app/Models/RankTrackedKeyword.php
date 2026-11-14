<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RankTrackedKeyword extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rank_tracked_keywords';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'keyword_id',
        'keyword',
        'target_url',
        'country',
        'device',
        'tracking_frequency',
        'current_position',
        'previous_position',
        'best_position',
        'last_checked_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_position' => 'integer',
        'previous_position' => 'integer',
        'best_position' => 'integer',
        'last_checked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'delta_rank',
    ];

    /**
     * Calculate rank delta (change compared to previous check).
     * Positive means rank improved (e.g. from 10 to 5 = +5).
     * Negative means rank dropped (e.g. from 5 to 8 = -3).
     * Zero means unchanged.
     */
    public function getDeltaRankAttribute(): ?int
    {
        if ($this->current_position === null && $this->previous_position === null) {
            return null;
        }

        // Dropped out of Top 100
        if ($this->current_position === null && $this->previous_position !== null) {
            return -100;
        }

        // Newly entered Top 100
        if ($this->current_position !== null && $this->previous_position === null) {
            return 101 - $this->current_position;
        }

        return $this->previous_position - $this->current_position;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function keywordEntity(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'keyword_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(RankTrackingHistory::class, 'rank_tracked_keyword_id')->orderBy('check_date', 'desc');
    }
}
