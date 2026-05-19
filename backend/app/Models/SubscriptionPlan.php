<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'articles_per_month',
        'ai_cost_budget_usd',
        'campaigns_limit',
        'wordpress_sites_limit',
        'team_members_limit',
        'serp_lookups_per_article',
        'price_monthly_usd',
        'price_yearly_usd',
        'is_active',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'articles_per_month' => 'integer',
        'ai_cost_budget_usd' => 'float',
        'campaigns_limit' => 'integer',
        'wordpress_sites_limit' => 'integer',
        'team_members_limit' => 'integer',
        'serp_lookups_per_article' => 'integer',
        'price_monthly_usd' => 'float',
        'price_yearly_usd' => 'float',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }
}
