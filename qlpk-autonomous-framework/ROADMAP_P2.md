# ROADMAP_P2.md — Đặc Tả Chi Tiết 9 Nhóm Task Giai Đoạn 2 (Phase 2)
## ADVANCED SEO AI & CONTENT INTELLIGENCE

> **Dự án:** SaaS Viết Bài SEO Tự Động (Multi-Tenant SaaS)  
> **Tech Stack:** Laravel 13 (PHP 8.3) + Next.js 16 (React 19, Tailwind CSS v4, shadcn/ui)  
> **Framework điều phối:** QLPK Multi-Agent Safe Autonomous Framework  
> **Nguyên tắc:** Làm tuần tự từng đợt, không nhảy cóc, nghiệm thu Anti-Fake-Done từng task.

---

## Bảng Tổng Hợp Thứ Tự Triển Khai (Phụ Thuộc & Cổng Nghiệm Thu)

```text
[Đợt 1: TASK P2.1 (Audit & Architecture Design - 100% Read-Only)]
        │
        ├──────────────────────────┬──────────────────────────┐
        ▼                          ▼                          ▼
[Đợt 2: TASK P2.2 (KW Research)] [Đợt 3: TASK P2.3 (SERP)] [Đợt 5: TASK P2.5 (Brand KB)]
        │                          │                          │
        └────────────┬─────────────┘                          │
                     ▼                                        │
[Đợt 4: TASK P2.4 (Topic Clusters & Gap)]                     │
                     │                                        │
                     └─────────────────────┬──────────────────┘
                                           ▼
                    [Đợt 6: TASK P2.6 (AI Content Studio)]
                                           │
                        ┌──────────────────┴──────────────────┐
                        ▼                                     ▼
[Đợt 7: TASK P2.7 (SEO Scoring Engine)]     [Đợt 8: TASK P2.8 (Internal Links & Images)]
                        │                                     │
                        └──────────────────┬──────────────────┘
                                           ▼
[Đợt 9: TASK P2.9 (Bulk Content Generation & E2E Sign-off)]
```

---

## CHI TIẾT 9 NHÓM TASK GIAI ĐOẠN 2

### [NHÓM 1] TASK P2.1: Read-Only Audit & Architecture Design
* **Ưu tiên:** P0 — Bắt buộc hoàn thành đầu tiên.
* **Mục tiêu:** Kiểm toán toàn diện nền tảng Phase 1, đánh giá khả năng tái sử dụng, phân tích khoảng trống và thiết kế chi tiết kiến trúc 15 mục cho Phase 2.
* **Nguyên tắc:** **100% Read-Only** — Không sửa code, không sửa migration, không chạy lệnh destructive.
* **Tệp cho phép tác động (`allowed_files`):**
  - `PHASE_2_ARCHITECTURE_AUDIT.md`
  - `qlpk-autonomous-framework/ROADMAP_P2.md`
  - `qlpk-autonomous-framework/PROGRESS_P2.md`
  - `qlpk-autonomous-framework/reports/tasks/P2.1-test.md`
  - `qlpk-autonomous-framework/reports/tasks/P2.1-review.md`
* **Lệnh kiểm thử:** `test -s PHASE_2_ARCHITECTURE_AUDIT.md && test -s qlpk-autonomous-framework/ROADMAP_P2.md`
* **Điều kiện nghiệm thu:** Báo cáo kiến trúc đầy đủ 15 phần, Reviewer Gemini 4 Argon đánh giá PASS 14 tiêu chí.

---

### [NHÓM 2] TASK P2.2: Keyword Research & Clustering Data Layer
* **Ưu tiên:** P0.
* **Mục tiêu:** Xây dựng công cụ nghiên cứu từ khóa nâng cao, phân loại ý định tìm kiếm (Search Intent) và nhóm từ khóa bằng Semantic Clustering.
* **Công việc chi tiết:**
  - `P2.2.1`: Migration bổ sung trường vào bảng `keywords`: `search_intent`, `lsi_keywords`.
  - `P2.2.2`: `KeywordResearchService`: Gọi Suggest API & SerpAPI, tích hợp AI phân tích Intent và nhóm cụm.
  - `P2.2.3`: API Controller & FormRequest: `GET /api/v1/keywords/research`, `POST /api/v1/keywords/cluster`.
  - `P2.2.4`: Trừ credit: 2 credits / lượt tìm kiếm 50 từ khóa.
  - `P2.2.5`: Frontend UI: Trang `/keywords/research` với bộ lọc Search Intent, Volume, Difficulty và nút lưu vào Campaign.
