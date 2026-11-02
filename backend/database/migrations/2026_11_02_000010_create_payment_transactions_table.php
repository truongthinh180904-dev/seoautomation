<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_order_id')->constrained('payment_orders')->restrictOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
                $table->string('transaction_code', 100)->nullable()->index();
                $table->unsignedBigInteger('amount_vnd');
                $table->string('direction', 20)->default('inbound');
                $table->string('status', 30)->default('verified');
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
