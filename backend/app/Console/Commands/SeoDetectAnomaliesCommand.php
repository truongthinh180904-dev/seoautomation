<?php

namespace App\Console\Commands;

use App\Services\SEO\Automation\SeoAutomationSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SeoDetectAnomaliesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:detect-anomalies
                            {--website= : Specific website ID to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run SEO intelligence heuristic anomaly detection and trigger notifications for critical issues';

    /**
     * Execute the console command.
     */
    public function handle(SeoAutomationSchedulerService $service): int
    {
        $websiteId = $this->option('website') ? (int) $this->option('website') : null;

        $this->info('[SEO Automation] Bắt đầu quét các bất thường & cơ hội SEO (Anomaly Detection)...');
        Log::info('[SeoDetectAnomaliesCommand] Starting daily SEO anomaly detection', [
            'website_id' => $websiteId,
        ]);

        $result = $service->scanAllAnomalies($websiteId);

        $this->info("   ✓ SEO Anomaly Scan: Đã quét {$result['websites_scanned']} websites ({$result['skipped_websites']} skipped).");
        $this->info("   ✓ Phát hiện {$result['insights_detected']} insights mới.");
        if ($result['critical_alerts'] > 0) {
            $this->warn("   ⚠️ Đã kích hoạt {$result['critical_alerts']} thông báo cảnh báo nghiêm trọng!");
        } else {
            $this->info('   ✓ Không có cảnh báo nghiêm trọng vượt ngưỡng.');
        }

        $this->info('[SEO Automation] Hoàn tất quét bất thường SEO hàng ngày.');

        return self::SUCCESS;
    }
}
