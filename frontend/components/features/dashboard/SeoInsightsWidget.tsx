"use client";

import React, { useState } from 'react';
import {
  Sparkles,
  RefreshCw,
  AlertTriangle,
  Flame,
  TrendingUp,
  ShieldCheck
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
  useSeoInsights,
  useSeoInsightsSummary,
  useTriggerSeoScan,
  SeoInsightItem
} from '@/hooks/useSeoInsights';
import { InsightCard } from './InsightCard';

interface SeoInsightsWidgetProps {
  websiteId?: number;
  maxDisplay?: number;
}

export function SeoInsightsWidget({ websiteId, maxDisplay = 6 }: SeoInsightsWidgetProps) {
  const [activeTab, setActiveTab] = useState<'all' | 'urgent' | 'opportunities' | 'in_progress'>('all');

  const { data: insightsData, isLoading, refetch } = useSeoInsights({
    website_id: websiteId,
    status: activeTab === 'in_progress' ? 'in_progress' : 'active',
    per_page: 30,
  });

  const { data: summaryData } = useSeoInsightsSummary(websiteId);
  const scanMutation = useTriggerSeoScan();

  const summary = summaryData?.data ?? {
    total_active: 0,
    critical_count: 0,
    warning_count: 0,
    info_count: 0,
    opportunity_count: 0,
    resolved_count: 0,
    dismissed_count: 0,
    avg_opportunity_score: 0,
    by_type: {},
  };

  const allItems: SeoInsightItem[] = insightsData?.data ?? [];

  // Filter items according to active tab
  const filteredItems = allItems.filter((item) => {
    if (activeTab === 'urgent') {
      return item.severity === 'critical' || item.severity === 'warning';
    }
    if (activeTab === 'opportunities') {
      return item.type === 'striking_distance' || item.type === 'high_imp_low_ctr' || item.opportunity_score >= 80;
    }
    if (activeTab === 'in_progress') {
      return item.status === 'in_progress';
    }
    return true;
  });

  const displayedItems = filteredItems.slice(0, maxDisplay);

  const handleScanNow = async () => {
    if (!websiteId) return;
    await scanMutation.mutateAsync(websiteId);
    refetch();
  };

  return (
    <div className="space-y-4">
      {/* Widget Header & Metrics Bar */}
      <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20">
              <Sparkles className="h-5 w-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-lg font-black tracking-tight text-slate-900">
                  Cơ Hội & Cảnh Báo SEO Thông Minh
                </h3>
                <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                  0 Credit
                </span>
              </div>
              <p className="text-xs text-slate-500">
                Tự động quét quy luật Heuristics từ dữ liệu GSC, GA4 và SERP Ranking
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={scanMutation.isPending || !websiteId}
              onClick={handleScanNow}
              className="h-9 rounded-xl border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50"
            >
              <RefreshCw
                className={`mr-1.5 h-3.5 w-3.5 ${scanMutation.isPending ? 'animate-spin text-blue-600' : ''}`}
              />
              {scanMutation.isPending ? 'Đang phân tích...' : 'Quét phân tích ngay'}
            </Button>
          </div>
        </div>

        {/* Quick Summary Counter Cards */}
        <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div className="rounded-xl border border-rose-100 bg-rose-50/50 p-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-rose-600">Khẩn cấp</span>
              <AlertTriangle className="h-4 w-4 text-rose-500" />
            </div>
            <p className="mt-1 text-2xl font-black text-rose-700">{summary.critical_count}</p>
            <p className="text-[10px] text-rose-500 font-medium">Tụt hạng & sụt giảm clicks</p>
          </div>

          <div className="rounded-xl border border-amber-100 bg-amber-50/50 p-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-amber-700">Cần lưu ý</span>
              <Flame className="h-4 w-4 text-amber-500" />
            </div>
            <p className="mt-1 text-2xl font-black text-amber-800">{summary.warning_count}</p>
            <p className="text-[10px] text-amber-600 font-medium">Decay, CTR thấp, cannibal</p>
          </div>

          <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Cơ hội vàng</span>
              <TrendingUp className="h-4 w-4 text-emerald-500" />
            </div>
            <p className="mt-1 text-2xl font-black text-emerald-800">{summary.opportunity_count}</p>
            <p className="text-[10px] text-emerald-600 font-medium">Bứt phá Top 3 & bùng nổ CTR</p>
          </div>

          <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-blue-700">Điểm tiềm năng</span>
              <Sparkles className="h-4 w-4 text-blue-500" />
            </div>
            <p className="mt-1 text-2xl font-black text-blue-800">
              {summary.avg_opportunity_score}
              <span className="text-xs font-bold text-blue-500">/100</span>
            </p>
            <p className="text-[10px] text-blue-600 font-medium">Độ chênh lệch tăng trưởng</p>
          </div>
        </div>

        {/* Tab Filters */}
        <div className="mt-5 flex items-center gap-1.5 overflow-x-auto border-t border-slate-100 pt-3">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setActiveTab('all')}
            className={`h-8 rounded-lg px-3 text-xs font-bold ${
              activeTab === 'all'
                ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            Tất cả ({summary.total_active})
          </Button>

          <Button
            variant="ghost"
            size="sm"
            onClick={() => setActiveTab('urgent')}
            className={`h-8 rounded-lg px-3 text-xs font-bold ${
              activeTab === 'urgent'
                ? 'bg-rose-50 text-rose-700 hover:bg-rose-100'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            Cần xử lý gấp ({summary.critical_count + summary.warning_count})
          </Button>

          <Button
            variant="ghost"
            size="sm"
            onClick={() => setActiveTab('opportunities')}
            className={`h-8 rounded-lg px-3 text-xs font-bold ${
              activeTab === 'opportunities'
                ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            Cơ hội tăng trưởng ({summary.opportunity_count})
          </Button>

          <Button
            variant="ghost"
            size="sm"
            onClick={() => setActiveTab('in_progress')}
            className={`h-8 rounded-lg px-3 text-xs font-bold ${
              activeTab === 'in_progress'
                ? 'bg-purple-50 text-purple-700 hover:bg-purple-100'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            Đang xử lý
          </Button>
        </div>
      </div>

      {/* Insights Cards List */}
      <div className="space-y-3">
        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {[1, 2, 3, 4].map((i) => (
              <Card key={i} className="rounded-2xl p-5 border border-slate-100">
                <div className="flex justify-between">
                  <Skeleton className="h-5 w-28 rounded-md" />
                  <Skeleton className="h-5 w-20 rounded-md" />
                </div>
                <Skeleton className="mt-3 h-5 w-3/4 rounded-md" />
                <Skeleton className="mt-2 h-4 w-full rounded-md" />
                <Skeleton className="mt-4 h-8 w-full rounded-xl" />
              </Card>
            ))}
          </div>
        ) : displayedItems.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {displayedItems.map((insight) => (
              <InsightCard key={insight.id} insight={insight} />
            ))}
          </div>
        ) : (
          <Card className="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-8 text-center">
            <CardContent className="flex flex-col items-center justify-center p-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 mb-3">
                <ShieldCheck className="h-6 w-6" />
              </div>
              <h4 className="text-base font-bold text-slate-800">Website đang hoạt động tối ưu!</h4>
              <p className="mt-1 text-xs text-slate-500 max-w-md">
                Không phát hiện từ khóa tụt hạng, traffic decay hay xung đột nội dung trong chu kỳ theo dõi gần nhất.
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
