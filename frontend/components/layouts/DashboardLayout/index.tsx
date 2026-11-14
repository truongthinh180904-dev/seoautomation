'use client';

import React, { ReactNode, useState } from 'react';
import { Sidebar } from '../Sidebar';
import { Header } from '../Header';
import { PanelLeftClose } from 'lucide-react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';

interface DashboardLayoutProps {
  children: ReactNode;
}

export function DashboardLayout({ children }: DashboardLayoutProps) {
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
  const pathname = usePathname();
  const isFixedViewportPage = pathname?.startsWith('/chatbot');

  return (
    <div
      className={cn(
        'flex flex-col bg-gradient-to-br from-sky-50/40 via-white to-sky-50/30',
        isFixedViewportPage ? 'h-screen max-h-screen overflow-hidden' : 'min-h-screen'
      )}
    >
      <div className={cn('flex flex-1', isFixedViewportPage && 'min-h-0 overflow-hidden')}>
        <aside
          className={cn(
            'hidden flex-col border-r border-sky-100 bg-white/95 backdrop-blur-xs shadow-2xs transition-all duration-200 md:flex shrink-0 sticky top-0 h-screen max-h-screen overflow-hidden',
            isSidebarCollapsed ? 'w-0 overflow-hidden border-r-0' : 'w-64'
          )}
        >
          <div className="flex h-16 shrink-0 items-center justify-between border-b border-sky-100/80 px-5 bg-gradient-to-r from-sky-50/70 to-white">
            <Link href="/dashboard" className="flex items-center gap-3 group">
              <div className="relative flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl border border-sky-200/80 bg-white shadow-2xs group-hover:border-sky-400 group-hover:shadow-sm transition-all">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src="/logo.png"
                  alt="AuraSEO Logo"
                  className="h-full w-full object-contain p-0.5 transition-transform group-hover:scale-105"
                />
              </div>
              <div className="flex flex-col">
                <span className="text-base font-black tracking-tight text-slate-800 leading-none">
                  Aura<span className="text-sky-600">SEO</span>
                </span>
                <span className="text-[10px] font-bold text-sky-600/80 tracking-wide mt-1">
                  AI Content & SEO Suite
                </span>
              </div>
            </Link>
            <button
              type="button"
              onClick={() => setIsSidebarCollapsed(true)}
              className="rounded-lg p-1.5 text-slate-400 hover:bg-sky-50 hover:text-sky-600 transition-colors"
              title="Thu gọn menu trái"
            >
              <PanelLeftClose className="h-4 w-4" />
            </button>
          </div>
          <div className="flex-1 min-h-0 overflow-y-auto px-3 py-4">
            <Sidebar />
          </div>
        </aside>

        <main
          className={cn(
            'flex w-full flex-col flex-1 min-w-0',
            isFixedViewportPage && 'h-screen max-h-screen overflow-hidden'
          )}
        >
          <Header
            isSidebarCollapsed={isSidebarCollapsed}
            onToggleSidebar={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
          />
          <div
            className={cn(
              'flex-1',
              isFixedViewportPage
                ? 'flex flex-col min-h-0 overflow-hidden p-2 md:p-3'
                : 'p-4 md:p-6 md:pt-4'
            )}
          >
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
