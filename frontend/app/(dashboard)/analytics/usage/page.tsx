"use client";

import { Loader2, WalletCards, FileText, AlertTriangle } from 'lucide-react';
import { useUsageAnalytics } from '@/hooks/useAnalytics';

function ProgressCard({
  title,
  used,
  limit,
  suffix,
}: {
  title: string;
  used: number;
  limit: number | null;
  suffix?: string;
}) {
  const percent = limit && limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;
  const isWarning = percent >= 80;

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{title}</p>
          <p className="mt-2 text-3xl font-black text-slate-900">
            {suffix === '$' ? `$${used.toFixed(4)}` : used}
            <span className="text-base font-bold text-slate-400">
              {' / '}
              {limit === null ? 'unlimited' : suffix === '$' ? `$${limit.toFixed(2)}` : limit}
            </span>
          </p>
        </div>
        {isWarning ? <AlertTriangle className="h-6 w-6 text-amber-500" /> : <FileText className="h-6 w-6 text-blue-600" />}
      </div>
      <div className="mt-5 h-3 rounded-full bg-slate-100">
        <div
          className={`h-3 rounded-full ${isWarning ? 'bg-amber-500' : 'bg-blue-600'}`}
          style={{ width: `${limit === null ? 0 : percent}%` }}
        />
      </div>
      <p className="mt-2 text-sm font-medium text-slate-500">{limit === null ? 'Không giới hạn trong plan hiện tại' : `${percent}% đã dùng tháng này`}</p>
    </div>
  );
}

export default function UsageAnalyticsPage() {
  const { data, isLoading } = useUsageAnalytics();
  const usage = data as UsageAnalytics | undefined;

  if (isLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  if (!usage) {
    return <div className="rounded-2xl bg-white p-6 text-slate-600">Không tải được usage analytics.</div>;
  }

  const jobCosts = Object.entries(usage.cost_by_job_type);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">AI Cost & Quota</h1>
        <p className="mt-1 text-slate-500">Theo dõi số bài và chi phí AI theo billing cycle hiện tại.</p>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex items-center gap-3">
          <div className="rounded-xl bg-emerald-50 p-3 text-emerald-600">
            <WalletCards className="h-5 w-5" />
          </div>
          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Current plan</p>
            <p className="text-xl font-black capitalize text-slate-900">{usage.subscription.plan.name}</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <ProgressCard
          title="Articles"
          used={usage.quota.articlesUsed}
          limit={usage.quota.articlesLimit}
        />
        <ProgressCard
          title="AI Cost"
          used={usage.quota.costUsedUsd}
          limit={usage.quota.costLimitUsd}
          suffix="$"
        />
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-black text-slate-900">Cost by job type</h2>
        <div className="mt-4 space-y-3">
          {jobCosts.length === 0 ? (
            <p className="text-sm text-slate-500">Chưa có AI usage log trong tháng này.</p>
          ) : (
            jobCosts.map(([jobType, cost]) => (
              <div key={jobType} className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                <span className="font-bold text-slate-700">{jobType}</span>
                <span className="font-black text-slate-900">${cost.toFixed(6)}</span>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
