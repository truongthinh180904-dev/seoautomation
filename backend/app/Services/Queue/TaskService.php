<?php

namespace App\Services\Queue;

use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\Task;
use App\Models\User;
use App\Services\Article\ArticlePipelineService;
use App\Services\Billing\CreditLedgerService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskService
{
    public function __construct(
        protected CreditLedgerService $creditLedger,
        protected ArticlePipelineService $articlePipeline
    ) {}

    public function createTask(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $task = Task::create([
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'workspace_id' => $data['workspace_id'],
                'website_id' => $data['website_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'keyword_id' => $data['keyword_id'] ?? null,
                'article_id' => $data['article_id'] ?? null,
                'triggered_by' => $data['triggered_by'] ?? null,
                'type' => $data['type'] ?? 'full_article_pipeline',
                'current_step' => $data['current_step'] ?? 'queued',
                'status' => TaskStatus::PENDING,
                'progress' => 0,
                'credits_reserved' => $data['credits_reserved'] ?? 0,
                'credits_settled' => 0,
                'attempts' => 0,
                'max_attempts' => $data['max_attempts'] ?? 3,
                'unique_lock_key' => $data['unique_lock_key'] ?? null,
                'payload' => $data['payload'] ?? null,
            ]);

            // Reserve credits if specified and workspace has credit account
            if ($task->credits_reserved > 0 && $task->workspace_id) {
                $this->creditLedger->reserveCredits(
                    workspace: $task->workspace_id,
                    amount: $task->credits_reserved,
                    referenceType: 'task',
                    referenceId: $task->id,
                    idempotencyKey: sprintf('reserve:task:%d:%s', $task->id, $task->uuid)
                );
            }

            return $task;
        });
    }

    public function startTask(Task $task, ?string $step = null): Task
    {
        $task->update([
            'status' => TaskStatus::RUNNING,
            'current_step' => $step ?: $task->current_step ?: 'running',
            'started_at' => $task->started_at ?: now(),
            'attempts' => $task->attempts + 1,
        ]);

        return $task->fresh();
    }

    public function updateProgress(Task $task, int $progress, ?string $step = null): Task
    {
        $updates = ['progress' => min(100, max(0, $progress))];
        if ($step !== null) {
            $updates['current_step'] = $step;
        }

        $task->update($updates);

        return $task->fresh();
    }

    public function completeTask(Task $task, ?array $payload = null): Task
    {
        return DB::transaction(function () use ($task, $payload) {
            /** @var Task $lockedTask */
            $lockedTask = Task::whereKey($task->id)->lockForUpdate()->first() ?? $task;

            // Settle reserved credits if any
            if ($lockedTask->credits_reserved > 0 && $lockedTask->credits_settled === 0 && $lockedTask->workspace_id) {
                $this->creditLedger->deductCredits(
                    workspace: $lockedTask->workspace_id,
                    amount: $lockedTask->credits_reserved,
                    referenceType: 'task',
                    referenceId: $lockedTask->id,
                    idempotencyKey: sprintf('deduct:task:%d:%s:settle', $lockedTask->id, $lockedTask->uuid),
                    fromReserved: true
                );

                $lockedTask->credits_settled = $lockedTask->credits_reserved;
            }

            $lockedTask->update([
                'status' => TaskStatus::SUCCESS,
                'progress' => 100,
                'current_step' => 'completed',
                'finished_at' => now(),
                'payload' => array_merge($lockedTask->payload ?? [], $payload ?? []),
            ]);

            return $lockedTask->fresh();
        });
    }

    public function failTask(Task $task, string $errorMessage, ?string $errorCode = null): Task
    {
        return DB::transaction(function () use ($task, $errorMessage, $errorCode) {
            /** @var Task $lockedTask */
            $lockedTask = Task::whereKey($task->id)->lockForUpdate()->first() ?? $task;

            // Release reserved credits if any
            if ($lockedTask->credits_reserved > 0 && $lockedTask->credits_settled === 0 && $lockedTask->workspace_id) {
                try {
                    $this->creditLedger->releaseCredits(
                        workspace: $lockedTask->workspace_id,
                        amount: $lockedTask->credits_reserved,
                        referenceType: 'task',
                        referenceId: $lockedTask->id,
                        reason: 'Task execution failed: '.$errorMessage
                    );
                } catch (\Throwable) {
                    // Non-fatal if already released
                }
            }

            $lockedTask->update([
                'status' => TaskStatus::FAILED,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'finished_at' => now(),
            ]);

            return $lockedTask->fresh();
        });
    }

    public function cancelTask(Task $task, ?User $actor = null, ?string $reason = null): Task
    {
        return DB::transaction(function () use ($task, $actor, $reason) {
            /** @var Task $lockedTask */
            $lockedTask = Task::whereKey($task->id)->lockForUpdate()->first() ?? $task;

            if ($lockedTask->status === TaskStatus::CANCELLED) {
                return $lockedTask;
            }

            // Release reserved credits if any
            if ($lockedTask->credits_reserved > 0 && $lockedTask->credits_settled === 0 && $lockedTask->workspace_id) {
                try {
                    $this->creditLedger->releaseCredits(
                        workspace: $lockedTask->workspace_id,
                        amount: $lockedTask->credits_reserved,
                        referenceType: 'task',
                        referenceId: $lockedTask->id,
                        reason: $reason ?: 'Task cancelled by user',
                        actor: $actor
                    );
                } catch (\Throwable) {
                    // Non-fatal
                }
            }

            $lockedTask->update([
                'status' => TaskStatus::CANCELLED,
                'cancelled_at' => now(),
                'error_message' => $reason ?: 'Task was cancelled.',
            ]);

            // Update associated article pipeline if present
            if ($lockedTask->article_id && ($article = Article::find($lockedTask->article_id))) {
                $this->articlePipeline->cancel($article, $reason);
            }

            // Update associated keyword if present
            if ($lockedTask->keyword_id && ($keyword = Keyword::find($lockedTask->keyword_id))) {
                if ($keyword->status === KeywordStatus::PROCESSING) {
                    $keyword->update(['status' => KeywordStatus::FAILED]);
                }
            }

            return $lockedTask->fresh();
        });
    }

    public function isCancelled(int|Task $task): bool
    {
        $taskId = $task instanceof Task ? $task->id : $task;

        return Task::where('id', $taskId)
            ->where('status', TaskStatus::CANCELLED)
            ->exists();
    }

    public function findForWorkspace(int $id, int $workspaceId): ?Task
    {
        return Task::where('id', $id)
            ->where('workspace_id', $workspaceId)
            ->with(['campaign:id,name', 'keyword:id,keyword', 'article:id,title,status'])
            ->first();
    }

    public function paginateForWorkspace(int $workspaceId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Task::where('workspace_id', $workspaceId)
            ->with(['campaign:id,name', 'keyword:id,keyword', 'article:id,title,status'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['campaign_id']), fn ($q) => $q->where('campaign_id', $filters['campaign_id']))
            ->latest('id')
            ->paginate($perPage);
    }
}
