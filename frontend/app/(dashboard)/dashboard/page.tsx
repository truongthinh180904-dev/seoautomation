"use client";

import React, { useState } from 'react';
import { 
  FileText, 
  Plus, 
  ArrowRight, 
  Zap, 
  CheckCircle2, 
  Award, 
  Wallet, 
  Building, 
  RefreshCw, 
  Coins, 
  Globe, 
  PieChart as PieChartIcon, 
  BarChart3, 
  CreditCard,
  Layers,
  Search,
  AlertTriangle
} from 'lucide-react';
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import Link from 'next/link';
import { useAuth } from '@/hooks/useAuth';
import { useAnalyticsSummary, useUsageAnalytics, useAICosts } from '@/hooks/useAnalytics';
import { useCurrentSubscription } from '@/hooks/useBilling';
import { 
  PieChart, 
  Pie, 
  Cell, 
  Tooltip, 
  Legend, 
  ResponsiveContainer, 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid,
  AreaChart,
  Area 
} from 'recharts';

const STATUS_COLORS: Record<string, string> = {
  draft: '#94a3b8',
  review: '#3b82f6',
  approved: '#10b981',
  publishing: '#a855f7',
  published: '#22c55e',
  rejected: '#f97316',
  failed: '#ef4444',
};

const STATUS_LABELS: Record<string, string> = {
  draft: 'Bản nháp',
  review: 'Chờ duyệt',
  approved: 'Đã duyệt',
  publishing: 'Đang đăng',
  published: 'Đã đăng',
  rejected: 'Từ chối',
  failed: 'Lỗi',
};

