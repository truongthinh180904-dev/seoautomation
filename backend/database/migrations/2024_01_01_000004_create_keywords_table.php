<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('user_id')->constrained('users');
            $table->string('keyword', 500);
            $table->string('language', 10)->default('vi');
            $table->unsignedInteger('search_volume')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->decimal('cpc', 10, 4)->nullable();
            $table->enum('search_intent', ['informational', 'navigational', 'transactional', 'commercial'])->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('wordpress_site_id')->nullable()->constrained('wordpress_sites')->nullOnDelete();
            $table->string('batch_id', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'keyword']);
            $table->index('batch_id');
            $table->index('scheduled_at');
            $table->index('wordpress_site_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('keywords');
    }
};
