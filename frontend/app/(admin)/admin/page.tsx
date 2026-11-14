"use client";

import React, { useState } from 'react';
import Link from 'next/link';
import { 
  Building2, 
  Users, 
  CreditCard, 
  Wallet, 
  Cpu, 
  AlertOctagon, 
  ArrowRight, 
  RefreshCw, 
  CheckCircle2,
  AlertTriangle
} from 'lucide-react';
import { useAdminPlatformOverview } from '@/hooks/useAnalytics';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { 
  ResponsiveContainer, 
  PieChart, 
  Pie, 
  Cell, 
  Tooltip, 
  Legend
} from 'recharts';

const PROVIDER_COLORS: Record<string, string> = {
  openai: '#10a37f',
  anthropic: '#d97706',
  gemini: '#3b82f6',
  open_ai: '#10a37f',
};

export default function AdminOverviewPage() {
  const [days, setDays] = useState(30);
  const { data, isLoading, refetch, isFetching, isError } = useAdminPlatformOverview(days);

  const workspaces = data?.workspaces;
  const users = data?.users;
  const websites = data?.websites;
  const orders = data?.orders;
  const ai = data?.ai;
  const queue = data?.queue;

  const providerChartData = (ai?.providers ?? []).map((p) => ({
    name: p.provider.toUpperCase(),
    cost: p.cost,
    calls: p.calls,
    color: PROVIDER_COLORS[p.provider.toLowerCase()] || '#6366f1',
  }));

  const failedTasks = queue?.recent_failed_tasks ?? [];

  return (
    <div className="space-y-8 animate-in fade-in duration-300">
      
      {/* Error Alert State */}
      {isError && (
        <div className="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-rose-900 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <AlertTriangle className="h-5 w-5 text-rose-600 shrink-0" />
            <div>
              <p className="text-sm font-bold">Không thể tải dữ liệu quản trị hệ thống</p>
              <p className="text-xs text-rose-600">Đã xảy ra lỗi khi lấy số liệu thống kê toàn sàn.</p>
            </div>
          </div>
          <Button 
            variant="outline" 
            size="sm" 
            onClick={() => refetch()}
            className="rounded-xl border-rose-300 bg-white text-rose-700 hover:bg-rose-100 text-xs font-bold"
          >
            <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Thử lại
          </Button>
        </div>
      )}

      {/* Header & Date Range Filter */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
              Tổng quan Hệ thống SaaS
            </h1>
            <Badge className="bg-purple-100 text-purple-800 border-purple-200 font-bold text-xs">
              Platform Admin
            </Badge>
          </div>
          <p className="mt-1 text-xs text-slate-500 sm:text-sm">
            Giám sát thời gian thực hoạt động của toàn bộ khách hàng, lưu lượng API AI, hàng đợi Horizon và doanh thu thanh toán.
          </p>
        </div>

        <div className="flex items-center gap-3">
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

          <Button
            variant="outline"
            size="sm"
            onClick={() => refetch()}
            disabled={isLoading}
            className="rounded-xl border-slate-200 text-xs font-semibold cursor-pointer"
          >
            <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${isFetching ? 'animate-spin' : ''}`} />
            Làm mới
          </Button>
        </div>
      </div>

      {/* KPI Cards Grid */}
      {isLoading && !data ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i} className="rounded-2xl border-slate-200 shadow-xs bg-white p-5">
              <Skeleton className="h-3 w-28 mb-3" />
              <Skeleton className="h-8 w-20 mb-2" />
              <Skeleton className="h-3 w-36" />
            </Card>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          
          {/* Workspaces */}
          <Card className="rounded-2xl border-slate-200 shadow-xs bg-white">
            <CardContent className="p-5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Không gian làm việc</p>
                  <h3 className="text-3xl font-black text-slate-900 mt-2">
                    {(workspaces?.total ?? 0).toLocaleString()}
                  </h3>
                  <div className="mt-2 flex items-center gap-2 text-[11px] font-bold">
                    <span className="text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                      {workspaces?.active ?? 0} active
                    </span>
                    {(workspaces?.suspended ?? 0) > 0 && (
                      <span className="text-rose-600 bg-rose-50 px-2 py-0.5 rounded">
                        {workspaces?.suspended} khóa
                      </span>
                    )}
                  </div>
                </div>
                <div className="rounded-2xl bg-blue-50 p-3.5 text-blue-600 border border-blue-100">
                  <Building2 className="h-6 w-6" />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Khách hàng & Websites */}
          <Card className="rounded-2xl border-slate-200 shadow-xs bg-white">
            <CardContent className="p-5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Khách hàng & Website</p>
                  <h3 className="text-3xl font-black text-slate-900 mt-2">
                    {(users?.total ?? 0).toLocaleString()}
                    <span className="text-sm font-bold text-slate-400 ml-1">users</span>
                  </h3>
                  <div className="mt-2 text-[11px] font-bold text-slate-500">
                    Quản lý <strong className="text-slate-800">{websites?.total ?? 0}</strong> websites WordPress
                  </div>
                </div>
                <div className="rounded-2xl bg-indigo-50 p-3.5 text-indigo-600 border border-indigo-100">
                  <Users className="h-6 w-6" />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Đơn thanh toán */}
          <Card className="rounded-2xl border-slate-200 shadow-xs bg-white">
            <CardContent className="p-5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Đơn hàng chuyển khoản</p>
                  <h3 className="text-3xl font-black text-slate-900 mt-2">
                    {(orders?.total ?? 0).toLocaleString()}
                  </h3>
                  <div className="mt-2 flex items-center gap-2 text-[11px] font-bold">
                    {(orders?.pending ?? 0) > 0 ? (
                      <span className="text-amber-700 bg-amber-50 px-2 py-0.5 rounded animate-pulse">
                        {orders?.pending} chờ duyệt
                      </span>
                    ) : (
                      <span className="text-slate-500 bg-slate-50 px-2 py-0.5 rounded">0 chờ duyệt</span>
                    )}
                    <span className="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                      {orders?.approved ?? 0} thành công
                    </span>
                  </div>
                </div>
                <div className="rounded-2xl bg-amber-50 p-3.5 text-amber-600 border border-amber-100">
                  <CreditCard className="h-6 w-6" />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Chi phí AI toàn sàn */}
          <Card className="rounded-2xl border-slate-200 shadow-xs bg-white">
            <CardContent className="p-5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Chi phí AI toàn sàn</p>
                  <h3 className="text-3xl font-black text-slate-900 mt-2">
                    ${(ai?.total_cost_usd ?? 0).toFixed(4)}
                  </h3>
                  <div className="mt-2 text-[11px] font-bold text-slate-500">
                    {(ai?.total_tokens ?? 0).toLocaleString()} tokens đã tiêu thụ
                  </div>
                </div>
                <div className="rounded-2xl bg-purple-50 p-3.5 text-purple-600 border border-purple-100">
                  <Wallet className="h-6 w-6" />
                </div>
              </div>
            </CardContent>
          </Card>

        </div>
      )}

      {/* Charts & Queue section */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Chi phí AI theo Provider */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Cpu className="h-5 w-5 text-indigo-600" />
              <h2 className="text-base font-extrabold text-slate-900">Chi phí AI theo Provider</h2>
            </div>
            <span className="text-xs font-semibold text-slate-400">{days} ngày</span>
          </div>

          {isLoading && !data ? (
            <div className="h-60 flex flex-col items-center justify-center gap-3">
              <Skeleton className="h-36 w-36 rounded-full" />
              <Skeleton className="h-4 w-28" />
            </div>
          ) : providerChartData.length > 0 ? (
            <ResponsiveContainer width="100%" height={240}>
              <PieChart>
                <Pie 
                  data={providerChartData} 
                  cx="50%" 
                  cy="50%" 
                  innerRadius={50} 
                  outerRadius={85} 
                  paddingAngle={4} 
                  dataKey="cost"
                >
                  {providerChartData.map((entry, idx) => (
                    <Cell key={idx} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip formatter={(val: unknown) => [`$${Number(val).toFixed(4)}`, 'Chi phí']} />
                <Legend iconType="circle" iconSize={10} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-60 flex flex-col items-center justify-center text-slate-400 text-xs">
              <Cpu className="w-8 h-8 mb-2 opacity-30" />
              Chưa có dữ liệu gọi API AI trong kỳ.
            </div>
          )}
        </div>

        {/* Hàng đợi Horizon & Dead Letter Queue */}
        <div className="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <AlertOctagon className="h-5 w-5 text-rose-600" />
                <h2 className="text-base font-extrabold text-slate-900">Giám sát Lỗi Hàng đợi (Dead Letter Queue)</h2>
              </div>
              <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${
                (queue?.failed_tasks_count ?? 0) > 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'
              }`}>
                {queue?.failed_tasks_count ?? 0} lỗi phát hiện
              </span>
            </div>

            {isLoading && !data ? (
              <div className="space-y-3 py-4">
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-full" />
              </div>
            ) : failedTasks.length > 0 ? (
              <div className="w-full overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-100 text-slate-400 uppercase tracking-wider font-extrabold">
                      <th className="pb-2.5 whitespace-nowrap">ID</th>
                      <th className="pb-2.5 whitespace-nowrap">Workspace</th>
                      <th className="pb-2.5 whitespace-nowrap">Loại tác vụ</th>
                      <th className="pb-2.5">Thông báo lỗi</th>
                      <th className="pb-2.5 whitespace-nowrap text-right">Thời gian</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {failedTasks.map((task) => (
                      <tr key={task.id} className="hover:bg-slate-50">
                        <td className="py-2.5 font-mono font-bold text-slate-500 whitespace-nowrap">#{task.id}</td>
                        <td className="py-2.5 font-semibold text-slate-700 whitespace-nowrap">WS #{task.workspace_id}</td>
                        <td className="py-2.5 whitespace-nowrap">
                          <span className="rounded bg-rose-50 px-2 py-0.5 font-mono text-[10px] font-bold text-rose-700">
                            {task.task_type}
                          </span>
                        </td>
                        <td className="py-2.5 text-rose-600 max-w-[280px] truncate font-medium" title={task.error_message || ''}>
                          {task.error_message || 'Unknown error'}
                        </td>
                        <td className="py-2.5 text-right text-slate-400 whitespace-nowrap">
                          {new Date(task.created_at).toLocaleTimeString('vi-VN')}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="py-12 text-center text-slate-400">
                <CheckCircle2 className="w-10 h-10 text-emerald-500 mx-auto mb-2 opacity-80" />
                <p className="text-xs font-bold text-slate-700">Hàng đợi đang vận hành ổn định</p>
                <p className="text-[11px] text-slate-400 mt-1">Không có tác vụ thất bại nào cần xử lý khẩn cấp.</p>
              </div>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center">
            <span className="text-xs text-slate-400">Auto-isolated Multi-Tenant Queue</span>
            <Link href="/queue">
              <Button variant="ghost" size="sm" className="text-xs font-bold text-blue-600 hover:bg-blue-50">
                Xem chi tiết Horizon Queue <ArrowRight className="w-3.5 h-3.5 ml-1" />
              </Button>
            </Link>
          </div>
        </div>

      </div>

      {/* Quick Action Navigation Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <Card className="rounded-2xl border-slate-200 p-6 bg-gradient-to-br from-blue-50/50 to-white hover:border-blue-300 transition-all shadow-xs">
          <div className="flex items-start justify-between">
            <div>
              <div className="flex items-center gap-2">
                <div className="rounded-xl bg-blue-600 p-2 text-white">
                  <CreditCard className="w-5 h-5" />
                </div>
                <h3 className="font-black text-slate-900 text-lg">Duyệt Đơn Chuyển Khoản</h3>
              </div>
              <p className="mt-2 text-xs text-slate-500 leading-relaxed font-medium">
                Kiểm tra minh chứng chuyển khoản của khách hàng, xác nhận số tiền thực tế và kích hoạt subscription kèm cấp credit tự động.
              </p>
              <div className="mt-4">
                <Link href="/admin/payment-orders">
                  <Button className="bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold">
                    Đi tới Quản lý Đơn hàng ({(orders?.pending ?? 0)} đơn chờ) <ArrowRight className="w-3.5 h-3.5 ml-1.5" />
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </Card>

        <Card className="rounded-2xl border-slate-200 p-6 bg-gradient-to-br from-purple-50/50 to-white hover:border-purple-300 transition-all shadow-xs">
          <div className="flex items-start justify-between">
            <div>
              <div className="flex items-center gap-2">
                <div className="rounded-xl bg-purple-600 p-2 text-white">
                  <Building2 className="w-5 h-5" />
                </div>
                <h3 className="font-black text-slate-900 text-lg">Quản lý Workspaces & Ví Credit</h3>
              </div>
              <p className="mt-2 text-xs text-slate-500 leading-relaxed font-medium">
                Kiểm soát toàn bộ không gian làm việc của khách hàng, thực hiện điều chỉnh số dư credit thủ công kèm ghi nhận lý do kiểm toán, khóa/mở khóa workspace.
              </p>
              <div className="mt-4">
                <Link href="/admin/workspaces">
                  <Button variant="outline" className="border-purple-200 text-purple-700 hover:bg-purple-100 rounded-xl text-xs font-bold">
                    Đi tới Danh sách Workspaces <ArrowRight className="w-3.5 h-3.5 ml-1.5" />
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </Card>

      </div>

    </div>
  );
}
