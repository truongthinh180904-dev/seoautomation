# TIẾN ĐỘ NÂNG CẤP AURASEO AI CONTENT GENERATION ENGINE (MASTER TASK)

**Cập nhật lần cuối:** 2026-11-11  
**Model trung tâm:** `gemini-3.1-pro-preview` (Gemini Pro 3.1 Thinking API)

---

## Tổng quan 6 Giai đoạn (6 Phases)

| Giai đoạn | Hạng mục nâng cấp | Trạng thái | Ghi chú kỹ thuật |
| :--- | :--- | :---: | :--- |
| **Phase 0** | **Audit kiến trúc hiện tại & Lập kế hoạch** | ✅ Hoàn tất | Xem báo cáo chi tiết tại `PRE_CONTENT_ENGINE_AUDIT.md` |
| **Phase 1** | **Business Profile (3.1–3.4) & Industry Profile Registry (3.5)** | ✅ Hoàn tất | Hỗ trợ 12+ nhóm ngành nghề, E-E-A-T Tier A Verified Credentials, tự động phân loại ngành & YMYL |
| **Phase 2** | **Content Generation Form (4.1–4.2) & 5-Step AI Strategy Engine (5)** | ✅ Hoàn tất | Hỗ trợ 14 Content Types, Primary/Secondary Search Intent, 5 bước phân tích trước khi viết |
| **Phase 3** | **Universal SEO Content Writer (6) & On-Page SEO + Selective Schema (9)** | ✅ Hoàn tất | 10 nguyên tắc viết chuyên gia, chống Hallucination, xóa template nha khoa cứng ở Chatbot |
| **Phase 4** | **Trust/Fact Verification (7: Tier A/B/C, E-E-A-T, YMYL) & 4-Role Review (10)** | ✅ Hoàn tất | SEO Reviewer, Content Editor, Fact Checker, Technical Reviewer + Targeted Auto-Fix |
| **Phase 5** | **Industry-Aware Image Planning, Prompt & Image SEO Integration (8)** | ✅ Hoàn tất | Tích hợp `ImagePlannerService` vào `GeminiImageGenerationService` (giữ nguyên luồng WebP) |
| **Phase 6** | **100-Point Quality Scoring, Critical Error Gate (11), 19-Field Output (12) & 6-Industry Tests (14)** | ✅ Hoàn tất | Chấm điểm 7 trụ cột = 100 điểm, cổng `PASS`/`REVIEW`/`BLOCK`, kiểm thử 6 ngành nghề |

---

## Chi tiết các Module đã tạo mới & nâng cấp

### 1. Hồ sơ Doanh nghiệp & Hồ sơ Ngành nghề (Phần I)
- [`backend/app/Services/Brand/IndustryProfileRegistry.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Brand/IndustryProfileRegistry.php): Quản lý 12+ Industry Profiles (`healthcare`, `legal`, `real_estate`, `technology`, `ecommerce`, `travel`, `finance`, `education`, `manufacturing`, `beauty`, `food_beverage`, `other`), cho phép đăng ký ngành mới động (`registerCustomProfile`), tự động nhận diện ngành (`detectIndustry`) và sinh quy tắc chuyên ngành (`buildIndustryPromptInstructions`).
- [`backend/app/Models/BrandKnowledgeBase.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Models/BrandKnowledgeBase.php) & [`backend/app/Services/Brand/BrandKnowledgeService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Brand/BrandKnowledgeService.php): Mở rộng lưu trữ toàn diện Thông tin cơ bản (3.1), Hoạt động kinh doanh (3.2), Chuyên môn & Độ tin cậy E-E-A-T (3.3), Định hướng thương hiệu (3.4) và Hồ sơ ngành nghề (3.5) với cơ chế tương thích ngược an toàn tuyệt đối.
- [`frontend/hooks/useBrandKnowledge.ts`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/hooks/useBrandKnowledge.ts) & [`frontend/app/(dashboard)/brand-knowledge/page.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/app/(dashboard)/brand-knowledge/page.tsx): Giao diện khai báo Hồ sơ Doanh nghiệp 4 tab trực quan kèm nút tự động phân loại Industry Profile.

