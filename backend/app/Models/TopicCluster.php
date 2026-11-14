<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicCluster extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'website_id',
        'pillar_keyword_id',
        'pillar_article_id',
        'name',
        'slug',
        'description',
        'target_audience',
        'total_search_volume',
        'avg_difficulty',
        'status',
    ];

    protected $casts = [
        'total_search_volume' => 'integer',
        'avg_difficulty' => 'float',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function pillarKeyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'pillar_keyword_id');
    }

    public function pillarArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'pillar_article_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'topic_cluster_id');
    }
}
