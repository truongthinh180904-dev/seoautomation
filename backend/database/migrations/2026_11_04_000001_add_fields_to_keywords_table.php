<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            if (! Schema::hasColumn('keywords', 'search_intent')) {
                $table->string('search_intent', 50)->nullable()->after('cpc');
            }

            if (! Schema::hasColumn('keywords', 'lsi_keywords')) {
                $table->json('lsi_keywords')->nullable()->after('search_intent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            if (Schema::hasColumn('keywords', 'lsi_keywords')) {
                $table->dropColumn('lsi_keywords');
            }
        });
    }
};
