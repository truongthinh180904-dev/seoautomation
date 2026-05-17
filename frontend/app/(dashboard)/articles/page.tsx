"use client";

import { useState } from 'react';
import ArticleTable from '@/components/features/articles/ArticleTable';
import { useArticles } from '@/hooks/useArticles';
import { Search, Filter, Plus } from 'lucide-react';

export default function ArticlesPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const { data, isLoading } = useArticles({
    page,
    status,
    search: debouncedSearch,
  });

  return (
    <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">Bài viết AI</h1>
          <p className="text-slate-500 mt-1">Theo dõi tiến độ, chi phí và chất lượng SEO</p>
        </div>
        <button className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm active:scale-95">
          <Plus className="w-4 h-4" />
          Tạo bài viết mới
        </button>
      </div>

      <div className="bg-white p-3 rounded-2xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-3 items-center">
        <div className="relative flex-1 w-full">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input 
            type="text" 
            placeholder="Tìm theo từ khoá hoặc tiêu đề..." 
            className="w-full pl-11 pr-4 py-2.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-transparent focus:border-blue-500 rounded-xl focus:ring-4 focus:ring-blue-500/10 outline-none transition-all"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') setDebouncedSearch(search);
            }}
          />
        </div>
        
        <div className="flex items-center gap-2 w-full sm:w-auto relative">
          <div className="absolute left-4 pointer-events-none">
             <Filter className="w-4 h-4 text-slate-400" />
          </div>
          <select 
            className="w-full sm:w-56 pl-10 pr-4 py-2.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-transparent focus:border-blue-500 rounded-xl focus:ring-4 focus:ring-blue-500/10 outline-none transition-all font-medium text-slate-700 cursor-pointer appearance-none"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Tất cả trạng thái</option>
            <option value="draft">Bản nháp</option>
            <option value="review">Chờ duyệt</option>
            <option value="approved">Đã duyệt</option>
            <option value="publishing">Đang đăng</option>
            <option value="published">Đã đăng</option>
            <option value="failed">Thất bại</option>
          </select>
        </div>
      </div>

      <ArticleTable articles={data?.data || []} isLoading={isLoading} />

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
