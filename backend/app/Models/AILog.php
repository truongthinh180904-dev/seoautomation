<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AILog extends Model
{
    protected $table = 'ai_logs';

    protected $fillable = [
        'tenant_id',
        'article_id',
        'keyword_id',
        'agent_type',
        'provider',
        'model',
        'prompt_version',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'latency_ms',
        'status',
        'error_message',
        'request_hash',
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $casts = [
        'status' => 'string',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost_usd' => 'float',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
