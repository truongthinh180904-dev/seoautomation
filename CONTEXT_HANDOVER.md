# 📘 AURASEO — TÀI LIỆU NGỮ CẢNH DỰ ÁN & BÀN GIAO TIẾP TỤC CODE
*(Cập nhật lúc: 11/11/2026 — Dành cho phiên làm việc tiếp theo)*

---

## 1. TỔNG QUAN HỆ THỐNG (PROJECT OVERVIEW)

* **Tên nền tảng:** **AuraSEO** (SaaS AI Content & Automated SEO Suite).
* **Mục tiêu:** Nền tảng SaaS tự động hóa toàn diện quy trình sản xuất nội dung chuẩn SEO: Từ cào đối thủ Top 3 SERP, phân tích Semantic Entities/LSI, sinh dàn ý và bài viết chuyên sâu 2.000+ từ, tự động gắn ảnh chuẩn y khoa/nén WebP <150KB, chấm điểm SEO On-Page 100 tiêu chí, cho đến xuất bản 1-click lên các website WordPress.
* **Mô hình kinh doanh hiện tại:** 10 khách hàng x 100 bài/tháng = 1.000 bài viết/tháng, doanh thu kỳ vọng 15.000.000 VNĐ/tháng (1.500.000 VNĐ/khách). Chi phí API SERP & Gemini AI chỉ tốn khoảng $5 - $10/tháng (lợi nhuận >98%).

---

## 2. KIẾN TRÚC CÔNG NGHỆ (TECH STACK)

### Frontend:
* **Framework:** Next.js 16 (App Router), React 19.
* **Ngôn ngữ:** TypeScript (chế độ strict).
* **Styling & UI:** TailwindCSS v4, shadcn/ui ported sang `@base-ui/react` (Base UI 1.0 Alpha), Lucide React icons.
* **State Management:** Zustand (AuthStore, TenancyStore), TanStack Query (React Query).
* **Editor:** Lexical Editor (Meta) được tùy biến cao cấp theo phong cách Gutenberg WordPress.
* **Port mặc định:** `http://localhost:3000`

### Backend:
* **Framework:** Laravel 12 API, PHP 8.2+.
* **Database:** MySQL local (`DBAURASEO`), PostgreSQL ready.
* **Cache & Queue:** Redis, Laravel Horizon.
* **Kiến trúc:** Service Pattern + Repository Pattern, Form Request Validation, API Resources, DTO.
* **AI & Search Engines:** 
  - Google Gemini 2.5 (Flash, Pro, Vision).
  - SERP API / Google Custom Search Engine (bóc tách Top 3 đối thủ làm mỏ neo chuẩn).
  - SeoScoringEngine (bộ chấm điểm thuần PHP 100 tiêu chí SEO kỹ thuật không tốn credit).
* **Port mặc định:** `http://localhost:8000`

---

## 3. CÁC TÍNH NĂNG VỪA HOÀN THIỆN (RECENT ACHIEVEMENTS)

### 3.1. Nhận diện thương hiệu & Logo chính thức
* **Logo chuẩn:** Chữ **A** cách điệu với vòng tròn quỹ đạo không gian neon blue sắc nét.
* **Tệp lưu trữ:**
  - [`frontend/public/logo.png`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/public/logo.png)
  - [`frontend/public/logo.jpg`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/public/logo.jpg)
  - Route handler fallback: [`frontend/app/api/logo/route.ts`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/app/api/logo/route.ts)
* **Vị trí áp dụng:** Dashboard Layout sidebar, Header, Favicon, Auth Pages, và Avatar chatbot AI Aura.

### 3.2. Trình soạn thảo bài viết phong cách WordPress Gutenberg (`/articles/[id]/edit` & `/content-studio/[id]`)
* **Gutenberg Image Block (`ImageNode.tsx` & `ImageDetailsDialog.tsx`):**
  - Bấm vào ảnh trong bài viết sẽ hiện thanh công cụ nổi giống 100% WordPress Gutenberg:
    + Căn lề: Căn Trái, Căn Giữa, Căn Phải.
    + Kích thước ảnh: Presets 25%, 50%, 75%, 100% hoặc kéo thanh trượt tùy ý.
    + Nút **Thay ảnh từ máy tính**: Upload ảnh trực tiếp từ máy tính lên backend, tự động nén WebP và lưu trữ.
    + Nút **Sửa chi tiết**: Cập nhật URL ảnh, thẻ `alt` (văn bản thay thế chuẩn SEO Google) và chú thích `caption` hiển thị dưới ảnh.
