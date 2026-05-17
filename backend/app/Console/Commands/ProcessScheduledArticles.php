<?php

namespace App\Console\Commands;

use App\Services\Queue\JobDispatcherService;
use Illuminate\Console\Command;

class ProcessScheduledArticles extends Command
{
    protected $signature   = 'seo:process-scheduled {--dry-run : Show what would be dispatched without actually dispatching}';
    protected $description = 'Process keywords scheduled for SERP research and AI content generation';

    public function handle(JobDispatcherService $dispatcher): int
    {
        $this->info('[SEO] Checking for scheduled keywords to process...');

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] Would dispatch keywords scheduled up to now. No jobs queued.');
            return self::SUCCESS;
        }

        $dispatched = $dispatcher->dispatchScheduledKeywords();

        if ($dispatched === 0) {
            $this->line('  No keywords scheduled for processing at this time.');
        } else {
            $this->info("  ✓ Dispatched {$dispatched} keyword(s) into the ai-research queue.");
        }

        return self::SUCCESS;
    }
}
