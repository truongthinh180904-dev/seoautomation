<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('topic_clusters')) {
            Schema::create('topic_clusters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->foreignId('pillar_keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
                $table->foreignId('pillar_article_id')->nullable()->constrained('articles')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('target_audience')->nullable();
                $table->unsignedInteger('total_search_volume')->default(0);
                $table->decimal('avg_difficulty', 5, 2)->default(0.00);
                $table->string('status', 50)->default('planning'); // planning, in_progress, completed
                $table->timestamps();

                $table->unique(['workspace_id', 'website_id', 'slug'], 'unique_cluster_slug');
                $table->index(['workspace_id', 'website_id'], 'idx_clusters_tenant');
                $table->index('status', 'idx_clusters_status');
            });
        }

        if (Schema::hasTable('keywords') && ! Schema::hasColumn('keywords', 'topic_cluster_id')) {
            Schema::table('keywords', function (Blueprint $table) {
                $table->foreignId('topic_cluster_id')->nullable()->after('campaign_id')->constrained('topic_clusters')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('content_gaps')) {
            Schema::create('content_gaps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('competitor_domain');
                $table->string('target_keyword');
                $table->unsignedInteger('search_volume')->default(0);
                $table->unsignedSmallInteger('keyword_difficulty')->default(0);
                $table->unsignedSmallInteger('competitor_rank')->default(0);
                $table->unsignedSmallInteger('our_rank')->nullable();
                $table->decimal('opportunity_score', 5, 2)->default(0.00);
                $table->string('status', 50)->default('identified'); // identified, targeted, written, ignored
                $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                $table->timestamps();

                $table->index(['workspace_id', 'website_id'], 'idx_content_gaps_tenant');
                $table->index('target_keyword', 'idx_content_gaps_keyword');
                $table->index('competitor_domain', 'idx_content_gaps_competitor');
                $table->index('status', 'idx_content_gaps_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('keywords') && Schema::hasColumn('keywords', 'topic_cluster_id')) {
            Schema::table('keywords', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['topic_cluster_id']);
                }
                $table->dropColumn('topic_cluster_id');
            });
        }

        Schema::dropIfExists('content_gaps');
        Schema::dropIfExists('topic_clusters');
    }
};
