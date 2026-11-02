<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_orders')) {
            Schema::create('payment_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
                $table->string('reference_code', 64)->unique();
                $table->unsignedBigInteger('amount_vnd');
                $table->char('currency', 3)->default('VND');
                $table->string('payment_method', 50)->default('bank_transfer');
                $table->string('status', 30)->default('pending_payment');
                $table->json('bank_info_snapshot')->nullable();
                $table->string('proof_image_path', 2000)->nullable();
                $table->text('customer_note')->nullable();
                $table->timestamp('proof_submitted_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->string('idempotency_key', 100)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['status', 'created_at']);
                $table->index('reviewed_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
