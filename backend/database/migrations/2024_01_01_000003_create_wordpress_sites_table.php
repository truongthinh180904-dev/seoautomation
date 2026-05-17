<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('url', 500);
            $table->string('api_url', 500);
            $table->string('username');
            $table->string('app_password', 500);
            $table->unsignedInteger('default_author_id')->nullable();
            $table->unsignedInteger('default_category_id')->nullable();
            $table->enum('default_status', ['draft', 'publish'])->default('draft');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->enum('connection_status', ['connected', 'error', 'unchecked'])->default('unchecked');
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('wordpress_sites');
    }
};
