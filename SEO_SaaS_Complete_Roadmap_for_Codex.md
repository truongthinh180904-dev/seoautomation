# AI SEO Automation SaaS — Complete Upgrade Plan v2
> Tài liệu này dành cho Codex/Cursor để phát triển tiếp. Đọc toàn bộ trước khi code bất kỳ task nào.

---

## 0. Tổng quan hệ thống hiện tại

### Đã hoàn thành (KHÔNG sửa lại)
- Excel keyword import (cột cơ bản)
- SERP research via Serper.dev → top 10
- Gemini phân tích SERP → outline/content brief
- Gemini viết bài hoàn chỉnh
- WordPress publishing service (50% — chưa có featured image, chưa có preflight)

### Mục tiêu upgrade
Từ "AI article generator" → **SaaS quản lý chiến dịch SEO** với:
1. Campaign management (chiến dịch)
2. Excel template v2 (content plan chuẩn)
3. WordPress publishing hoàn chỉnh 100%
4. Media/image pipeline
5. SEO QA scoring + auto-fix
6. Approval workflow
7. Notification (email + Telegram, bỏ Zalo)
8. **[MỚI] Kiểm soát chi phí AI + quota bài viết**
9. **[MỚI] Subscription plans + usage limits**

---

## 1. PHÂN TÍCH & ĐÁNH GIÁ TÀI LIỆU GỐC

### Bảng đánh giá mức độ hoàn thiện

| Hạng mục | Mức độ tài liệu gốc | Vấn đề còn thiếu | Ưu tiên |
|---|---|---|---|
| Excel import | 40% — chỉ có cột cơ bản | Thiếu campaign columns, internal links, media, SEO strategy | HIGH |
| Campaign management | 0% — chưa có | Cần xây từ đầu hoàn toàn | HIGH |
| WordPress publishing | 60% — có basic flow | Thiếu preflight, media upload, tag create, full field map | HIGH |
| Media/image pipeline | 0% — chưa có | Cần xây từ đầu | MEDIUM |
| QA/SEO scoring | 30% — có QAValidationAgent cơ bản | Thiếu scoring groups, auto-fix, SERP gap | MEDIUM |
| Internal linking | 40% — có InternalLinkingAgent | Thiếu Excel-provided links, orphan detection | MEDIUM |
| Notification | 20% — đang là Zalo (sai hướng) | Phải thay toàn bộ sang email + Telegram | HIGH |
| Approval workflow | 10% — chỉ mô tả trạng thái | Thiếu token, email link, dashboard flow | MEDIUM |
| **Cost control** | **0% — HOÀN TOÀN THIẾU** | **Cần xây từ đầu — bắt buộc cho SaaS** | **CRITICAL** |
| **Quota/subscription** | **0% — HOÀN TOÀN THIẾU** | **Cần xây từ đầu — bắt buộc cho SaaS** | **CRITICAL** |
| Dashboard analytics | 20% — có route cơ bản | Thiếu campaign stats, cost dashboard, usage chart | MEDIUM |
| SERP intelligence | 30% — lưu nhưng không hiển thị | Thiếu gap analysis, competitor panel | LOW |

---

## 2. BỔ SUNG QUAN TRỌNG: COST CONTROL + QUOTA MANAGEMENT

> **Đây là phần tài liệu gốc HOÀN TOÀN BỎ QUA nhưng bắt buộc phải có cho SaaS.**

### 2.1 Vì sao cần thiết

- Mỗi bài viết tốn ~$0.05–$0.30 AI cost (Gemini tokens + Serper calls)
- Không có limit → khách dùng thoải mái → bạn lỗ tiền API
- SaaS cần subscription plans với giới hạn rõ ràng
- Admin cần dashboard xem chi phí thực tế từng tenant

### 2.2 Subscription Plans (đề xuất)

```
Plan: starter
  articles_per_month: 30
  ai_cost_budget_usd: 10.00
  campaigns: 2
  wordpress_sites: 1
  serp_lookups_per_article: 10
  team_members: 1

Plan: professional
  articles_per_month: 150
  ai_cost_budget_usd: 50.00
  campaigns: 10
  wordpress_sites: 3
  serp_lookups_per_article: 10
  team_members: 5

Plan: agency
  articles_per_month: 500
  ai_cost_budget_usd: 200.00
  campaigns: unlimited
  wordpress_sites: unlimited
  serp_lookups_per_article: 10
  team_members: unlimited

Plan: enterprise
  articles_per_month: custom
  ai_cost_budget_usd: custom
  campaigns: unlimited
  wordpress_sites: unlimited
  team_members: unlimited
```

### 2.3 Database schema mới cho cost control

#### Table: `subscription_plans`
```
id
name (starter/professional/agency/enterprise)
articles_per_month
ai_cost_budget_usd
campaigns_limit (null = unlimited)
wordpress_sites_limit (null = unlimited)
team_members_limit (null = unlimited)
serp_lookups_per_article
price_monthly_usd
price_yearly_usd
is_active
features JSON
timestamps
```

#### Table: `tenant_subscriptions`
```
id
tenant_id
plan_id
status (active/past_due/cancelled/trialing)
articles_used_this_month
ai_cost_used_this_month_usd
billing_cycle_start
billing_cycle_end
trial_ends_at
cancelled_at
timestamps
```

#### Table: `ai_usage_logs`
```
id
tenant_id
campaign_id (nullable)
article_id (nullable)
keyword_id (nullable)
job_type (serp_lookup / outline_generation / article_writing / qa_validation / internal_linking / campaign_planning)
provider (gemini / serper / openai)
model
prompt_tokens
completion_tokens
total_tokens
cost_usd (calculated)
duration_ms
status (success/failed)
error_message
timestamps
```

