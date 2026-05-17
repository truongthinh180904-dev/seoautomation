<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('competitor_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serp_result_id')->constrained('serp_results')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->string('url', 2000);
            $table->unsignedInteger('word_count')->nullable();
            $table->json('heading_structure')->nullable();
            $table->json('semantic_entities')->nullable();
            $table->json('semantic_keywords')->nullable();
            $table->string('search_intent', 100)->nullable();
            $table->json('faqs')->nullable();
            $table->json('article_structure')->nullable();
            $table->decimal('topical_relevance', 5, 2)->nullable();
            $table->char('raw_html_hash', 64)->nullable();
            $table->boolean('analysis_completed')->default(false);
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            
            $table->index('keyword_id');
            $table->index('serp_result_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('competitor_analyses');
    }
};
