<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'default_workspace_id')) {
                $table->foreignId('default_workspace_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('workspaces')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'is_platform_admin')) {
                $table->boolean('is_platform_admin')->default(false)->after('role');
            }

            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
        });

        // Make tenant_id nullable for super_admins/SaaS mode
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        } catch (Throwable $e) {
            // Driver specific fallback
        }

        // Expand app_password length on wordpress_sites
        try {
            Schema::table('wordpress_sites', function (Blueprint $table) {
                $table->text('app_password')->nullable()->change();
            });
        } catch (Throwable $e) {
            // Driver specific fallback
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'default_workspace_id')) {
                    try {
                        $table->dropForeign(['default_workspace_id']);
                    } catch (Throwable $e) {
                        // Ignore
                    }
                }

                $columnsToDrop = [];
                foreach (['default_workspace_id', 'is_platform_admin', 'email_verified_at', 'remember_token'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $columnsToDrop[] = $column;
                    }
                }

                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