#### Table: `monthly_usage_snapshots`
```
id
tenant_id
year
month
articles_generated
articles_published
total_ai_cost_usd
serper_calls
gemini_tokens_prompt
gemini_tokens_completion
top_campaigns JSON
timestamps
```

### 2.4 Cost tracking service

```php
// CostTrackingService.php
class CostTrackingService
{
    // Gọi sau mỗi AI API call
    public function logUsage(array $data): AiUsageLog

    // Kiểm tra trước khi bắt đầu job
    public function checkQuota(int $tenantId): QuotaCheckResult

    // Tính chi phí theo provider + model
    public function calculateCost(string $provider, string $model, int $tokens): float

    // Lấy usage hiện tháng
    public function getMonthlyUsage(int $tenantId): MonthlyUsage

    // Cảnh báo khi gần đạt limit
    public function checkAndWarnThresholds(int $tenantId): void
}
```

#### Bảng giá tham khảo (cập nhật theo API provider)
```php
const COST_PER_1K_TOKENS = [
    'gemini-1.5-pro' => ['input' => 0.00125, 'output' => 0.005],
    'gemini-1.5-flash' => ['input' => 0.000075, 'output' => 0.0003],
    'gemini-2.0-flash' => ['input' => 0.0001, 'output' => 0.0004],
];

const SERPER_COST_PER_CALL = 0.001; // ~$1 per 1000 calls
```

### 2.5 Quota check middleware

```php
// Trước khi chạy bất kỳ AI job nào, PHẢI check quota
class CheckQuotaBeforeAiJob
{
    public function handle(Job $job, Closure $next)
    {
        $quota = $this->costTracking->checkQuota($job->tenantId);

        if ($quota->articlesExceeded) {
            throw new QuotaExceededException('Monthly article limit reached');
        }

        if ($quota->costExceeded) {
            throw new QuotaExceededException('Monthly AI cost budget exceeded');
        }

        if ($quota->warningThreshold) {
            // Gửi notification cảnh báo (80% của limit)
            $this->notifyQuotaWarning($job->tenantId, $quota);
        }

        return $next($job);
    }
}
```

### 2.6 Notification types bổ sung cho cost control

```
quota_articles_warning (80% articles used)
quota_articles_exceeded (100% — jobs paused)
quota_cost_warning (80% budget used)
quota_cost_exceeded (100% — jobs paused)
billing_cycle_reset (đầu tháng mới, quota reset)
```

### 2.7 Frontend: Cost & Usage Dashboard

**Page: `/analytics/usage`**

Hiển thị:
- Articles used / limit this month (progress bar)
- AI cost used / budget this month (progress bar)
- Cost breakdown by job type (pie chart)
- Daily cost chart (line chart, 30 ngày)
- Top campaigns by cost
- Top campaigns by articles
- Projected end-of-month cost

**Page: `/admin/tenants/{id}/usage`** (admin only)

Hiển thị toàn bộ usage của tenant:
- Lịch sử usage 12 tháng
- Chi phí theo provider (Gemini vs Serper)
- So sánh với plan limit
- Nút điều chỉnh plan

---

## 3. BỔ SUNG: CONTENT QUALITY CONTROLS

> Phần này tài liệu gốc có nhắc đến nhưng chưa đủ cụ thể.

### 3.1 Article quality thresholds

```php
// config/quality.php
return [
    'min_word_count' => 800,
    'min_seo_score' => 70,          // 0-100
    'min_readability_score' => 60,  // 0-100
    'min_internal_links' => 2,
    'min_heading_count' => 3,
    'keyword_density_min' => 0.5,   // %
    'keyword_density_max' => 2.5,   // %
    'meta_title_min' => 45,
    'meta_title_max' => 60,
    'meta_desc_min' => 120,
    'meta_desc_max' => 160,
    'featured_image_required' => true,
    'faq_required' => false,        // configurable per campaign
    'auto_publish_threshold' => 80, // nếu score >= 80 và approval_required = false
];
```

### 3.2 Article scoring chi tiết

```
SEO Score (40 điểm)
  keyword_in_title: 10 điểm
  keyword_in_h1: 5 điểm
  keyword_in_first_paragraph: 5 điểm
  keyword_density_ok: 5 điểm
  meta_title_length_ok: 5 điểm
  meta_desc_length_ok: 5 điểm
  internal_links_count_ok: 5 điểm

Readability Score (30 điểm)
  word_count_ok: 10 điểm
  heading_structure_ok: 10 điểm
  faq_exists: 5 điểm
  no_placeholder_text: 5 điểm

Media Score (15 điểm)
  featured_image_exists: 10 điểm
  all_images_have_alt: 5 điểm

WordPress Readiness Score (15 điểm)
  slug_not_duplicate: 5 điểm
  categories_valid: 5 điểm
  tags_valid: 5 điểm

Total: 100 điểm
Auto-publish threshold: >= 80
Warn threshold: 60-79
Block publish threshold: < 60
```

---

## 4. DATABASE SCHEMA ĐẦY ĐỦ (bổ sung vào tài liệu gốc)

### Bảng đã được định nghĩa trong tài liệu gốc
- `campaigns` ✓
- `keywords` (update) ✓
- `articles` (update) ✓
- `media_assets` ✓
- `campaign_imports` ✓
- `approval_requests` ✓
- `notifications` (thay zalo_notifications) ✓

### Bảng MỚI cần thêm (chưa có trong tài liệu gốc)

```sql
-- subscription_plans
-- tenant_subscriptions
-- ai_usage_logs
-- monthly_usage_snapshots
-- quality_reports (tách ra khỏi articles.quality_report JSON để dễ query)
-- serp_cache (cache SERP results để tiết kiệm Serper credits)
-- article_revisions (lưu lịch sử chỉnh sửa)
```

