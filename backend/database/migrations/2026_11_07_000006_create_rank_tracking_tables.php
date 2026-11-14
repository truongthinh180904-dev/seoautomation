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
        Schema::create('rank_tracked_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->string('keyword', 255);
            $table->string('target_url', 1024)->nullable();
            $table->char('country', 3)->default('vn');
            $table->string('device', 20)->default('desktop');
            $table->string('tracking_frequency', 20)->default('daily');
            $table->unsignedSmallInteger('current_position')->nullable();
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->unsignedSmallInteger('best_position')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['website_id', 'keyword', 'country', 'device'],
                'uk_tracked_keyword'
            );
            $table->index('workspace_id', 'idx_tracked_workspace');
            $table->index(['website_id', 'is_active'], 'idx_tracked_status');
        });

        Schema::create('rank_tracking_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('rank_tracked_keyword_id')->constrained('rank_tracked_keywords')->cascadeOnDelete();
            $table->date('check_date');
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('found_url', 1024)->nullable();
            $table->json('serp_features')->nullable();
            $table->timestamps();

            $table->unique(
                ['rank_tracked_keyword_id', 'check_date'],
                'uk_rank_check'
            );
            $table->index(['website_id', 'check_date'], 'idx_rank_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rank_tracking_history');
        Schema::dropIfExists('rank_tracked_keywords');
    }
};
