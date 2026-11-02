<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => PlanCode::FREE->value,
                'name' => 'Free',
                'description' => 'Trải nghiệm viết bài tự động miễn phí 5 bài mỗi ngày.',
                'price_monthly_vnd' => 0,
                'billing_interval' => BillingInterval::DAILY->value,
                'credits_per_cycle' => 100,
                'articles_limit_per_cycle' => 5,
                'websites_limit' => 1,
                'max_concurrent_tasks' => 1,
                'max_retries_per_task' => 1,
                'max_images_per_article' => 1,
                'carryover' => false,
                'features' => [
                    '5 bài/ngày (cấp 100 credit/24h)',
                    '1 website WordPress',
                    'SERP research cơ bản',
                    'Hỗ trợ cộng đồng',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => PlanCode::BASIC->value,
                'name' => 'Basic',
                'description' => 'Dành cho cá nhân hoặc website vệ tinh bắt đầu xây dựng nội dung SEO.',
                'price_monthly_vnd' => 450000,
                'billing_interval' => BillingInterval::MONTHLY->value,
                'credits_per_cycle' => 2000,
                'articles_limit_per_cycle' => 100,
                'websites_limit' => 1,
                'max_concurrent_tasks' => 2,
                'max_retries_per_task' => 2,
                'max_images_per_article' => 2,
                'carryover' => false,
                'features' => [
                    '100 bài/tháng (2.000 credit/tháng)',
                    '1 website WordPress',
                    'Auto Internal Links',
                    'Chấm điểm SEO Yoast/RankMath',
                    'Hỗ trợ qua email',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => PlanCode::PRO->value,
                'name' => 'Pro',
                'description' => 'Dành cho doanh nghiệp vừa và nhỏ, làm SEO bài bản đa lĩnh vực.',
                'price_monthly_vnd' => 1250000,
                'billing_interval' => BillingInterval::MONTHLY->value,
                'credits_per_cycle' => 7000,
                'articles_limit_per_cycle' => 350,
                'websites_limit' => 3,
                'max_concurrent_tasks' => 5,
                'max_retries_per_task' => 3,
                'max_images_per_article' => 3,
                'carryover' => false,
                'features' => [
                    '350 bài/tháng (7.000 credit/tháng)',
                    '3 website WordPress',
                    'Crawl & Phân tích đối thủ chuyên sâu',
                    'Sinh ảnh AI Imagen 4',
                    'Đăng bài tự động theo lịch',
                    'Hỗ trợ ưu tiên',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => PlanCode::BUSINESS->value,
                'name' => 'Business',
                'description' => 'Dành cho Agency và hệ thống website quy mô lớn với sản lượng nội dung cao.',
                'price_monthly_vnd' => 2500000,
                'billing_interval' => BillingInterval::MONTHLY->value,
                'credits_per_cycle' => 20000,
                'articles_limit_per_cycle' => 1000,
                'websites_limit' => 10,
                'max_concurrent_tasks' => 10,
                'max_retries_per_task' => 3,
                'max_images_per_article' => 5,
                'carryover' => false,
                'features' => [
                    '1.000 bài/tháng (20.000 credit/tháng)',
                    '10 website WordPress',
                    'Queue ưu tiên tốc độ cao',
                    'Prompt chuyên biệt theo ngành',
                    'API Webhook tích hợp riêng',
                    'Quản lý tài khoản riêng 24/7',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}
