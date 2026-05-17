import React from 'react';

type ArticleStatus = 'draft' | 'review' | 'approved' | 'rejected' | 'publishing' | 'published' | 'failed';

const statusConfig: Record<ArticleStatus, { label: string, classes: string }> = {
  draft: { label: 'Bản nháp', classes: 'bg-slate-100 text-slate-800 border-slate-200' },
  review: { label: 'Chờ duyệt', classes: 'bg-blue-50 text-blue-700 border-blue-200 animate-pulse' },
  approved: { label: 'Đã duyệt', classes: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  rejected: { label: 'Từ chối', classes: 'bg-orange-50 text-orange-700 border-orange-200' },
  publishing: { label: 'Đang đăng', classes: 'bg-purple-50 text-purple-700 border-purple-200' },
  published: { label: 'Đã đăng', classes: 'bg-green-50 text-green-700 border-green-200' },
  failed: { label: 'Thất bại', classes: 'bg-red-50 text-red-700 border-red-200' },
};

export default function ArticleStatusBadge({ status }: { status: ArticleStatus | string }) {
  const config = statusConfig[status as ArticleStatus] || { label: status, classes: 'bg-gray-100 text-gray-800' };
  
  return (
    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${config.classes}`}>
      {config.label}
    </span>
  );
}
