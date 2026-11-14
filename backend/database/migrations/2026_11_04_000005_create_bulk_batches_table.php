<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        if (! Schema::hasTable('bulk_batches')) {
            Schema::create('bulk_batches', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                $table->string('name', 255);
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('completed_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->unsignedInteger('credits_reserved')->default(0);
                $table->unsignedInteger('credits_settled')->default(0);
                $table->string('status', 50)->default('pending'); // pending, processing, completed, partially_failed, cancelled, failed
                $table->json('generation_settings')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'website_id'], 'idx_bulk_batches_tenant');
                $table->index('status', 'idx_bulk_batches_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            $conn = DB::connection();
            $conn->setSchemaGrammar(new class($conn) extends SQLiteGrammar
            {
                public function compileDropColumn(Blueprint $blueprint, Fluent $command)
                {
                    $safeColumns = array_filter((array) $command->columns, function ($col) {
                        return ! in_array($col, ['brand_knowledge_id', 'topic_cluster_id', 'brand_voice_summary'], true);
                    });

                    if (empty($safeColumns)) {
                        return [];
                    }

                    $command->columns = array_values($safeColumns);

                    return parent::compileDropColumn($blueprint, $command);
                }
            });
        }

        Schema::dropIfExists('bulk_batches');
    }
};
