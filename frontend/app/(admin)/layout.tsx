"use client";

import React, { useEffect } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { ShieldCheck, CreditCard, Building2, ArrowLeft, Loader2, AlertTriangle, LayoutDashboard } from 'lucide-react';
import { useAuthStore } from '@/stores/authStore';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, isAuthenticated } = useAuthStore();

  const isSuperAdmin =
    user?.role === 'super_admin' ||
    Boolean((user as { is_platform_admin?: boolean } | null)?.is_platform_admin);

  useEffect(() => {
    if (!isAuthenticated) {
      router.push('/login');
    }
  }, [isAuthenticated, router]);

  if (!isAuthenticated || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  if (!isSuperAdmin) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 text-center">
        <div className="rounded-2xl border border-rose-200 bg-white p-8 shadow-sm max-w-md">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 mb-4">
            <AlertTriangle className="h-6 w-6" />
          </div>
          <h2 className="text-xl font-bold text-slate-900">Từ chối quyền truy cập (403)</h2>
          <p className="mt-2 text-xs text-slate-500">
            Khu vực Quản trị Hệ thống chỉ dành riêng cho Quản trị viên Toàn quyền (Super Admin).
          </p>
          <div className="mt-6">
            <Link href="/dashboard">
              <Button className="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold">
                <ArrowLeft className="mr-1.5 h-3.5 w-3.5" /> Trở về Trang làm việc
              </Button>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const navItems = [
    {
      label: 'Tổng quan hệ thống',
      href: '/admin',
      icon: LayoutDashboard,
      exact: true,
    },
    {
      label: 'Đơn thanh toán chuyển khoản',
      href: '/admin/payment-orders',
      icon: CreditCard,
      exact: false,
    },
    {
      label: 'Quản lý Workspaces & Credit',
      href: '/admin/workspaces',
      icon: Building2,
      exact: false,
    },
  ];

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      {/* Admin Top Navigation */}
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white shadow-xs">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex h-16 items-center justify-between">
            <div className="flex items-center space-x-4">
              <div className="flex items-center space-x-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white font-bold">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div>
                  <span className="font-extrabold text-sm text-slate-900">Admin Portal</span>
                  <Badge variant="outline" className="ml-2 border-purple-300 bg-purple-50 text-purple-700 text-[10px]">
                    Super Admin
                  </Badge>
                </div>
              </div>

              {/* Navigation links */}
              <nav className="hidden md:flex space-x-1 pl-6">
                {navItems.map((item) => {
                  const Icon = item.icon;
                  const isActive = item.exact ? pathname === item.href : pathname.startsWith(item.href);

                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      className={`inline-flex items-center px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                        isActive
                          ? 'bg-blue-50 text-blue-700'
                          : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                      }`}
                    >
                      <Icon className="mr-1.5 h-3.5 w-3.5" />
                      {item.label}
                    </Link>
                  );
                })}
              </nav>
            </div>

            <div className="flex items-center space-x-3">
              <span className="text-xs text-slate-500 hidden sm:inline">
                {user?.email}
              </span>
              <Link href="/dashboard">
                <Button variant="outline" size="sm" className="h-8 text-xs font-medium border-slate-200">
                  <ArrowLeft className="mr-1.5 h-3.5 w-3.5" /> Về Khách hàng
                </Button>
              </Link>
            </div>
          </div>
        </div>

        {/* Mobile Navigation bar */}
        <div className="flex md:hidden border-t border-slate-100 px-4 py-2 space-x-2 overflow-x-auto">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive = item.exact ? pathname === item.href : pathname.startsWith(item.href);

            return (
              <Link
                key={item.href}
                href={item.href}
                className={`inline-flex whitespace-nowrap items-center px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                  isActive
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-slate-600 hover:bg-slate-100'
                }`}
              >
                <Icon className="mr-1.5 h-3.5 w-3.5" />
                {item.label}
              </Link>
            );
          })}
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1 mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {children}
      </main>
    </div>
  );
}
