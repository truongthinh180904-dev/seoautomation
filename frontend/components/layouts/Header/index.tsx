'use client';

import {
  Menu,
  UserCircle,
  Shield,
  CreditCard,
  LogOut,
  Sparkles,
  BotMessageSquare,
  FileText,
  BarChart3,
  PanelLeftOpen,
} from 'lucide-react';
import { Sheet, SheetContent, SheetTrigger, SheetTitle } from '@/components/ui/sheet';
import { Sidebar } from '../Sidebar';
import { useAuth } from '@/hooks/useAuth';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useAuthStore } from '@/stores/authStore';
import { cn } from '@/lib/utils';

interface HeaderProps {
  isSidebarCollapsed?: boolean;
  onToggleSidebar?: () => void;
}

export function Header({ isSidebarCollapsed = false, onToggleSidebar }: HeaderProps) {
  const { logout, isLoggingOut } = useAuth();
  const pathname = usePathname();
  const user = useAuthStore((state) => state.user);

  const isSuperAdmin =
    user?.role === 'super_admin' ||
    Boolean((user as { is_platform_admin?: boolean } | null)?.is_platform_admin);

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map((n) => n[0])
      .join('')
      .toUpperCase()
      .substring(0, 2);
  };

  return (
    <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-sky-100 bg-white/90 backdrop-blur-md px-4 md:px-6 shadow-2xs">
      <div className="flex items-center gap-3">
        {/* Mobile Hamburger */}
        <div className="flex items-center md:hidden">
          <Sheet>
            <SheetTrigger className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-sky-200 bg-sky-50/60 text-sky-700 hover:bg-sky-100 md:hidden cursor-pointer">
              <Menu className="h-5 w-5" />
              <span className="sr-only">Mở menu điều hướng</span>
            </SheetTrigger>
            <SheetContent side="left" className="w-68 p-0 border-r-sky-100 bg-white">
              <SheetTitle className="sr-only">Menu Điều Hướng</SheetTitle>
              <div className="flex h-16 items-center gap-2.5 border-b border-sky-100 px-5 bg-sky-50/50">
                <div className="relative flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl border border-sky-200 bg-white shadow-2xs">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src="/logo.png" alt="AuraSEO Logo" className="h-full w-full object-contain p-0.5" />
                </div>
                <span className="text-base font-black tracking-tight text-slate-800">
                  Aura<span className="text-sky-600">SEO</span>
                </span>
              </div>
              <div className="p-3 overflow-y-auto max-h-[calc(100vh-4rem)]">
                <Sidebar />
              </div>
            </SheetContent>
          </Sheet>
        </div>

        {/* Desktop Expand Sidebar Button (when collapsed) */}
        {isSidebarCollapsed && onToggleSidebar && (
          <button
            type="button"
            onClick={onToggleSidebar}
            className="hidden md:inline-flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50/70 px-3 py-1.5 text-xs font-bold text-sky-700 hover:bg-sky-100 transition-colors"
            title="Mở lại menu chức năng bên trái"
          >
            <PanelLeftOpen className="h-4 w-4" />
            <span>Mở Menu Chức Năng</span>
          </button>
        )}

        {/* Quick Left Breadcrumb / Functional Switcher */}
        <div className="hidden lg:flex items-center gap-1.5 rounded-xl bg-sky-50/70 p-1 border border-sky-100">
          <Link
            href="/chatbot"
            className={cn(
              'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
              pathname.startsWith('/chatbot')
                ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-2xs'
                : 'text-slate-600 hover:text-sky-700 hover:bg-white/80'
            )}
          >
            <BotMessageSquare className="h-3.5 w-3.5" />
            <span>Trợ lý AI Aura</span>
          </Link>
          <Link
            href="/articles"
            className={cn(
              'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
              pathname.startsWith('/articles') || pathname.startsWith('/content-studio')
                ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-2xs'
                : 'text-slate-600 hover:text-sky-700 hover:bg-white/80'
            )}
          >
            <FileText className="h-3.5 w-3.5" />
            <span>Bài viết SEO</span>
          </Link>
          <Link
            href="/analytics"
            className={cn(
              'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
              pathname.startsWith('/analytics') || pathname.startsWith('/rank-tracker')
                ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-2xs'
                : 'text-slate-600 hover:text-sky-700 hover:bg-white/80'
            )}
          >
            <BarChart3 className="h-3.5 w-3.5" />
            <span>Phân tích & Thứ hạng</span>
          </Link>
        </div>
      </div>

      {/* Right Functional Actions */}
      <div className="flex items-center justify-end gap-2.5">
        {isSuperAdmin && (
          <Link
            href="/admin"
            className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-sky-700 bg-sky-50 border border-sky-200 rounded-xl hover:bg-sky-100 transition-colors"
          >
            <Shield className="w-3.5 h-3.5 text-sky-600" />
            <span>Admin Portal</span>
          </Link>
        )}

        <Link
          href="/settings/subscription"
          className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-sky-800 bg-gradient-to-r from-sky-50 to-blue-50 border border-sky-200/80 rounded-xl hover:from-sky-100 hover:to-blue-100 transition-colors"
        >
          <CreditCard className="w-3.5 h-3.5 text-sky-600" />
          <span>Gói cước & Credit</span>
        </Link>

        <DropdownMenu>
          <DropdownMenuTrigger className="relative flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50/50 p-1 pr-2.5 outline-none hover:bg-sky-100/60 focus-visible:ring-2 focus-visible:ring-sky-400 cursor-pointer transition-colors">
            <Avatar className="h-7 w-7 border border-sky-300">
              <AvatarImage src={user?.avatar_url || ''} alt={user?.name || 'User'} />
              <AvatarFallback className="bg-sky-600 text-white text-xs font-bold">
                {user?.name ? getInitials(user.name) : <UserCircle className="h-4 w-4" />}
              </AvatarFallback>
            </Avatar>
            <span className="hidden md:inline text-xs font-bold text-slate-700 max-w-[120px] truncate">
              {user?.name || 'Tài khoản'}
            </span>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-56 border-sky-100" align="end">
            <DropdownMenuLabel className="font-normal">
              <div className="flex flex-col space-y-1">
                <p className="text-sm font-bold leading-none text-slate-800">
                  {user?.name || 'User'}
                </p>
                <p className="text-xs leading-none text-slate-500">
                  {user?.email || 'user@example.com'}
                </p>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem className="p-0">
              <Link
                href="/settings/subscription"
                className="flex items-center gap-2 px-2 py-1.5 w-full cursor-pointer text-xs font-medium"
              >
                <CreditCard className="w-4 h-4 text-sky-600" />
                <span>Gói dịch vụ & Ví Credit</span>
              </Link>
            </DropdownMenuItem>
            {isSuperAdmin && (
              <DropdownMenuItem className="p-0">
                <Link
                  href="/admin"
                  className="flex items-center gap-2 px-2 py-1.5 w-full text-sky-700 cursor-pointer text-xs font-semibold"
                >
                  <Shield className="w-4 h-4" />
                  <span>Trang Quản trị Admin</span>
                </Link>
              </DropdownMenuItem>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => logout()}
              disabled={isLoggingOut}
              className="text-rose-600 cursor-pointer focus:text-rose-600 flex items-center gap-2 text-xs font-semibold"
            >
              <LogOut className="w-4 h-4" />
              <span>Đăng xuất</span>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
