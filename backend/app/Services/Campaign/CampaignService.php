<?php

namespace App\Services\Campaign;

use App\Enums\CampaignStatus;
use App\Enums\KeywordStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceStatus;
use App\Exceptions\QuotaExceededException;
use App\Jobs\Keywords\ProcessKeywordBatchJob;
use App\Models\Campaign;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Services\Billing\CreditLedgerService;
use App\Services\Cost\CostTrackingService;
use App\Services\Queue\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        protected CampaignRepositoryInterface $campaigns,
        protected ?CreditLedgerService $creditLedger = null,
        protected ?CostTrackingService $costTracking = null,
        protected ?TaskService $taskService = null
    ) {
        $this->creditLedger = $this->creditLedger ?? app(CreditLedgerService::class);
        $this->costTracking = $this->costTracking ?? app(CostTrackingService::class);
        $this->taskService = $this->taskService ?? app(TaskService::class);
    }

    public function create(array $data, int $tenantId, int $userId): Campaign
    {
        return $this->campaigns->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'slug' => $this->uniqueSlug($data['name'], $tenantId),
            'status' => $data['status'] ?? CampaignStatus::DRAFT,
        ]));
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (isset($data['name']) && $data['name'] !== $campaign->name && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $campaign->tenant_id, $campaign->id);
        }

        $this->campaigns->update($campaign->id, $data);

        return $campaign->fresh(['wordpressSite:id,name,url', 'creator:id,name']);
    }

    public function setStatus(Campaign $campaign, CampaignStatus $status): Campaign
    {
        $data = ['status' => $status];

        if ($status === CampaignStatus::PROCESSING && ! $campaign->started_at) {
            $data['started_at'] = now();
        }

        if ($status === CampaignStatus::COMPLETED) {
            $data['completed_at'] = now();
        }

        $this->campaigns->update($campaign->id, $data);

        return $campaign->fresh(['wordpressSite:id,name,url', 'creator:id,name']);
    }

    public function start(Campaign $campaign, ?User $actor = null): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = Campaign::whereKey($campaign->id)->lockForUpdate()->first() ?? $campaign;

            if ($lockedCampaign->workspace && $lockedCampaign->workspace->status === WorkspaceStatus::SUSPENDED) {
                throw new \RuntimeException('Cannot start campaign: Workspace is suspended.');
            }

            $pendingKeywords = $lockedCampaign->keywords()
                ->where('status', KeywordStatus::PENDING)
                ->get();

            $keywordIds = [];
            if ($pendingKeywords->isNotEmpty()) {
                $keywordIds = $pendingKeywords->pluck('id')->toArray();
                $creditPerArticle = (int) config('ai_costs.credits.standard_article', 20);
                $requiredCredits = count($keywordIds) * $creditPerArticle;

                if ($lockedCampaign->workspace_id) {
                    $this->creditLedger->checkCredits($lockedCampaign->workspace_id, $requiredCredits);
                } elseif ($lockedCampaign->tenant_id) {
                    $quota = $this->costTracking->checkQuota($lockedCampaign->tenant_id);
                    if (! $quota->canProceed) {
                        throw new QuotaExceededException($quota->message ?? 'Quota exceeded');
                    }
                }
            }

            $lockedCampaign = $this->setStatus($lockedCampaign, CampaignStatus::PROCESSING);

            if ($pendingKeywords->isNotEmpty()) {
                $batchTask = $this->taskService->createTask([
                    'workspace_id' => $lockedCampaign->workspace_id,
                    'campaign_id' => $lockedCampaign->id,
                    'triggered_by' => $actor?->id,
                    'type' => 'campaign_batch',
                    'current_step' => 'dispatching',
                    'credits_reserved' => 0,
                    'payload' => [
                        'campaign_id' => $lockedCampaign->id,
                        'keyword_count' => count($keywordIds),
                        'keyword_ids' => $keywordIds,
                    ],
                ]);
                $this->taskService->startTask($batchTask, 'dispatching');

                foreach (array_chunk($keywordIds, 10) as $chunk) {
                    ProcessKeywordBatchJob::dispatch($chunk)->onQueue('imports');
                }

                $lockedCampaign->keywords()
                    ->whereIn('id', $keywordIds)
                    ->update(['status' => KeywordStatus::PROCESSING]);
            }

            return $lockedCampaign->fresh(['wordpressSite:id,name,url', 'creator:id,name']);
        });
    }

    public function pause(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign) {
            $campaign = $this->setStatus($campaign, CampaignStatus::PAUSED);

            // Reset processing keywords back to pending so resume can pick them up
            $campaign->keywords()
                ->where('status', KeywordStatus::PROCESSING)
                ->update(['status' => KeywordStatus::PENDING]);

            $tasksToCancel = Task::where('campaign_id', $campaign->id)
                ->whereIn('status', [TaskStatus::PENDING, TaskStatus::RUNNING])
                ->get();

            foreach ($tasksToCancel as $task) {
                $this->taskService->cancelTask($task, null, 'Campaign paused by user.');
            }

            return $campaign->fresh(['wordpressSite:id,name,url', 'creator:id,name']);
        });
    }

    public function resume(Campaign $campaign, ?User $actor = null): Campaign
    {
        return $this->start($campaign, $actor);
    }

    protected function uniqueSlug(string $name, int $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $counter = 2;

        while (Campaign::where('tenant_id', $tenantId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
