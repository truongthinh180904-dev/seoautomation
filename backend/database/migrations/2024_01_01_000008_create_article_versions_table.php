<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedSmallInteger('version_number');
            $table->string('title', 500);
            $table->longText('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->string('change_reason')->nullable();
            $table->json('diff_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['article_id', 'version_number']);
            $table->index('article_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('article_versions');
    }
};
