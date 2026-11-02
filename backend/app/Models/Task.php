<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'website_id',
        'campaign_id',
        'keyword_id',
        'article_id',
        'triggered_by',
        'type',
        'current_step',
        'status',
        'progress',
        'credits_reserved',
        'credits_settled',
        'attempts',
        'max_attempts',
        'unique_lock_key',
        'error_code',
        'error_message',
        'payload',
        'started_at',
        'finished_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'progress' => 'integer',
        'credits_reserved' => 'integer',
        'credits_settled' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
