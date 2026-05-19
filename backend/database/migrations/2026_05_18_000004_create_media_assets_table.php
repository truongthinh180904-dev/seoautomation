<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->enum('source_type', ['uploaded', 'external_url', 'ai_generated', 'wordpress_existing'])->default('external_url');
            $table->string('source_url', 2000)->nullable();
            $table->string('local_path', 2000)->nullable();
            $table->bigInteger('wordpress_media_id')->nullable();
            $table->string('wordpress_media_url', 2000)->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->string('credit', 500)->nullable();
            $table->enum('status', ['pending', 'downloaded', 'uploaded', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'campaign_id']);
            $table->index(['article_id', 'status']);
            $table->index('wordpress_media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
