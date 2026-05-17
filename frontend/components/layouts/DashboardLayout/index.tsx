import { ReactNode } from 'react';
import { Sidebar } from '../Sidebar';
import { Header } from '../Header';

interface DashboardLayoutProps {
  children: ReactNode;
}

export function DashboardLayout({ children }: DashboardLayoutProps) {
  return (
    <div className="flex min-h-screen flex-col bg-muted/20">
      <div className="flex flex-1">
        <aside className="hidden w-64 flex-col border-r bg-background md:flex">
          <div className="flex h-16 items-center border-b px-6">
            <span className="text-lg font-bold tracking-tight">AI SEO</span>
          </div>
          <div className="flex-1 overflow-y-auto p-4">
            <Sidebar />
          </div>
        </aside>
        
        <main className="flex w-full flex-col flex-1 min-w-0">
          <Header />
          <div className="flex-1 p-4 md:p-6 md:pt-4">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
