<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('semantic_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->string('entity_text', 500);
            $table->enum('entity_type', ['person', 'organization', 'location', 'product', 'concept', 'event', 'other']);
            $table->decimal('relevance_score', 5, 4)->nullable();
            $table->unsignedSmallInteger('frequency')->default(1);
            $table->enum('source', ['competitor', 'ai_generated'])->default('competitor');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('article_id');
            $table->index('entity_type');
        });
    }
    public function down(): void {
        Schema::dropIfExists('semantic_entities');
    }
};
