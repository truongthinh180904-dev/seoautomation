<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('cron_expression');
            }

            if (!Schema::hasColumn('schedules', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('scheduled_at');
            }

            if (!Schema::hasColumn('schedules', 'status')) {
                $table->enum('status', ['active', 'paused', 'completed', 'failed'])
                    ->default('active')
                    ->after('is_recurring');
            }

            if (!Schema::hasColumn('schedules', 'config')) {
                $table->json('config')->nullable()->after('status');
            }

            if (!Schema::hasColumn('schedules', 'last_run_at')) {
                $table->timestamp('last_run_at')->nullable()->after('config');
            }

            if (!Schema::hasColumn('schedules', 'next_run_at')) {
                $table->timestamp('next_run_at')->nullable()->after('last_run_at');
            }

            if (!Schema::hasColumn('schedules', 'run_count')) {
                $table->unsignedInteger('run_count')->default(0)->after('next_run_at');
            }

            if (!Schema::hasColumn('schedules', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('run_count')
                    ->constrained('users');
            }

            if (!Schema::hasColumn('schedules', 'created_at')) {
                $table->timestamps();
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasIndex('schedules', 'schedules_tenant_id_status_index')
                && !Schema::hasIndex('schedules', 'schedules_tenant_status_index')) {
                $table->index(['tenant_id', 'status'], 'schedules_tenant_status_index');
            }

            if (!Schema::hasIndex('schedules', 'schedules_next_run_at_index')) {
                $table->index('next_run_at', 'schedules_next_run_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasIndex('schedules', 'schedules_tenant_status_index')) {
                $table->dropIndex('schedules_tenant_status_index');
            }

            if (Schema::hasIndex('schedules', 'schedules_next_run_at_index')) {
                $table->dropIndex('schedules_next_run_at_index');
            }

            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'scheduled_at',
                'is_recurring',
                'status',
                'config',
                'last_run_at',
                'next_run_at',
                'run_count',
                'created_at',
                'updated_at',
            ]);
        });
    }
};
