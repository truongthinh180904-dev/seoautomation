<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('articles_per_month')->nullable();
            $table->decimal('ai_cost_budget_usd', 10, 2)->nullable();
            $table->unsignedInteger('campaigns_limit')->nullable();
            $table->unsignedInteger('wordpress_sites_limit')->nullable();
            $table->unsignedInteger('team_members_limit')->nullable();
            $table->unsignedInteger('serp_lookups_per_article')->default(10);
            $table->decimal('price_monthly_usd', 10, 2)->default(0);
            $table->decimal('price_yearly_usd', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->enum('status', ['active', 'past_due', 'cancelled', 'trialing'])->default('trialing');
            $table->unsignedInteger('articles_used_this_month')->default(0);
            $table->decimal('ai_cost_used_this_month_usd', 12, 6)->default(0);
            $table->timestamp('billing_cycle_start')->nullable();
            $table->timestamp('billing_cycle_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['status', 'billing_cycle_end']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->string('job_type', 100);
            $table->string('provider', 50);
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost_usd', 12, 8)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->enum('status', ['success', 'failed', 'timeout', 'rate_limited'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'job_type']);
            $table->index(['campaign_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('monthly_usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('articles_generated')->default(0);
            $table->unsignedInteger('articles_published')->default(0);
            $table->decimal('total_ai_cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('serper_calls')->default(0);
            $table->unsignedBigInteger('gemini_tokens_prompt')->default(0);
            $table->unsignedBigInteger('gemini_tokens_completion')->default(0);
            $table->json('top_campaigns')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_usage_snapshots');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
