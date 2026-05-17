export const ARTICLE_STATUS_CONFIG: Record<string, { label: string; color: string; description: string }> = {
  draft: {
    label: 'Bản nháp',
    color: 'bg-slate-200 text-slate-800',
    description: 'Bài viết đang được soạn thảo',
  },
  review: {
    label: 'Chờ duyệt',
    color: 'bg-blue-200 text-blue-800',
    description: 'Bài viết đang chờ phê duyệt',
  },
  approved: {
    label: 'Đã duyệt',
    color: 'bg-emerald-200 text-emerald-800',
    description: 'Bài viết đã được phê duyệt',
  },
  rejected: {
    label: 'Từ chối',
    color: 'bg-red-200 text-red-800',
    description: 'Bài viết đã bị từ chối',
  },
  publishing: {
    label: 'Đang đăng',
    color: 'bg-yellow-200 text-yellow-800',
    description: 'Bài viết đang được đăng tải',
  },
  published: {
    label: 'Đã đăng',
    color: 'bg-green-200 text-green-800',
    description: 'Bài viết đã được đăng tải thành công',
  },
  failed: {
    label: 'Lỗi',
    color: 'bg-rose-200 text-rose-800',
    description: 'Có lỗi xảy ra trong quá trình xử lý',
  },
};
