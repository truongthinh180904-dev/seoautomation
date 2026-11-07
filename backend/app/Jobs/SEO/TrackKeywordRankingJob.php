<?php

namespace App\Jobs\SEO;

use App\Jobs\BaseSyncJob;
use App\Models\RankTrackedKeyword;
use App\Services\SEO\RankTracking\RankTrackingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrackKeywordRankingJob extends BaseSyncJob
{
    public int $rankTrackedKeywordId;

    public ?string $checkDate;

    public function __construct(int $workspaceId, int $rankTrackedKeywordId, ?string $checkDate = null)
    {
        parent::__construct($workspaceId);
        $this->rankTrackedKeywordId = $rankTrackedKeywordId;
        $this->checkDate = $checkDate;
    }

    /**
     * Unique idempotency key identifying this tracking run.
     */
    public function getIdempotencyKey(): string
    {
        $date = $this->checkDate ?: Carbon::today()->toDateString();

        return "track_rank:{$this->workspaceId}:{$this->rankTrackedKeywordId}:{$date}";
    }

    /**
     * Execute tracking within tenant context and distributed lock.
     */
    protected function executeSync(): void
    {
        $keyword = RankTrackedKeyword::find($this->rankTrackedKeywordId);

        if (! $keyword) {
            Log::info("[TrackKeywordRankingJob] Keyword {$this->rankTrackedKeywordId} not found, skipping.");

            return;
        }

        if (! $keyword->is_active) {
            Log::info("[TrackKeywordRankingJob] Keyword {$this->rankTrackedKeywordId} is paused/inactive, skipping.");

            return;
        }

        $service = app(RankTrackingService::class);
        $service->trackKeyword($keyword, $this->checkDate);
    }
}
