<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGap extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'website_id',
        'competitor_domain',
        'target_keyword',
        'search_volume',
        'keyword_difficulty',
        'competitor_rank',
        'our_rank',
        'opportunity_score',
        'status',
        'campaign_id',
    ];

    protected $casts = [
        'search_volume' => 'integer',
        'keyword_difficulty' => 'integer',
        'competitor_rank' => 'integer',
        'our_rank' => 'integer',
        'opportunity_score' => 'float',
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
}
