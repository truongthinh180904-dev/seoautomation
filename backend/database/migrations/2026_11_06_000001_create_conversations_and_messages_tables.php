<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->nullable()->constrained('websites')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title')->default('Cuộc trò chuyện mới');
                $table->text('context_summary')->nullable();
                $table->string('current_mode', 50)->default('general');
                $table->boolean('pinned')->default(false);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'website_id', 'user_id'], 'idx_conv_tenant');
                $table->index(['workspace_id', 'last_message_at'], 'idx_conv_last_message');
                $table->index(['workspace_id', 'pinned'], 'idx_conv_pinned');
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role', 30);
                $table->longText('content');
                $table->json('tool_calls')->nullable();
                $table->json('tool_results')->nullable();
                $table->json('metadata')->nullable();
                $table->string('action_type', 50)->nullable();
                $table->string('status', 30)->default('completed');
                $table->timestamps();

                $table->index(['conversation_id', 'created_at'], 'idx_msg_conv');
                $table->index(['workspace_id'], 'idx_msg_tenant');
                $table->index(['workspace_id', 'status'], 'idx_msg_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
