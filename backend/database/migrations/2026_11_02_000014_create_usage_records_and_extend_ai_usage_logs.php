<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usage_records')) {
            Schema::create('usage_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
                $table->unsignedBigInteger('task_id')->nullable();
                $table->foreignId('credit_transaction_id')->nullable()->constrained('credit_transactions')->nullOnDelete();
                $table->string('action_type', 80);
                $table->unsignedInteger('credits_charged')->default(0);
                $table->unsignedInteger('tokens_used')->default(0);
                $table->decimal('provider_cost_usd', 12, 8)->default(0);
                $table->string('status', 30)->default('settled');
                $table->date('usage_date');
                $table->timestamps();

                $table->index(['workspace_id', 'usage_date']);
                $table->index(['workspace_id', 'website_id', 'usage_date']);
                $table->index(['action_type', 'usage_date']);
            });
        }

        if (Schema::hasTable('ai_usage_logs')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_usage_logs', 'workspace_id')) {
                    $table->foreignId('workspace_id')
                        ->nullable()
                        ->after('tenant_id')
                        ->constrained('workspaces')
                        ->nullOnDelete();
                    $table->index(['workspace_id', 'created_at']);
                }

                if (! Schema::hasColumn('ai_usage_logs', 'website_id')) {
                    $table->foreignId('website_id')
                        ->nullable()
                        ->after('workspace_id')
                        ->constrained('websites')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('ai_usage_logs', 'task_id')) {
                    $table->unsignedBigInteger('task_id')
                        ->nullable()
                        ->after('keyword_id');
                }

                if (! Schema::hasColumn('ai_usage_logs', 'credits_charged')) {
                    $table->unsignedInteger('credits_charged')
                        ->default(0)
                        ->after('cost_usd');
                }

                if (! Schema::hasColumn('ai_usage_logs', 'request_hash')) {
                    $table->char('request_hash', 64)
                        ->nullable()
                        ->after('credits_charged');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');

        if (DB::getDriverName() !== 'sqlite' && Schema::hasTable('ai_usage_logs')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                if (Schema::hasColumn('ai_usage_logs', 'workspace_id')) {
                    try {
                        $table->dropForeign(['workspace_id']);
                    } catch (Throwable $e) {
                        // Foreign key might not exist
                    }
                }
                if (Schema::hasColumn('ai_usage_logs', 'website_id')) {
                    try {
                        $table->dropForeign(['website_id']);
                    } catch (Throwable $e) {
                        // Foreign key might not exist
                    }
                }

                $cols = [];
                foreach (['workspace_id', 'website_id', 'task_id', 'credits_charged', 'request_hash'] as $col) {
                    if (Schema::hasColumn('ai_usage_logs', $col)) {
                        $cols[] = $col;
                    }
                }

                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