* **Backend Upload Service:**
  - Route: `POST /api/v1/editor/images`
  - Controller: [`EditorMediaController.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Http/Controllers/Api/V1/EditorMediaController.php)
  - Service: [`EditorImageUploadService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Media/EditorImageUploadService.php)
* **Giao diện bài viết:** Tab bên phải (Cài đặt & SEO Score) được mặc định **ĐÓNG**, chỉ khi người dùng bấm nút mới trượt ra, giúp không gian soạn thảo thoáng đãng tối đa.

### 3.3. Trợ lý AI Aura Chatbot (`/chatbot`)
* **Lưu trữ & Đồng bộ:** Tích hợp API Backend `/conversations` và `/conversations/{uuid}/messages`, lưu lịch sử hội thoại vào Database thật.
* **Cơ chế SERP Top 3:** Quét đúng 3 kết quả hàng đầu trên Google SERP. Nếu từ khóa khó/ngách không đủ 3 đối thủ thì hệ thống tự động fallback sang cơ sở tri thức ngành chuyên sâu (Brand Knowledge Base) + Semantic Knowledge Graph mà không bị gián đoạn.
* **Xử lý sạch thẻ HTML:** Bộ phân tích Markdown (`ArticleHtmlBlock`) ngăn chặn hoàn toàn hiện tượng render lỗi thẻ HTML thô (`<tr>`, `<thead>`, `<tbody>`) ra màn hình chat.
* **Khắc phục lỗi React Hydration & Import:**
  - Sửa lỗi `AvatarImage is not defined` bằng việc import chuẩn trong [`ChatMessageItem.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/components/features/chatbot/ChatMessageItem.tsx).
  - Sửa triệt để lỗi Hydration `<button> cannot contain a nested <button>`: Chuyển đổi toàn bộ Trigger của `@base-ui/react` sang cơ chế `render={children}` khi có `asChild`, loại bỏ thẻ `<button>` lồng nhau ở `ChatSidebar.tsx` và `chatbot/page.tsx`.

### 3.4. Xuất bản WordPress 1-Click & Đồng bộ Media ALT Chuẩn
* **Liên kết & Quản lý Site:** Bổ sung Card cài đặt WordPress Site trong trình chỉnh sửa [`ArticleImportSettings.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/components/features/articles/ArticleImportSettings.tsx): hỗ trợ chọn site, test kết nối REST API trực tiếp và lưu vào bài viết.
* **Tự động bóc tách ảnh:** [`MediaParserService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/Media/MediaParserService.php) tự động quét thẻ `<img>` và ảnh đại diện (`featured_image_url`), nén WebP và đẩy lên thư viện media WordPress trước khi đăng.
* **Kích hoạt từ Chatbot:** [`chatbot/page.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/app/(dashboard)/chatbot/page.tsx) kết nối trực tiếp nút duyệt xuất bản Human-in-the-Loop với endpoint `/api/v1/articles/{id}/approve-publish`.

