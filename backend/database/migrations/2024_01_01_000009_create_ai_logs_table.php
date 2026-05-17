<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->string('agent_type', 100);
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('prompt_version', 50)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost_usd', 12, 8)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->enum('status', ['success', 'failed', 'timeout', 'rate_limited']);
            $table->text('error_message')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['tenant_id', 'created_at']);
            $table->index('article_id');
            $table->index('agent_type');
            $table->index('status');
        });
    }
    public function down(): void {
        Schema::dropIfExists('ai_logs');
    }
};
