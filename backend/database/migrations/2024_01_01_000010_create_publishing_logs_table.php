<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('publishing_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('article_id')->constrained('articles');
            $table->foreignId('wordpress_site_id')->constrained('wordpress_sites');
            $table->bigInteger('wordpress_post_id')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->enum('status', ['success', 'failed', 'partial']);
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->string('published_url', 2000)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('article_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('publishing_logs');
    }
};