function QuotaProgress({
  title,
  used,
  limit,
  prefix = '',
  suffix = '',
  colorClass = 'from-blue-500 to-indigo-600',
  bgClass = 'bg-blue-50/50'
}: {
  title: string;
  used: number;
  limit: number | null;
  prefix?: string;
  suffix?: string;
  colorClass?: string;
  bgClass?: string;
}) {
  const percent = limit && limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;
  const isWarning = percent >= 80;

  return (
    <div className={`rounded-2xl border border-slate-100 ${bgClass} p-5 backdrop-blur-sm transition-all duration-300 hover:shadow-sm`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">{title}</p>
          <p className="mt-2 text-2xl font-black text-slate-800">
            {prefix}{used.toLocaleString()}{suffix}
            <span className="text-sm font-bold text-slate-400">
              {' / '}
              {limit === null ? 'Không giới hạn' : `${prefix}${limit.toLocaleString()}${suffix}`}
            </span>
          </p>
        </div>
        <div className={`rounded-full px-2 py-0.5 text-[10px] font-black tracking-wide ${isWarning ? 'bg-rose-100 text-rose-700 animate-pulse' : 'bg-slate-100 text-slate-600'}`}>
          {percent}%
        </div>
      </div>
      <div className="mt-4 h-2.5 rounded-full bg-slate-200/60 overflow-hidden">
        <div
          className={`h-2.5 rounded-full bg-gradient-to-r ${isWarning ? 'from-rose-500 to-red-600' : colorClass} transition-all duration-500`}
          style={{ width: `${limit === null ? 100 : percent}%` }}
        />
      </div>
    </div>
  );
}

export default function DashboardPage() {
  const { user } = useAuth();
  const [days, setDays] = useState(30);

  const summary = useAnalyticsSummary(days);
  const usage = useUsageAnalytics();
  const subQuery = useCurrentSubscription();
  const aiCostsQuery = useAICosts(days);

  const handleRefresh = () => {
    summary.refetch();
    usage.refetch();
    subQuery.refetch();
    aiCostsQuery.refetch();
  };

  const isAnyError = summary.isError || usage.isError || subQuery.isError || aiCostsQuery.isError;
  const isInitialLoading = summary.isLoading && !summary.data;

  const currentPlan = subQuery.data?.plan?.name || usage.data?.subscription?.plan?.name || 'Free';
  const availableCredits = subQuery.data?.balance?.available_credits ?? 0;
  const articlesUsed = usage.data?.quota?.articlesUsed ?? 0;
  const articlesLimit = usage.data?.quota?.articlesLimit ?? 100;
  const costUsed = usage.data?.quota?.costUsedUsd ?? 0;
  const costLimit = usage.data?.quota?.costLimitUsd ?? 20.0;

  // Chart 1: Donut data
  const donutData = Object.entries(summary.data?.by_status ?? {})
    .map(([status, count]) => ({
      name: STATUS_LABELS[status] ?? status,
      value: Number(count),
      color: STATUS_COLORS[status] ?? '#cbd5e1',
    }))
    .filter((d) => d.value > 0);

  // Chart 2: Credit consumption daily
  const creditDailyData = (summary.data?.credit_history_daily ?? []).map((row) => ({
    date: row.date.slice(5),
    credits: row.credits_used,
    txs: row.tx_count,
  }));

  // Chart 3: AI Costs daily
  const aiCostData = ((aiCostsQuery.data?.data ?? []) as Array<{ date?: string; total_cost?: number; calls?: number }>).map((row) => ({
    date: row.date ? row.date.slice(5) : '',
    cost: Number(Number(row.total_cost || 0).toFixed(4)),
    calls: Number(row.calls || 0),
  }));

  // Table: AI costs by Website
  const websiteCosts = summary.data?.ai_costs_by_website ?? [];

  const stats = [
    { 
      title: 'Số dư Credit', 
      value: subQuery.isLoading ? '...' : availableCredits.toLocaleString(), 
      icon: Coins, 
      color: 'text-amber-600', 
      bg: 'bg-amber-50/70 border-amber-100',
      description: 'Khả dụng cho bài viết & AI'
    },
    { 
      title: 'Tổng bài viết', 
      value: summary.isLoading ? '...' : String(summary.data?.total ?? 0), 
      icon: FileText, 
      color: 'text-blue-600', 
      bg: 'bg-blue-50/70 border-blue-100',
      description: 'Tổng số bài viết đã tạo'
    },
    { 
      title: 'Tổng từ khóa', 
      value: summary.isLoading ? '...' : String(summary.data?.total_keywords ?? 0), 
      icon: Search, 
      color: 'text-cyan-600', 
      bg: 'bg-cyan-50/70 border-cyan-100',
      description: 'Từ khóa hạt giống đã nạp'
    },
    { 
      title: 'Tổng chiến dịch', 
      value: summary.isLoading ? '...' : String(summary.data?.total_campaigns ?? 0), 
      icon: Layers, 
      color: 'text-indigo-600', 
      bg: 'bg-indigo-50/70 border-indigo-100',
      description: 'Chiến dịch đang quản lý'
    },
    { 
      title: 'Điểm SEO TB', 
      value: summary.isLoading ? '...' : `${summary.data?.avg_seo ?? 0}/100`, 
      icon: Award, 
      color: 'text-emerald-600', 
      bg: 'bg-emerald-50/70 border-emerald-100',
      description: 'Độ tối ưu SEO trung bình'
    },
    { 
      title: 'Chi phí AI tiêu thụ', 
      value: summary.isLoading ? '...' : `$${(summary.data?.total_cost || 0).toFixed(4)}`, 
      icon: Wallet, 
      color: 'text-purple-600', 
      bg: 'bg-purple-50/70 border-purple-100',
      description: 'Ngân sách API đã tiêu thụ'
    },
  ];

  return (
    <div className="space-y-8 max-w-[1280px] mx-auto animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      {/* Error Alert State */}
      {isAnyError && (
        <div className="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-rose-900 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <AlertTriangle className="h-5 w-5 text-rose-600 shrink-0" />
            <div>
              <p className="text-sm font-bold">Không thể tải dữ liệu thống kê bảng điều khiển</p>
              <p className="text-xs text-rose-600">Đã xảy ra lỗi khi lấy dữ liệu. Vui lòng thử lại.</p>
            </div>
          </div>
          <Button 
            variant="outline" 
            size="sm" 
            onClick={handleRefresh}
            className="rounded-xl border-rose-300 bg-white text-rose-700 hover:bg-rose-100 text-xs font-bold"
          >
            <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Thử lại
          </Button>
        </div>
      )}

      {/* Welcome Header - White & Sky Blue Theme */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gradient-to-br from-sky-500 via-sky-600 to-blue-600 text-white p-8 rounded-3xl border border-sky-400/50 shadow-lg shadow-sky-200/60 overflow-hidden relative group">
        <div className="relative z-10 max-w-2xl space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-white/20 text-white border border-white/30 uppercase tracking-widest backdrop-blur-xs">
            <Zap className="w-3.5 h-3.5" /> AuraSEO AI Engine Active
          </span>
          <h1 className="text-3xl md:text-4xl font-black text-white tracking-tight">
            Chào mừng, {user?.name || 'Admin'}! 👋
          </h1>
          <p className="text-sky-50 text-sm md:text-base font-medium leading-relaxed">
            Hệ thống tự động hóa nội dung và tối ưu hóa SEO của bạn đã sẵn sàng. Bạn đang có{' '}
            <strong className="text-amber-200 font-extrabold underline decoration-amber-300/60 underline-offset-4">
              {availableCredits.toLocaleString()} Credits
            </strong>{' '}
            khả dụng để tạo bài viết và tối ưu SEO.
          </p>
          <div className="flex flex-wrap gap-3 pt-3">
            <Button asChild className="rounded-xl px-5 py-5 font-extrabold bg-white text-sky-700 hover:bg-sky-50 border-none shadow-md transition-all hover:scale-[1.02] cursor-pointer">
              <Link href="/chatbot">
                <Zap className="w-4 h-4 mr-2 text-sky-600" />
                Mở Trợ Lý AI Viết Bài
              </Link>
            </Button>
            <Button asChild variant="outline" className="rounded-xl px-5 py-5 font-bold border-white/40 bg-white/15 text-white hover:bg-white/25 hover:text-white transition-all hover:scale-[1.02] cursor-pointer">
              <Link href="/keywords/import">
                <Plus className="w-4 h-4 mr-2" />
                Nhập Từ Khóa
              </Link>
            </Button>
            <Button asChild variant="outline" className="rounded-xl px-5 py-5 font-bold border-white/40 bg-white/15 text-white hover:bg-white/25 hover:text-white transition-all hover:scale-[1.02] cursor-pointer">
              <Link href="/settings/subscription">
                <CreditCard className="w-4 h-4 mr-2" /> Nạp Credit & Gói Cước
              </Link>
            </Button>
            <Button 
              variant="ghost" 
              size="icon" 
              onClick={handleRefresh}
              className="rounded-xl border border-white/30 bg-white/15 text-white hover:bg-white/25 hover:text-white cursor-pointer"
              title="Làm mới dữ liệu"
            >
              <RefreshCw className={`w-4 h-4 ${summary.isFetching || subQuery.isFetching ? 'animate-spin' : ''}`} />
            </Button>
          </div>
        </div>
        <div className="absolute right-0 top-0 bottom-0 w-1/3 bg-gradient-to-l from-white/15 to-transparent opacity-60 hidden md:block" />
        <div className="w-56 h-56 rounded-full bg-white/15 blur-3xl absolute -right-6 -bottom-6 hidden md:block" />
      </div>

      {/* Plan & Quota Usage Tracker Widget */}
      <div className="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm space-y-6">
        <div className="flex flex-wrap justify-between items-center gap-4">
          <div className="flex items-center gap-3">
            <div className="rounded-2xl bg-indigo-50 p-3.5 text-indigo-600 border border-indigo-100">
              <Building className="h-6 w-6" />
            </div>
            <div>
              <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Gói dịch vụ đang kích hoạt</p>
              <div className="flex items-center gap-2 mt-1">
                <span className="text-xl font-black capitalize text-slate-800">{currentPlan}</span>
                <span className="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black bg-indigo-100 text-indigo-800 uppercase tracking-wider">
                  Active
                </span>
              </div>
            </div>
          </div>
          <Link href="/settings/subscription">
            <Button variant="ghost" className="text-xs font-bold text-indigo-600 hover:bg-indigo-50 rounded-xl cursor-pointer">
              Quản lý gói & Lịch sử Credit <ArrowRight className="w-3.5 h-3.5 ml-1" />
            </Button>
          </Link>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          <QuotaProgress
            title="Số bài viết tháng này"
            used={articlesUsed}
            limit={articlesLimit}
            colorClass="from-emerald-500 to-teal-600"
            bgClass="bg-emerald-50/20 border-emerald-100/50"
          />
          <QuotaProgress
            title="Ngân sách AI đã dùng"
            used={costUsed}
            limit={costLimit}
            prefix="$"
            colorClass="from-amber-500 to-orange-600"
            bgClass="bg-amber-50/20 border-amber-100/50"
          />
        </div>
      </div>

      {/* Section Header with Date Range Filter (TASK-07.2) */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-2">
        <div>
          <h2 className="text-xl font-black text-slate-900 tracking-tight">Số liệu & Hiệu suất tổng quan</h2>
          <p className="text-xs text-slate-500 font-medium">Thống kê chi tiết theo thời gian thực về bài viết, từ khóa, chiến dịch và chi phí AI</p>
        </div>
        <div className="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-xs">
          {[7, 30, 90].map((d) => (
            <button
              key={d}
              onClick={() => setDays(d)}
              className={`rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer ${
                days === d
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'text-slate-600 hover:bg-slate-100'
              }`}
            >
              {d} ngày
            </button>
          ))}
        </div>
      </div>

      {/* Stats Grid - 6 KPI cards (TASK-07.1) */}
      {isInitialLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <Card key={i} className="border-slate-200/80 shadow-sm rounded-2xl bg-white p-5">
              <Skeleton className="h-3 w-20 mb-3" />
              <Skeleton className="h-8 w-24 mb-2" />
              <Skeleton className="h-3 w-28" />
            </Card>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
          {stats.map((stat, i) => (
            <Card key={i} className="border-slate-200/80 shadow-sm rounded-2xl overflow-hidden hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 bg-white">
              <CardContent className="p-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{stat.title}</p>
                    <h3 className="text-2xl font-black text-slate-800 mt-2">{stat.value}</h3>
                    <p className="text-[11px] font-medium text-slate-400 mt-1">{stat.description}</p>
                  </div>
                  <div className={`p-3 rounded-2xl border ${stat.bg} ${stat.color}`}>
                    <stat.icon className="w-5 h-5" />
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Recharts Analytics Section (TASK-07.2) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        {/* Biểu đồ 1: Trạng thái bài viết (Donut Chart) */}
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <div className="rounded-xl bg-blue-50 p-2 text-blue-600">
                <PieChartIcon className="w-5 h-5" />
              </div>
              <h2 className="text-lg font-black text-slate-900">Bài viết theo trạng thái</h2>
            </div>
            <span className="text-xs font-bold text-slate-400">Dữ liệu thực tế</span>
          </div>

          {summary.isLoading ? (
            <div className="h-64 flex flex-col items-center justify-center gap-3">
              <Skeleton className="h-44 w-44 rounded-full" />
              <Skeleton className="h-4 w-32" />
            </div>
          ) : donutData.length > 0 ? (
            <ResponsiveContainer width="100%" height={260}>
              <PieChart>
                <Pie data={donutData} cx="50%" cy="50%" innerRadius={65} outerRadius={100} paddingAngle={4} dataKey="value">
                  {donutData.map((entry, idx) => (
                    <Cell key={idx} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip formatter={(val: unknown) => [`${val} bài viết`, 'Số lượng']} />
                <Legend iconType="circle" iconSize={10} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-64 flex flex-col items-center justify-center text-slate-400">
              <FileText className="w-10 h-10 mb-2 opacity-30" />
              <p className="text-sm font-medium">Chưa có bài viết nào được tạo.</p>
            </div>
          )}
        </div>

        {/* Biểu đồ 2: Tiêu thụ Credit theo ngày (Bar Chart) */}
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <div className="rounded-xl bg-amber-50 p-2 text-amber-600">
                <Coins className="w-5 h-5" />
              </div>
              <h2 className="text-lg font-black text-slate-900">Tiêu thụ Credit theo ngày</h2>
            </div>
            <span className="text-xs font-bold text-slate-400">{days} ngày gần nhất</span>
          </div>

          {summary.isLoading ? (
            <div className="h-64 flex flex-col items-center justify-center gap-2">
              <Skeleton className="h-44 w-full rounded-xl" />
              <Skeleton className="h-4 w-48" />
            </div>
          ) : creditDailyData.length > 0 ? (
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={creditDailyData} margin={{ top: 10, right: 10, left: -10, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" vertical={false} />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                <YAxis tick={{ fontSize: 11, fill: '#94a3b8' }} />
                <Tooltip formatter={(val: unknown) => [`${val} credit`, 'Đã dùng']} />
                <Bar dataKey="credits" fill="#f59e0b" radius={[6, 6, 0, 0]} name="Credit đã dùng" />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-64 flex flex-col items-center justify-center text-slate-400">
              <Coins className="w-10 h-10 mb-2 opacity-30" />
              <p className="text-sm font-medium">Chưa phát sinh giao dịch tiêu thụ credit.</p>
            </div>
          )}
        </div>

      </div>

      {/* Biểu đồ 3: Chi phí AI theo ngày & Website */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Chi phí AI theo ngày */}
        <div className="lg:col-span-2 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <div className="rounded-xl bg-emerald-50 p-2 text-emerald-600">
                <BarChart3 className="w-5 h-5" />
              </div>
              <h2 className="text-lg font-black text-slate-900">Chi phí AI thực tế theo ngày (USD)</h2>
            </div>
            <span className="text-xs font-bold text-slate-400">LLM & SERP API ({days} ngày)</span>
          </div>

          {aiCostsQuery.isLoading ? (
            <div className="h-64 flex flex-col items-center justify-center gap-2">
              <Skeleton className="h-44 w-full rounded-xl" />
              <Skeleton className="h-4 w-48" />
            </div>
          ) : aiCostData.length > 0 ? (
            <ResponsiveContainer width="100%" height={260}>
              <AreaChart data={aiCostData} margin={{ top: 10, right: 10, left: -10, bottom: 0 }}>
                <defs>
                  <linearGradient id="aiCostGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.2} />
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" vertical={false} />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                <YAxis tick={{ fontSize: 11, fill: '#94a3b8' }} tickFormatter={(v) => `$${v}`} />
                <Tooltip formatter={(val: unknown) => [`$${val}`, 'Chi phí']} />
                <Area type="monotone" dataKey="cost" stroke="#10b981" strokeWidth={2} fill="url(#aiCostGrad)" name="USD" />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-64 flex flex-col items-center justify-center text-slate-400">
              <Wallet className="w-10 h-10 mb-2 opacity-30" />
              <p className="text-sm font-medium">Chưa có dữ liệu chi phí AI trong kỳ.</p>
            </div>
          )}
        </div>

        {/* Bảng chi phí theo Website */}
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center gap-2 mb-4">
              <div className="rounded-xl bg-purple-50 p-2 text-purple-600">
                <Globe className="w-5 h-5" />
              </div>
              <h2 className="text-lg font-black text-slate-900">Chi phí theo Website</h2>
            </div>

            {summary.isLoading ? (
              <div className="space-y-3 py-4">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
              </div>
            ) : websiteCosts.length > 0 ? (
              <div className="w-full overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-100 text-slate-400 uppercase tracking-wider font-extrabold">
                      <th className="pb-3">Website</th>
                      <th className="pb-3 text-right whitespace-nowrap">Cuộc gọi</th>
                      <th className="pb-3 text-right whitespace-nowrap">Chi phí</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {websiteCosts.slice(0, 5).map((site) => (
                      <tr key={site.website_id} className="hover:bg-slate-50/80">
                        <td className="py-2.5 font-bold text-slate-800">
                          <div className="truncate max-w-[140px]" title={site.name}>{site.name}</div>
                          {site.domain && <div className="text-[10px] text-slate-400 truncate max-w-[140px]">{site.domain}</div>}
                        </td>
                        <td className="py-2.5 text-right font-medium text-slate-500 whitespace-nowrap">{site.calls}</td>
                        <td className="py-2.5 text-right font-bold text-slate-800 whitespace-nowrap">${site.cost_usd.toFixed(4)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="py-12 text-center text-slate-400">
                <Globe className="w-8 h-8 mx-auto mb-2 opacity-30" />
                <p className="text-xs font-medium">Chưa có số liệu theo website</p>
              </div>
            )}
          </div>

          <div className="pt-4 border-t border-slate-100 mt-4">
            <Button asChild variant="outline" className="w-full rounded-xl text-xs font-bold text-slate-700 py-4 cursor-pointer">
              <Link href="/wordpress">
                Quản lý WordPress Sites <ArrowRight className="w-3.5 h-3.5 ml-1.5" />
              </Link>
            </Button>
          </div>
        </div>

      </div>

      {/* System Status & Quick Guides */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        {/* Quick Start Guide */}
        <div className="lg:col-span-2 space-y-5">
          <h2 className="text-lg font-black text-slate-800 flex items-center gap-2">
            <Zap className="w-4 h-4 text-amber-500" />
            Hướng dẫn bắt đầu nhanh
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div className="p-6 rounded-2xl border border-slate-100 bg-white shadow-sm space-y-4 hover:border-blue-200 transition-all duration-300 group hover:shadow-md relative overflow-hidden">
              <div className="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-black text-xs border border-blue-100">1</div>
              <h3 className="font-extrabold text-slate-800">Tải lên từ khóa</h3>
              <p className="text-xs text-slate-500 leading-relaxed font-medium">Tải lên danh sách từ khóa bằng tệp Excel chuẩn v2. Hệ thống sẽ tự động cluster và ước tính chi phí API trước khi chạy.</p>
              <div className="pt-2">
                <Link href="/keywords/import" className="text-xs font-black text-blue-600 flex items-center group-hover:gap-1.5 transition-all">
                  Nhập từ khóa <ArrowRight className="w-3.5 h-3.5 ml-1" />
                </Link>
              </div>
            </div>

            <div className="p-6 rounded-2xl border border-slate-100 bg-white shadow-sm space-y-4 hover:border-emerald-200 transition-all duration-300 group hover:shadow-md relative overflow-hidden">
              <div className="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 font-black text-xs border border-emerald-100">2</div>
              <h3 className="font-extrabold text-slate-800">Kiểm tra & duyệt bài</h3>
              <p className="text-xs text-slate-500 leading-relaxed font-medium">Sau vài phút xử lý, bài viết sẽ ở trạng thái Chờ Duyệt (Review). Sử dụng công cụ Auto-Fix SEO để tự động sửa lỗi và xuất bản sang WordPress.</p>
              <div className="pt-2">
                <Link href="/articles" className="text-xs font-black text-emerald-600 flex items-center group-hover:gap-1.5 transition-all">
                  Xem danh sách bài <ArrowRight className="w-3.5 h-3.5 ml-1" />
                </Link>
              </div>
            </div>
          </div>
        </div>

        {/* System Status */}
        <div className="space-y-5">
          <h2 className="text-lg font-black text-slate-800 flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-blue-500" />
            Trạng thái vận hành
          </h2>
          <div className="bg-slate-900 rounded-3xl p-6 text-white space-y-4 shadow-xl border border-slate-800/80">
            <div className="flex items-center justify-between py-1 border-b border-slate-800">
              <span className="text-xs font-semibold text-slate-400">AI Horizon Workers</span>
              <span className="flex items-center gap-1.5 text-xs font-black text-emerald-400">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Active
              </span>
            </div>
            <div className="flex items-center justify-between py-1 border-b border-slate-800">
              <span className="text-xs font-semibold text-slate-400">Multi-Agent Pipeline</span>
              <span className="flex items-center gap-1.5 text-xs font-black text-emerald-400">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Operational
              </span>
            </div>
            <div className="flex items-center justify-between py-1 border-b border-slate-800">
              <span className="text-xs font-semibold text-slate-400">Credit Ledger</span>
              <span className="inline-flex px-2 py-0.5 rounded bg-emerald-950 text-[10px] font-bold text-emerald-300 border border-emerald-800">
                Synchronized
              </span>
            </div>
            <div className="pt-2">
              <Button asChild variant="outline" className="w-full rounded-xl border-slate-700 bg-slate-800/40 hover:bg-slate-800 text-slate-200 text-xs font-bold py-5 cursor-pointer">
                <Link href="/queue">
                  Theo dõi hàng đợi AI (Queue)
                </Link>
              </Button>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
