<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('price_monthly_vnd')->default(0);
                $table->string('billing_interval', 30)->default('monthly');
                $table->unsignedInteger('credits_per_cycle')->default(0);
                $table->unsignedInteger('articles_limit_per_cycle')->default(0);
                $table->unsignedInteger('websites_limit')->default(1);
                $table->unsignedSmallInteger('max_concurrent_tasks')->default(2);
                $table->unsignedTinyInteger('max_retries_per_task')->default(2);
                $table->unsignedTinyInteger('max_images_per_article')->default(3);
                $table->boolean('carryover')->default(false);
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
