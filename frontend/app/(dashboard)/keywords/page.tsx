"use client";

import { useState } from 'react';
import { useSearchParams } from 'next/navigation';
import KeywordTable from '@/components/features/keywords/KeywordTable';
import { useKeywords } from '@/hooks/useKeywords';
import { Search, Plus, FileSpreadsheet } from 'lucide-react';
import Link from 'next/link';

export default function KeywordsPage() {
  const searchParams = useSearchParams();
  const campaignId = Number(searchParams.get('campaign_id'));
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const { data, isLoading } = useKeywords(
    page,
    debouncedSearch,
    Number.isFinite(campaignId) && campaignId > 0 ? campaignId : undefined
  );

  return (
    <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">Từ Khoá (Keywords)</h1>
          <p className="text-slate-500 mt-1 font-medium">Quản lý và nạp dữ liệu từ khoá hạt giống cho hệ thống</p>
        </div>
        <div className="flex gap-3 w-full sm:w-auto">
          <Link href="/keywords/import" className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:border-emerald-300 rounded-xl font-bold transition-all active:scale-95 shadow-sm">
            <FileSpreadsheet className="w-4 h-4" />
            Import Excel
          </Link>
          <button className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-bold transition-all active:scale-95 shadow-sm">
            <Plus className="w-4 h-4" />
            Thêm mới
          </button>
        </div>
      </div>

      <div className="bg-white p-3 rounded-2xl shadow-sm border border-slate-200">
        <div className="relative w-full sm:w-[400px]">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
          <input 
            type="text" 
            placeholder="Tìm kiếm từ khoá..." 
            className="w-full pl-12 pr-4 py-3 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-transparent focus:border-blue-500 rounded-xl focus:ring-4 focus:ring-blue-500/10 outline-none transition-all font-medium text-slate-700"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') setDebouncedSearch(search);
            }}
          />
        </div>
      </div>

      <KeywordTable keywords={data?.data || []} isLoading={isLoading} />

      {data?.meta && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between bg-white px-6 py-4 border border-slate-200 rounded-2xl shadow-sm">
          <div className="text-sm font-medium text-slate-500">
            Trang <span className="text-slate-900 bg-slate-100 px-2 py-0.5 rounded">{data.meta.current_page}</span> / {data.meta.last_page}
          </div>
          <div className="flex gap-2">
            <button 
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={page === 1}
              className="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all active:scale-95 shadow-sm"
            >
              Trước
            </button>
            <button 
              onClick={() => setPage(p => Math.min(data.meta.last_page, p + 1))}
              disabled={page === data.meta.last_page}
              className="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all active:scale-95 shadow-sm"
            >
              Sau
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
