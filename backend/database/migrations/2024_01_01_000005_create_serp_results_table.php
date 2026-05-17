<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('serp_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedTinyInteger('position');
            $table->string('url', 2000);
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('domain')->nullable();
            $table->boolean('is_crawled')->default(false);
            $table->timestamp('crawled_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('keyword_id');
            $table->index(['tenant_id', 'keyword_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('serp_results');
    }
};
