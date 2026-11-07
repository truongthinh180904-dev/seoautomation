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
        Schema::create('website_search_consoles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('google_account_id')->constrained('google_accounts')->cascadeOnDelete();
            $table->string('site_url', 500);
            $table->string('property_type', 30)->default('url_prefix');
            $table->string('permission_level', 50)->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('idle');
            $table->timestamps();

            $table->unique('website_id', 'uk_website_gsc');
            $table->index('workspace_id', 'idx_gsc_workspace');
            $table->index('google_account_id', 'idx_gsc_google_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_search_consoles');
    }
};
