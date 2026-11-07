<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoIntelligenceInsight extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seo_intelligence_insights';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'article_id',
        'type',
        'severity',
        'opportunity_score',
        'title',
        'description',
        'metrics_snapshot',
        'suggested_action',
        'status',
        'resolved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'opportunity_score' => 'integer',
        'metrics_snapshot' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the opportunity score attribute with fallback to snapshot.
     */
    public function getOpportunityScoreAttribute(?int $value): int
    {
        if ($value !== null && $value > 0) {
            return $value;
        }

        return (int) ($this->metrics_snapshot['opportunity_score'] ?? 0);
    }

    /**
     * Scope a query to only include active insights (detected or in_progress).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['detected', 'in_progress']);
    }

    /**
     * Scope a query to filter by severity.
     */
    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope a query to filter by insight type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by website id.
     */
    public function scopeByWebsite(Builder $query, int $websiteId): Builder
    {
        return $query->where('website_id', $websiteId);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
