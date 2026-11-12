'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { ROUTES } from '@/lib/constants/routes';
import { useAuthStore } from '@/stores/authStore';
import {
  LayoutDashboard,
  BotMessageSquare,
  Target,
  Key,
  FileSpreadsheet,
  FileText,
  ListOrdered,
  BarChart,
  Globe,
  Bell,
  Calendar,
  Settings,
  CreditCard,
  ShieldCheck,
  Search,
  Network,
  Swords,
  Sparkles,
  Layers,
  BookOpen,
  Link2,
  TrendingUp,
} from 'lucide-react';

interface NavItem {
  title: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  badge?: string;
}

interface NavGroup {
  label: string;
  items: NavItem[];
}

const navGroups: NavGroup[] = [
  {
    label: 'Trung tâm Điều hành',
    items: [
      { title: 'Bảng điều khiển', href: ROUTES.DASHBOARD.HOME, icon: LayoutDashboard },
      { title: 'Trợ lý AI Aura', href: ROUTES.DASHBOARD.CHATBOT, icon: BotMessageSquare, badge: 'AI' },
    ],
  },
  {
    label: 'Nghiên cứu & Chiến lược',
    items: [
      { title: 'Quản lý Từ khóa', href: ROUTES.DASHBOARD.KEYWORDS, icon: Key },
      { title: 'Khám phá Từ khóa', href: '/keywords/research', icon: Search },
      { title: 'Cụm chủ đề (Clusters)', href: '/keywords/clusters', icon: Network },
      { title: 'Đối thủ & Top 3 SERP', href: '/competitor/serp', icon: Swords },
      { title: 'Chiến dịch SEO', href: ROUTES.DASHBOARD.CAMPAIGNS, icon: Target },
    ],
  },
  {
    label: 'Sản xuất & Nội dung',
    items: [
      { title: 'Kho Bài viết SEO', href: ROUTES.DASHBOARD.ARTICLES, icon: FileText },
      { title: 'Sản xuất Hàng loạt', href: '/bulk-generator', icon: Layers },
      { title: 'Liên kết Nội bộ (SILO)', href: '/internal-links', icon: Link2 },
      { title: 'Hồ sơ Thương hiệu', href: '/brand-knowledge', icon: BookOpen },
      { title: 'Nhập từ khóa Excel', href: ROUTES.DASHBOARD.IMPORT_WIZARD, icon: FileSpreadsheet },
    ],
  },
  {
    label: 'Phân tích & Tự động hóa',
    items: [
      { title: 'Thống kê GSC & GA4', href: ROUTES.DASHBOARD.ANALYTICS, icon: BarChart },
      { title: 'Theo dõi Thứ hạng', href: '/rank-tracker', icon: TrendingUp },
      { title: 'Lịch chạy Tự động', href: ROUTES.DASHBOARD.SCHEDULES, icon: Calendar },
      { title: 'Hàng đợi Tác vụ', href: ROUTES.DASHBOARD.QUEUE, icon: ListOrdered },
    ],
  },
  {
    label: 'Hệ thống & Kết nối',
    items: [
      { title: 'Websites & WordPress', href: ROUTES.DASHBOARD.WORDPRESS, icon: Globe },
      { title: 'Gói cước & Ví Credit', href: ROUTES.DASHBOARD.SUBSCRIPTION, icon: CreditCard },
      { title: 'Thông báo Hệ thống', href: ROUTES.DASHBOARD.NOTIFICATIONS, icon: Bell },
      { title: 'Cài đặt Chung', href: ROUTES.DASHBOARD.SETTINGS, icon: Settings },
    ],
  },
];

export function Sidebar({ className }: { className?: string }) {
  const pathname = usePathname();
  const user = useAuthStore((state) => state.user);

  const isSuperAdmin =
    user?.role === 'super_admin' ||
    Boolean((user as { is_platform_admin?: boolean } | null)?.is_platform_admin);

  return (
    <nav className={cn('flex flex-col gap-5 select-none', className)}>
      {isSuperAdmin && (
        <div className="px-1">
          <Link
            href="/admin"
            className={cn(
              'flex items-center justify-between rounded-xl px-3.5 py-2.5 text-xs font-bold transition-all shadow-2xs',
              pathname.startsWith('/admin')
                ? 'bg-gradient-to-r from-sky-600 to-blue-600 text-white shadow-sky-200/60'
                : 'bg-sky-50/90 text-sky-700 hover:bg-sky-100/80 border border-sky-200/80'
            )}
          >
            <span className="flex items-center gap-2.5">
              <ShieldCheck className="h-4 w-4 shrink-0" />
              <span>Quản trị Hệ thống (Admin)</span>
            </span>
            <Sparkles className="h-3.5 w-3.5 opacity-80" />
          </Link>
        </div>
      )}

      {navGroups.map((group) => (
        <div key={group.label} className="space-y-1">
          <p className="px-3 text-[10px] font-extrabold uppercase tracking-wider text-sky-700/70">
            {group.label}
          </p>
          <div className="space-y-0.5">
            {group.items.map((item) => {
              const isExactSubroute =
                item.href === ROUTES.DASHBOARD.KEYWORDS &&
                (pathname.startsWith('/keywords/research') || pathname.startsWith('/keywords/clusters'));
              const isActive =
                !isExactSubroute &&
                (pathname === item.href ||
                  (item.href !== ROUTES.DASHBOARD.HOME && pathname.startsWith(`${item.href}/`)));

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    'group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition-all duration-150',
                    isActive
                      ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-xs shadow-sky-200/70'
                      : 'text-slate-600 hover:bg-sky-50/80 hover:text-sky-700'
                  )}
                >
                  <span className="flex items-center gap-2.5 truncate">
                    <item.icon
                      className={cn(
                        'h-4 w-4 shrink-0 transition-colors',
                        isActive ? 'text-white' : 'text-sky-600/80 group-hover:text-sky-600'
                      )}
                    />
                    <span className="truncate">{item.title}</span>
                  </span>
                  {item.badge && (
                    <span
                      className={cn(
                        'ml-2 rounded-full px-1.5 py-0.2 text-[9px] font-extrabold uppercase tracking-wider',
                        isActive
                          ? 'bg-white/25 text-white'
                          : 'bg-sky-100 text-sky-700 border border-sky-200'
                      )}
                    >
                      {item.badge}
                    </span>
                  )}
                </Link>
              );
            })}
          </div>
        </div>
      ))}
    </nav>
  );
}
