<?php

namespace App\Jobs\SERP;

use App\Agents\SerpResearchAgent;
use App\Enums\KeywordStatus;
use App\Models\Keyword;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SerpResearchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300];

    public function __construct(
        public Keyword $keyword
    ) {
        $this->onQueue('ai-research');
    }

    public function handle(SerpResearchAgent $agent): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $context = [
            'keyword' => $this->keyword->keyword,
            'keyword_id' => $this->keyword->id,
            'tenant_id' => $this->keyword->tenant_id,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $this->fail(new \Exception($result->error ?? 'SerpResearchAgent failed without specific error message.'));
            return;
        }

        // Successfully researched. The pipeline might dispatch next jobs here or via event.
        // For now, we update to completed if it's just this job.
        // Assuming keyword becomes completed after serp if no further jobs in this epic, 
        // but typically it moves to outline. We'll leave it in processing for now 
        // or let subsequent epic handle dispatching.
    }

    public function failed(Throwable $exception): void
    {
        $this->keyword->update(['status' => KeywordStatus::FAILED]);
    }
}
