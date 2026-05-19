"use client";

import React from 'react';
import { 
  FileText, 
  Search, 
  Send, 
  AlertCircle, 
  TrendingUp, 
  Plus, 
  ArrowRight,
  Zap,
  CheckCircle2
} from 'lucide-react';
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import Link from 'next/link';

export default function DashboardPage() {
  // Mock data for initial view
  const stats = [
    { title: 'Total Articles', value: '0', icon: FileText, color: 'text-blue-600', bg: 'bg-blue-50' },
    { title: 'Keywords Tracking', value: '0', icon: Search, color: 'text-purple-600', bg: 'bg-purple-50' },
    { title: 'Published Today', value: '0', icon: Send, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    { title: 'Failed Jobs', value: '0', icon: AlertCircle, color: 'text-rose-600', bg: 'bg-rose-50' },
  ];

  return (
    <div className="space-y-8 max-w-[1200px] mx-auto">
      {/* Welcome Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-8 rounded-3xl border border-slate-200 shadow-sm overflow-hidden relative">
        <div className="relative z-10">
          <h1 className="text-3xl font-black text-slate-900 tracking-tight">Chào mừng Admin! 👋</h1>
          <p className="text-slate-500 mt-2 font-medium">Hệ thống AI SEO của bạn đã sẵn sàng. Hãy bắt đầu bằng việc thêm từ khóa mới.</p>
          <div className="flex gap-3 mt-6">
            <Link href="/keywords/import">
              <Button className="rounded-xl px-6 bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-200">
                <Plus className="w-4 h-4 mr-2" />
                Import Keywords
              </Button>
            </Link>
            <Link href="/analytics">
              <Button variant="outline" className="rounded-xl px-6 border-slate-200">
                View Analytics
              </Button>
            </Link>
          </div>
        </div>
        <div className="absolute right-0 top-0 bottom-0 w-1/3 bg-gradient-to-l from-blue-50 to-transparent opacity-50 hidden md:block" />
        <TrendingUp className="w-32 h-32 text-blue-500/10 absolute -right-4 -bottom-4 rotate-12 hidden md:block" />
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat, i) => (
          <Card key={i} className="border-slate-200 shadow-sm rounded-2xl overflow-hidden hover:shadow-md transition-shadow">
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">{stat.title}</p>
                  <h3 className="text-3xl font-black text-slate-900 mt-1">{stat.value}</h3>
                </div>
                <div className={`p-3 rounded-xl ${stat.bg} ${stat.color}`}>
                  <stat.icon className="w-6 h-6" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Quick Start Guide */}
        <div className="lg:col-span-2 space-y-6">
          <h2 className="text-xl font-black text-slate-900 flex items-center gap-2">
            <Zap className="w-5 h-5 text-amber-500" />
            Quick Start Guide
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-6 rounded-2xl border border-slate-100 bg-white shadow-sm space-y-4 hover:border-blue-200 transition-all group">
              <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">1</div>
              <h3 className="font-bold text-slate-900">Import Keywords</h3>
              <p className="text-sm text-slate-500 leading-relaxed">Tải lên danh sách từ khóa qua file Excel để AI bắt đầu nghiên cứu SERP.</p>
              <Link href="/keywords/import" className="text-sm font-bold text-blue-600 flex items-center group-hover:gap-2 transition-all">
                Go to Import <ArrowRight className="w-4 h-4 ml-1" />
              </Link>
            </div>

            <div className="p-6 rounded-2xl border border-slate-100 bg-white shadow-sm space-y-4 hover:border-emerald-200 transition-all group">
              <div className="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 font-bold">2</div>
              <h3 className="font-bold text-slate-900">Review Drafts</h3>
              <p className="text-sm text-slate-500 leading-relaxed">Sau 5-10 phút, kiểm tra và duyệt các bài viết do AI tạo ra.</p>
              <Link href="/articles" className="text-sm font-bold text-emerald-600 flex items-center group-hover:gap-2 transition-all">
                View Articles <ArrowRight className="w-4 h-4 ml-1" />
              </Link>
            </div>
          </div>
        </div>

        {/* System Status */}
        <div className="space-y-6">
          <h2 className="text-xl font-black text-slate-900 flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 text-blue-500" />
            System Status
          </h2>
          <div className="bg-slate-900 rounded-3xl p-6 text-white space-y-4 shadow-xl">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-slate-400">AI Workers (Horizon)</span>
              <span className="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                Active
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-slate-400">API Gateway</span>
              <span className="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                Online
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-slate-400">DB Persistence</span>
              <span className="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                Healthy
              </span>
            </div>
            <div className="pt-4 border-t border-slate-800">
              <Link href="/queue">
                <Button variant="outline" className="w-full rounded-xl border-slate-700 bg-transparent hover:bg-slate-800 text-slate-300">
                  Monitor Queue Pipeline
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
