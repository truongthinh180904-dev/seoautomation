"use client";

import React, { useState } from 'react';
import { Search, ExternalLink, ArrowUpDown } from 'lucide-react';
import { useTopPages, SeoQueryParams } from '@/hooks/useSeoPerformance';

interface TopPagesTableProps {
  queryParams: SeoQueryParams;
}

export default function TopPagesTable({ queryParams }: TopPagesTableProps) {
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState<
    'clicks' | 'impressions' | 'ctr' | 'position' | 'sessions' | 'active_users' | 'engagement_rate' | 'conversions'
  >('clicks');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');

  const { data, isLoading, isError } = useTopPages({
    ...queryParams,
    search: searchTerm,
    sort_by: sortBy,
    sort_dir: sortDir,
    limit: 20,
  });

  const pages = data?.data ?? [];

  const handleSort = (
    column: 'clicks' | 'impressions' | 'ctr' | 'position' | 'sessions' | 'active_users' | 'engagement_rate' | 'conversions'
  ) => {
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
          <h3 className="text-base font-extrabold text-slate-900">Trang hiệu suất hàng đầu</h3>
          <p className="text-xs text-slate-500 mt-0.5">
            Tổng hợp dữ liệu kết hợp Google Search Console và Google Analytics 4
          </p>
        </div>

        <div className="relative w-full sm:w-72">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Tìm theo đường dẫn URL..."
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
              <th className="py-3 px-4">Đường dẫn trang đích</th>
              <th
                onClick={() => handleSort('clicks')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Nhấp (GSC)
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
              <th
                onClick={() => handleSort('impressions')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Hiển thị (GSC)
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
              <th
                onClick={() => handleSort('sessions')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Phiên (GA4)
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
              <th
                onClick={() => handleSort('engagement_rate')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Tương tác
                  <ArrowUpDown className="w-3 h-3 text-slate-400" />
                </span>
              </th>
              <th
                onClick={() => handleSort('conversions')}
                className="py-3 px-4 text-right cursor-pointer hover:text-blue-600 whitespace-nowrap"
              >
                <span className="inline-flex items-center gap-1">
                  Chuyển đổi
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
                    <div className="h-4 bg-slate-100 rounded w-64" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-12 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-16 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-10 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-12 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-12 ml-auto" />
                  </td>
                  <td className="py-3 px-4 text-right">
                    <div className="h-4 bg-slate-100 rounded w-8 ml-auto" />
                  </td>
                </tr>
              ))
            ) : isError ? (
              <tr>
                <td colSpan={7} className="py-8 text-center text-rose-500 font-medium">
                  Đã xảy ra lỗi khi tải danh sách trang.
                </td>
              </tr>
            ) : pages.length === 0 ? (
              <tr>
                <td colSpan={7} className="py-8 text-center text-slate-400 font-medium">
                  Chưa có dữ liệu trang nào trong khoảng thời gian này.
                </td>
              </tr>
            ) : (
              pages.map((p, idx) => (
                <tr key={idx} className="hover:bg-slate-50/60 transition-colors">
                  <td className="py-3 px-4 font-semibold text-slate-900 max-w-sm truncate">
                    <span className="inline-flex items-center gap-1.5 group">
                      <span className="truncate">{p.page_url}</span>
                      {p.page_url.startsWith('http') && (
                        <a
                          href={p.page_url}
                          target="_blank"
                          rel="noreferrer"
                          className="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-blue-600 transition-opacity"
                        >
                          <ExternalLink className="w-3 h-3" />
                        </a>
                      )}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-right font-bold text-blue-600 whitespace-nowrap">
                    {p.clicks.toLocaleString()}
                  </td>
                  <td className="py-3 px-4 text-right text-slate-600 whitespace-nowrap">
                    {p.impressions.toLocaleString()}
                  </td>
                  <td className="py-3 px-4 text-right font-semibold whitespace-nowrap text-slate-800">
                    {p.position > 0 ? p.position.toFixed(1) : '--'}
                  </td>
                  <td className="py-3 px-4 text-right font-bold text-cyan-700 whitespace-nowrap">
                    {p.sessions.toLocaleString()}
                  </td>
                  <td className="py-3 px-4 text-right whitespace-nowrap">
                    <span className="px-2 py-0.5 rounded-full font-bold bg-slate-100 text-slate-700">
                      {(p.engagement_rate * 100).toFixed(1)}%
                    </span>
                  </td>
                  <td className="py-3 px-4 text-right font-bold text-emerald-600 whitespace-nowrap">
                    {p.conversions > 0 ? p.conversions.toLocaleString() : '0'}
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