* **Phụ thuộc:** P2.1.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_add_fields_to_keywords_table.php`
  - `backend/app/Models/Keyword.php`
  - `backend/app/Services/SEO/KeywordResearchService.php`
  - `backend/app/Http/Controllers/Api/V1/KeywordResearchController.php`
  - `backend/app/Http/Requests/KeywordResearchRequest.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/KeywordResearchTest.php`
  - `frontend/app/(dashboard)/keywords/research/page.tsx`
  - `frontend/hooks/useKeywordResearch.ts`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=KeywordResearchTest`

---

### [NHÓM 3] TASK P2.3: SERP & Competitor Intelligence Engine
* **Ưu tiên:** P0.
* **Mục tiêu:** Nâng cấp khả năng bóc tách đối thủ, phân tích cấu trúc H1–H4, mật độ từ khóa và trích xuất thực thể ngữ nghĩa LSI.
* **Công việc chi tiết:**
  - `P2.3.1`: Nâng cấp `SerpResearchService` và `CompetitorCrawlService` (bóc tách Heading map, Schema, Word count, LSI entities).
  - `P2.3.2`: Chặn đứng lỗ hổng SSRF khi cào nội dung URL đối thủ.
  - `P2.3.3`: `CompetitorAnalysisService`: Tính toán bảng Benchmark đối thủ Top 10.
  - `P2.3.4`: API Controller: `GET /api/v1/competitor/serp-analysis`.
  - `P2.3.5`: Frontend UI: Trang `/competitor/serp` hiển thị bảng so sánh đối thủ trực quan.
* **Phụ thuộc:** P2.1.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/SEO/CompetitorAnalysisService.php`
  - `backend/app/Services/SEO/SerpResearchService.php`
  - `backend/app/Http/Controllers/Api/V1/CompetitorAnalysisController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/CompetitorAnalysisTest.php`
  - `frontend/app/(dashboard)/competitor/serp/page.tsx`
  - `frontend/components/features/competitor/SerpBenchmarkTable.tsx`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=CompetitorAnalysisTest`

---

### [NHÓM 4] TASK P2.4: Topic Clusters & Content Gap Analysis
* **Ưu tiên:** P1.
* **Mục tiêu:** Quản lý mô hình Cụm chủ đề (Pillar & Supporting Clusters) và tự động phát hiện khoảng trống nội dung so với đối thủ.
* **Công việc chi tiết:**
  - `P2.4.1`: Migration tạo bảng `topic_clusters` và `content_gaps` với khóa ngoại và tenant scoping đầy đủ.
  - `P2.4.2`: `TopicClusterService`: Tạo cụm, gán từ khóa trụ cột, tính tổng volume và độ khó trung bình.
  - `P2.4.3`: `ContentGapService`: So sánh từ khóa đối thủ đã lên top với website của mình, tính `opportunity_score`.
  - `P2.4.4`: API Controller: `TopicClusterController` và `ContentGapController`.
  - `P2.4.5`: Frontend UI: Trang `/keywords/clusters` (bản đồ trực quan) và `/competitor/content-gap`.
* **Phụ thuộc:** P2.2, P2.3.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_create_topic_clusters_and_content_gaps_tables.php`
  - `backend/app/Models/TopicCluster.php`
  - `backend/app/Models/ContentGap.php`
  - `backend/app/Services/SEO/TopicClusterService.php`
  - `backend/app/Services/SEO/ContentGapService.php`
  - `backend/app/Http/Controllers/Api/V1/TopicClusterController.php`
  - `backend/app/Http/Controllers/Api/V1/ContentGapController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/TopicClusterTest.php`
  - `frontend/app/(dashboard)/keywords/clusters/page.tsx`
  - `frontend/app/(dashboard)/competitor/content-gap/page.tsx`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=TopicClusterTest`

