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
        if (! Schema::hasTable('brand_knowledge_bases')) {
            Schema::create('brand_knowledge_bases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('brand_name');
                $table->string('tagline')->nullable();
                $table->string('tone_of_voice', 100)->default('professional');
                $table->json('target_personas')->nullable();
                $table->json('unique_selling_points')->nullable();
                $table->json('product_catalog')->nullable();
                $table->json('taboo_words')->nullable();
                $table->json('preferred_terms')->nullable();
                $table->text('custom_rules')->nullable();
                $table->boolean('is_default')->default(true);
                $table->timestamps();

                $table->index(['workspace_id', 'website_id'], 'idx_brand_kb_tenant');
                $table->index(['website_id', 'is_default'], 'idx_brand_kb_default');
            });
        }

        if (Schema::hasTable('articles') && ! Schema::hasColumn('articles', 'brand_knowledge_id')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->foreignId('brand_knowledge_id')->nullable()->after('website_id')->constrained('brand_knowledge_bases')->nullOnDelete();
            });
        }

        if (Schema::hasTable('websites') && ! Schema::hasColumn('websites', 'brand_voice_summary')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->text('brand_voice_summary')->nullable()->after('settings');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('articles') && Schema::hasColumn('articles', 'brand_knowledge_id')) {
            Schema::table('articles', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['brand_knowledge_id']);
                }
                $table->dropColumn('brand_knowledge_id');
            });
        }

        if (Schema::hasTable('websites') && Schema::hasColumn('websites', 'brand_voice_summary')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('brand_voice_summary');
            });
        }

        Schema::dropIfExists('brand_knowledge_bases');
    }
};