### 3.5. Bộ nhớ đệm Đa tầng Redis & SERP Caching (7 - 14 ngày)
* **Kiến trúc Cache:** Nâng cấp [`SerpCacheService.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Services/SEO/SerpCacheService.php) lưu trữ đa tầng: Lớp 1 (Redis in-memory cache) và Lớp 2 (Database `serp_caches`).
* **Thời gian lưu trữ:** Tăng TTL từ 1 ngày lên 14 ngày (`config('seo.serp_cache_ttl_days', 14)`), giúp tiết kiệm hơn 95% chi phí SerpAPI đối với các từ khóa tra cứu lặp lại.

### 3.6. Sản xuất hàng loạt từ Topic Cluster & Excel (`/bulk-generator`)
* **Thanh công cụ nạp nhanh:** Bổ sung thanh công cụ nạp từ khóa trong [`bulk-generator/page.tsx`](file:///Users/truongthinh/Documents/SaasAutoSeo/frontend/app/(dashboard)/bulk-generator/page.tsx):
  - Nhập 1-click toàn bộ từ khóa từ Cụm chủ đề SEO (Topic Clusters).
  - Tải lên danh sách từ khóa từ tệp CSV / TXT.
  - Bộ từ khóa nha khoa chuẩn SEO mẫu (10 từ khóa) để kiểm thử luồng hàng đợi Horizon tức thì.

### 3.7. Bảo mật & Phân lập Dữ liệu Multi-Tenancy
* **Workspace Scoping:** [`ArticleRepository.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Repositories/ArticleRepository.php) và [`WordPressSiteRepository.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Repositories/WordPressSiteRepository.php) tự động phân lập theo `current_workspace_id`, chống rò rỉ dữ liệu giữa các khách hàng khác nhau.
* **Xác thực thành viên:** [`WebsiteController.php`](file:///Users/truongthinh/Documents/SaasAutoSeo/backend/app/Http/Controllers/Api/V1/WebsiteController.php) bổ sung kiểm tra tư cách thành viên workspace để ngăn chặn giả mạo header.

---

## 4. BẢNG TỔNG HỢP CÁC TỆP QUAN TRỌNG (KEY FILES DIRECTORY)

| Tệp / Thư mục | Vai trò / Mô tả |
| :--- | :--- |
| `frontend/app/(dashboard)/chatbot/page.tsx` | Trang chính của Trợ lý AI Aura SEO Copilot. |
| `frontend/components/features/chatbot/ChatMessageItem.tsx` | Render tin nhắn chat, widget metadata, bộ lọc thẻ HTML, Avatar AI. |
| `frontend/components/features/chatbot/ChatSidebar.tsx` | Sidebar lịch sử hội thoại, bộ chọn Workspace & WordPress Site. |
| `frontend/app/(dashboard)/articles/[id]/edit/page.tsx` | Trang chỉnh sửa bài viết chuẩn SEO, tích hợp SEOScorePanel và Timeline. |
| `frontend/components/features/articles/ArticleEditor/nodes/ImageNode.tsx` | Gutenberg Custom Image Node cho Lexical (chỉnh sửa ảnh giống WordPress). |
| `frontend/components/features/articles/ArticleEditor/nodes/ImageDetailsDialog.tsx` | Modal sửa thẻ ALT, Caption, Width, URL ảnh và tải ảnh từ máy lên. |
| `frontend/components/features/articles/ArticleEditor/ToolbarPlugin.tsx` | Thanh công cụ soạn thảo đầy đủ chức năng (Heading H1-H4, Format, Table, Link). |
| `backend/app/Services/Media/EditorImageUploadService.php` | Xử lý upload, validate và nén ảnh WebP từ trình soạn thảo. |
| `backend/app/Http/Controllers/Api/V1/EditorMediaController.php` | Endpoint nhận upload ảnh: `POST /api/v1/editor/images`. |
| `frontend/components/ui/` (`dropdown-menu`, `dialog`, `sheet`, `popover`, `button`) | Bộ UI Component shadcn tương thích `@base-ui/react`, đã chống lỗi hydration. |

---

## 5. QUY TẮC BẮT BUỘC KHI TIẾP TỤC CODE (`AGENTS.md`)

1. **Không xóa business logic cốt lõi**, không xóa cấu hình môi trường `.env`.
2. **Không phá vỡ API contracts** hiện có giữa Laravel và Next.js.
3. **Giữ kiến trúc phân tầng rõ ràng:**
   - Frontend: Feature-based folder structure, TypeScript strict, shadcn/ui + Base UI, TanStack Query, React Hook Form + Zod.
   - Backend: Thin Controller, logic nghiệp vụ đặt trong Services, tương tác dữ liệu qua Repositories/DTO, tác vụ nặng đẩy vào Horizon Jobs.
4. **Lưu ý Runner Mac OS:** Công cụ `run_command` trên môi trường này đôi khi trả về exit code 137 nếu tác vụ quá nặng. Ưu tiên sử dụng các công cụ thao tác tệp trực tiếp (`read_file`, `write_file`, `edit_file`).

---

## 6. DANH SÁCH VIỆC TIẾP TỤC THỰC HIỆN (NEXT ROADMAP TASKS)

1. **Dashboard Phân tích Hiệu suất SEO & Biểu đồ trực quan (Phase 3.4 & GSC/GA4):**
   - Hoàn thiện giao diện biểu đồ tăng trưởng Clicks/Impressions từ Google Search Console và GA4 trên Dashboard.
   - Thống kê tỷ lệ chuyển đổi bài viết xuất bản WordPress theo từng chiến dịch.
2. **Cổng thanh toán & Nạp Credit tự động (VNPay / Momo / Stripe):**
   - Kết nối webhook nạp tiền và quy đổi credit tự động theo bảng giá dịch vụ SaaS (1.500.000 VNĐ/tháng).
3. **Lập lịch Quét thứ hạng Từ khóa tự động (Rank Tracker Cron):**
   - Thiết lập cron job chạy lúc 00:00 hàng ngày quét thứ hạng các từ khóa trong danh sách theo dõi và gửi thông báo biến động SEO.

---
*Tài liệu này đã được lưu vào gốc dự án tại `CONTEXT_HANDOVER.md`. Bạn chỉ cần đăng nhập tài khoản khác và yêu cầu trợ lý đọc tệp này là có thể tiếp tục công việc ngay lập tức!*
