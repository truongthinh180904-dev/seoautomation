<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'articles_used_this_month',
        'ai_cost_used_this_month_usd',
        'billing_cycle_start',
        'billing_cycle_end',
        'trial_ends_at',
        'cancelled_at',
    ];

    protected $casts = [
        'articles_used_this_month' => 'integer',
        'ai_cost_used_this_month_usd' => 'float',
        'billing_cycle_start' => 'datetime',
        'billing_cycle_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
