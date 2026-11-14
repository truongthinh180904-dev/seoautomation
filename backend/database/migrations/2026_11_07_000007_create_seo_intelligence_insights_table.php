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
        Schema::create('seo_intelligence_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('type', 50);
            $table->string('severity', 20)->default('info');
            $table->unsignedSmallInteger('opportunity_score')->default(0);
            $table->string('title', 255);
            $table->text('description');
            $table->json('metrics_snapshot');
            $table->string('suggested_action', 100);
            $table->string('status', 30)->default('detected');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'website_id', 'status'], 'idx_insights_tenant');
            $table->index('article_id', 'idx_insights_article');
            $table->index(['website_id', 'type'], 'idx_insights_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_intelligence_insights');
    }
};
