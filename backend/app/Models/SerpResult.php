<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SerpResult extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'keyword_id',
        'tenant_id',
        'position',
        'url',
        'title',
        'description',
        'domain',
        'is_crawled',
        'crawled_at',
        'raw_data',
        'created_at',
    ];

    const UPDATED_AT = null;

    protected $casts = [
        'is_crawled' => 'boolean',
        'raw_data' => 'array',
        'crawled_at' => 'datetime',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function competitorAnalysis(): HasOne
    {
        return $this->hasOne(CompetitorAnalysis::class);
    }
}