#### Table: `serp_cache`
```
id
keyword
language
country
provider (serper)
results JSON
expires_at
timestamps
```
> Lý do: cùng keyword search nhiều lần → tiết kiệm Serper credits. TTL 24h.

#### Table: `article_revisions`
```
id
article_id
revision_number
content
word_count
seo_score
quality_score
changed_by (system/user_id)
change_reason
timestamps
```
> Lý do: khi regenerate section, auto-fix, hoặc editor chỉnh tay → cần rollback.

#### Table: `quality_reports`
```
id
article_id
seo_score
readability_score
media_score
wordpress_readiness_score
total_score
checks JSON (chi tiết từng check)
warnings JSON
blocking_errors JSON
auto_fixable JSON
generated_at
timestamps
```

---

## 5. EXCEL TEMPLATE V2 — HƯỚNG DẪN CHI TIẾT

### File template chuẩn

Tên file: `campaign_template_v2.xlsx`
Sheet 1: `Keywords` (bắt buộc)
Sheet 2: `Instructions` (hướng dẫn cho user)
Sheet 3: `Examples` (ví dụ mẫu)

### Column validation rules

```php
// ExcelColumnConfig.php
const REQUIRED_COLUMNS = ['keyword'];

const OPTIONAL_COLUMNS_WITH_DEFAULTS = [
    'search_intent' => 'informational',
    'priority' => 'medium',
    'target_word_count' => 1200,
    'publish_status' => 'draft',
    'language' => 'vi',
];

const VALIDATION_RULES = [
    'keyword' => ['required', 'string', 'min:2', 'max:200'],
    'target_word_count' => ['integer', 'min:300', 'max:10000'],
    'priority' => ['in:low,medium,high,critical'],
    'search_intent' => ['in:informational,navigational,transactional,commercial'],
    'publish_status' => ['in:draft,publish,private'],
    'scheduled_at' => ['date', 'after:now'],
    'featured_image_url' => ['url'],
    'internal_links' => ['string'], // pipe-separated format
];
```

### Import preview format

```json
{
  "file": "campaign_jan_2025.xlsx",
  "template_version": "v2",
  "total_rows": 50,
  "valid_rows": 47,
  "invalid_rows": 3,
  "campaign": {
    "name": "Campaign tháng 1",
    "wordpress_site_id": 1,
    "detected_from_excel": true
  },
  "errors": [
    {"row": 12, "column": "scheduled_at", "message": "Ngày không hợp lệ: '32/01/2025'"},
    {"row": 23, "column": "target_word_count", "message": "Phải là số nguyên: 'abc'"},
    {"row": 31, "column": "featured_image_url", "message": "URL không hợp lệ"}
  ],
  "warnings": [
    {"row": 5, "message": "Keyword trùng với row 18: 'thiết kế website'"},
    {"row": 40, "message": "Thiếu search_intent, dùng mặc định: informational"}
  ],
  "estimated_cost": {
    "serper_calls": 47,
    "serper_cost_usd": 0.047,
    "gemini_tokens_estimated": 940000,
    "gemini_cost_estimated_usd": 3.52,
    "total_estimated_usd": 3.57
  },
  "quota_check": {
    "articles_remaining": 120,
    "budget_remaining_usd": 45.00,
    "can_proceed": true,
    "warning": null
  }
}
```

> **Quan trọng**: Hiển thị `estimated_cost` và `quota_check` TRƯỚC KHI user confirm import. Đây là điểm khác biệt SaaS so với tool đơn giản.

---

## 6. WORDPRESS PUBLISHING — HOÀN THIỆN 100%

### 6.1 Preflight checklist (chạy trước mỗi lần publish)

```php
class WordPressPreflightCheckService
{
    public function runAll(Article $article, WordPressSite $site): PreflightResult
    {
        return new PreflightResult([
            $this->checkConnection($site),
            $this->checkAuthToken($site),
            $this->checkCategoryIds($article, $site),
            $this->checkOrCreateTags($article, $site),
            $this->checkAuthorId($article, $site),
            $this->checkSlugDuplicate($article, $site),
            $this->checkMediaReady($article),
            $this->checkContentNotEmpty($article),
            $this->checkSeoScoreThreshold($article),
        ]);
    }
}
```

### 6.2 Full publishing DTO

```php
class WordPressPublishingDTO
{
    public string $title;
    public string $content;           // HTML
    public string $excerpt;
    public string $status;            // draft/publish/future/private
    public string $slug;
    public int $author;
    public array $categories;         // [1, 2]
    public array $tags;               // [5, 9] — IDs sau khi create/find
    public ?int $featured_media;      // WP media ID
    public ?string $date;             // ISO 8601 cho scheduled
    public string $comment_status;    // open/closed
    public string $ping_status;       // open/closed
    public string $post_type;         // post/page/custom
    public array $meta;               // Yoast/RankMath fields
    public ?string $canonical_url;
}
```

### 6.3 Tag create-or-find service

```php
class WordPressTagService
{
    // Tìm tag theo tên, nếu không có thì tạo mới
    public function findOrCreate(string $tagName, WordPressSite $site): int

    // Batch xử lý mảng tag names
    public function resolveTagNames(array $tagNames, WordPressSite $site): array
}
```

---

## 7. NOTIFICATION SYSTEM — THIẾT KẾ ĐẦY ĐỦ

### Notification types đầy đủ (bao gồm cả cost control)

