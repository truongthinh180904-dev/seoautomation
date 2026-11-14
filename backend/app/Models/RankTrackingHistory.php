<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankTrackingHistory extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rank_tracking_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'rank_tracked_keyword_id',
        'check_date',
        'position',
        'found_url',
        'serp_features',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'check_date' => 'date:Y-m-d',
        'position' => 'integer',
        'serp_features' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(RankTrackedKeyword::class, 'rank_tracked_keyword_id');
    }
}
