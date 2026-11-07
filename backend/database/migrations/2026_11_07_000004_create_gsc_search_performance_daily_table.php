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
        Schema::create('gsc_search_performance_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->date('date');
            $table->string('query', 255);
            $table->string('page_url', 1024);
            $table->char('page_hash', 32);
            $table->char('query_hash', 32);
            $table->char('country', 3)->default('vnm');
            $table->string('device', 20)->default('DESKTOP');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0.0000);
            $table->decimal('position', 8, 2)->default(0.00);
            $table->timestamps();

            $table->unique(
                ['website_id', 'date', 'page_hash', 'query_hash', 'country', 'device'],
                'uk_gsc_daily_metric'
            );
            $table->index('workspace_id', 'idx_gsc_perf_workspace');
            $table->index(['website_id', 'date'], 'idx_gsc_date_lookup');
            $table->index(['website_id', 'page_hash', 'date'], 'idx_gsc_page_lookup');
            $table->index(['website_id', 'query_hash', 'date'], 'idx_gsc_query_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gsc_search_performance_daily');
    }
};
