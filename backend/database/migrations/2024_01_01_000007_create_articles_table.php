<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('keyword_id')->constrained('keywords');
            $table->foreignId('wordpress_site_id')->nullable()->constrained('wordpress_sites')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->string('title', 500);
            $table->string('slug', 500)->nullable();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('focus_keyword')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->json('outline')->nullable();
            $table->json('faqs')->nullable();
            $table->json('internal_links')->nullable();
            $table->string('featured_image_url', 2000)->nullable();
            $table->string('ai_provider', 50)->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->unsignedInteger('ai_tokens_used')->default(0);
            $table->decimal('ai_cost_usd', 10, 6)->default(0);
            $table->enum('status', ['draft', 'review', 'approved', 'rejected', 'publishing', 'published', 'failed'])->default('draft');
            $table->char('review_token', 64)->nullable()->unique();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('scheduled_publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->bigInteger('wordpress_post_id')->nullable();
            $table->string('wordpress_post_url', 2000)->nullable();
            $table->char('duplicate_check_hash', 64)->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index('keyword_id');
            $table->index('review_token');
            $table->index(['tenant_id', 'scheduled_publish_at']);
            $table->index('duplicate_check_hash');
        });
    }
    public function down(): void {
        Schema::dropIfExists('articles');
    }
};
