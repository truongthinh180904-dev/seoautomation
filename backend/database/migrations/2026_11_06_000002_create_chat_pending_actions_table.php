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
        if (! Schema::hasTable('chat_pending_actions')) {
            Schema::create('chat_pending_actions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('action_name', 100);
                $table->string('target_entity_type', 100)->nullable();
                $table->unsignedBigInteger('target_entity_id')->nullable();
                $table->unsignedInteger('estimated_credits')->default(0);
                $table->json('action_payload');
                $table->string('status', 30)->default('pending');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index(['conversation_id', 'status'], 'idx_action_status');
                $table->index(['workspace_id', 'status'], 'idx_action_tenant');
                $table->index(['status', 'expires_at'], 'idx_action_expiry');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_pending_actions');
    }
};
