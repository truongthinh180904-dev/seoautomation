<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'starter',
                'articles_per_month' => 30,
                'ai_cost_budget_usd' => 10,
                'campaigns_limit' => 2,
                'wordpress_sites_limit' => 1,
                'team_members_limit' => 1,
                'serp_lookups_per_article' => 10,
                'price_monthly_usd' => 19,
                'price_yearly_usd' => 190,
                'features' => ['basic_ai_writing', 'wordpress_publish', 'dashboard_approval'],
            ],
            [
                'name' => 'professional',
                'articles_per_month' => 150,
                'ai_cost_budget_usd' => 50,
                'campaigns_limit' => 10,
                'wordpress_sites_limit' => 3,
                'team_members_limit' => 5,
                'serp_lookups_per_article' => 10,
                'price_monthly_usd' => 79,
                'price_yearly_usd' => 790,
                'features' => ['campaign_planning', 'media_pipeline', 'seo_qa'],
            ],
            [
                'name' => 'agency',
                'articles_per_month' => 500,
                'ai_cost_budget_usd' => 200,
                'campaigns_limit' => null,
                'wordpress_sites_limit' => null,
                'team_members_limit' => null,
                'serp_lookups_per_article' => 10,
                'price_monthly_usd' => 249,
                'price_yearly_usd' => 2490,
                'features' => ['unlimited_campaigns', 'advanced_reporting', 'priority_queue'],
            ],
            [
                'name' => 'enterprise',
                'articles_per_month' => null,
                'ai_cost_budget_usd' => null,
                'campaigns_limit' => null,
                'wordpress_sites_limit' => null,
                'team_members_limit' => null,
                'serp_lookups_per_article' => 10,
                'price_monthly_usd' => 0,
                'price_yearly_usd' => 0,
                'features' => ['custom_limits', 'sso_later', 'dedicated_support'],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                array_merge($plan, ['is_active' => true])
            );
        }
    }
}