### 2. Form Tạo Bài Viết & AI Content Strategy Engine 5 Bước (Phần II & III)
- [`backend/app/Providers/AI/GeminiProvider.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Providers/AI/GeminiProvider.php): Nâng cấp hỗ trợ `gemini-3.1-pro-preview` (Gemini Pro 3.1 Thinking API), bóc tách chính xác phần nội dung phản hồi khỏi khối `thought` nội bộ.
- [`backend/app/Services/Article/ContentStrategyService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Article/ContentStrategyService.php): Thực thi quy trình 5 bước trước khi viết (`Step 1: Business Context`, `Step 2: Keyword & Multi-Intent Analysis`, `Step 3: Audience & Trust Barrier Analysis`, `Step 4: Content Strategy & 14 Content Types`, `Step 5: Logical Outline Blueprint`).
- [`backend/app/Agents/OutlineAgent.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Agents/OutlineAgent.php) & [`backend/app/Jobs/AI/GenerateOutlineJob.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Jobs/AI/GenerateOutlineJob.php): Kết nối trực tiếp `ContentStrategyService` và `BrandKnowledgeService`.
- [`frontend/components/features/articles/ContentGenerationModal.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/components/features/articles/ContentGenerationModal.tsx) & [`frontend/app/(dashboard)/articles/page.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/app/(dashboard)/articles/page.tsx): Biểu mẫu tạo bài viết đa ngành đầy đủ trường bắt buộc & tùy chọn.

### 3. Universal SEO Content Writer & Selective Schema.org (Phần IV & VII)
- [`backend/app/Agents/WritingAgent.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Agents/WritingAgent.php) & [`backend/app/Jobs/AI/GenerateArticleJob.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Jobs/AI/GenerateArticleJob.php): Áp dụng 10 nguyên tắc viết bài chuyên gia, độ dài linh hoạt (Short / Standard / Comprehensive / Advanced), chống nhồi nhét từ khóa và chống bịa đặt thông tin.
- [`backend/app/Services/SEO/SchemaBuilderService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/SEO/SchemaBuilderService.php): Sinh JSON-LD (`Organization`, `BreadcrumbList`, `Article`/`BlogPosting`, `FAQPage`, `Service`, `Product`, `LocalBusiness`) có điều kiện chỉ khi dữ liệu thực sự hiển thị trong bài.
- [`backend/app/Http/Controllers/Api/V1/Chatbot/ConversationController.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Http/Controllers/Api/V1/Chatbot/ConversationController.php) & [`frontend/app/(dashboard)/chatbot/page.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/app/(dashboard)/chatbot/page.tsx): Gỡ bỏ hoàn toàn dữ liệu nha khoa hardcode, chuyển sang thích ứng đa ngành theo `IndustryProfileRegistry`.

### 4. Trust, Fact-Check, E-E-A-T, YMYL & 4-Role Review Pipeline (Phần V & VIII)
- [`backend/app/Services/Quality/TrustAndFactVerificationService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Quality/TrustAndFactVerificationService.php): Phân tầng dữ liệu Hạng A / B / C, phát hiện tuyên bố tuyệt đối 100% phi thực tế, kiểm tra giấy phép chưa xác minh, đánh giá 4 trụ cột E-E-A-T và kiểm soát YMYL Disclaimer.
- [`backend/app/Services/Quality/ContentReviewPipelineService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Quality/ContentReviewPipelineService.php): Kiểm duyệt độc lập qua 4 vai trò (`SEO Reviewer`, `Content Editor`, `Fact Checker`, `Technical Reviewer`) kèm cơ chế sửa lỗi có mục tiêu (`Targeted Auto-Fix`) mà không làm hỏng toàn bài.

### 5. Lập Kế Hoạch Hình Ảnh & Image SEO Đa Ngành (Phần VI)
- [`backend/app/Services/Media/ImagePlannerService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Media/ImagePlannerService.php) & [`backend/app/Services/Media/GeminiImageGenerationService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Media/GeminiImageGenerationService.php): Lập kế hoạch ảnh đại diện + ảnh minh họa theo từng thẻ H2/H3, sinh Prompt theo đặc thù từng ngành nghề, tối ưu tên file SEO, ALT text tự nhiên và Caption trung thực (không nhận vơ ảnh minh họa là ảnh cơ sở thật).

### 6. Chấm Điểm 100 Điểm, Critical Error Gate & Kiểm Thử 6 Ngành Nghề (Phần IX, X, XII)
- [`backend/app/Services/Quality/ContentQualityScoringService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Quality/ContentQualityScoringService.php): Chấm điểm 7 trụ cột = 100 điểm, kiểm soát cổng `PASS` (>=85) / `REVIEW` (75–84) / `BLOCK` (<75 hoặc vi phạm Critical Error Gate), đóng gói trọn vẹn 19 trường kết quả đầu ra.
- [`backend/tests/Feature/MultiIndustryContentEngineTest.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/tests/Feature/MultiIndustryContentEngineTest.php): Bộ kiểm thử tự động bao phủ cả 6 Test Cases chuẩn (Nha khoa, Bất động sản, Luật, Công nghệ/SaaS, Du lịch, Thương mại điện tử) và kiểm thử Targeted Auto-Fix cho YMYL.
