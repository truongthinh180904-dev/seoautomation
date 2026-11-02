<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_accounts')) {
            Schema::create('credit_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
                $table->integer('available_credits')->default(0);
                $table->integer('reserved_credits')->default(0);
                $table->unsignedInteger('cycle_granted_credits')->default(0);
                $table->unsignedInteger('cycle_used_credits')->default(0);
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->unsignedBigInteger('version')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
