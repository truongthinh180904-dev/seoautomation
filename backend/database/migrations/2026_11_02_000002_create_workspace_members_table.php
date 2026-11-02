<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspace_members')) {
            Schema::create('workspace_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('editor');
                $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('joined_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['workspace_id', 'user_id']);
                $table->index(['user_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
    }
};
