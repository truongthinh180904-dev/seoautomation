<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalLink extends Model
{
    protected $fillable = [
        'tenant_id',
        'wordpress_site_id',
        'source_article_id',
        'target_url',
        'target_post_id',
        'anchor_text',
        'context_sentence',
        'relevance_score',
        'is_placed',
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $casts = [
        'is_placed' => 'boolean',
        'relevance_score' => 'float',
        'created_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'source_article_id');
    }
}
