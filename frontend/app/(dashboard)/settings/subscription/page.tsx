"use client";

import Link from 'next/link';
import { CreditCard, FileText, WalletCards } from 'lucide-react';
import { useUsageAnalytics } from '@/hooks/useAnalytics';
import { Button } from '@/components/ui/button';

export default function SubscriptionSettingsPage() {
  const { data } = useUsageAnalytics();
  const usage = data as UsageAnalytics | undefined;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Subscription</h1>
        <p className="mt-1 text-slate-500">Plan, quota và giới hạn dùng AI của tenant hiện tại.</p>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <WalletCards className="h-6 w-6 text-blue-600" />
          <div className="mt-4 text-xs font-bold uppercase tracking-widest text-slate-400">Current plan</div>
          <div className="mt-1 text-3xl font-black capitalize text-slate-900">{usage?.subscription.plan.name ?? 'starter'}</div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <FileText className="h-6 w-6 text-emerald-600" />
          <div className="mt-4 text-xs font-bold uppercase tracking-widest text-slate-400">Articles</div>
          <div className="mt-1 text-3xl font-black text-slate-900">
            {usage?.quota.articlesUsed ?? 0}
            <span className="text-base font-bold text-slate-400"> / {usage?.quota.articlesLimit ?? '∞'}</span>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <CreditCard className="h-6 w-6 text-amber-600" />
          <div className="mt-4 text-xs font-bold uppercase tracking-widest text-slate-400">AI budget</div>
          <div className="mt-1 text-3xl font-black text-slate-900">
            ${(usage?.quota.costUsedUsd ?? 0).toFixed(4)}
            <span className="text-base font-bold text-slate-400"> / {usage?.quota.costLimitUsd ? `$${usage.quota.costLimitUsd}` : '∞'}</span>
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-black text-slate-900">Usage detail</h2>
        <p className="mt-2 text-sm text-slate-500">Trang usage có breakdown theo job type và progress bar quota.</p>
        <Link href="/analytics/usage">
          <Button className="mt-4 bg-blue-600 hover:bg-blue-700">View usage dashboard</Button>
        </Link>
      </div>
    </div>
  );
}
