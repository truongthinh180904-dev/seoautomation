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
        if (! Schema::hasTable('internal_link_suggestions')) {
            Schema::create('internal_link_suggestions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->foreignId('source_article_id')->constrained('articles')->cascadeOnDelete();
                $table->foreignId('target_article_id')->constrained('articles')->cascadeOnDelete();
                $table->string('suggested_anchor_text', 255);
                $table->text('context_sentence')->nullable();
                $table->decimal('relevance_score', 5, 2)->default(0.00);
                $table->string('status', 50)->default('pending'); // pending, accepted, rejected, applied
                $table->timestamps();

                $table->index(['workspace_id', 'website_id'], 'idx_link_sugg_tenant');
                $table->index('source_article_id', 'idx_link_sugg_source');
                $table->index('target_article_id', 'idx_link_sugg_target');
                $table->index('status', 'idx_link_sugg_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_link_suggestions');
    }
};
