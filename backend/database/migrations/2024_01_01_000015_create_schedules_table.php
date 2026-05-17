<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name', 255);
            $table->enum('type', ['keyword_processing', 'publishing', 'report']);
            $table->string('cron_expression', 100)->nullable();
        });
    }
    public function down(): void {
        Schema::dropIfExists('schedules');
    }
};
