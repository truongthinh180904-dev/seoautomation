<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishingLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'article_id',
        'wordpress_site_id',
        'wordpress_post_id',
        'attempt_number',
        'status',
        'http_status_code',
        'response_body',
        'error_message',
        'published_url',
        'wordpress_edit_url',
        'duration_ms',
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $casts = [
        'attempt_number' => 'integer',
        'http_status_code' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }
}
