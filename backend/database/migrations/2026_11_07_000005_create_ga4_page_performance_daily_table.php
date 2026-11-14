<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ga4_page_performance_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->date('date');
            $table->string('landing_page', 1024);
            $table->char('page_hash', 32);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('active_users')->default(0);
            $table->decimal('engagement_rate', 8, 4)->default(0.0000);
            $table->unsignedInteger('conversions')->default(0);
            $table->decimal('avg_session_duration', 10, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['website_id', 'date', 'page_hash'], 'uk_ga4_daily_metric');
            $table->index('workspace_id', 'idx_ga4_perf_workspace');
            $table->index(['website_id', 'date'], 'idx_ga4_date_lookup');
            $table->index(['article_id', 'date'], 'idx_ga4_article_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ga4_page_performance_daily');
    }
};
