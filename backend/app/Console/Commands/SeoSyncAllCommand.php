<?php

namespace App\Console\Commands;

use App\Services\SEO\Automation\SeoAutomationSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SeoSyncAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:sync-all
                            {--website= : Specific website ID to synchronize}
                            {--service=all : Service to synchronize (gsc, ga4, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch daily synchronization jobs for Google Search Console and GA4 on active websites with automation enabled';

    /**
     * Execute the console command.
     */
    public function handle(SeoAutomationSchedulerService $service): int
    {
        $websiteId = $this->option('website') ? (int) $this->option('website') : null;
        $syncService = strtolower((string) ($this->option('service') ?: 'all'));

        $this->info("[SEO Automation] Bắt đầu đồng bộ dữ liệu (Service: {$syncService})...");
        Log::info('[SeoSyncAllCommand] Starting daily SEO sync', [
            'website_id' => $websiteId,
            'service' => $syncService,
        ]);

        $gscResult = null;
        $ga4Result = null;

        if (in_array($syncService, ['gsc', 'all'], true)) {
            $this->line('-> Đang quét và kích hoạt đồng bộ Google Search Console...');
            $gscResult = $service->syncAllWebsitesGsc($websiteId);
            $this->info("   ✓ GSC: {$gscResult['dispatched']} dispatched, {$gscResult['skipped']} skipped (Tổng {$gscResult['total_websites']} websites)");
        }

        if (in_array($syncService, ['ga4', 'all'], true)) {
            $this->line('-> Đang quét và kích hoạt đồng bộ Google Analytics 4...');
            $ga4Result = $service->syncAllWebsitesGa4($websiteId);
            $this->info("   ✓ GA4: {$ga4Result['dispatched']} dispatched, {$ga4Result['skipped']} skipped (Tổng {$ga4Result['total_websites']} websites)");
        }

        $this->info('[SEO Automation] Hoàn tất kích hoạt tiến trình đồng bộ hàng ngày.');

        return self::SUCCESS;
    }
}
