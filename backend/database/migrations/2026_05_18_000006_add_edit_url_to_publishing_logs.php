<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('publishing_logs', function (Blueprint $table) {
            $table->string('wordpress_edit_url', 2000)->nullable()->after('published_url');
        });
    }

    public function down(): void
    {
        Schema::table('publishing_logs', function (Blueprint $table) {
            $table->dropColumn('wordpress_edit_url');
        });
    }
};
