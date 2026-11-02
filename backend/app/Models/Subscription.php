<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'last_credit_granted_at',
        'next_credit_grant_at',
        'activated_by_order_id',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'last_credit_granted_at' => 'datetime',
        'next_credit_grant_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creditGrants(): HasMany
    {
        return $this->hasMany(CreditGrant::class);
    }
}
