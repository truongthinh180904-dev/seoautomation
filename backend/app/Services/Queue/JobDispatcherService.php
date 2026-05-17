<?php

namespace App\Services\Queue;

use App\Enums\KeywordStatus;
use App\Jobs\Keywords\ProcessKeywordBatchJob;
use App\Models\Keyword;
use Illuminate\Support\Facades\Log;

class JobDispatcherService
{
    /**
     * Dispatch batch SERP research for all keywords scheduled before now.
     * Called by cron every 5 minutes.
     */
    public function dispatchScheduledKeywords(): int
    {
        $keywords = Keyword::query()
            ->where('status', KeywordStatus::NEW)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            })
            ->limit(50) // safety cap per cron tick
            ->pluck('id')
            ->toArray();

        if (empty($keywords)) {
            return 0;
        }

        // Batch dispatch — ProcessKeywordBatchJob fans out one SerpResearchJob per keyword
        foreach (array_chunk($keywords, 10) as $chunk) {
            ProcessKeywordBatchJob::dispatch($chunk);
        }

        // Mark as processing so they're not re-picked
        Keyword::whereIn('id', $keywords)->update(['status' => KeywordStatus::PROCESSING]);

        Log::info("JobDispatcherService: dispatched " . count($keywords) . " keywords into queue.");

        return count($keywords);
    }

    /**
     * Reset stalled jobs: keywords stuck in PROCESSING > $thresholdMinutes.
     */
    public function resetStalledJobs(int $thresholdMinutes = 10): int
    {
        $stalled = Keyword::query()
            ->where('status', KeywordStatus::PROCESSING)
            ->where('updated_at', '<', now()->subMinutes($thresholdMinutes))
            ->get();

        if ($stalled->isEmpty()) {
            return 0;
        }

        foreach ($stalled as $keyword) {
            $keyword->update(['status' => KeywordStatus::NEW]);
            Log::warning("JobDispatcherService: reset stalled keyword #{$keyword->id} ({$keyword->keyword})");
        }

        return $stalled->count();
    }
}
