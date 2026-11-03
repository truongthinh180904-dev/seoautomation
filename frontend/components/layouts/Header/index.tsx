'use client';

import { Menu, UserCircle, Shield, CreditCard, LogOut } from 'lucide-react';
import { Sheet, SheetContent, SheetTrigger, SheetTitle } from '@/components/ui/sheet';
import { Sidebar } from '../Sidebar';
import { useAuth } from '@/hooks/useAuth';
import Link from 'next/link';
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuLabel, 
  DropdownMenuSeparator, 
  DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useAuthStore } from '@/stores/authStore';

export function Header() {
  const { logout, isLoggingOut } = useAuth();
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
    <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b bg-background px-4 md:px-6">
      <div className="flex items-center gap-4 md:hidden">
        <Sheet>
          <SheetTrigger className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-input bg-background text-sm font-medium hover:bg-accent md:hidden cursor-pointer">
            <Menu className="h-5 w-5" />
            <span className="sr-only">Toggle navigation menu</span>
          </SheetTrigger>
          <SheetContent side="left" className="w-64 p-0">
            <SheetTitle className="sr-only">Navigation Menu</SheetTitle>
            <div className="flex h-16 items-center border-b px-6">
              <span className="text-lg font-bold tracking-tight">AI SEO</span>
            </div>
            <div className="p-4">
              <Sidebar />
            </div>
          </SheetContent>
        </Sheet>
      </div>

      <div className="flex w-full items-center justify-end gap-4">
        {isSuperAdmin && (
          <Link
            href="/admin"
            className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors"
          >
            <Shield className="w-3.5 h-3.5" />
            Admin Portal
          </Link>
        )}

        <Link
          href="/settings/subscription"
          className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors"
        >
          <CreditCard className="w-3.5 h-3.5 text-slate-500" />
          Gói cước & Credit
        </Link>

        <DropdownMenu>
          <DropdownMenuTrigger className="relative flex h-8 w-8 items-center justify-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer">
            <Avatar className="h-8 w-8">
              <AvatarImage src={user?.avatar_url || ''} alt={user?.name || 'User'} />
              <AvatarFallback>{user?.name ? getInitials(user.name) : <UserCircle className="h-5 w-5" />}</AvatarFallback>
            </Avatar>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-56" align="end">
            <DropdownMenuLabel className="font-normal">
              <div className="flex flex-col space-y-1">
                <p className="text-sm font-medium leading-none">{user?.name || 'User'}</p>
                <p className="text-xs leading-none text-muted-foreground">
                  {user?.email || 'user@example.com'}
                </p>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem className="p-0">
              <Link href="/settings/subscription" className="flex items-center gap-2 px-1.5 py-1 w-full cursor-pointer">
                <CreditCard className="w-4 h-4 text-slate-500" />
                <span>Gói dịch vụ & Ví Credit</span>
              </Link>
            </DropdownMenuItem>
            {isSuperAdmin && (
              <DropdownMenuItem className="p-0">
                <Link href="/admin" className="flex items-center gap-2 px-1.5 py-1 w-full text-purple-700 cursor-pointer font-medium">
                  <Shield className="w-4 h-4" />
                  <span>Trang Quản trị Admin</span>
                </Link>
              </DropdownMenuItem>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuItem 
              onClick={() => logout()} 
              disabled={isLoggingOut} 
              className="text-red-600 cursor-pointer focus:text-red-600 flex items-center gap-2"
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
