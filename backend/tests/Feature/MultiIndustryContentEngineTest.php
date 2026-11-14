<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\BrandKnowledgeBase;
use App\Models\Keyword;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Article\ContentStrategyService;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Brand\IndustryProfileRegistry;
use App\Services\Quality\ContentQualityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiIndustryContentEngineTest extends TestCase
{
    use RefreshDatabase;

    public static function multiIndustryCasesProvider(): array
    {
        return [
            'Test Case 1 - Nha khoa (Healthcare)' => [
                'brand_name' => 'Nha Khoa Aura Quốc Tế',
                'primary_industry' => 'Nha khoa & Răng hàm mặt',
                'business_model' => 'B2C',
                'keyword' => 'trồng răng implant giá bao nhiêu',
                'expected_industry_key' => IndustryProfileRegistry::INDUSTRY_HEALTHCARE,
                'expected_ymyl' => 'high',
                'sample_content' => '<h2>1. Cấu trúc chi phí trồng răng implant giá bao nhiêu hiện nay</h2>'
                    .'<p>Khi tìm hiểu <strong>trồng răng implant giá bao nhiêu</strong>, người bệnh cần xem xét tổng thể cấu tạo gồm trụ Titanium, khớp nối Abutment và mão sứ phục hình trên Implant tại Nha Khoa Aura Quốc Tế.</p>'
                    .'<h2>2. Bảng tổng hợp các yếu tố ảnh hưởng đến chi phí cấy ghép</h2>'
                    .'<table><thead><tr><th>Hạng mục</th><th>Đặc điểm lâm sàng</th></tr></thead><tbody><tr><td>Tình trạng xương ổ răng</td><td>Tùy thuộc mật độ xương hàm qua chẩn đoán hình ảnh</td></tr></tbody></table>'
                    .'<h2>3. Quy trình thăm khám và cấy ghép tiêu chuẩn vô trùng</h2>'
                    .'<ol><li>Bước 1: Thăm khám lâm sàng và kiểm tra tích hợp xương.</li><li>Bước 2: Lập phác đồ điều trị cá nhân hóa theo từng tình trạng răng miệng.</li></ol>'
                    .'<h2>4. Lưu ý an toàn và câu hỏi thường gặp</h2>'
                    .'<p>Hiệu quả phục hình phụ thuộc vào cơ địa và chế độ chăm sóc răng miệng sau cấy ghép. Liên hệ để đặt lịch tư vấn cùng bác sĩ chuyên khoa.</p>'
                    .'<p><em>Lưu ý: Thông tin trong bài viết chỉ mang tính chất tham khảo kiến thức y khoa tổng quát, không thay thế cho việc chẩn đoán hoặc chỉ định điều trị trực tiếp của bác sĩ chuyên khoa.</em></p>',
            ],
            'Test Case 2 - Bất động sản (Real Estate)' => [
                'brand_name' => 'NovaLand Advisory',
                'primary_industry' => 'Bất động sản & Môi giới căn hộ',
                'business_model' => 'B2C',
                'keyword' => 'kinh nghiệm mua căn hộ chung cư trả góp',
                'expected_industry_key' => IndustryProfileRegistry::INDUSTRY_REAL_ESTATE,
                'expected_ymyl' => 'high',
                'sample_content' => '<h2>1. Tổng quan kinh nghiệm mua căn hộ chung cư trả góp an toàn</h2>'
                    .'<p>Nắm vững <strong>kinh nghiệm mua căn hộ chung cư trả góp</strong> giúp người mua nhà đánh giá đúng khả năng tài chính, pháp lý dự án và tiến độ bàn giao từ NovaLand Advisory.</p>'
                    .'<h2>2. Phân tích bài toán tài chính và biên độ lãi suất thả nổi</h2>'
                    .'<ul><li>Tỷ lệ vốn tự có tối thiểu nên đạt từ 30% đến 50% giá trị căn hộ.</li><li>Kiểm tra kỹ thời gian ưu đãi lãi suất và biên độ lãi suất sau ưu đãi.</li></ul>'
                    .'<h2>3. Hồ sơ pháp lý dự án và hợp đồng mua bán cần thẩm định</h2>'
                    .'<p>Người mua cần kiểm tra văn bản đủ điều kiện bán nhà ở hình thành trong tương lai, bảo lãnh ngân hàng và quy hoạch hạ tầng trước khi ký hợp đồng.</p>'
                    .'<h2>4. Câu hỏi thường gặp khi vay mua chung cư</h2>'
                    .'<p>Lưu ý: Thông tin quy hoạch và chính sách vay vốn mang tính chất tham khảo, nhà đầu tư cần kiểm tra hồ sơ pháp lý thực tế trước khi giao dịch. Liên hệ nhận bảng tính dòng tiền chi tiết.</p>',
            ],
            'Test Case 3 - Luật (Legal)' => [
                'brand_name' => 'Văn Phòng Luật Minh Chính',
                'primary_industry' => 'Luật & Tư vấn pháp lý doanh nghiệp',
                'business_model' => 'B2B',
                'keyword' => 'thủ tục thành lập công ty tnhh 1 thành viên',
                'expected_industry_key' => IndustryProfileRegistry::INDUSTRY_LEGAL,
                'expected_ymyl' => 'high',
                'sample_content' => '<h2>1. Điều kiện pháp lý và thủ tục thành lập công ty tnhh 1 thành viên</h2>'
                    .'<p>Thực hiện <strong>thủ tục thành lập công ty tnhh 1 thành viên</strong> đòi hỏi doanh nghiệp chuẩn bị đầy đủ hồ sơ pháp lý theo Luật Doanh nghiệp hiện hành dưới sự hướng dẫn của Văn Phòng Luật Minh Chính.</p>'
                    .'<h2>2. Danh mục hồ sơ đăng ký doanh nghiệp cần chuẩn bị</h2>'
                    .'<ul><li>Giấy đề nghị đăng ký doanh nghiệp theo mẫu quy định.</li><li>Điều lệ công ty và bản sao giấy tờ pháp lý của chủ sở hữu, người đại diện theo pháp luật.</li></ul>'
                    .'<h2>3. Quy trình nộp hồ sơ và thời hạn giải quyết tại Sở Kế hoạch và Đầu tư</h2>'
                    .'<ol><li>Bước 1: Nộp hồ sơ trực tuyến qua Cổng thông tin quốc gia về đăng ký doanh nghiệp.</li><li>Bước 2: Khắc dấu pháp nhân và thực hiện thủ tục thuế ban đầu.</li></ol>'
                    .'<h2>4. Lưu ý pháp lý sau khi được cấp Giấy chứng nhận đăng ký doanh nghiệp</h2>'
                    .'<p>Lưu ý: Nội dung bài viết chỉ mang tính chất tham khảo thông tin pháp luật chung, không phải là ý kiến tư vấn pháp lý chính thức. Liên hệ luật sư để được rà soát hồ sơ cụ thể.</p>',
            ],
            'Test Case 4 - Công nghệ / SaaS (Technology)' => [
                'brand_name' => 'CloudPOS Enterprise',
                'primary_industry' => 'Phần mềm SaaS & Công nghệ quản lý bán hàng',
                'business_model' => 'B2B',
                'keyword' => 'phần mềm quản lý bán hàng cho chuỗi cửa hàng',
                'expected_industry_key' => IndustryProfileRegistry::INDUSTRY_TECHNOLOGY,
                'expected_ymyl' => 'low',
                'sample_content' => '<h2>1. Bài toán vận hành và vai trò của phần mềm quản lý bán hàng cho chuỗi cửa hàng</h2>'
                    .'<p>Triển khai <strong>phần mềm quản lý bán hàng cho chuỗi cửa hàng</strong> giúp đồng bộ tồn kho thời gian thực, chuẩn hóa quy trình vận hành đa điểm bán cùng CloudPOS Enterprise.</p>'
                    .'<h2>2. Các tính năng cốt lõi và khả năng tích hợp API</h2>'
                    .'<table><thead><tr><th>Phân hệ</th><th>Giá trị vận hành</th></tr></thead><tbody><tr><td>Đồng bộ tồn kho đa chi nhánh</td><td>Kiểm soát luân chuyển hàng hóa và cảnh báo định mức tự động</td></tr></tbody></table>'
                    .'<h2>3. Tiêu chuẩn bảo mật dữ liệu và phân quyền nhân sự</h2>'
                    .'<p>Hệ thống hỗ trợ phân quyền chi tiết theo vai trò, sao lưu dữ liệu đám mây và tích hợp linh hoạt với cổng thanh toán, hóa đơn điện tử.</p>'
                    .'<h2>4. Hướng dẫn triển khai và đăng ký dùng thử</h2>'
                    .'<p>Doanh nghiệp có thể đăng ký dùng thử miễn phí hoặc đặt lịch Demo 1-1 cùng chuyên gia giải pháp để đánh giá mức độ phù hợp thực tế.</p>',
            ],
            'Test Case 5 - Du lịch (Travel)' => [
                'brand_name' => 'VietHorizon Travel',
                'primary_industry' => 'Du lịch, Lữ hành & Khách sạn nghỉ dưỡng',
                'business_model' => 'B2C',
                'keyword' => 'kinh nghiệm du lịch phú quốc tự túc 4 ngày 3 đêm',
                'expected_industry_key' => IndustryProfileRegistry::INDUSTRY_TRAVEL,
                'expected_ymyl' => 'low',
                'sample_content' => '<h2>1. Thời điểm lý tưởng và kinh nghiệm du lịch phú quốc tự túc 4 ngày 3 đêm</h2>'
                    .'<p>Tổng hợp <strong>kinh nghiệm du lịch phú quốc tự túc 4 ngày 3 đêm</strong> từ VietHorizon Travel giúp du khách chủ động lên lịch trình tham quan Bắc Đảo, Nam Đảo và dự trù chi phí hợp lý.</p>'
                    .'<h2>2. Gợi ý lịch trình di chuyển chi tiết từng ngày</h2>'
                    .'<ol><li>Ngày 1: Nhận phòng khu vực Dương Đông, tham quan Dinh Cậu và chợ đêm.</li><li>Ngày 2: Khám phá Bắc Đảo, Rạch Vẹm và ngắm hoàng hôn.</li><li>Ngày 3: Trải nghiệm tour cano Nam Đảo và cáp treo Hòn Thơm.</li></ol>'
                    .'<h2>3. Kinh nghiệm chọn khách sạn lưu trú và ẩm thực địa phương</h2>'
                    .'<p>Lưu ý: Chi phí vé máy bay và giá phòng lưu trú có thể thay đổi tùy theo mùa cao điểm hoặc dịp lễ tết.</p>'
                    .'<h2>4. Câu hỏi thường gặp khi đi Phú Quốc tự túc</h2>'
                    .'<p>Liên hệ tư vấn lịch trình và nhận báo giá combo vé máy bay + khách sạn ưu đãi.</p>',
            ],
            'Test Case 6 - Thương mại điện tử (Ecommerce)' => [
                'brand_name' => 'PureHome Việt Nam',
                'primary_industry' => 'Thương mại điện tử & Bán lẻ thiết bị gia dụng',
                'business_model' => 'B2C',
                'keyword' => 'máy lọc không khí nào tốt cho phòng ngủ',
                'expected_industry_key' => IndustryProfileRegistry::INDUSTRY_ECOMMERCE,
                'expected_ymyl' => 'low',
                'sample_content' => '<h2>1. Tiêu chí đánh giá máy lọc không khí nào tốt cho phòng ngủ</h2>'
                    .'<p>Để trả lời câu hỏi <strong>máy lọc không khí nào tốt cho phòng ngủ</strong>, người tiêu dùng tại PureHome Việt Nam cần đối chiếu diện tích phòng với lưu lượng gió CADR, màng lọc HEPA và độ ồn khi vận hành ban đêm.</p>'
                    .'<h2>2. Bảng so sánh thông số kỹ thuật theo diện tích phòng ngủ</h2>'
                    .'<table><thead><tr><th>Tiêu chí kỹ thuật</th><th>Khuyến nghị cho phòng ngủ 15-25m2</th></tr></thead><tbody><tr><td>Độ ồn chế độ ngủ (Sleep Mode)</td><td>Dưới 25 dB giúp giấc ngủ sâu, không gây tiếng ồn khó chịu</td></tr></tbody></table>'
                    .'<h2>3. Hướng dẫn sử dụng và vệ sinh màng lọc định kỳ</h2>'
                    .'<ul><li>Đặt máy cách tường tối thiểu 20-30cm để luồng khí đối lưu tối ưu.</li><li>Kiểm tra và thay thế màng lọc HEPA định kỳ theo khuyến nghị của nhà sản xuất.</li></ul>'
                    .'<h2>4. Chính sách bảo hành chính hãng và đổi trả</h2>'
                    .'<p>Tham khảo danh mục sản phẩm chính hãng và liên hệ tư vấn chọn dòng máy phù hợp với diện tích phòng của gia đình bạn.</p>',
            ],
        ];
    }

    /**
     * @dataProvider multiIndustryCasesProvider
     */
    public function test_multi_industry_content_engine_strategy_review_and_19_field_packaging(
        string $brandName,
        string $primaryIndustry,
        string $businessModel,
        string $keywordText,
        string $expectedIndustryKey,
        string $expectedYmyl,
        string $sampleContent
    ): void {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $workspace = Workspace::create([
            'name' => 'Workspace '.$brandName,
            'slug' => 'ws-'.uniqid(),
            'owner_id' => $user->id,
            'legacy_tenant_id' => $tenant->id,
        ]);
        $website = Website::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'name' => $brandName,
            'domain' => 'example.vn',
            'url' => 'https://example.vn',
        ]);

        /** @var BrandKnowledgeService $brandService */
        $brandService = app(BrandKnowledgeService::class);
        $kb = $brandService->createBrandKnowledge($workspace, $website->id, [
            'brand_name' => $brandName,
            'business_model' => $businessModel,
            'tone_of_voice' => 'professional',
            'unique_selling_points' => ['Minh bạch thông tin', 'Quy trình chuẩn hóa'],
            'business_profile' => [
                'primary_industry' => $primaryIndustry,
                'country_market' => 'Việt Nam',
                'service_area' => 'TP.HCM & Hà Nội',
                'business_model' => $businessModel,
            ],
            'eeat_credentials' => [
                'content_reviewer' => 'Hội đồng Chuyên môn '.$brandName,
            ],
        ]);

        $this->assertSame($expectedIndustryKey, $kb->industry_key);
        $this->assertSame($expectedYmyl, $kb->industry_profile['ymyl_level']);

        $keyword = Keyword::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'user_id' => $user->id,
            'keyword' => $keywordText,
            'language' => 'vi',
        ]);

        /** @var ContentStrategyService $strategyService */
        $strategyService = app(ContentStrategyService::class);
        $strategy = $strategyService->analyzeBeforeWriting($website, $keyword, [
            'brand_knowledge_id' => $kb->id,
            'primary_keyword' => $keywordText,
            'desired_length' => 'standard',
        ]);

        $this->assertSame($expectedIndustryKey, $strategy['step_1_business_context']['industry_key']);
        $this->assertNotEmpty($strategy['step_2_keyword_analysis']['primary_search_intent']);
        $this->assertNotEmpty($strategy['step_4_content_strategy']['content_type']);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'title' => ucfirst($keywordText).' - Phân tích chuyên sâu cùng '.$brandName,
            'slug' => \Illuminate\Support\Str::slug($keywordText),
            'focus_keyword' => $keywordText,
            'seo_title' => ucfirst($keywordText).' | '.$brandName,
            'seo_description' => "Tìm hiểu chi tiết về {$keywordText} cùng {$brandName}: tiêu chí đánh giá, quy trình thực hiện và những lưu ý quan trọng.",
            'content' => $sampleContent,
            'word_count' => 950,
            'outline' => [
                'h1' => ucfirst($keywordText),
                'content_type' => $strategy['step_4_content_strategy']['content_type'],
                'primary_search_intent' => $strategy['step_2_keyword_analysis']['primary_search_intent'],
                'secondary_search_intents' => $strategy['step_2_keyword_analysis']['secondary_search_intents'],
                'strategy_analysis' => $strategy,
            ],
            'faqs' => [
                [
                    'question' => "Cần lưu ý gì khi tìm hiểu {$keywordText}?",
                    'answer' => 'Cần đối chiếu nhu cầu thực tế và tham khảo tư vấn chuyên môn minh bạch.',
                ],
            ],
            'status' => ArticleStatus::REVIEW,
        ]);

        /** @var ContentQualityScoringService $scoringService */
        $scoringService = app(ContentQualityScoringService::class);
        $evaluation = $scoringService->evaluateAndPackage($article, true);

        $this->assertTrue($evaluation['review_pipeline']['overall_passed']);
        $this->assertGreaterThanOrEqual(85, $evaluation['scoring']['total_score']);
        $this->assertSame('PASS', $evaluation['scoring']['gate_status']);
        $this->assertFalse($evaluation['scoring']['critical_error_gate_triggered']);

        // Kiểm tra đủ 19 trường kết quả đầu ra theo PHẦN X (Section 12)
        $pkg = $evaluation['output_package_19_fields'];
        $expectedKeys = [
            '1_business_summary',
            '2_industry_profile',
            '3_content_type',
            '4_search_intent',
            '5_keyword_map',
            '6_content_strategy',
            '7_article_outline',
            '8_seo_title',
            '9_meta_description',
            '10_url_slug',
            '11_h1',
            '12_full_article_html',
            '13_faq_section',
            '14_cta_section',
            '15_internal_link_suggestions',
            '16_image_plan_and_prompts',
            '17_schema_json_ld',
            '18_content_quality_score',
            '19_warnings_and_verification_notes',
        ];

        foreach ($expectedKeys as $reqKey) {
            $this->assertArrayHasKey($reqKey, $pkg, "Missing required output field {$reqKey}");
        }
    }

    public function test_targeted_auto_fix_neutralizes_prohibited_ymyl_claims_and_adds_disclaimer(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $workspace = Workspace::create([
            'name' => 'Dental Workspace',
            'slug' => 'ws-dental-'.uniqid(),
            'owner_id' => $user->id,
            'legacy_tenant_id' => $tenant->id,
        ]);
        $website = Website::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'name' => 'Nha Khoa Chuẩn Y Khoa',
            'domain' => 'nhakhoa.vn',
            'url' => 'https://nhakhoa.vn',
        ]);

        app(BrandKnowledgeService::class)->createBrandKnowledge($workspace, $website->id, [
            'brand_name' => 'Nha Khoa Chuẩn Y Khoa',
            'business_profile' => ['primary_industry' => 'Nha khoa'],
            'taboo_words' => ['rẻ nhất quả đất'],
        ]);

        $keyword = Keyword::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'user_id' => $user->id,
            'keyword' => 'niềng răng trong suốt',
        ]);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'title' => 'Niềng răng trong suốt: Quy trình chỉnh nha hiện đại',
            'slug' => 'nieng-rang-trong-suot',
            'focus_keyword' => 'niềng răng trong suốt',
            'content' => '<p>Trong thời đại công nghệ số hiện nay, dịch vụ niềng răng trong suốt cam kết chữa khỏi 100% và rẻ nhất quả đất.</p>'
                .'<h2>1. Khái niệm niềng răng trong suốt</h2><p>Khay niềng trong suốt hỗ trợ dịch chuyển răng theo phác đồ.</p>'
                .'<h2>2. Quy trình chỉnh nha</h2><p>Thực hiện thăm khám lâm sàng và chụp phim.</p>'
                .'<h2>3. Chi phí tham khảo</h2><p>Tùy thuộc mức độ lệch lạc của khớp cắn.</p>',
            'word_count' => 850,
            'status' => ArticleStatus::DRAFT,
        ]);

        $evaluation = app(ContentQualityScoringService::class)->evaluateAndPackage($article, true);
        $article->refresh();

        $this->assertStringNotContainsString('cam kết chữa khỏi 100%', $article->content);
        $this->assertStringNotContainsString('rẻ nhất quả đất', $article->content);
        $this->assertStringContainsString('Lưu ý chuyên môn:', $article->content);
        $this->assertNotEmpty($evaluation['review_pipeline']['targeted_fixes_applied']);
    }
}