```php
enum NotificationType: string
{
    // Article lifecycle
    case ARTICLE_REVIEW_REQUESTED = 'article_review_requested';
    case ARTICLE_APPROVED = 'article_approved';
    case ARTICLE_REJECTED = 'article_rejected';
    case ARTICLE_PUBLISHED = 'article_published';
    case ARTICLE_PUBLISH_FAILED = 'article_publish_failed';

    // Campaign lifecycle
    case CAMPAIGN_COMPLETED = 'campaign_completed';
    case CAMPAIGN_FAILED = 'campaign_failed';
    case CAMPAIGN_PAUSED_QUOTA = 'campaign_paused_quota';

    // Quota & cost alerts
    case QUOTA_ARTICLES_WARNING = 'quota_articles_warning';      // 80%
    case QUOTA_ARTICLES_EXCEEDED = 'quota_articles_exceeded';    // 100%
    case QUOTA_COST_WARNING = 'quota_cost_warning';              // 80%
    case QUOTA_COST_EXCEEDED = 'quota_cost_exceeded';            // 100%
    case BILLING_CYCLE_RESET = 'billing_cycle_reset';

    // QA
    case ARTICLE_QA_FAILED = 'article_qa_failed';
    case ARTICLE_QA_PASSED = 'article_qa_passed';

    // System
    case WORDPRESS_CONNECTION_FAILED = 'wordpress_connection_failed';
    case SERPER_QUOTA_WARNING = 'serper_quota_warning';
}
```

---

## 8. FRONTEND PAGES ĐẦY ĐỦ

### Pages cần xây

```
/dashboard                          — Overview với cost summary
/campaigns                          — Campaign list
/campaigns/create                   — Create + Excel upload
/campaigns/{id}                     — Campaign detail
/campaigns/{id}/keywords            — Keyword table
/campaigns/{id}/articles            — Article table với filter
/campaigns/{id}/calendar            — Publish calendar
/campaigns/{id}/internal-links      — Internal link map
/campaigns/{id}/media               — Media manager
/campaigns/{id}/analytics           — Campaign analytics

/articles/{id}/preview              — Article preview + SEO panel
/articles/{id}/approve              — Quick approve page (email link)
/articles/{id}/reject               — Quick reject page (email link)

/analytics/usage                    — Cost & quota dashboard [MỚI]
/analytics/performance              — SEO performance (sau khi publish)

/import/wizard                      — Excel import wizard (6 bước)

/settings/wordpress                 — WordPress sites management
/settings/notifications             — Email + Telegram config
/settings/subscription              — Plan + billing
/settings/quality                   — Quality thresholds config

/admin/tenants                      — Admin: tenant list
/admin/tenants/{id}/usage           — Admin: tenant cost detail [MỚI]
/admin/costs                        — Admin: tổng chi phí AI toàn hệ thống [MỚI]
```

### Import wizard — 7 bước (thêm bước cost estimate)

```
Bước 1: Upload Excel
Bước 2: Map columns (nếu template không chuẩn)
Bước 3: Validate rows (hiển thị lỗi + warnings)
Bước 4: AI Campaign planning preview (CampaignPlannerAgent)
Bước 5: [MỚI] Cost estimate + quota check
Bước 6: Review generated plan + confirm
Bước 7: Start processing
```

---

## 9. TASK ROADMAP HOÀN CHỈNH CHO CODEX

> Format: mỗi task có ID, files liên quan, acceptance criteria, dependencies, commit message.

---

### EPIC A — Cost Control & Quota Management [CRITICAL — LÀM TRƯỚC]

#### TASK BE-COST-001: Subscription plans schema + seeder
**Files:**
- `database/migrations/create_subscription_plans_table.php`
- `database/migrations/create_tenant_subscriptions_table.php`
- `database/seeders/SubscriptionPlanSeeder.php`
- `app/Models/SubscriptionPlan.php`
- `app/Models/TenantSubscription.php`

**Acceptance criteria:**
- 4 plans seeded: starter/professional/agency/enterprise
- Tenant subscription tracked per billing cycle
- articles_used_this_month reset khi qua billing_cycle_end

**Dependencies:** none
**Commit:** `feat: add subscription plans and tenant quota tracking`

---

#### TASK BE-COST-002: AI usage logging
**Files:**
- `database/migrations/create_ai_usage_logs_table.php`
- `app/Models/AiUsageLog.php`
- `app/Services/CostTrackingService.php`
- `config/ai_costs.php`

**Acceptance criteria:**
- Mỗi Gemini call → log tokens + cost_usd vào ai_usage_logs
- Mỗi Serper call → log 1 call + $0.001
- calculateCost() đúng với bảng giá config
- Cost tính theo từng job_type

**Dependencies:** BE-COST-001
**Commit:** `feat: add AI usage logging and cost calculation service`

---

#### TASK BE-COST-003: Quota check middleware + job guard
**Files:**
- `app/Services/QuotaCheckService.php`
- `app/Jobs/Middleware/CheckQuotaBeforeAiJob.php`
- `app/Exceptions/QuotaExceededException.php`

**Acceptance criteria:**
- Trước khi chạy AI job → check articles remaining + cost remaining
- Nếu exceeded → throw QuotaExceededException, job không chạy
- Nếu >= 80% → fire quota_warning notification
- Campaign tự động pause nếu quota exceeded

**Dependencies:** BE-COST-001, BE-COST-002
**Commit:** `feat: add quota check middleware for AI jobs`

---

#### TASK BE-COST-004: Monthly snapshot + reset job
**Files:**
- `database/migrations/create_monthly_usage_snapshots_table.php`
- `app/Models/MonthlyUsageSnapshot.php`
- `app/Jobs/CreateMonthlySnapshotJob.php`
- `app/Console/Commands/ResetMonthlyQuota.php`

**Acceptance criteria:**
- Cuối tháng tự snapshot usage vào monthly_usage_snapshots
- Đầu tháng mới reset articles_used_this_month = 0
- Scheduled via Laravel scheduler

**Dependencies:** BE-COST-002
**Commit:** `feat: add monthly usage snapshots and quota reset`

---

