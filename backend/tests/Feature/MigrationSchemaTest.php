<?php

namespace Tests\Feature;

use App\Enums\BillingInterval;
use App\Enums\CreditGrantSource;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\TaskStatus;
use App\Enums\UsageRecordStatus;
use App\Enums\WebsiteStatus;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\CreditAccount;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\Keyword;
use App\Models\PaymentOrder;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_saas_database_tables_exist(): void
    {
        $expectedTables = [
            'users',
            'tenants',
            'workspaces',
            'workspace_members',
            'websites',
            'plans',
            'subscriptions',
            'credit_accounts',
            'payment_orders',
            'payment_transactions',
            'credit_grants',
            'credit_transactions',
            'tasks',
            'usage_records',
            'audit_logs',
            'wordpress_sites',
            'campaigns',
            'campaign_imports',
            'keywords',
            'articles',
            'article_versions',
            'media_assets',
            'quality_reports',
            'schedules',
            'ai_usage_logs',
            'personal_access_tokens',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table '{$table}' should exist in the schema.");
        }
    }

    public function test_expected_columns_exist_on_saas_tables(): void
    {
        $tableColumns = [
            'users' => [
                'tenant_id',
                'default_workspace_id',
                'is_platform_admin',
                'email_verified_at',
                'remember_token',
            ],
            'workspaces' => [
                'owner_id',
                'legacy_tenant_id',
                'name',
                'slug',
                'status',
                'settings',
                'suspended_at',
                'suspension_reason',
            ],
            'workspace_members' => [
                'workspace_id',
                'user_id',
                'role',
                'invited_by',
                'joined_at',
                'is_active',
            ],
            'websites' => [
                'workspace_id',
                'wordpress_site_id',
                'name',
                'domain',
                'url',
                'default_language',
                'niche',
                'status',
                'settings',
            ],
            'plans' => [
                'code',
                'name',
                'description',
                'price_monthly_vnd',
                'billing_interval',
                'credits_per_cycle',
                'articles_limit_per_cycle',
                'websites_limit',
                'max_concurrent_tasks',
                'max_retries_per_task',
                'max_images_per_article',
                'carryover',
                'features',
                'is_active',
                'sort_order',
            ],
            'subscriptions' => [
                'workspace_id',
                'plan_id',
                'status',
                'current_period_start',
                'current_period_end',
                'last_credit_granted_at',
                'next_credit_grant_at',
                'activated_by_order_id',
                'cancelled_at',
                'metadata',
            ],
            'credit_accounts' => [
                'workspace_id',
                'available_credits',
                'reserved_credits',
                'cycle_granted_credits',
                'cycle_used_credits',
                'period_start',
                'period_end',
                'version',
            ],
            'payment_orders' => [
                'workspace_id',
                'user_id',
                'plan_id',
                'reference_code',
                'amount_vnd',
                'currency',
                'payment_method',
                'status',
                'bank_info_snapshot',
                'proof_image_path',
                'customer_note',
                'proof_submitted_at',
                'reviewed_by',
                'reviewed_at',
                'admin_note',
                'rejection_reason',
                'idempotency_key',
                'expires_at',
            ],
            'payment_transactions' => [
                'payment_order_id',
                'workspace_id',
                'transaction_code',
                'amount_vnd',
                'direction',
                'status',
                'verified_by',
                'paid_at',
                'raw_payload',
            ],
            'credit_grants' => [
                'credit_account_id',
                'workspace_id',
                'subscription_id',
                'payment_order_id',
                'source_type',
                'credits_granted',
                'credits_remaining',
                'granted_at',
                'expires_at',
                'expired_processed_at',
                'granted_by',
                'idempotency_key',
                'note',
            ],
            'credit_transactions' => [
                'credit_account_id',
                'workspace_id',
                'credit_grant_id',
                'type',
                'amount',
                'balance_before',
                'balance_after',
                'reference_type',
                'reference_id',
                'actor_id',
                'idempotency_key',
                'description',
                'metadata',
                'created_at',
            ],
            'tasks' => [
                'uuid',
                'workspace_id',
                'website_id',
                'campaign_id',
                'keyword_id',
                'article_id',
                'triggered_by',
                'type',
                'current_step',
                'status',
                'progress',
                'credits_reserved',
                'credits_settled',
                'attempts',
                'max_attempts',
                'unique_lock_key',
                'error_code',
                'error_message',
                'payload',
                'started_at',
                'finished_at',
                'cancelled_at',
            ],
            'usage_records' => [
                'workspace_id',
                'website_id',
                'campaign_id',
                'article_id',
                'task_id',
                'credit_transaction_id',
                'action_type',
                'credits_charged',
                'tokens_used',
                'provider_cost_usd',
                'status',
                'usage_date',
            ],
            'audit_logs' => [
                'workspace_id',
                'actor_id',
                'actor_role',
                'action',
                'auditable_type',
                'auditable_id',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent',
                'notes',
                'created_at',
            ],
            'wordpress_sites' => ['workspace_id', 'website_id'],
            'campaigns' => ['workspace_id', 'website_id'],
            'keywords' => ['workspace_id', 'website_id'],
            'articles' => ['workspace_id', 'website_id'],
            'quality_reports' => ['tenant_id', 'workspace_id', 'website_id'],
            'ai_usage_logs' => ['workspace_id', 'website_id', 'task_id', 'credits_charged', 'request_hash'],
        ];

        foreach ($tableColumns as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "Table '{$table}' must contain column '{$column}'."
                );
            }
        }
    }

    public function test_plan_seeder_creates_four_saas_plans(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseCount('plans', 4);

        $free = Plan::where('code', PlanCode::FREE)->first();
        $this->assertNotNull($free);
        $this->assertEquals(0, $free->price_monthly_vnd);
        $this->assertEquals(BillingInterval::DAILY, $free->billing_interval);
        $this->assertEquals(100, $free->credits_per_cycle);
        $this->assertEquals(5, $free->articles_limit_per_cycle);

        $basic = Plan::where('code', PlanCode::BASIC)->first();
        $this->assertNotNull($basic);
        $this->assertEquals(450000, $basic->price_monthly_vnd);
        $this->assertEquals(BillingInterval::MONTHLY, $basic->billing_interval);
        $this->assertEquals(2000, $basic->credits_per_cycle);
        $this->assertEquals(100, $basic->articles_limit_per_cycle);

        $pro = Plan::where('code', PlanCode::PRO)->first();
        $this->assertNotNull($pro);
        $this->assertEquals(1250000, $pro->price_monthly_vnd);
        $this->assertEquals(BillingInterval::MONTHLY, $pro->billing_interval);
        $this->assertEquals(7000, $pro->credits_per_cycle);
        $this->assertEquals(350, $pro->articles_limit_per_cycle);

        $business = Plan::where('code', PlanCode::BUSINESS)->first();
        $this->assertNotNull($business);
        $this->assertEquals(2500000, $business->price_monthly_vnd);
        $this->assertEquals(BillingInterval::MONTHLY, $business->billing_interval);
        $this->assertEquals(20000, $business->credits_per_cycle);
        $this->assertEquals(1000, $business->articles_limit_per_cycle);
    }

    public function test_database_seeder_runs_idempotently(): void
    {
        // First run
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('workspaces', ['slug' => 'default-workspace']);

        // Second run must not crash
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('plans', 4);
    }

    public function test_models_and_relationships(): void
    {
        $this->seed(PlanSeeder::class);

        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'John Owner',
            'email' => 'john@acme.com',
            'password' => bcrypt('secret123'),
        ]);

        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'legacy_tenant_id' => $tenant->id,
            'name' => 'Acme Primary Workspace',
            'slug' => 'acme-primary',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        $owner->default_workspace_id = $workspace->id;
        $owner->save();

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMemberRole::OWNER,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $creditAccount = CreditAccount::create([
            'workspace_id' => $workspace->id,
            'available_credits' => 500,
            'reserved_credits' => 50,
            'cycle_granted_credits' => 500,
            'cycle_used_credits' => 0,
            'version' => 1,
        ]);

        $website = Website::create([
            'workspace_id' => $workspace->id,
            'name' => 'Acme Blog',
            'domain' => 'acme.vn',
            'url' => 'https://acme.vn',
            'default_language' => 'vi',
            'status' => WebsiteStatus::ACTIVE,
        ]);

        $proPlan = Plan::where('code', PlanCode::PRO)->first();

        $subscription = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $proPlan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $paymentOrder = PaymentOrder::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'plan_id' => $proPlan->id,
            'reference_code' => 'SEOPRO_'.Str::upper(Str::random(6)),
            'amount_vnd' => $proPlan->price_monthly_vnd,
            'currency' => 'VND',
            'payment_method' => 'bank_transfer',
            'status' => PaymentOrderStatus::APPROVED,
            'idempotency_key' => 'IDEM_'.Str::random(16),
        ]);

        $paymentTx = PaymentTransaction::create([
            'payment_order_id' => $paymentOrder->id,
            'workspace_id' => $workspace->id,
            'transaction_code' => 'TX_'.Str::random(10),
            'amount_vnd' => $paymentOrder->amount_vnd,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::VERIFIED,
            'paid_at' => now(),
        ]);

        $creditGrant = CreditGrant::create([
            'credit_account_id' => $creditAccount->id,
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscription->id,
            'payment_order_id' => $paymentOrder->id,
            'source_type' => CreditGrantSource::SUBSCRIPTION_CYCLE,
            'credits_granted' => 7000,
            'credits_remaining' => 7000,
            'granted_at' => now(),
            'expires_at' => now()->addMonth(),
            'idempotency_key' => 'GRANT_'.Str::random(16),
        ]);

        $creditTx = CreditTransaction::create([
            'credit_account_id' => $creditAccount->id,
            'workspace_id' => $workspace->id,
            'credit_grant_id' => $creditGrant->id,
            'type' => CreditTransactionType::GRANT,
            'amount' => 7000,
            'balance_before' => 500,
            'balance_after' => 7500,
            'reference_type' => 'payment_order',
            'reference_id' => $paymentOrder->id,
            'actor_id' => $owner->id,
            'idempotency_key' => 'CTX_'.Str::random(16),
            'description' => 'Cấp credit chu kỳ gói Pro',
        ]);

        $task = Task::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'triggered_by' => $owner->id,
            'type' => 'full_article_pipeline',
            'status' => TaskStatus::PENDING,
        ]);

        $usageRecord = UsageRecord::create([
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'task_id' => $task->id,
            'credit_transaction_id' => $creditTx->id,
            'action_type' => 'article_generation',
            'credits_charged' => 20,
            'tokens_used' => 2500,
            'provider_cost_usd' => 0.005,
            'status' => UsageRecordStatus::SETTLED,
            'usage_date' => now()->toDateString(),
        ]);

        $auditLog = AuditLog::create([
            'workspace_id' => $workspace->id,
            'actor_id' => $owner->id,
            'actor_role' => 'owner',
            'action' => 'billing.order_approved',
            'auditable_type' => PaymentOrder::class,
            'auditable_id' => $paymentOrder->id,
            'notes' => 'Test audit log created successfully',
        ]);

        // Assert relationships
        $this->assertEquals($owner->id, $workspace->owner->id);
        $this->assertEquals($workspace->id, $owner->defaultWorkspace->id);
        $this->assertCount(1, $owner->ownedWorkspaces);
        $this->assertCount(1, $workspace->members);
        $this->assertCount(1, $workspace->websites);
        $this->assertEquals($creditAccount->id, $workspace->creditAccount->id);
        $this->assertCount(1, $workspace->subscriptions);
        $this->assertCount(1, $workspace->paymentOrders);
        $this->assertCount(1, $paymentOrder->transactions);
        $this->assertEquals($paymentTx->id, $paymentOrder->transactions->first()->id);
        $this->assertCount(1, $creditAccount->grants);
        $this->assertCount(1, $creditAccount->transactions);
        $this->assertEquals($workspace->id, $task->workspace->id);
        $this->assertEquals($workspace->id, $usageRecord->workspace->id);
        $this->assertEquals($workspace->id, $auditLog->workspace->id);
    }

    public function test_hidden_sensitive_attributes(): void
    {
        $tenant = Tenant::create(['name' => 'Demo Tenant', 'slug' => 'demo-tenant']);

        $site = WordPressSite::create([
            'tenant_id' => $tenant->id,
            'name' => 'Secret Site',
            'url' => 'https://example.com',
            'api_url' => 'https://example.com/wp-json',
            'username' => 'admin',
            'app_password' => 'secret_app_password_123',
        ]);

        $siteArray = $site->toArray();
        $this->assertArrayNotHasKey('app_password', $siteArray, 'WordPressSite app_password should be hidden from serialization.');

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo User',
            'email' => 'demo@tenant.com',
            'password' => bcrypt('secret'),
        ]);

        $keyword = Keyword::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'keyword' => 'seo tools',
        ]);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'draft',
            'review_token' => 'super_secret_review_token_456',
        ]);

        $articleArray = $article->toArray();
        $this->assertArrayNotHasKey('review_token', $articleArray, 'Article review_token should be hidden from serialization.');
    }

    public function test_migration_rollback_runs_cleanly(): void
    {
        // Verify rolling back all new SaaS migrations
        $step = DB::table('migrations')
            ->where('migration', '>=', '2026_11_02_000001')
            ->count();
        $exitCode = Artisan::call('migrate:rollback', ['--step' => $step > 0 ? $step : 25]);
        $this->assertEquals(0, $exitCode, 'Artisan migrate:rollback should succeed with exit code 0.');

        // Verify tables dropped in rollback
        $this->assertFalse(Schema::hasTable('audit_logs'), 'audit_logs table should be dropped.');
        $this->assertFalse(Schema::hasTable('usage_records'), 'usage_records table should be dropped.');
        $this->assertFalse(Schema::hasTable('tasks'), 'tasks table should be dropped.');
        $this->assertFalse(Schema::hasTable('credit_transactions'), 'credit_transactions table should be dropped.');
        $this->assertFalse(Schema::hasTable('credit_grants'), 'credit_grants table should be dropped.');
        $this->assertFalse(Schema::hasTable('payment_transactions'), 'payment_transactions table should be dropped.');
        $this->assertFalse(Schema::hasTable('payment_orders'), 'payment_orders table should be dropped.');
        $this->assertFalse(Schema::hasTable('credit_accounts'), 'credit_accounts table should be dropped.');
        $this->assertFalse(Schema::hasTable('subscriptions'), 'subscriptions table should be dropped.');
        $this->assertFalse(Schema::hasTable('plans'), 'plans table should be dropped.');
        $this->assertFalse(Schema::hasTable('websites'), 'websites table should be dropped.');
        $this->assertFalse(Schema::hasTable('workspace_members'), 'workspace_members table should be dropped.');
        $this->assertFalse(Schema::hasTable('workspaces'), 'workspaces table should be dropped.');

        // Re-migrate to restore state
        $reMigrateExitCode = Artisan::call('migrate');
        $this->assertEquals(0, $reMigrateExitCode, 'Artisan migrate should re-run cleanly.');
    }
}
