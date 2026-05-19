<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('user_id')->constrained('campaigns')->nullOnDelete();
            $table->string('pillar_topic', 255)->nullable()->after('wordpress_site_id');
            $table->string('content_cluster', 255)->nullable()->after('pillar_topic');
            $table->string('funnel_stage', 100)->nullable()->after('content_cluster');
            $table->unsignedInteger('target_word_count')->nullable()->after('funnel_stage');
            $table->string('target_url', 2000)->nullable()->after('target_word_count');
            $table->string('canonical_url', 2000)->nullable()->after('target_url');
            $table->text('brief_notes')->nullable()->after('canonical_url');
            $table->json('must_include_points')->nullable()->after('brief_notes');
            $table->json('avoid_topics')->nullable()->after('must_include_points');
            $table->json('reference_urls')->nullable()->after('avoid_topics');
            $table->json('competitor_urls_override')->nullable()->after('reference_urls');
            $table->json('raw_import_row')->nullable()->after('competitor_urls_override');
            $table->string('template_version', 50)->nullable()->after('raw_import_row');

            $table->index(['tenant_id', 'campaign_id']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('keyword_id')->constrained('campaigns')->nullOnDelete();
            $table->string('wp_post_type', 50)->default('post')->after('wordpress_post_url');
            $table->string('wp_status', 50)->nullable()->after('wp_post_type');
            $table->json('wp_category_ids')->nullable()->after('wp_status');
            $table->json('wp_tag_ids')->nullable()->after('wp_category_ids');
            $table->json('wp_tag_names')->nullable()->after('wp_tag_ids');
            $table->unsignedBigInteger('wp_author_id')->nullable()->after('wp_tag_names');
            $table->string('wp_slug', 500)->nullable()->after('wp_author_id');
            $table->string('canonical_url', 2000)->nullable()->after('wp_slug');
            $table->text('primary_cta')->nullable()->after('canonical_url');
            $table->text('secondary_cta')->nullable()->after('primary_cta');
            $table->json('media_plan')->nullable()->after('secondary_cta');
            $table->json('image_assets')->nullable()->after('media_plan');
            $table->json('quality_report')->nullable()->after('image_assets');
            $table->boolean('approval_required')->default(true)->after('quality_report');
            $table->timestamp('approved_at')->nullable()->after('approval_required');

            $table->index(['tenant_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'campaign_id']);
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn([
                'wp_post_type',
                'wp_status',
                'wp_category_ids',
                'wp_tag_ids',
                'wp_tag_names',
                'wp_author_id',
                'wp_slug',
                'canonical_url',
                'primary_cta',
                'secondary_cta',
                'media_plan',
                'image_assets',
                'quality_report',
                'approval_required',
                'approved_at',
            ]);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'campaign_id']);
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn([
                'pillar_topic',
                'content_cluster',
                'funnel_stage',
                'target_word_count',
                'target_url',
                'canonical_url',
                'brief_notes',
                'must_include_points',
                'avoid_topics',
                'reference_urls',
                'competitor_urls_override',
                'raw_import_row',
                'template_version',
            ]);
        });
    }
};
