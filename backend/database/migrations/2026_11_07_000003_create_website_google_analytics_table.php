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
        Schema::create('website_google_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('google_account_id')->constrained('google_accounts')->cascadeOnDelete();
            $table->string('property_id', 100);
            $table->string('property_name', 255)->nullable();
            $table->string('account_id', 100)->nullable();
            $table->string('account_name', 255)->nullable();
            $table->string('measurement_id', 50)->nullable();
            $table->string('time_zone', 100)->default('UTC');
            $table->string('currency_code', 20)->default('USD');
            $table->boolean('is_verified')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('idle');
            $table->timestamps();

            $table->unique('website_id', 'uk_website_ga4');
            $table->index('workspace_id', 'idx_ga4_workspace');
            $table->index('google_account_id', 'idx_ga4_google_account');
            $table->index('property_id', 'idx_ga4_property_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_google_analytics');
    }
};
