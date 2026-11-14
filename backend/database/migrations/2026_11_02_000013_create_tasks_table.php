<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                $table->foreignId('keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
                $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
                $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 80);
                $table->string('current_step', 80)->nullable();
                $table->string('status', 30)->default('pending');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->unsignedInteger('credits_reserved')->default(0);
                $table->unsignedInteger('credits_settled')->default(0);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(3);
                $table->string('unique_lock_key', 191)->nullable()->index();
                $table->string('error_code', 80)->nullable();
                $table->text('error_message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['campaign_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
