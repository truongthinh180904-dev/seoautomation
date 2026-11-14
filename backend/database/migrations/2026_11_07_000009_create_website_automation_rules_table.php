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
        if (! Schema::hasTable('website_automation_rules')) {
            Schema::create('website_automation_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->boolean('sync_gsc_enabled')->default(true);
                $table->boolean('sync_ga4_enabled')->default(true);
                $table->boolean('rank_tracking_enabled')->default(true);
                $table->boolean('anomaly_scan_enabled')->default(true);
                $table->unsignedSmallInteger('alert_ranking_drop_threshold')->default(3);
                $table->unsignedSmallInteger('alert_traffic_drop_percentage')->default(20);
                $table->boolean('email_notifications_enabled')->default(true);
                $table->timestamps();

                $table->unique('website_id', 'uk_website_rules');
                $table->index('workspace_id', 'idx_rules_workspace');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_automation_rules');
    }
};