#### TASK BE-COST-005: SERP cache để tiết kiệm credits
**Files:**
- `database/migrations/create_serp_cache_table.php`
- `app/Models/SerpCache.php`
- `app/Services/SerpCacheService.php`

**Acceptance criteria:**
- Trước khi gọi Serper API → check cache theo keyword + language + country
- Cache TTL 24 giờ
- Cache hit → không tính Serper cost
- Cache miss → call API, lưu kết quả

**Dependencies:** none
**Commit:** `feat: add SERP cache to reduce Serper API costs`

---

#### TASK FE-COST-001: Usage & cost dashboard page
**Files:**
- `app/(dashboard)/analytics/usage/page.tsx`
- `components/usage/QuotaProgressBar.tsx`
- `components/usage/CostBreakdownChart.tsx`
- `components/usage/DailyCostChart.tsx`

**Acceptance criteria:**
- Hiển thị articles used / limit (progress bar, đỏ khi >80%)
- Hiển thị cost used / budget (progress bar, đỏ khi >80%)
- Pie chart cost theo job_type
- Line chart daily cost 30 ngày
- Estimated end-of-month cost

**Dependencies:** BE-COST-002
**Commit:** `feat: add usage and cost analytics dashboard`

---

#### TASK FE-COST-002: Cost estimate trong import wizard (bước 5)
**Files:**
- `app/(dashboard)/import/wizard/steps/CostEstimateStep.tsx`

**Acceptance criteria:**
- Hiển thị estimated Serper cost, Gemini cost, total
- Hiển thị quota remaining sau khi chạy campaign này
- Warning nếu campaign sẽ vượt quota
- Nút "Proceed anyway" nếu gần giới hạn

**Dependencies:** FE-COST-001, BE-XLS-003
**Commit:** `feat: add cost estimation step in import wizard`

---

### EPIC B — Replace Zalo with Notification System

#### TASK BE-NOTIF-001: Notifications migration + enums
**Files:**
- `database/migrations/create_notifications_table.php` (replace zalo)
- `app/Enums/NotificationType.php`
- `app/Enums/NotificationChannel.php`
- `app/Models/Notification.php`
- `config/notifications.php`

**Acceptance criteria:**
- Xóa zalo_notifications table
- notifications table có: type, channel, tenant_id, user_id, data JSON, read_at
- Tất cả NotificationType đã liệt kê ở mục 7 đều có trong enum

**Dependencies:** none
**Commit:** `feat: replace Zalo with generic notification system`

---

#### TASK BE-NOTIF-002: Email notification service
**Files:**
- `app/Services/EmailNotificationService.php`
- `app/Mail/ArticleReviewRequestedMail.php`
- `app/Mail/QuotaWarningMail.php`
- `app/Mail/ArticlePublishedMail.php`
- `resources/views/emails/` (Blade templates)

**Acceptance criteria:**
- Email gửi được qua Laravel Mail (SMTP/Mailgun/SES)
- Template có link preview bài viết với public token
- Quota warning email hiển thị current usage vs limit

**Dependencies:** BE-NOTIF-001
**Commit:** `feat: add email notification service with templates`

---

#### TASK BE-NOTIF-003: Telegram notification (optional)
**Files:**
- `app/Services/TelegramNotificationService.php`
- `config/notifications.php` (thêm Telegram config)

**Acceptance criteria:**
- Gửi được Telegram message qua Bot API
- Enable/disable qua TELEGRAM_ENABLED=true/false
- Format message có emoji, markdown

**Dependencies:** BE-NOTIF-001
**Commit:** `feat: add optional Telegram notification channel`

---

#### TASK BE-NOTIF-004: NotificationService + SendNotificationJob
**Files:**
- `app/Services/NotificationService.php`
- `app/Jobs/SendNotificationJob.php`

**Acceptance criteria:**
- NotificationService.send() → dispatch SendNotificationJob
- Job tự chọn channel dựa trên user preference
- Retry 3 lần nếu fail
- Log kết quả vào notifications table

**Dependencies:** BE-NOTIF-002, BE-NOTIF-003
**Commit:** `feat: add unified notification service and job`

---

#### TASK BE-NOTIF-005: Replace Zalo references
**Files:**
- Tìm và replace toàn bộ: ZaloNotificationService, SendZaloNotificationJob, ZaloController, ZaloWebhookService
- `routes/api.php` (xóa Zalo routes)

**Acceptance criteria:**
- Không còn bất kỳ Zalo reference nào trong codebase
- Test toàn bộ notification triggers vẫn hoạt động

**Dependencies:** BE-NOTIF-004
**Commit:** `refactor: remove all Zalo references, replace with NotificationService`

---

### EPIC C — Campaign Management

#### TASK BE-CAMP-001: Campaigns migration/model/repository
**Files:**
- `database/migrations/create_campaigns_table.php`
- `app/Models/Campaign.php`
- `app/Repositories/CampaignRepository.php`
- `app/Enums/CampaignStatus.php`

**Acceptance criteria:**
- Tất cả fields như đã định nghĩa ở mục 4
- CampaignStatus enum: draft/imported/planning/ready/processing/reviewing/publishing/completed/paused/failed
- Soft deletes

**Dependencies:** BE-COST-001
**Commit:** `feat: add campaigns table, model, and repository`

---

#### TASK BE-CAMP-002: Add campaign_id to keywords/articles
**Files:**
- `database/migrations/add_campaign_id_to_keywords_table.php`
- `database/migrations/add_campaign_id_to_articles_table.php`
- `database/migrations/add_new_columns_to_keywords_table.php`
- `database/migrations/add_new_columns_to_articles_table.php`

**Acceptance criteria:**
- keywords.campaign_id → foreign key to campaigns
- articles.campaign_id → foreign key to campaigns
- Tất cả keyword columns mới đã được thêm
- Tất cả article columns mới đã được thêm

