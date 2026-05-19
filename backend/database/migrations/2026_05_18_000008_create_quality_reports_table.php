<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quality_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->unsignedTinyInteger('readability_score')->default(0);
            $table->unsignedTinyInteger('media_score')->default(0);
            $table->unsignedTinyInteger('wordpress_readiness_score')->default(0);
            $table->unsignedTinyInteger('total_score')->default(0);
            $table->json('checks')->nullable();
            $table->json('warnings')->nullable();
            $table->json('blocking_errors')->nullable();
            $table->json('auto_fixable')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'generated_at']);
            $table->index('total_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_reports');
    }
};
