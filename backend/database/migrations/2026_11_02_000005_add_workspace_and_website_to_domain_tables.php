<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables receiving workspace_id and website_id
     */
    protected array $tables = [
        'wordpress_sites',
        'campaigns',
        'campaign_imports',
        'keywords',
        'articles',
        'media_assets',
        'schedules',
        'notifications',
        'publishing_logs',
        'quality_reports',
    ];

    public function up(): void
    {
        // 1. Add workspace_id and website_id to domain tables
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'workspace_id')) {
                    $table->foreignId('workspace_id')
                        ->nullable()
                        ->constrained('workspaces')
                        ->nullOnDelete();
                    $table->index('workspace_id');
                }

                if (! Schema::hasColumn($tableName, 'website_id')) {
                    $table->foreignId('website_id')
                        ->nullable()
                        ->constrained('websites')
                        ->nullOnDelete();
                    $table->index('website_id');
                }
            });
        }

        // 2. Add tenant_id to quality_reports if missing
        if (Schema::hasTable('quality_reports') && ! Schema::hasColumn('quality_reports', 'tenant_id')) {
            Schema::table('quality_reports', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->constrained('tenants')
                    ->nullOnDelete();
                $table->index('tenant_id');
            });
        }

        // 3. Add missing tenant_id indexes on legacy tables if missing
        foreach (['competitor_analyses', 'article_versions', 'semantic_entities'] as $legacyTable) {
            if (Schema::hasTable($legacyTable) && Schema::hasColumn($legacyTable, 'tenant_id')) {
                try {
                    Schema::table($legacyTable, function (Blueprint $table) {
                        $table->index('tenant_id');
                    });
                } catch (Throwable $e) {
                    // Index might already exist
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach ($this->tables as $tableName) {
                if (! Schema::hasTable($tableName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'workspace_id')) {
                        try {
                            $table->dropForeign(['workspace_id']);
                        } catch (Throwable $e) {
                            // Foreign key might not exist
                        }
                    }
                    if (Schema::hasColumn($tableName, 'website_id')) {
                        try {
                            $table->dropForeign(['website_id']);
                        } catch (Throwable $e) {
                            // Foreign key might not exist
                        }
                    }

                    $cols = [];
                    if (Schema::hasColumn($tableName, 'workspace_id')) {
                        $cols[] = 'workspace_id';
                    }
                    if (Schema::hasColumn($tableName, 'website_id')) {
                        $cols[] = 'website_id';
                    }

                    if (! empty($cols)) {
                        $table->dropColumn($cols);
                    }
                });
            }

            if (Schema::hasTable('quality_reports') && Schema::hasColumn('quality_reports', 'tenant_id')) {
                Schema::table('quality_reports', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['tenant_id']);
                    } catch (Throwable $e) {
                        // Ignore
                    }
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
