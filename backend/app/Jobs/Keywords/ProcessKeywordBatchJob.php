<?php

namespace App\Jobs\Keywords;

use App\Enums\KeywordStatus;
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

        $keywords = Keyword::whereIn('id', $this->keywordIds)->get();

        foreach ($keywords as $keyword) {
            $keyword->update(['status' => KeywordStatus::PROCESSING]);
            SerpResearchJob::dispatch($keyword)->onQueue('ai-research');
        }
    }

    public function failed(Throwable $exception): void
    {
        Keyword::whereIn('id', $this->keywordIds)->update([
            'status' => KeywordStatus::FAILED
        ]);
    }
}
