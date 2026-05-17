<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('zalo_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('article_id')->constrained('articles');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('zalo_user_id', 100);
            $table->enum('message_type', ['review_request', 'approved', 'rejected', 'published']);
            $table->text('message_text')->nullable();
            $table->string('zalo_message_id', 255)->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('article_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('zalo_notifications');
    }
};
