<?php

namespace App\Console\Commands;

use App\Services\Queue\JobDispatcherService;
use Illuminate\Console\Command;

class CheckStalledJobs extends Command
{
    protected $signature   = 'seo:check-stalled {--threshold=10 : Minutes before a processing job is considered stalled}';
    protected $description = 'Detect and reset keywords stuck in PROCESSING state beyond the threshold';

    public function handle(JobDispatcherService $dispatcher): int
    {
        $threshold = (int) $this->option('threshold');

        $this->info("[SEO] Checking for stalled jobs (threshold: {$threshold} min)...");

        $reset = $dispatcher->resetStalledJobs($threshold);

        if ($reset === 0) {
            $this->line('  No stalled jobs found.');
        } else {
            $this->warn("  ⚠ Reset {$reset} stalled keyword(s) back to NEW status.");
        }

        return self::SUCCESS;
    }
}
