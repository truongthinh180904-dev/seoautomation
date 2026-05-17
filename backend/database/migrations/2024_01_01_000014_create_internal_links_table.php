<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('internal_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('wordpress_site_id')->constrained('wordpress_sites');
            $table->foreignId('source_article_id')->constrained('articles');
            $table->string('target_url', 2000);
            $table->bigInteger('target_post_id')->nullable();
            $table->string('anchor_text', 500);
            $table->text('context_sentence')->nullable();
            $table->decimal('relevance_score', 5, 4)->nullable();
            $table->boolean('is_placed')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('source_article_id');
            $table->index(['tenant_id', 'wordpress_site_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('internal_links');
    }
};
