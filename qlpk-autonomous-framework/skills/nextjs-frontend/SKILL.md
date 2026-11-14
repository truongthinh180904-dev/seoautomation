# SKILL.md — Cẩm Nang Phát Triển Frontend Next.js 16

> **Áp dụng cho:** Các thành phần trong thư mục `frontend/`  
> **Tech Stack:** Next.js 16 (App Router), React 19, TypeScript strict, Tailwind CSS v4, shadcn/ui, TanStack Query v5, Zustand  

---

## 1. Cấu Trúc Thư Mục & Phân Tách Giao Diện

Mã nguồn Frontend tổ chức theo cấu trúc hướng tính năng (Feature-based structure):
* `frontend/app/(dashboard)/`: Các route trang thuộc dashboard quản trị.
* `frontend/app/(auth)/`: Các route xác thực (đăng nhập, đăng ký).
* `frontend/components/ui/`: Các nguyên tử giao diện dùng chung (Button, Dialog, Card, Badge, Table...) từ shadcn/ui.
* `frontend/components/features/`: Các component lớn theo domain (`articles/`, `keywords/`, `queue/`, `analytics/`).
* `frontend/hooks/`: Quản lý server-state bằng TanStack Query (`useArticles`, `useKeywords`).
* `frontend/stores/`: Quản lý client-state toàn cục bằng Zustand (`authStore`).

---

## 2. Các Quy Tắc Kỹ Thuật Bắt Buộc

### 2.1 TypeScript Nghiêm Ngặt (Strict Typing)
* Tuyệt đối không dùng kiểu `any`. Định nghĩa đầy đủ interface hoặc DTO trong `types/`.
* Các phản hồi từ API phải được type-safe qua generic:
```typescript
const { data, isLoading } = useQuery<ApiResponse<Article[]>>({
  queryKey: ['articles', filters],
  queryFn: () => get('/articles', filters),
});
```

### 2.2 Quy Chuẩn Bảng Dữ Liệu (Responsive Tables)
Mọi bảng dữ liệu HTML `<table>` bắt buộc phải tuân theo 2 quy tắc:
1. Bọc thẻ `<table>` trong container `div` có class `overflow-x-auto` để bảng không bị tràn vỡ giao diện trên màn hình nhỏ.
2. Các cột ngắn, huy hiệu trạng thái (status badge), ngày tháng hoặc nút thao tác bắt buộc phải có class `whitespace-nowrap`.

```tsx
<div className="w-full overflow-x-auto rounded-xl border border-slate-200">
  <table className="w-full text-left text-sm">
    <thead>
      <tr className="border-b bg-slate-50">
        <th className="p-3 font-semibold">Tiêu đề bài viết</th>
        <th className="p-3 font-semibold whitespace-nowrap">Trạng thái</th>
        <th className="p-3 font-semibold whitespace-nowrap text-right">Thao tác</th>
      </tr>
    </thead>
    <tbody>
      {/* rows */}
    </tbody>
  </table>
</div>
```

### 2.3 Chống Bấm Gửi Đúp (Double-Submit Protection)
Mọi form và nút bấm thực hiện hành động đột biến dữ liệu (Create, Update, Delete, Publish, Start) bắt buộc phải có trạng thái loading và disabled khi đang xử lý:

```tsx
<Button 
  type="submit" 
  disabled={isSubmitting || mutation.isPending}
  className="flex items-center gap-2"
>
  {(isSubmitting || mutation.isPending) && (
    <Loader2 className="h-4 w-4 animate-spin" />
  )}
  {mutation.isPending ? 'Đang xử lý...' : 'Xác nhận tạo'}
</Button>
```

### 2.4 Tránh Nhân Bản Logic UI
Luôn tái sử dụng các component dùng chung (`EmptyState`, `LoadingSpinner`, `StatusBadge`) từ `components/shared/` thay vì tự viết lại markup CSS.