---

### [NHÓM 5] TASK P2.5: Brand Knowledge Base & Context Injector
* **Ưu tiên:** P1.
* **Mục tiêu:** Xây dựng kho tri thức thương hiệu (USP, Persona, Tone of Voice, Taboo Words) và tự động nhúng vào mọi câu lệnh AI viết bài.
* **Công việc chi tiết:**
  - `P2.5.1`: Migration tạo bảng `brand_knowledge_bases` gắn với `workspace_id` và `website_id`.
  - `P2.5.2`: `BrandKnowledgeService`: Quản lý kho kiến thức và cung cấp hàm inject ngữ cảnh vào Prompt.
  - `P2.5.3`: Nâng cấp `PromptBuilderService` để tự động đọc tri thức thương hiệu của Website hiện tại.
  - `P2.5.4`: API Controller: `BrandKnowledgeController` (CRUD theo Website).
  - `P2.5.5`: Frontend UI: Trang `/brand-knowledge` với giao diện trực quan và quản lý từ ngữ cấm kỵ.
* **Phụ thuộc:** P2.1.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_create_brand_knowledge_bases_table.php`
  - `backend/app/Models/BrandKnowledgeBase.php`
  - `backend/app/Services/Brand/BrandKnowledgeService.php`
  - `backend/app/Http/Controllers/Api/V1/BrandKnowledgeController.php`
  - `backend/app/Http/Requests/BrandKnowledgeRequest.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/BrandKnowledgeTest.php`
  - `frontend/app/(dashboard)/brand-knowledge/page.tsx`
  - `frontend/hooks/useBrandKnowledge.ts`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=BrandKnowledgeTest`

---

### [NHÓM 6] TASK P2.6: AI Content Studio & Interactive Editor
* **Ưu tiên:** P0.
* **Mục tiêu:** Xây dựng phòng biên tập bài viết tương tác với bộ soạn thảo Rich-Text và trợ lý AI Copilot tức thì.
* **Công việc chi tiết:**
  - `P2.6.1`: `ContentStudioService`: Xử lý các tác vụ AI theo đoạn văn (Rewrite, Expand, Shorten, Change Tone) và lưu nháp.
  - `P2.6.2`: API Controller: `POST /api/v1/content-studio/assist`, `POST /api/v1/content-studio/autosave`.
  - `P2.6.3`: Trừ credit: 1 credit / yêu cầu AI Copilot.
  - `P2.6.4`: Frontend UI: Trang `/content-studio/[id]` với bố cục 3 cột (Outline Tree, Rich-Text Editor, Floating AI Menu).
* **Phụ thuộc:** P2.4, P2.5.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/Article/ContentStudioService.php`
  - `backend/app/Http/Controllers/Api/V1/ContentStudioController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/ContentStudioTest.php`
  - `frontend/app/(dashboard)/content-studio/[id]/page.tsx`
  - `frontend/components/features/studio/LexicalStudioEditor.tsx`
  - `frontend/components/features/studio/StudioOutlineTree.tsx`
  - `frontend/components/features/studio/FloatingAiMenu.tsx`
  - `frontend/hooks/useContentStudio.ts`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=ContentStudioTest && cd ../frontend && npm run build`

---

### [NHÓM 7] TASK P2.7: Content Quality & Real-Time SEO Scoring Engine
* **Ưu tiên:** P0.
* **Mục tiêu:** Xây dựng engine chấm điểm SEO 100 điểm bằng mã nguồn thuần (Deterministic PHP), chạy nhanh < 50ms và không tốn credit.
* **Công việc chi tiết:**
  - `P2.7.1`: `SeoScoringEngine`: Thuật toán kiểm tra 9 tiêu chí SEO (H1, Meta, Heading, Keyword Density 1-2%, Image Alts, Readability, Links, FAQs, Schema).
  - `P2.7.2`: API Controller: `POST /api/v1/articles/{id}/score-audit`.
  - `P2.7.3`: Tích hợp sidebar hiển thị điểm số thời gian thực trên Content Studio.
