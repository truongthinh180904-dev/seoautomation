<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_monthly_vnd',
        'billing_interval',
        'credits_per_cycle',
        'articles_limit_per_cycle',
        'websites_limit',
        'max_concurrent_tasks',
        'max_retries_per_task',
        'max_images_per_article',
        'carryover',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'code' => PlanCode::class,
        'billing_interval' => BillingInterval::class,
        'price_monthly_vnd' => 'integer',
        'credits_per_cycle' => 'integer',
        'articles_limit_per_cycle' => 'integer',
        'websites_limit' => 'integer',
        'max_concurrent_tasks' => 'integer',
        'max_retries_per_task' => 'integer',
        'max_images_per_article' => 'integer',
        'carryover' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }
}
