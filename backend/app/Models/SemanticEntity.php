<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemanticEntity extends Model
{
    protected $fillable = [
        'article_id',
        'tenant_id',
        'entity_text',
        'entity_type',
        'relevance_score',
        'frequency',
        'source',
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $casts = [
        'relevance_score' => 'float',
        'frequency' => 'integer',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
