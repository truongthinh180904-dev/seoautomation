<?php

namespace App\Console\Commands;

use App\Services\SEO\Automation\SeoAutomationSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SeoTrackRankingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:track-rankings
                            {--website= : Specific website ID to track}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scheduled rank tracking jobs for active tracked keywords';

    /**
     * Execute the console command.
     */
    public function handle(SeoAutomationSchedulerService $service): int
    {
        $websiteId = $this->option('website') ? (int) $this->option('website') : null;

        $this->info('[SEO Automation] Bắt đầu kích hoạt kiểm tra thứ hạng từ khóa (Rank Tracking)...');
        Log::info('[SeoTrackRankingsCommand] Starting daily rank tracking', [
            'website_id' => $websiteId,
        ]);

        $result = $service->trackAllRankings($websiteId);

        $this->info("   ✓ Rank Tracking: {$result['dispatched_keywords']} keyword jobs dispatched (Tổng {$result['total_websites']} websites, {$result['skipped_websites']} skipped)");
        $this->info('[SEO Automation] Hoàn tất kích hoạt tiến trình Rank Tracking hàng ngày.');

        return self::SUCCESS;
    }
}
