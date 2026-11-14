<?php

namespace App\Jobs;

use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $workspaceId;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Exponential backoff delays in seconds between attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(int $workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    /**
     * Unique idempotency key identifying this sync run.
     */
    abstract public function getIdempotencyKey(): string;

    /**
     * Concrete sync logic to execute within tenant context and distributed lock.
     */
    abstract protected function executeSync(): void;

    /**
     * Distributed cache lock key.
     */
    public function getLockKey(): string
    {
        return 'lock:sync:'.$this->getIdempotencyKey();
    }

    /**
     * Duration in seconds to hold the distributed lock.
     */
    public function getLockSeconds(): int
    {
        return 600;
    }

    /**
     * Execute the job with tenant context and distributed lock guard.
     *
     * @throws \Throwable
     */
    public function handle(): void
    {
        BelongsToWorkspace::setExecutionContext($this->workspaceId);

        $lock = Cache::lock($this->getLockKey(), $this->getLockSeconds());

        if (! $lock->get()) {
            Log::warning("[BaseSyncJob] Job already running or locked for key: {$this->getLockKey()}", [
                'workspace_id' => $this->workspaceId,
                'idempotency_key' => $this->getIdempotencyKey(),
            ]);

            return;
        }

        try {
            Log::info("[BaseSyncJob] Started execution for key: {$this->getIdempotencyKey()}");
            $this->executeSync();
            Log::info("[BaseSyncJob] Successfully completed key: {$this->getIdempotencyKey()}");
        } catch (\Throwable $e) {
            Log::error("[BaseSyncJob] Execution failed: {$e->getMessage()}", [
                'workspace_id' => $this->workspaceId,
                'idempotency_key' => $this->getIdempotencyKey(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Ignore lock release failures if already expired
            }

            BelongsToWorkspace::setExecutionContext(null);
        }
    }
}
