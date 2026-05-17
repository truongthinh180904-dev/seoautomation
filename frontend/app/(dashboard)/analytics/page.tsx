"use client";

import { useState } from 'react';
import AIUsageChart from '@/components/features/analytics/AIUsageChart';
import PublishingStats from '@/components/features/analytics/PublishingStats';
import { BarChart2, Calendar } from 'lucide-react';

const DATE_RANGES = [
  { label: '7 ngày', value: 7 },
  { label: '30 ngày', value: 30 },
  { label: '90 ngày', value: 90 },
];

export default function AnalyticsPage() {
  const [days, setDays] = useState(30);

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
            <span className="p-2 bg-blue-100 rounded-xl text-blue-600"><BarChart2 className="w-6 h-6" /></span>
            Analytics
          </h1>
          <p className="text-slate-500 mt-1 font-medium">Tổng quan hiệu suất AI Pipeline và chi phí vận hành</p>
        </div>

        {/* Date range filter */}
        <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl p-1.5 shadow-sm">
          <Calendar className="w-4 h-4 text-slate-400 ml-2" />
          {DATE_RANGES.map((range) => (
            <button
              key={range.value}
              onClick={() => setDays(range.value)}
              className={`px-4 py-2 rounded-xl text-sm font-bold transition-all ${
                days === range.value
                  ? 'bg-blue-600 text-white shadow-sm'
                  : 'text-slate-600 hover:bg-slate-100'
              }`}
            >
              {range.label}
            </button>
          ))}
        </div>
      </div>

      {/* Summary stats + charts */}
      <PublishingStats days={days} />

      {/* AI Cost Chart */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-extrabold text-slate-900">Chi phí AI theo ngày</h2>
          <span className="text-sm font-medium text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full">
            {days} ngày gần nhất
          </span>
        </div>
        <AIUsageChart days={days} />
      </div>
    </div>
  );
}
