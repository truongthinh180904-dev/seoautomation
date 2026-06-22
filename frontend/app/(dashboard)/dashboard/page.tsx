"use client";

import React from 'react';
import { 
  FileText, 
  Search, 
  TrendingUp, 
  Plus, 
  ArrowRight,
  Zap,
  CheckCircle2,
  AlertCircle,
  Award,
  Wallet,
  Building,
  RefreshCw
} from 'lucide-react';
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import Link from 'next/link';
import { useAuth } from '@/hooks/useAuth';
import { useAnalyticsSummary, useUsageAnalytics } from '@/hooks/useAnalytics';

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
  const summary = useAnalyticsSummary(30);
  const usage = useUsageAnalytics();

  const handleRefresh = () => {
    summary.refetch();
    usage.refetch();
  };

  const currentPlan = usage.data?.subscription?.plan?.name || 'Starter';
  const articlesUsed = usage.data?.quota?.articlesUsed ?? 0;
  const articlesLimit = usage.data?.quota?.articlesLimit ?? 30;
  const costUsed = usage.data?.quota?.costUsedUsd ?? 0;
  const costLimit = usage.data?.quota?.costLimitUsd ?? 10.0;

  const stats = [
    { 
      title: 'Tổng bài viết', 
      value: summary.isLoading ? '...' : (summary.data?.total || '0'), 
      icon: FileText, 
      color: 'text-blue-600', 
      bg: 'bg-blue-50/70 border-blue-100',
      description: 'Tổng số bài viết đã tạo'
    },
    { 
      title: 'Điểm SEO TB', 
      value: summary.isLoading ? '...' : `${summary.data?.avg_seo || '0'}/100`, 
      icon: Award, 
      color: 'text-emerald-600', 
      bg: 'bg-emerald-50/70 border-emerald-100',
      description: 'Độ tối ưu SEO trung bình'
    },
    { 
      title: 'Số từ trung bình', 
      value: summary.isLoading ? '...' : (summary.data?.avg_words ? summary.data.avg_words.toLocaleString() : '0'), 
      icon: TrendingUp, 
      color: 'text-purple-600', 
      bg: 'bg-purple-50/70 border-purple-100',
      description: 'Độ dài trung bình mỗi bài'
    },
    { 
      title: 'Chi phí AI', 
      value: summary.isLoading ? '...' : `$${(summary.data?.total_cost || 0).toFixed(4)}`, 
      icon: Wallet, 
      color: 'text-amber-600', 
      bg: 'bg-amber-50/70 border-amber-100',
      description: 'Ngân sách API đã tiêu thụ'
    },
  ];

  return (
    <div className="space-y-8 max-w-[1200px] mx-auto animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      {/* Welcome Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 text-white p-8 rounded-3xl border border-slate-800 shadow-xl overflow-hidden relative group">
        <div className="relative z-10 max-w-xl space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-blue-500/20 text-blue-400 border border-blue-500/30 uppercase tracking-widest">
            <Zap className="w-3.5 h-3.5" /> AI SEO Engine Active
          </span>
          <h1 className="text-3xl md:text-4xl font-black text-white tracking-tight">
            Chào mừng, {user?.name || 'Admin'}! 👋
          </h1>
          <p className="text-slate-400 text-sm md:text-base font-medium leading-relaxed">
            Hệ thống tự động hóa nội dung và tối ưu hóa SEO của bạn đã sẵn sàng. Hãy bắt đầu chiến dịch bằng cách tải lên danh sách từ khóa.
          </p>
          <div className="flex flex-wrap gap-3 pt-3">
            <Link href="/keywords/import">
              <Button className="rounded-xl px-5 py-5 font-bold bg-blue-600 hover:bg-blue-700 border-none text-white shadow-lg shadow-blue-500/30 transition-all hover:scale-[1.02] cursor-pointer">
                <Plus className="w-4 h-4 mr-2" />
                Import Keywords
              </Button>
            </Link>
            <Link href="/campaigns">
              <Button variant="outline" className="rounded-xl px-5 py-5 font-bold border-slate-700 bg-slate-800/50 text-slate-200 hover:bg-slate-800 hover:text-white transition-all hover:scale-[1.02] cursor-pointer">
                Quản lý chiến dịch
              </Button>
            </Link>
            <Button 
              variant="ghost" 
              size="icon" 
              onClick={handleRefresh}
              className="rounded-xl border border-slate-700 bg-slate-800/30 text-slate-400 hover:bg-slate-800 hover:text-white cursor-pointer"
              title="Làm mới dữ liệu"
            >
              <RefreshCw className={`w-4 h-4 ${summary.isFetching || usage.isFetching ? 'animate-spin' : ''}`} />
            </Button>
          </div>
        </div>
        <div className="absolute right-0 top-0 bottom-0 w-1/3 bg-gradient-to-l from-blue-500/10 to-transparent opacity-40 hidden md:block" />
        <TrendingUp className="w-48 h-48 text-blue-500/5 absolute -right-6 -bottom-6 rotate-12 hidden md:block transition-all group-hover:scale-110" />
      </div>

      {/* Plan & Quota Usage Tracker Widget */}
      <div className="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm space-y-6">
        <div className="flex flex-wrap justify-between items-center gap-4">
          <div className="flex items-center gap-3">
            <div className="rounded-2xl bg-indigo-50 p-3.5 text-indigo-600 border border-indigo-100">
              <Building className="h-6 w-6" />
            </div>
            <div>
              <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Gói dịch vụ hiện tại</p>
              <div className="flex items-center gap-2 mt-1">
                <span className="text-xl font-black capitalize text-slate-800">{currentPlan}</span>
                <span className="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black bg-indigo-100 text-indigo-800 uppercase tracking-wider">
                  Active
                </span>
              </div>
            </div>
          </div>
          <Link href="/analytics/usage">
            <Button variant="ghost" className="text-xs font-bold text-indigo-600 hover:bg-indigo-50 rounded-xl cursor-pointer">
              Xem chi tiết ngân sách <ArrowRight className="w-3.5 h-3.5 ml-1" />
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

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {stats.map((stat, i) => (
          <Card key={i} className="border-slate-200/80 shadow-sm rounded-2xl overflow-hidden hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 bg-white">
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{stat.title}</p>
                  <h3 className="text-2xl font-black text-slate-800 mt-2">{stat.value}</h3>
                  <p className="text-[11px] font-medium text-slate-400 mt-1">{stat.description}</p>
                </div>
                <div className={`p-3.5 rounded-2xl border ${stat.bg} ${stat.color}`}>
                  <stat.icon className="w-5 h-5" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Guides and System Status */}
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
              <p className="text-xs text-slate-500 leading-relaxed font-medium">Sau vài phút xử lý, bài viết sẽ ở trạng thái Chờ Duyệt (Review). Sử dụng công cụ Auto-Fix SEO để tự động sửa lỗi và tối ưu điểm số.</p>
              <div className="pt-2">
                <Link href="/articles" className="text-xs font-black text-emerald-600 flex items-center group-hover:gap-1.5 transition-all">
                  Xem danh sách bài <ArrowRight className="w-3.5 h-3.5 ml-1" />
                </Link>
              </div>
            </div>
          </div>
        </div>

        {/* System Status & Horizon */}
        <div className="space-y-5">
          <h2 className="text-lg font-black text-slate-800 flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-blue-500" />
            Trạng thái hệ thống
          </h2>
          <div className="bg-slate-900 rounded-3xl p-6 text-white space-y-4 shadow-xl border border-slate-800/80">
            <div className="flex items-center justify-between py-1 border-b border-slate-800">
              <span className="text-xs font-semibold text-slate-400">AI Workers (Horizon)</span>
              <span className="flex items-center gap-1.5 text-xs font-black text-emerald-400">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Active
              </span>
            </div>
            <div className="flex items-center justify-between py-1 border-b border-slate-800">
              <span className="text-xs font-semibold text-slate-400">API Gateway</span>
              <span className="flex items-center gap-1.5 text-xs font-black text-emerald-400">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Online
              </span>
            </div>
            <div className="flex items-center justify-between py-1 border-b border-slate-800">
              <span className="text-xs font-semibold text-slate-400">Database Connection</span>
              <span className="flex items-center gap-1.5 text-xs font-black text-emerald-400">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Healthy
              </span>
            </div>
            <div className="flex items-center justify-between py-1">
              <span className="text-xs font-semibold text-slate-400">WordPress Sites</span>
              <span className="inline-flex px-2 py-0.5 rounded bg-slate-800 text-[10px] font-bold text-slate-300">
                Connected
              </span>
            </div>
            <div className="pt-2">
              <Link href="/queue">
                <Button variant="outline" className="w-full rounded-xl border-slate-700 bg-slate-800/40 hover:bg-slate-800 text-slate-200 text-xs font-bold py-5 cursor-pointer">
                  Theo dõi hàng đợi AI (Queue)
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
