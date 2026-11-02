<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 50)->nullable();
                $table->string('action', 120);
                $table->string('auditable_type', 150)->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['workspace_id', 'created_at']);
                $table->index(['actor_id', 'created_at']);
                $table->index(['action', 'created_at']);
                $table->index(['auditable_type', 'auditable_id']);
            });
        }

        // Backfill data migration inside transaction
        DB::transaction(function () {
            if (! Schema::hasTable('tenants') || ! Schema::hasTable('workspaces')) {
                return;
            }

            $tenants = DB::table('tenants')->get();
            foreach ($tenants as $tenant) {
                $existingWorkspace = DB::table('workspaces')->where('legacy_tenant_id', $tenant->id)->first();
                if ($existingWorkspace) {
                    continue;
                }

                // Find a user belonging to this tenant
                $user = DB::table('users')->where('tenant_id', $tenant->id)->first()
                    ?? DB::table('users')->first();

                if (! $user) {
                    continue;
                }

                // Ensure unique slug
                $slug = $tenant->slug ?? Str::slug($tenant->name);
                if (DB::table('workspaces')->where('slug', $slug)->exists()) {
                    $slug .= '-'.$tenant->id;
                }

                $now = now();
                $workspaceId = DB::table('workspaces')->insertGetId([
                    'owner_id' => $user->id,
                    'legacy_tenant_id' => $tenant->id,
                    'name' => $tenant->name ?? 'Default Workspace',
                    'slug' => $slug,
                    'status' => 'active',
                    'settings' => $tenant->settings ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Attach member
                if (! DB::table('workspace_members')->where('workspace_id', $workspaceId)->where('user_id', $user->id)->exists()) {
                    DB::table('workspace_members')->insert([
                        'workspace_id' => $workspaceId,
                        'user_id' => $user->id,
                        'role' => 'owner',
                        'joined_at' => $now,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Create credit account
                if (Schema::hasTable('credit_accounts') && ! DB::table('credit_accounts')->where('workspace_id', $workspaceId)->exists()) {
                    DB::table('credit_accounts')->insert([
                        'workspace_id' => $workspaceId,
                        'available_credits' => 0,
                        'reserved_credits' => 0,
                        'cycle_granted_credits' => 0,
                        'cycle_used_credits' => 0,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Update default_workspace_id on user if empty
                if (Schema::hasColumn('users', 'default_workspace_id')) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->whereNull('default_workspace_id')
                        ->update(['default_workspace_id' => $workspaceId]);
                }

                // Backfill domain tables workspace_id
                $domainTables = [
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
                    'ai_usage_logs',
                ];

                foreach ($domainTables as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id') && Schema::hasColumn($table, 'workspace_id')) {
                        DB::table($table)
                            ->where('tenant_id', $tenant->id)
                            ->whereNull('workspace_id')
                            ->update(['workspace_id' => $workspaceId]);
                    }
                }

                // Backfill websites from wordpress_sites
                if (Schema::hasTable('wordpress_sites') && Schema::hasTable('websites')) {
                    $wpSites = DB::table('wordpress_sites')->where('tenant_id', $tenant->id)->get();
                    foreach ($wpSites as $wpSite) {
                        $parsedHost = parse_url($wpSite->url, PHP_URL_HOST) ?? ($wpSite->url ?: 'site-'.$wpSite->id.'.local');
                        $domain = strtolower($parsedHost);

                        $websiteId = DB::table('websites')->insertGetId([
                            'workspace_id' => $workspaceId,
                            'wordpress_site_id' => $wpSite->id,
                            'name' => $wpSite->name,
                            'domain' => $domain,
                            'url' => $wpSite->url,
                            'default_language' => 'vi',
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        if (Schema::hasColumn('wordpress_sites', 'website_id')) {
                            DB::table('wordpress_sites')->where('id', $wpSite->id)->update(['website_id' => $websiteId]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
