"use client";

import React, { useState } from 'react';
import KeywordBulkActions from './KeywordBulkActions';
import { useKeywordActions } from '@/hooks/useKeywords';
import { useArticleActions } from '@/hooks/useArticles';
import { Sparkles } from 'lucide-react';

interface KeywordTableProps {
  keywords: Keyword[];
  isLoading: boolean;
}

export default function KeywordTable({ keywords, isLoading }: KeywordTableProps) {
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const { bulkDelete } = useKeywordActions();
  const { generateArticle } = useArticleActions();

  if (isLoading) {
    return <div className="text-center py-16 text-slate-500 animate-pulse font-medium">Đang tải dữ liệu từ khoá...</div>;
  }

  if (!keywords?.length) {
    return (
      <div className="text-center py-16 bg-slate-50 rounded-2xl border border-slate-200 border-dashed">
        <p className="text-slate-500 font-medium">Chưa có từ khoá nào. Hãy import hoặc thêm mới!</p>
      </div>
    );
  }

  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked) {
      setSelectedIds(keywords.map(k => k.id));
    } else {
      setSelectedIds([]);
    }
  };

  const handleSelect = (id: number) => {
    setSelectedIds(prev => 
      prev.includes(id) ? prev.filter(item => item !== id) : [...prev, id]
    );
  };

  const handleBulkDelete = () => {
    if (confirm(`Bạn có chắc muốn xoá ${selectedIds.length} từ khoá đã chọn?`)) {
      bulkDelete.mutate(selectedIds, {
        onSuccess: () => setSelectedIds([])
      });
    }
  };

  const handleGenerateArticle = (keywordId: number) => {
    generateArticle.mutate(keywordId);
  };

  const getStatusBadge = (status: string) => {
    const config: Record<string, string> = {
      pending: 'bg-slate-100 text-slate-700',
      processing: 'bg-blue-100 text-blue-700 animate-pulse',
      completed: 'bg-emerald-100 text-emerald-700',
      failed: 'bg-red-100 text-red-700'
    };
    const c = config[status] || config.pending;
    const label = {
      pending: 'Mới',
      processing: 'Đang AI xử lý',
      completed: 'Hoàn thành',
      failed: 'Lỗi'
    }[status] || status;
    
    return <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${c}`}>{label}</span>;
  };

  return (
    <>
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-100">
            <thead className="bg-slate-50/80 backdrop-blur-sm sticky top-0">
              <tr>
                <th className="px-6 py-4 text-left w-12">
                  <input 
                    type="checkbox" 
                    className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-colors"
                    onChange={handleSelectAll}
                    checked={selectedIds.length === keywords.length && keywords.length > 0}
                  />
                </th>
                <th className="px-6 py-4 text-left text-xs font-extrabold text-slate-500 uppercase tracking-wider">Từ khoá</th>
                <th className="px-6 py-4 text-left text-xs font-extrabold text-slate-500 uppercase tracking-wider">Lượt tìm kiếm (Vol)</th>
                <th className="px-6 py-4 text-left text-xs font-extrabold text-slate-500 uppercase tracking-wider">Trạng thái</th>
                <th className="px-6 py-4 text-left text-xs font-extrabold text-slate-500 uppercase tracking-wider">Bài viết AI</th>
                <th className="px-6 py-4 text-right text-xs font-extrabold text-slate-500 uppercase tracking-wider">Thao tác</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-slate-100">
              {keywords.map((kw) => (
                <tr key={kw.id} className={`${selectedIds.includes(kw.id) ? 'bg-blue-50/60' : 'hover:bg-slate-50/80'} transition-colors group cursor-pointer`} onClick={() => handleSelect(kw.id)}>
                  <td className="px-6 py-4" onClick={(e) => e.stopPropagation()}>
                    <input 
                      type="checkbox" 
                      className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-colors cursor-pointer"
                      checked={selectedIds.includes(kw.id)}
                      onChange={() => handleSelect(kw.id)}
                    />
                  </td>
                  <td className="px-6 py-4 text-sm font-bold text-slate-900">{kw.keyword}</td>
                  <td className="px-6 py-4 text-sm font-medium text-slate-500">{kw.search_volume ? kw.search_volume.toLocaleString() : '-'}</td>
                  <td className="px-6 py-4">
                    {getStatusBadge(kw.status)}
                  </td>
                  <td className="px-6 py-4 text-sm font-medium">
                    {kw.article_id ? (
                      <span className="inline-flex items-center gap-1.5 text-emerald-600">
                        <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Đã có
                      </span>
                    ) : (
                      <span className="text-slate-400">-</span>
                    )}
                  </td>
                  <td className="px-6 py-4 text-right" onClick={(e) => e.stopPropagation()}>
                    <button
                      type="button"
                      onClick={() => handleGenerateArticle(kw.id)}
                      disabled={generateArticle.isPending || kw.status === 'processing'}
                      className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <Sparkles className="h-3.5 w-3.5" />
                      {kw.article_id ? 'Viết lại' : 'AI viết bài'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      
      <KeywordBulkActions 
        selectedIds={selectedIds} 
        onBulkDelete={handleBulkDelete} 
        isDeleting={bulkDelete.isPending} 
      />
    </>
  );
}
