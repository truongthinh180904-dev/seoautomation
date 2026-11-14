<?php

namespace App\Jobs\Keywords;

use App\Enums\CampaignStatus;
use App\Enums\KeywordStatus;
use App\Enums\WorkspaceStatus;
use App\Jobs\SERP\SerpResearchJob;
use App\Models\Keyword;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Throwable;

class ProcessKeywordBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public function __construct(
        public array|Collection $keywordIds
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $keywords = Keyword::with(['campaign', 'workspace'])
            ->whereIn('id', $this->keywordIds)
            ->get();

        $validKeywords = [];

        foreach ($keywords as $keyword) {
            // Skip keywords if workspace is SUSPENDED
            if ($keyword->workspace && $keyword->workspace->status === WorkspaceStatus::SUSPENDED) {
                continue;
            }

            // Skip keywords if campaign is PAUSED or CANCELLED
            if ($keyword->campaign && in_array($keyword->campaign->status, [CampaignStatus::PAUSED, CampaignStatus::CANCELLED])) {
                continue;
            }

            $validKeywords[] = $keyword;
        }

        if (empty($validKeywords)) {
            return;
        }

        $validIds = array_map(fn ($k) => $k->id, $validKeywords);

        // Grouped update outside loop
        Keyword::whereIn('id', $validIds)->update(['status' => KeywordStatus::PROCESSING]);

        foreach ($validKeywords as $keyword) {
            SerpResearchJob::dispatch($keyword)->onQueue('ai-research');
        }
    }

    public function failed(Throwable $exception): void
    {
        Keyword::whereIn('id', $this->keywordIds)->update([
            'status' => KeywordStatus::FAILED,
        ]);
    }
}
