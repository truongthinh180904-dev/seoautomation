"use client";

import { useState } from 'react';
import Link from 'next/link';
import { BarChart3, CalendarClock, Loader2, Plus, Search, Target } from 'lucide-react';
import { useCampaigns } from '@/hooks/useCampaigns';
import { Button } from '@/components/ui/button';

const statusLabels: Record<Campaign['status'], string> = {
  draft: 'Nháp',
  imported: 'Đã import',
  planning: 'Đang lập kế hoạch',
  ready: 'Sẵn sàng',
  processing: 'Đang chạy',
  reviewing: 'Chờ duyệt',
  publishing: 'Đang đăng',
  completed: 'Hoàn thành',
  paused: 'Tạm dừng',
  failed: 'Lỗi',
};

export default function CampaignsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const { data, isLoading } = useCampaigns({ page, search: debouncedSearch });
  const campaigns = data?.data ?? [];

  return (
    <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Chiến dịch SEO</h1>
          <p className="mt-1 text-slate-500">Quản lý keyword, bài viết, lịch đăng và chất lượng theo từng campaign</p>
        </div>
        <Link href="/campaigns/create">
          <Button className="rounded-xl bg-blue-600 px-5 hover:bg-blue-700">
            <Plus className="mr-2 h-4 w-4" />
            Tạo campaign
          </Button>
        </Link>
      </div>

      <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div className="relative flex-1">
          <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                setDebouncedSearch(search);
                setPage(1);
              }
            }}
            placeholder="Tìm campaign..."
            className="w-full rounded-xl border border-transparent bg-slate-50 py-2.5 pl-11 pr-4 outline-none transition-all focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
          />
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-60 items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      ) : campaigns.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
          <Target className="mx-auto h-10 w-10 text-slate-300" />
          <h2 className="mt-4 text-lg font-bold text-slate-900">Chưa có chiến dịch SEO</h2>
          <p className="mt-1 text-sm text-slate-500">Tạo campaign đầu tiên rồi import Excel v2 vào campaign đó.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {campaigns.map((campaign) => (
            <Link
              key={campaign.id}
              href={`/campaigns/${campaign.id}`}
              className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-blue-200 hover:shadow-md"
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                      {statusLabels[campaign.status]}
                    </span>
                    {campaign.wordpress_site && (
                      <span className="text-xs font-medium text-slate-400">{campaign.wordpress_site.name}</span>
                    )}
                  </div>
                  <h2 className="mt-3 text-xl font-black text-slate-900 group-hover:text-blue-700">{campaign.name}</h2>
                  <p className="mt-1 line-clamp-2 text-sm text-slate-500">
                    {campaign.description || campaign.content_goal || 'Campaign quản lý content plan SEO và bài viết AI.'}
                  </p>
                </div>
                <Target className="h-6 w-6 shrink-0 text-slate-300 group-hover:text-blue-500" />
              </div>

              <div className="mt-5 grid grid-cols-3 gap-3">
                <div className="rounded-xl bg-slate-50 p-3">
                  <div className="text-xs font-bold uppercase tracking-wide text-slate-400">Keywords</div>
                  <div className="mt-1 text-2xl font-black text-slate-900">{campaign.keywords_count ?? 0}</div>
                </div>
                <div className="rounded-xl bg-slate-50 p-3">
                  <div className="text-xs font-bold uppercase tracking-wide text-slate-400">Articles</div>
                  <div className="mt-1 text-2xl font-black text-slate-900">{campaign.articles_count ?? 0}</div>
                </div>
                <div className="rounded-xl bg-slate-50 p-3">
                  <div className="text-xs font-bold uppercase tracking-wide text-slate-400">Words</div>
                  <div className="mt-1 text-2xl font-black text-slate-900">{campaign.default_word_count}</div>
                </div>
              </div>

              <div className="mt-4 flex items-center gap-4 text-xs font-semibold text-slate-500">
                <span className="flex items-center gap-1.5">
                  <BarChart3 className="h-4 w-4" />
                  SEO dashboard
                </span>
                <span className="flex items-center gap-1.5">
                  <CalendarClock className="h-4 w-4" />
                  Publish calendar
                </span>
              </div>
            </Link>
          ))}
        </div>
      )}

      {data?.meta && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
          <div className="text-sm font-medium text-slate-500">
            Trang <span className="rounded bg-slate-100 px-2 py-0.5 text-slate-900">{data.meta.current_page}</span> / {data.meta.last_page}
          </div>
          <div className="flex gap-2">
            <Button variant="outline" disabled={page === 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>
              Trước
            </Button>
            <Button variant="outline" disabled={page === data.meta.last_page} onClick={() => setPage((value) => Math.min(data.meta.last_page, value + 1))}>
              Sau
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
