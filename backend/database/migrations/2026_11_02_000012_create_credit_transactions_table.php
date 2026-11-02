<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_transactions')) {
            Schema::create('credit_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credit_account_id')->constrained('credit_accounts')->restrictOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
                $table->foreignId('credit_grant_id')->nullable()->constrained('credit_grants')->nullOnDelete();
                $table->string('type', 30);
                $table->integer('amount');
                $table->integer('balance_before');
                $table->integer('balance_after');
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('idempotency_key', 120)->unique();
                $table->string('description', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['workspace_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
                $table->index(['workspace_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
