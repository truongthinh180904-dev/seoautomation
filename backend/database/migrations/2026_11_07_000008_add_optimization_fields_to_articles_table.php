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
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'last_optimized_at')) {
                $table->timestamp('last_optimized_at')->nullable()->after('quality_report');
            }
            if (! Schema::hasColumn('articles', 'optimization_notes')) {
                $table->text('optimization_notes')->nullable()->after('last_optimized_at');
            }
            if (! Schema::hasColumn('articles', 'pre_optimization_score')) {
                $table->unsignedInteger('pre_optimization_score')->nullable()->after('optimization_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['last_optimized_at', 'optimization_notes', 'pre_optimization_score']);
        });
    }
};
