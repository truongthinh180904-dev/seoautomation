<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brand_knowledge_bases')) {
            Schema::table('brand_knowledge_bases', function (Blueprint $table) {
                if (! Schema::hasColumn('brand_knowledge_bases', 'industry_key')) {
                    $table->string('industry_key', 64)->nullable()->after('tone_of_voice');
                }
                if (! Schema::hasColumn('brand_knowledge_bases', 'business_model')) {
                    $table->string('business_model', 32)->nullable()->after('industry_key');
                }
                if (! Schema::hasColumn('brand_knowledge_bases', 'business_profile')) {
                    $table->json('business_profile')->nullable()->after('custom_rules');
                }
                if (! Schema::hasColumn('brand_knowledge_bases', 'eeat_credentials')) {
                    $table->json('eeat_credentials')->nullable()->after('business_profile');
                }
                if (! Schema::hasColumn('brand_knowledge_bases', 'brand_orientation')) {
                    $table->json('brand_orientation')->nullable()->after('eeat_credentials');
                }
                if (! Schema::hasColumn('brand_knowledge_bases', 'industry_profile')) {
                    $table->json('industry_profile')->nullable()->after('brand_orientation');
                }
            });
        }

        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) {
                if (! Schema::hasColumn('articles', 'brand_knowledge_id')) {
                    $table->unsignedBigInteger('brand_knowledge_id')->nullable()->after('website_id');
                }
                if (! Schema::hasColumn('articles', 'content_type')) {
                    $table->string('content_type', 64)->nullable()->after('focus_keyword');
                }
                if (! Schema::hasColumn('articles', 'search_intent')) {
                    $table->string('search_intent', 64)->nullable()->after('content_type');
                }
                if (! Schema::hasColumn('articles', 'secondary_intents')) {
                    $table->json('secondary_intents')->nullable()->after('search_intent');
                }
                if (! Schema::hasColumn('articles', 'secondary_keywords')) {
                    $table->json('secondary_keywords')->nullable()->after('secondary_intents');
                }
                if (! Schema::hasColumn('articles', 'content_brief')) {
                    $table->json('content_brief')->nullable()->after('secondary_keywords');
                }
                if (! Schema::hasColumn('articles', 'strategy_analysis')) {
                    $table->json('strategy_analysis')->nullable()->after('outline');
                }
                if (! Schema::hasColumn('articles', 'schema_markup')) {
                    $table->json('schema_markup')->nullable()->after('internal_links');
                }
                if (! Schema::hasColumn('articles', 'review_pipeline_report')) {
                    $table->json('review_pipeline_report')->nullable()->after('quality_report');
                }
                if (! Schema::hasColumn('articles', 'gate_status')) {
                    $table->string('gate_status', 32)->nullable()->after('review_pipeline_report');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('brand_knowledge_bases')) {
            Schema::table('brand_knowledge_bases', function (Blueprint $table) {
                $cols = ['industry_key', 'business_model', 'business_profile', 'eeat_credentials', 'brand_orientation', 'industry_profile'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('brand_knowledge_bases', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) {
                $cols = [
                    'brand_knowledge_id',
                    'content_type',
                    'search_intent',
                    'secondary_intents',
                    'secondary_keywords',
                    'content_brief',
                    'strategy_analysis',
                    'schema_markup',
                    'review_pipeline_report',
                    'gate_status',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('articles', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