**Dependencies:** BE-CAMP-001
**Commit:** `feat: add campaign_id and new SEO columns to keywords/articles`

---

#### TASK BE-CAMP-003: Campaign CRUD API
**Files:**
- `app/Http/Controllers/CampaignController.php`
- `app/Http/Requests/CreateCampaignRequest.php`
- `app/Http/Requests/UpdateCampaignRequest.php`
- `app/Services/CampaignService.php`
- `routes/api.php`

**Endpoints:**
```
GET    /api/campaigns
POST   /api/campaigns
GET    /api/campaigns/{id}
PUT    /api/campaigns/{id}
DELETE /api/campaigns/{id}
POST   /api/campaigns/{id}/pause
POST   /api/campaigns/{id}/resume
POST   /api/campaigns/{id}/start
GET    /api/campaigns/{id}/stats
```

**Dependencies:** BE-CAMP-001, BE-CAMP-002
**Commit:** `feat: add campaign CRUD API endpoints`

---

#### TASK BE-CAMP-004: CampaignStatsService
**Files:**
- `app/Services/CampaignStatsService.php`

**Acceptance criteria:**
- Tính: total keywords, articles generated/review/approved/published/failed
- Tính: total AI cost, average SEO score
- Tính: publish calendar data (bài nào scheduled ngày nào)
- Tính: internal link coverage %
- Tính: media coverage %

**Dependencies:** BE-CAMP-002
**Commit:** `feat: add campaign statistics service`

---

#### TASK BE-CAMP-005: CampaignPlannerAgent
**Files:**
- `app/AI/Agents/CampaignPlannerAgent.php`
- `app/Services/CampaignPlanningService.php`

**Acceptance criteria:**
- Input: toàn bộ keywords của campaign
- Output JSON: campaign_summary, clusters, publish_order, duplicate_intent_groups, internal_link_suggestions, warnings
- Phát hiện keywords trùng intent
- Đề xuất pillar → cluster grouping
- Đề xuất thứ tự publish (pillar pages trước)
- Estimate cost trước khi chạy

**Dependencies:** BE-CAMP-002, BE-COST-002
**Commit:** `feat: add AI campaign planner agent`

---

#### TASK FE-CAMP-001: Campaign list page
**Files:**
- `app/(dashboard)/campaigns/page.tsx`
- `components/campaigns/CampaignCard.tsx`
- `components/campaigns/CampaignFilters.tsx`

**Dependencies:** BE-CAMP-003
**Commit:** `feat: add campaign list page`

---

#### TASK FE-CAMP-002: Campaign detail dashboard
**Files:**
- `app/(dashboard)/campaigns/[id]/page.tsx`
- `components/campaigns/CampaignStatCards.tsx`
- `components/campaigns/CampaignProgressChart.tsx`
- `components/campaigns/PublishCalendar.tsx`

**Dependencies:** BE-CAMP-004
**Commit:** `feat: add campaign detail dashboard with stats`

---

#### TASK FE-CAMP-003: Campaign action controls
**Files:**
- `components/campaigns/CampaignActions.tsx`

**Actions:** Import Excel, Run AI Planner, Start, Pause, Resume, Approve All, Publish Selected, Export Report

**Dependencies:** FE-CAMP-002
**Commit:** `feat: add campaign action controls`

---

### EPIC D — Excel Template v2

#### TASK BE-XLS-001: ExcelTemplateVersion enum + column config
**Files:**
- `app/Enums/ExcelTemplateVersion.php`
- `config/excel_template.php`
- `app/Services/Excel/ExcelColumnConfig.php`

**Dependencies:** none
**Commit:** `feat: add Excel template v2 column configuration`

---

#### TASK BE-XLS-002: Upgrade KeywordParserService
**Files:**
- `app/Services/Excel/KeywordParserService.php` (upgrade)
- `app/Services/Excel/InternalLinkParserService.php` (mới)
- `app/Services/Excel/MediaColumnParserService.php` (mới)

**Acceptance criteria:**
- Đọc tất cả columns v2
- Parse internal_links từ pipe-separated hoặc JSON format
- Parse image_urls từ pipe-separated format
- Map wordpress columns: wp_category_ids, wp_tag_names, wp_slug, etc.

**Dependencies:** BE-XLS-001
**Commit:** `feat: upgrade keyword parser for Excel template v2`

---

#### TASK BE-XLS-003: ExcelImportPreviewService
**Files:**
- `app/Services/Excel/ExcelImportPreviewService.php`
- `app/Http/Controllers/ImportController.php`
- `app/Jobs/ValidateExcelImportJob.php`

**Acceptance criteria:**
- Trả về preview JSON với errors, warnings, estimated_cost, quota_check
- Validate từng row theo rules
- Detect duplicate keywords
- Không commit vào DB cho đến khi user confirm

**Dependencies:** BE-XLS-002, BE-COST-002
**Commit:** `feat: add Excel import preview with validation and cost estimate`

---

#### TASK BE-XLS-004: CampaignImport model + import job
**Files:**
- `database/migrations/create_campaign_imports_table.php`
- `app/Models/CampaignImport.php`
- `app/Jobs/ProcessCampaignImportJob.php`

**Dependencies:** BE-XLS-003, BE-CAMP-001
**Commit:** `feat: add campaign import tracking and processing job`

---

#### TASK BE-XLS-005: Import validation report
**Files:**
- `app/Services/Excel/ImportValidationReportService.php`

**Acceptance criteria:**
- Báo cáo: tổng rows, valid, invalid, warnings, skipped
- Partial import nếu user chọn (chỉ import valid rows)
- Lưu report vào campaign_imports.errors JSON

**Dependencies:** BE-XLS-004
**Commit:** `feat: add import validation report with partial import support`

---

