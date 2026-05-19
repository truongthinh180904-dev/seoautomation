<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyUsageSnapshot extends Model
{
    protected $fillable = [
        'tenant_id',
        'year',
        'month',
        'articles_generated',
        'articles_published',
        'total_ai_cost_usd',
        'serper_calls',
        'gemini_tokens_prompt',
        'gemini_tokens_completion',
        'top_campaigns',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'articles_generated' => 'integer',
        'articles_published' => 'integer',
        'total_ai_cost_usd' => 'float',
        'serper_calls' => 'integer',
        'gemini_tokens_prompt' => 'integer',
        'gemini_tokens_completion' => 'integer',
        'top_campaigns' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
