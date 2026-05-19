<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('wordpress_site_id')->nullable()->constrained('wordpress_sites')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->string('language', 10)->default('vi');
            $table->string('brand_voice', 255)->nullable();
            $table->string('target_audience', 255)->nullable();
            $table->string('content_goal', 255)->nullable();
            $table->unsignedInteger('default_word_count')->default(1200);
            $table->json('default_category_ids')->nullable();
            $table->json('default_tag_names')->nullable();
            $table->boolean('approval_required')->default(true);
            $table->enum('status', [
                'draft',
                'imported',
                'planning',
                'ready',
                'processing',
                'reviewing',
                'publishing',
                'completed',
                'paused',
                'failed',
            ])->default('draft');
            $table->json('settings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index('wordpress_site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
