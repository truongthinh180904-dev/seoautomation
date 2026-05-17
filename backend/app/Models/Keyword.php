<?php

namespace App\Models;

use App\Enums\KeywordStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Keyword extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'keyword',
        'language',
        'search_volume',
        'difficulty',
        'cpc',
        'search_intent',
        'priority',
        'status',
        'scheduled_at',
        'processed_at',
        'wordpress_site_id',
        'batch_id',
        'meta',
    ];

    protected $casts = [
        'status' => KeywordStatus::class,
        'search_intent' => 'string',
        'meta' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function serpResults(): HasMany
    {
        return $this->hasMany(SerpResult::class);
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }
}
