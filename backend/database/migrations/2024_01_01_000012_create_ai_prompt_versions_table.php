<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('agent_type', 100);
            $table->string('version', 50);
            $table->string('name', 255);
            $table->longText('system_prompt')->nullable();
            $table->longText('user_prompt_template');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['agent_type', 'is_active']);
            $table->index(['tenant_id', 'agent_type']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('ai_prompt_versions');
    }
};
