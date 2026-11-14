# SKILL.md — Cẩm Nang Phát Triển Backend Laravel 12/13

> **Áp dụng cho:** Các thành phần trong thư mục `backend/`  
> **Tech Stack:** Laravel 12/13, PHP 8.3, PostgreSQL, Redis, Laravel Horizon, Sanctum  

---

## 1. Kiến Trúc Phân Lớp (Layered Architecture)

Mã nguồn backend tuân thủ nghiêm ngặt mô hình phân tách trách nhiệm:
* **Controllers (`app/Http/Controllers/`):** Luôn giữ Controller mỏng (Thin Controller). Chỉ nhận request, ủy thác xử lý cho Service và trả về API Resource / JsonResponse.
* **Form Requests (`app/Http/Requests/`):** Mọi dữ liệu đầu vào bắt buộc phải qua Form Request chuyên biệt. Không validate bằng `$request->validate()` trong Controller.
* **Services (`app/Services/`):** Nơi chứa 100% logic nghiệp vụ (Business Logic).
* **Repositories (`app/Repositories/`):** Đóng gói logic truy vấn CSDL phức tạp.
* **Jobs & Queues (`app/Jobs/`):** Tách biệt các tác vụ tốn thời gian (gọi LLM, cào SERP, tải/tối ưu ảnh, push WordPress) thành các Job không đồng bộ.

---

## 2. Các Quy Tắc Kỹ Thuật Bắt Buộc

### 2.1 Bọc Giao Dịch Bằng `DB::transaction(...)`
Khi thực hiện các thao tác ghi dữ liệu ảnh hưởng từ 2 bảng trở lên (ví dụ: tạo bài viết + lưu metadata + trừ tín dụng quota), bắt buộc phải bọc trong transaction:
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($articleData, $tenantId) {
    $article = $this->articleRepo->create($articleData);
    $this->costTracking->incrementArticleCount($tenantId);
    return $article;
});
```

### 2.2 Chống Lỗi N+1 Query (Luôn Eager Load)
Tuyệt đối không duyệt vòng lặp rồi gọi quan hệ Eloquent trực tiếp:
```php
// SAI (Gây ra N+1 queries):
$articles = Article::all();
foreach ($articles as $a) {
    echo $a->wordpressSite->name;
}

// ĐÚNG:
$articles = Article::with(['wordpressSite:id,name,url', 'keyword:id,keyword'])->get();
```

### 2.3 Cô Lập Dữ Liệu Multi-Tenancy Trong Queue Jobs
Khi truyền model vào Job, bắt buộc truyền tường minh `tenant_id`:
```php
class GenerateArticleJob implements ShouldQueue
{
    public function __construct(
        public int $articleId,
        public int $tenantId
    ) {}
}
```
Trong hàm `handle()`, luôn kiểm tra model có thuộc đúng `tenant_id` được cấp phép hay không.

### 2.4 Chuẩn Hóa Code Style Bằng Laravel Pint
Trước khi nộp code, Coder phải đảm bảo code thỏa mãn kiểm tra của Laravel Pint:
```bash
./vendor/bin/pint --test
```
Không để lại cú pháp không đồng nhất, thụt lề sai hoặc unused imports.
