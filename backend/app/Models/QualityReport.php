<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityReport extends Model
{
    protected $fillable = [
        'article_id',
        'seo_score',
        'readability_score',
        'media_score',
        'wordpress_readiness_score',
        'total_score',
        'checks',
        'warnings',
        'blocking_errors',
        'auto_fixable',
        'generated_at',
    ];

    protected $casts = [
        'checks' => 'array',
        'warnings' => 'array',
        'blocking_errors' => 'array',
        'auto_fixable' => 'array',
        'generated_at' => 'datetime',
        'seo_score' => 'integer',
        'readability_score' => 'integer',
        'media_score' => 'integer',
        'wordpress_readiness_score' => 'integer',
        'total_score' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
