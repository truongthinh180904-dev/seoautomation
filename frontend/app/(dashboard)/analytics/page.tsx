"use client";

import React, { useState } from 'react';
import AIUsageChart from '@/components/features/analytics/AIUsageChart';
import PublishingStats from '@/components/features/analytics/PublishingStats';
import SeoPerformanceOverview from '@/components/features/analytics/SeoPerformanceOverview';
import TopQueriesTable from '@/components/features/analytics/TopQueriesTable';
import TopPagesTable from '@/components/features/analytics/TopPagesTable';
import {
  BarChart2,
  Calendar,
  Globe,
  RefreshCw,
  Loader2,
  Search,
  Sparkles,
} from 'lucide-react';
import {
  useSeoPerformanceOverview,
  useTriggerSeoSync,
  useWebsitesList,
  SeoQueryParams,
} from '@/hooks/useSeoPerformance';
import { toast } from 'sonner';

const DATE_RANGES = [
  { label: '7 ngày', value: 7 },
  { label: '28 ngày', value: 28 },
  { label: '90 ngày', value: 90 },
];

export default function AnalyticsPage() {
  const [days, setDays] = useState<number>(28);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [activeTab, setActiveTab] = useState<'seo' | 'ai'>('seo');

  const { data: websitesData } = useWebsitesList();
  const websites = websitesData?.data ?? [];

  // Compute date_from and date_to (YYYY-MM-DD)
  const dateTo = new Date().toISOString().split('T')[0];
  const dateFromObj = new Date();
  dateFromObj.setDate(dateFromObj.getDate() - (days - 1));
  const dateFrom = dateFromObj.toISOString().split('T')[0];

  const queryParams: SeoQueryParams = {
    website_id: selectedWebsiteId,
    date_from: dateFrom,
    date_to: dateTo,
    compare: true,
  };

  const { data: overviewData, isLoading: isLoadingOverview, refetch: refetchOverview } =
    useSeoPerformanceOverview(queryParams, activeTab === 'seo');

  const syncMutation = useTriggerSeoSync();

  const handleSyncNow = () => {
    if (!selectedWebsiteId) {
      if (websites.length > 0) {
        setSelectedWebsiteId(websites[0].id);
        triggerSync(websites[0].id);
      } else {
        toast.error('Vui lòng kết nối ít nhất một Website để đồng bộ dữ liệu.');
      }
      return;
    }
    triggerSync(selectedWebsiteId);
  };

  const triggerSync = (websiteId: number) => {
    syncMutation.mutate(
      {
        website_id: websiteId,
        days: Math.min(days, 28),
      },
      {
        onSuccess: (res) => {
          toast.success(res.message || 'Đã gửi yêu cầu đồng bộ GSC & GA4');
          setTimeout(() => {
            refetchOverview();
          }, 3000);
        },
        onError: (err) => {
          toast.error(`Đồng bộ thất bại: ${err.message}`);
        },
      }
    );
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 pb-12">
      {/* Top Header */}
      <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
            <span className="p-2 bg-blue-100 rounded-xl text-blue-600">
              <BarChart2 className="w-6 h-6" />
            </span>
            SEO Analytics & Performance
          </h1>
          <p className="text-slate-500 mt-1 font-medium">
            Trung tâm dữ liệu lịch sử tìm kiếm Google Search Console, Google Analytics 4 và AI Pipeline
          </p>
        </div>

        {/* Action Controls */}
        <div className="flex flex-wrap items-center gap-3 w-full lg:w-auto">
          {/* Website Selector */}
          <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl px-3 py-1.5 shadow-xs">
            <Globe className="w-4 h-4 text-slate-400" />
            <select
              value={selectedWebsiteId ?? ''}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedWebsiteId(val ? Number(val) : null);
              }}
              className="text-xs font-bold text-slate-700 bg-transparent border-none focus:outline-hidden cursor-pointer"
            >
              <option value="">Tất cả Websites</option>
              {websites.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name || w.domain}
                </option>
              ))}
            </select>
          </div>

          {/* Date range filter */}
          <div className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-2xl p-1 shadow-xs">
            <Calendar className="w-4 h-4 text-slate-400 ml-2" />
            {DATE_RANGES.map((range) => (
              <button
                key={range.value}
                type="button"
                onClick={() => setDays(range.value)}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
                  days === range.value
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-slate-600 hover:bg-slate-100'
                }`}
              >
                {range.label}
              </button>
            ))}
          </div>

          {/* Sync Button (Double-submit protected) */}
          <button
            type="button"
            onClick={handleSyncNow}
            disabled={syncMutation.isPending}
            className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-xs font-extrabold rounded-2xl shadow-xs transition-all cursor-pointer disabled:cursor-not-allowed"
          >
            {syncMutation.isPending ? (
              <>
                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                Đang kích hoạt...
              </>
            ) : (
              <>
                <RefreshCw className="w-3.5 h-3.5 text-blue-400" />
                Đồng bộ ngay
              </>
            )}
          </button>
        </div>
      </div>

      {/* Main Tab Navigation */}
      <div className="flex border-b border-slate-200">
        <button
          type="button"
          onClick={() => setActiveTab('seo')}
          className={`flex items-center gap-2 pb-3.5 px-4 text-sm font-extrabold border-b-2 transition-all cursor-pointer ${
            activeTab === 'seo'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Search className="w-4 h-4" />
          Hiệu suất SEO & Traffic (GSC & GA4)
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('ai')}
          className={`flex items-center gap-2 pb-3.5 px-4 text-sm font-extrabold border-b-2 transition-all cursor-pointer ${
            activeTab === 'ai'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Sparkles className="w-4 h-4" />
          Chi phí AI & Xuất bản (Operation)
        </button>
      </div>

      {/* Active Tab Content */}
      {activeTab === 'seo' ? (
        <div className="space-y-8">
          {/* SEO Performance Overview Cards + Area Chart */}
          <SeoPerformanceOverview
            data={overviewData?.data}
            isLoading={isLoadingOverview}
          />

          {/* Tables Grid: Top Queries & Top Pages */}
          <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <TopQueriesTable queryParams={queryParams} />
            <TopPagesTable queryParams={queryParams} />
          </div>
        </div>
      ) : (
        <div className="space-y-8">
          {/* Summary stats + charts */}
          <PublishingStats days={days} />

          {/* AI Cost Chart */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-extrabold text-slate-900">Chi phí AI theo ngày</h2>
              <span className="text-sm font-medium text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full">
                {days} ngày gần nhất
              </span>
            </div>
            <AIUsageChart days={days} />
          </div>
        </div>
      )}
    </div>
  );
}
