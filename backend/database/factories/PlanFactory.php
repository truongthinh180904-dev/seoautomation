<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => PlanCode::PRO,
            'name' => 'Pro Plan',
            'description' => 'Professional tier for growing businesses.',
            'price_monthly_vnd' => 1250000,
            'billing_interval' => BillingInterval::MONTHLY,
            'credits_per_cycle' => 7000,
            'articles_limit_per_cycle' => 350,
            'websites_limit' => 3,
            'max_concurrent_tasks' => 5,
            'max_retries_per_task' => 3,
            'max_images_per_article' => 3,
            'carryover' => false,
            'features' => ['all_ai_models', 'priority_support', 'unlimited_keywords'],
            'is_active' => true,
            'sort_order' => 3,
        ];
    }
}
