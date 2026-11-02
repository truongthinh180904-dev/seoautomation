<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
                $table->string('status', 30)->default('active');
                $table->timestamp('current_period_start');
                $table->timestamp('current_period_end');
                $table->timestamp('last_credit_granted_at')->nullable();
                $table->timestamp('next_credit_grant_at')->nullable();
                $table->unsignedBigInteger('activated_by_order_id')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['next_credit_grant_at', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
