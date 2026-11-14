<?php

namespace App\Jobs\Bulk;

use App\Models\BulkBatch;
use App\Services\Billing\CreditLedgerService;
use App\Services\Bulk\BulkGenerationService;
use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBulkBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần thử tối đa trước khi đánh dấu job thất bại.
     */
    public int $tries = 3;

    /**
     * Thời gian timeout tính bằng giây (15 phút cho lô lớn).
     */
    public int $timeout = 900;

    public function __construct(
        public int $bulkBatchId,
        public ?int $actorId = null
    ) {
        $this->onQueue('bulk-generation');
    }

    /**
     * Thực thi xử lý lô bài viết hàng loạt.
     */
    public function handle(BulkGenerationService $service): void
    {
        $batch = BulkBatch::find($this->bulkBatchId);
        if (! $batch) {
            Log::warning('ProcessBulkBatchJob: BulkBatch not found', ['bulk_batch_id' => $this->bulkBatchId]);

            return;
        }

        if ($batch->isCompleted()) {
            Log::info('ProcessBulkBatchJob: BulkBatch already finished', [
                'bulk_batch_id' => $batch->id,
                'status' => $batch->status,
            ]);

            return;
        }

        BelongsToWorkspace::setExecutionContext($batch->workspace_id);

        try {
            $service->processBatch($batch);
        } finally {
            BelongsToWorkspace::setExecutionContext(null);
        }
    }

    /**
     * Xử lý khi Job gặp lỗi ngoại lệ nghiêm trọng không thể retry.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessBulkBatchJob failed fatally', [
            'bulk_batch_id' => $this->bulkBatchId,
            'error' => $exception->getMessage(),
        ]);

        $batch = BulkBatch::find($this->bulkBatchId);
        if ($batch && ! $batch->isCompleted()) {
            if ($batch->credits_reserved > 0) {
                try {
                    $creditLedger = app(CreditLedgerService::class);
                    $creditLedger->releaseCredits(
                        workspace: $batch->workspace_id,
                        amount: $batch->credits_reserved,
                        referenceType: 'bulk_batch_fatal_fail',
                        referenceId: $batch->id,
                        idempotencyKey: 'release:fatal:batch:'.$batch->id,
                        reason: 'ProcessBulkBatchJob fatal failure: '.$exception->getMessage()
                    );
                } catch (Throwable $e) {
                    Log::error('Failed to release credits on job failure', [
                        'bulk_batch_id' => $batch->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $batch->update([
                'status' => 'failed',
                'credits_reserved' => 0,
                'finished_at' => now(),
            ]);
        }
    }
}
