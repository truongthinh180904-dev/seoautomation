<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('legacy_tenant_id')->nullable()->unique()->constrained('tenants')->nullOnDelete();
                $table->string('name', 255);
                $table->string('slug', 120)->unique();
                $table->string('status', 30)->default('active');
                $table->json('settings')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->string('suspension_reason', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('owner_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