#### TASK FE-XLS-001: Excel import wizard UI (7 bước)
**Files:**
- `app/(dashboard)/import/wizard/page.tsx`
- `components/import/WizardStepper.tsx`
- `components/import/steps/` (7 step components)

**Dependencies:** BE-XLS-005, FE-COST-002
**Commit:** `feat: add 7-step Excel import wizard UI`

---

### EPIC E — Media Pipeline

#### TASK BE-MEDIA-001: media_assets table + model
**Files:**
- `database/migrations/create_media_assets_table.php`
- `app/Models/MediaAsset.php`
- `app/Enums/MediaAssetStatus.php`
- `app/Enums/MediaSourceType.php`

**Dependencies:** none
**Commit:** `feat: add media assets table and model`

---

#### TASK BE-MEDIA-002: MediaParserService
**Files:**
- `app/Services/Media/MediaParserService.php`

**Acceptance criteria:**
- Parse featured_image_url từ Excel row
- Parse image_urls (pipe-separated: url|alt|caption)
- Parse image_generation_prompt nếu có
- Tạo MediaAsset records với status=pending

**Dependencies:** BE-MEDIA-001
**Commit:** `feat: add media parser service for Excel image columns`

---

#### TASK BE-MEDIA-003: DownloadImageJob
**Files:**
- `app/Jobs/DownloadImageJob.php`
- `app/Services/Media/ImageDownloadService.php`

**Acceptance criteria:**
- Download image từ external URL
- Validate: file type (jpg/png/webp), max size 10MB
- Lưu vào local storage: `storage/app/media/{tenant_id}/`
- Update MediaAsset.status = downloaded hoặc failed

**Dependencies:** BE-MEDIA-001
**Commit:** `feat: add image download job with validation`

---

#### TASK BE-MEDIA-004: WordPressMediaService
**Files:**
- `app/Services/WordPress/WordPressMediaService.php`
- `app/Jobs/UploadToWordPressMediaJob.php`

**Acceptance criteria:**
- Upload local image lên WordPress Media Library qua REST API
- Lưu wordpress_media_id + wordpress_media_url
- Set alt_text, caption, description tại WordPress
- Update MediaAsset.status = uploaded

**Dependencies:** BE-MEDIA-003
**Commit:** `feat: add WordPress media upload service`

---

#### TASK BE-MEDIA-005: Attach featured_media to post
**Files:**
- `app/Services/WordPress/WordPressPublishService.php` (update)

**Acceptance criteria:**
- Khi publish post → set featured_media = wordpress_media_id
- Nếu featured image chưa upload → upload trước
- Nếu không có featured image → warning (không block)

**Dependencies:** BE-MEDIA-004
**Commit:** `feat: attach featured media when publishing WordPress post`

---

#### TASK BE-MEDIA-006: Insert images into article HTML
**Files:**
- `app/Services/Media/ArticleImageInserterService.php`

**Acceptance criteria:**
- Sau khi upload media → update article.content HTML
- Thay placeholder bằng WordPress hosted URL
- Chèn ảnh phụ mỗi ~700 từ nếu có available images
- Alt text chứa keyword hoặc semantic phrase

**Dependencies:** BE-MEDIA-005
**Commit:** `feat: insert WordPress media URLs into article HTML`

---

### EPIC F — WordPress Publishing Completion

#### TASK BE-WP-001: Extend PublishingDTO với full WP fields
**Files:**
- `app/DTOs/WordPressPublishingDTO.php` (update)
- `app/Services/WordPress/WordPressPublishService.php` (update)

**Dependencies:** none
**Commit:** `feat: extend WordPress publishing DTO with all post fields`

---

#### TASK BE-WP-002: WordPressPreflightCheckService
**Files:**
- `app/Services/WordPress/WordPressPreflightCheckService.php`
- `app/DTOs/PreflightResult.php`

**Dependencies:** BE-WP-001
**Commit:** `feat: add WordPress preflight check before publishing`

---

#### TASK BE-WP-003: Tag create/find service
**Files:**
- `app/Services/WordPress/WordPressTagService.php`

**Dependencies:** none
**Commit:** `feat: add WordPress tag create-or-find service`

---

#### TASK BE-WP-004: Category/author validation
**Files:**
- `app/Services/WordPress/WordPressCategoryService.php`
- `app/Services/WordPress/WordPressAuthorService.php`

**Dependencies:** none
**Commit:** `feat: add WordPress category and author validation`

---

#### TASK BE-WP-005: Scheduled publishing support
**Files:**
- `app/Jobs/ScheduledPublishJob.php`
- `app/Console/Commands/ProcessScheduledPublishing.php`

**Acceptance criteria:**
- Bài có scheduled_at → set WP post status = future + date
- Laravel scheduler check mỗi phút cho bài đến hạn
- Nếu WP publish thành công → update article.status = published

**Dependencies:** BE-WP-002
**Commit:** `feat: add scheduled WordPress publishing support`

---

#### TASK BE-WP-006: Store publish result metadata
**Files:**
- `app/Services/WordPress/WordPressPublishService.php` (update)
- `database/migrations/add_publish_metadata_to_articles_table.php`

**Fields thêm vào articles:**
```
wordpress_post_id
wordpress_post_url
wordpress_edit_url
published_at
publishing_log_id
```

**Dependencies:** BE-WP-002
**Commit:** `feat: store WordPress publish result URLs and metadata`

---

### EPIC G — SEO QA & Quality Control

#### TASK BE-QA-001: ArticleQualityReport schema + model
**Files:**
- `database/migrations/create_quality_reports_table.php`
- `app/Models/QualityReport.php`
- `app/DTOs/QualityReportDTO.php`

**Dependencies:** none
**Commit:** `feat: add quality report table and model`

---

