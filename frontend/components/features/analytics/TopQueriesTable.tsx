"use client";

import React, { useState } from 'react';
import { Search, ArrowUpDown } from 'lucide-react';
import { useTopQueries, SeoQueryParams } from '@/hooks/useSeoPerformance';

interface TopQueriesTableProps {
  queryParams: SeoQueryParams;
}

export default function TopQueriesTable({ queryParams }: TopQueriesTableProps) {
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState<'clicks' | 'impressions' | 'ctr' | 'position'>('clicks');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');

  const { data, isLoading, isError } = useTopQueries({
    ...queryParams,
    search: searchTerm,
    sort_by: sortBy,
    sort_dir: sortDir,
    limit: 20,
  });

  const queries = data?.data ?? [];

  const handleSort = (column: 'clicks' | 'impressions' | 'ctr' | 'position') => {
    if (sortBy === column) {
      setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
    } else {
      setSortBy(column);
      setSortDir(column === 'position' ? 'asc' : 'desc');
    }
  };

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
      {/* Header & Search */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h3 className="text-base font-extrabold text-slate-900">Từ khóa tìm kiếm hàng đầu</h3>
          <p className="text-xs text-slate-500 mt-0.5">
            Dữ liệu truy vấn trực tiếp từ Google Search Console
          </p>
        </div>

        <div className="relative w-full sm:w-64">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Tìm kiếm từ khóa..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
          />
        </div>
      </div>

      {/* Table responsive container */}
      <div className="overflow-x-auto rounded-xl border border-slate-100">
        <table className="w-full text-left text-xs">
          <thead className="bg-slate-50/80 text-slate-600 font-bold border-b border-slate-200/80">
            <tr>
              <th className="py-3 px-4">Từ khóa (Search Query)</th>
              <th
                onClick={() => handleSort('clicks')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Lượt nhấp
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
              <th
                onClick={() => handleSort('impressions')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Lượt hiển thị
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
              <th
                onClick={() => handleSort('ctr')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  CTR (%)
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
              <th
                onClick={() => handleSort('position')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Vị trí TB
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
            {isLoading ? (
              [...Array(5)].map((_, i) => (
                <tr key={i} className="animate-pulse">
                  <td className="py-3 px-4">
                    <div className="h-4 bg-slate-100 rounded w-48" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-12 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-16 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-12 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-10 ml-auto" />
                  </td>
                </tr>
              ))
            ) : isError ? (
              <tr>
                <td colSpan={5} className="py-8 text-center text-rose-500 font-medium">
                  Đã xảy ra lỗi khi tải danh sách từ khóa.
                </td>
              </tr>
            ) : queries.length === 0 ? (
              <tr>
                <td colSpan={5} className="py-8 text-center text-slate-400 font-medium">
                  Không tìm thấy từ khóa nào trong khoảng thời gian này.
                </td>
              </tr>
            ) : (
              queries.map((q, idx) => (
                <tr key={idx} className="hover:bg-slate-50/60 transition-colors">
                  <td className="py-3 px-4 font-semibold text-slate-900 max-w-xs truncate">
                    {q.query}
                  </td>
                  <td className="py-3 px-4 text-right font-bold text-blue-600 whitespace-nowrap">
                    {q.clicks.toLocaleString()}
                  </td>
                  <td className="py-3 px-4 text-right text-slate-600 whitespace-nowrap">
                    {q.impressions.toLocaleString()}
                  </td>
                  <td className="py-3 px-4 text-right whitespace-nowrap">
                    <span className="px-2 py-0.5 rounded-full font-bold bg-slate-100 text-slate-700">
                      {(q.ctr * 100).toFixed(2)}%
                    </span>
                  </td>
                  <td className="py-3 px-4 text-right font-bold whitespace-nowrap text-slate-800">
                    {q.position.toFixed(1)}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
