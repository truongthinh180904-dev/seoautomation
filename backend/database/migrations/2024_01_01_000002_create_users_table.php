<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->enum('role', ['super_admin', 'admin', 'editor', 'viewer']);
            $table->string('zalo_user_id', 100)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['email', 'tenant_id']);
            $table->index(['tenant_id', 'zalo_user_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('users');
    }
};