#### TASK BE-QA-002: Upgrade QAValidationAgent scoring
**Files:**
- `app/AI/Agents/QAValidationAgent.php` (upgrade)
- `app/Services/SEO/SeoScoringService.php`

**Acceptance criteria:**
- 4 scoring groups: SEO (40pt), Readability (30pt), Media (15pt), WP Readiness (15pt)
- Từng check trả về: passed/failed + points
- Total score 0-100
- Blocking errors vs warnings vs suggestions
- Auto-fixable flags

**Dependencies:** BE-QA-001
**Commit:** `feat: upgrade QA scoring with 4 scoring groups (100 point scale)`

---

#### TASK BE-QA-003: SEOAutoFixService
**Files:**
- `app/Services/SEO/SEOAutoFixService.php`

**Auto-fix actions:**
- Generate missing meta title/description (Gemini)
- Insert internal links
- Generate FAQ nếu thiếu
- Rewrite title nếu thiếu keyword
- Add alt text cho images thiếu
- Shorten meta description nếu quá dài

**Dependencies:** BE-QA-002
**Commit:** `feat: add SEO auto-fix service for common issues`

---

#### TASK BE-QA-004: SERPGapAnalysisService
**Files:**
- `app/Services/SEO/SERPGapAnalysisService.php`

**Acceptance criteria:**
- So sánh article content với top 10 competitors
- Xác định: topics covered, topics missed, common FAQs
- Store vào articles.serp_analysis JSON

**Dependencies:** none
**Commit:** `feat: add SERP gap analysis service`

---

#### TASK BE-QA-005: InternalLinkCoverageService
**Files:**
- `app/Services/SEO/InternalLinkCoverageService.php`

**Acceptance criteria:**
- Check bài nào không có link đến/từ
- Orphan detection: bài không có incoming links
- Coverage % cho campaign

**Dependencies:** none
**Commit:** `feat: add internal link coverage analysis`

---

### EPIC H — Article Revision Tracking [MỚI]

#### TASK BE-REV-001: article_revisions table
**Files:**
- `database/migrations/create_article_revisions_table.php`
- `app/Models/ArticleRevision.php`
- `app/Services/ArticleRevisionService.php`
- `app/Observers/ArticleObserver.php`

**Acceptance criteria:**
- Mỗi lần article content thay đổi (auto hoặc manual) → tạo revision
- Lưu: content, word_count, seo_score, changed_by, change_reason
- Max 10 revisions per article (xóa cũ nhất khi vượt)
- API: GET /api/articles/{id}/revisions
- API: POST /api/articles/{id}/revisions/{rev_id}/restore

**Dependencies:** none
**Commit:** `feat: add article revision history and restore`

---

## 10. THỨ TỰ TRIỂN KHAI ĐỀ XUẤT

### Sprint 1 — Foundation (2 tuần)
1. `BE-COST-001` → subscription plans
2. `BE-COST-002` → AI usage logging
3. `BE-COST-003` → quota middleware
4. `BE-NOTIF-001` → notifications migration
5. `BE-CAMP-001` → campaigns table
6. `BE-CAMP-002` → add campaign_id

### Sprint 2 — Excel v2 + Campaign (2 tuần)
1. `BE-COST-005` → SERP cache
2. `BE-XLS-001..005` → Excel v2
3. `BE-CAMP-003..005` → Campaign API + planner
4. `FE-CAMP-001..003` → Campaign pages
5. `FE-XLS-001` → Import wizard

### Sprint 3 — WordPress hoàn chỉnh (2 tuần)
1. `BE-WP-001..006` → WP publishing full
2. `BE-MEDIA-001..006` → Media pipeline
3. `BE-NOTIF-002..005` → Notification system
4. `FE-WP-001..002` → WP settings + logs

### Sprint 4 — QA + Dashboard (2 tuần)
1. `BE-QA-001..005` → SEO QA scoring
2. `BE-REV-001` → Revision tracking
3. `BE-COST-004` → Monthly snapshots
4. `FE-COST-001..002` → Cost dashboard
5. `FE-QA-001..003` → SEO panels

---

## 11. CONSTRAINTS & NOTES CHO CODEX

1. **KHÔNG rewrite** các module đã chạy được: SERP search, Gemini outline/writer, basic WP publish
2. **Mọi AI job PHẢI** gọi `CostTrackingService->logUsage()` sau khi hoàn thành
3. **Mọi AI job PHẢI** có `CheckQuotaBeforeAiJob` middleware
4. **SERP calls** phải check `SerpCacheService` trước khi call API
5. **Import preview** phải hiển thị cost estimate TRƯỚC khi user confirm
6. **Article revisions** tự động tạo khi content thay đổi (dùng Observer)
7. **Tất cả notification** đi qua `NotificationService`, không gọi trực tiếp email/Telegram
8. **Quota exceeded** → campaign tự động pause, gửi notification
9. **Mỗi task** deploy độc lập, không phụ thuộc task chưa merge
10. **Laravel backend** + **Next.js frontend** — không mix

---

## 12. ENVIRONMENT VARIABLES ĐẦY ĐỦ

```env
# AI Providers
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.0-flash
SERPER_API_KEY=

# WordPress
WP_DEFAULT_APP_PASSWORD=
WP_REQUEST_TIMEOUT=30

# Notifications
NOTIFICATION_DEFAULT_CHANNEL=email
NOTIFICATION_EMAIL_ENABLED=true
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
TELEGRAM_ENABLED=false

# Cost control
AI_COST_ALERT_THRESHOLD_PERCENT=80
QUOTA_ALERT_THRESHOLD_PERCENT=80
SERPER_COST_PER_CALL=0.001

# Quality
MIN_SEO_SCORE_TO_PUBLISH=60
AUTO_PUBLISH_SCORE_THRESHOLD=80

# Cache
SERP_CACHE_TTL_HOURS=24
```
