<?php

namespace App\Models;

use App\Enums\UsageRecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'website_id',
        'campaign_id',
        'article_id',
        'task_id',
        'credit_transaction_id',
        'action_type',
        'credits_charged',
        'tokens_used',
        'provider_cost_usd',
        'status',
        'usage_date',
    ];

    protected $casts = [
        'status' => UsageRecordStatus::class,
        'credits_charged' => 'integer',
        'tokens_used' => 'integer',
        'provider_cost_usd' => 'float',
        'usage_date' => 'date',
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

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }
}
