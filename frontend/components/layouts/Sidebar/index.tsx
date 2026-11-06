'use client';

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
  History, 
  Users, 
  Settings,
  CreditCard,
  ShieldCheck
} from 'lucide-react';

const navItems = [
  { title: 'Dashboard', href: ROUTES.DASHBOARD.HOME, icon: LayoutDashboard },
  { title: 'Aura Assistant', href: ROUTES.DASHBOARD.CHATBOT, icon: BotMessageSquare },
  { title: 'Gói cước & Credit', href: ROUTES.DASHBOARD.SUBSCRIPTION, icon: CreditCard },
  { title: 'Campaigns', href: ROUTES.DASHBOARD.CAMPAIGNS, icon: Target },
  { title: 'Keywords', href: ROUTES.DASHBOARD.KEYWORDS, icon: Key },
  { title: 'Import Wizard', href: ROUTES.DASHBOARD.IMPORT_WIZARD, icon: FileSpreadsheet },
  { title: 'Articles', href: ROUTES.DASHBOARD.ARTICLES, icon: FileText },
  { title: 'Queue', href: ROUTES.DASHBOARD.QUEUE, icon: ListOrdered },
  { title: 'Analytics', href: ROUTES.DASHBOARD.ANALYTICS, icon: BarChart },
  { title: 'WordPress', href: ROUTES.DASHBOARD.WORDPRESS, icon: Globe },
  { title: 'Notifications', href: ROUTES.DASHBOARD.NOTIFICATIONS, icon: Bell },
  { title: 'Schedules', href: ROUTES.DASHBOARD.SCHEDULES, icon: Calendar },
  { title: 'Logs', href: ROUTES.DASHBOARD.LOGS, icon: History },
  { title: 'Users', href: ROUTES.DASHBOARD.USERS, icon: Users },
  { title: 'Settings', href: ROUTES.DASHBOARD.SETTINGS, icon: Settings },
];

export function Sidebar({ className }: { className?: string }) {
  const pathname = usePathname();
  const user = useAuthStore((state) => state.user);

  const isSuperAdmin =
    user?.role === 'super_admin' ||
    Boolean((user as { is_platform_admin?: boolean } | null)?.is_platform_admin);

  return (
    <nav className={cn("flex flex-col gap-2", className)}>
      {isSuperAdmin && (
        <Link
          href="/admin"
          className={cn(
            "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold transition-colors mb-1",
            pathname.startsWith('/admin')
              ? "bg-purple-600 text-white shadow-xs"
              : "bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200"
          )}
        >
          <ShieldCheck className="h-4 w-4" />
          Admin Portal
        </Link>
      )}

      {navItems.map((item) => {
        const isActive = pathname === item.href || (item.href !== ROUTES.DASHBOARD.HOME && pathname.startsWith(`${item.href}/`));
        return (
          <Link
            key={item.href}
            href={item.href}
            className={cn(
              "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              isActive 
                ? "bg-primary text-primary-foreground font-semibold" 
                : "text-muted-foreground hover:bg-muted hover:text-foreground"
            )}
          >
            <item.icon className="h-4 w-4" />
            {item.title}
          </Link>
        );
      })}
    </nav>
  );
}
