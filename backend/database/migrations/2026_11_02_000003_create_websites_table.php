<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('websites')) {
            Schema::create('websites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('wordpress_site_id')->nullable()->constrained('wordpress_sites')->nullOnDelete();
                $table->string('name', 255);
                $table->string('domain', 255);
                $table->string('url', 500);
                $table->string('default_language', 10)->default('vi');
                $table->string('niche', 255)->nullable();
                $table->string('status', 30)->default('active');
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['workspace_id', 'domain']);
                $table->index(['workspace_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
