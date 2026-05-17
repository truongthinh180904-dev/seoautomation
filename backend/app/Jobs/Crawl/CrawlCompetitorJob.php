<?php

namespace App\Jobs\Crawl;

use App\Agents\CompetitorAnalysisAgent;
use App\Models\CompetitorAnalysis;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlCompetitorJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60, 180];

    public function __construct(
        public int $serpResultId,
        public int $keywordId,
        public int $tenantId,
        public string $url,
        public string $keyword
    ) {
        $this->onQueue('ai-research');
    }

    public function handle(CompetitorAnalysisAgent $agent): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $context = [
            'url' => $this->url,
            'keyword' => $this->keyword,
            'keyword_id' => $this->keywordId,
            'serp_result_id' => $this->serpResultId,
            'tenant_id' => $this->tenantId,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $this->markAsFailed("Agent error: " . $result->error);
            if (str_contains(strtolower($result->error ?? ''), 'crawl failed')) {
                throw new \Exception($result->error);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markAsFailed("Job failed: " . $exception->getMessage());
    }

    protected function markAsFailed(string $error): void
    {
        CompetitorAnalysis::updateOrCreate(
            ['serp_result_id' => $this->serpResultId, 'keyword_id' => $this->keywordId],
            [
                'tenant_id' => $this->tenantId,
                'url' => $this->url,
                'analysis_completed' => false,
                'faqs' => ['error' => $error], 
            ]
        );
        Log::error("CrawlCompetitorJob failed for URL {$this->url}: {$error}");
    }
}