* **Phụ thuộc:** P2.6.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/SEO/SeoScoringEngine.php`
  - `backend/app/Http/Controllers/Api/V1/SeoScoringController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/SeoScoringEngineTest.php`
  - `frontend/components/features/studio/SeoScoreSidebar.tsx`
  - `frontend/hooks/useSeoScore.ts`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=SeoScoringEngineTest`

---

### [NHÓM 8] TASK P2.8: Internal Linking Intelligence & AI Images
* **Ưu tiên:** P1.
* **Mục tiêu:** Xây dựng đồ thị liên kết nội bộ tự động đề xuất câu chèn anchor text và tích hợp sinh ảnh AI theo ngữ cảnh H2.
* **Công việc chi tiết:**
  - `P2.8.1`: Migration tạo bảng `internal_link_suggestions`.
  - `P2.8.2`: `InternalLinkingEngine`: Quét kho bài viết của website, phát hiện cơ hội chèn link và tạo câu tự nhiên.
  - `P2.8.3`: `AiImageService`: Sinh ảnh Google Imagen 3 theo tiêu đề mục H2, tự động nén WebP và gắn thẻ ALT chuẩn SEO.
  - `P2.8.4`: API Controller: `InternalLinkController`.
  - `P2.8.5`: Frontend UI: Trang `/internal-links` và hộp thoại gợi ý link trong Studio.
* **Phụ thuộc:** P2.6.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_create_internal_link_suggestions_table.php`
  - `backend/app/Models/InternalLinkSuggestion.php`
  - `backend/app/Services/SEO/InternalLinkingEngine.php`
  - `backend/app/Services/Media/AiImageService.php`
  - `backend/app/Http/Controllers/Api/V1/InternalLinkController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/InternalLinkingTest.php`
  - `frontend/app/(dashboard)/internal-links/page.tsx`
  - `frontend/components/features/studio/InternalLinkSuggestionsModal.tsx`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=InternalLinkingTest`

---

### [NHÓM 9] TASK P2.9: Bulk Content Generation, Integration & E2E Sign-off
* **Ưu tiên:** P0.
* **Mục tiêu:** Điều phối sản xuất 10–100 bài viết cùng lúc theo lô, bóp nghẹt tốc độ tránh nghẽn API, tự động giữ và hoàn credit an toàn, hoàn tất toàn diện Phase 2.
* **Công việc chi tiết:**
  - `P2.9.1`: Migration tạo bảng `bulk_batches`.
  - `P2.9.2`: `BulkGenerationService` & `ProcessBulkBatchJob`: Giữ trước credit theo lô, dispatch tác vụ với khoảng nghỉ an toàn.
  - `P2.9.3`: Tự động hoàn credit cho các bài viết bị lỗi vĩnh viễn.
  - `P2.9.4`: API Controller: `POST /api/v1/bulk-batches`, `GET /api/v1/bulk-batches/{uuid}/progress`.
  - `P2.9.5`: Frontend UI: Trang `/bulk-generator` hiển thị thanh tiến độ lô bài thời gian thực.
  - `P2.9.6`: Bộ kiểm thử End-to-End toàn bộ Phase 2: `EndToEndPhase2WorkflowTest.php`.
* **Phụ thuộc:** P2.4, P2.7, P2.8.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_create_bulk_batches_table.php`
  - `backend/app/Models/BulkBatch.php`
  - `backend/app/Services/Bulk/BulkGenerationService.php`
  - `backend/app/Jobs/Bulk/ProcessBulkBatchJob.php`
  - `backend/app/Http/Controllers/Api/V1/BulkGenerationController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/BulkGenerationTest.php`
  - `backend/tests/Feature/EndToEndPhase2WorkflowTest.php`
  - `frontend/app/(dashboard)/bulk-generator/page.tsx`
  - `frontend/components/features/bulk/BulkProgressMonitor.tsx`
  - `qlpk-autonomous-framework/reports/tasks/PHASE_2_COMPLETION_REPORT.md`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test && cd ../frontend && npm run build`

---
