<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('serp_cache', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 500);
            $table->string('language', 10)->default('vi');
            $table->string('country', 10)->default('vn');
            $table->string('provider', 50)->default('serper');
            $table->json('results');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['keyword', 'language', 'country', 'provider']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_cache');
    }
};
