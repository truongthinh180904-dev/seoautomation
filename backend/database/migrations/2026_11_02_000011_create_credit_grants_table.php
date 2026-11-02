<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_grants')) {
            Schema::create('credit_grants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credit_account_id')->constrained('credit_accounts')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
                $table->string('source_type', 40);
                $table->unsignedInteger('credits_granted');
                $table->unsignedInteger('credits_remaining');
                $table->timestamp('granted_at');
                $table->timestamp('expires_at');
                $table->timestamp('expired_processed_at')->nullable();
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('idempotency_key', 120)->unique();
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'expires_at']);
                $table->index(['expired_processed_at', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_grants');
    }
};
