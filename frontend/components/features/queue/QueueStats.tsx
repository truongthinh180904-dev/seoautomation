import React from 'react';
import type { QueueStat } from '@/hooks/useQueueStatus';

interface QueueStatsProps {
  stats: QueueStat[];
  isLoading: boolean;
}

function StatCard({ stat }: { stat: QueueStat }) {
  const total = stat.pending + stat.processing + stat.failed;

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-shadow">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-bold text-slate-800">{stat.label}</h3>
        <span className="text-xs font-mono bg-slate-100 text-slate-500 px-2.5 py-1 rounded-full">{stat.name}</span>
      </div>

      <div className="grid grid-cols-3 gap-3">
        <div className="text-center p-3 bg-amber-50 rounded-xl border border-amber-100">
          <div className="text-2xl font-black text-amber-600">{stat.pending}</div>
          <div className="text-xs font-bold text-amber-500 mt-1 uppercase tracking-wide">Chờ</div>
        </div>
        <div className="text-center p-3 bg-blue-50 rounded-xl border border-blue-100">
          <div className={`text-2xl font-black text-blue-600 ${stat.processing > 0 ? 'animate-pulse' : ''}`}>
            {stat.processing}
          </div>
          <div className="text-xs font-bold text-blue-500 mt-1 uppercase tracking-wide">Đang chạy</div>
        </div>
        <div className="text-center p-3 bg-red-50 rounded-xl border border-red-100">
          <div className="text-2xl font-black text-red-600">{stat.failed}</div>
          <div className="text-xs font-bold text-red-500 mt-1 uppercase tracking-wide">Lỗi</div>
        </div>
      </div>

      {total > 0 && (
        <div className="mt-4 h-1.5 bg-slate-100 rounded-full overflow-hidden flex">
          {stat.pending > 0 && (
            <div className="bg-amber-400 h-full transition-all" style={{ width: `${(stat.pending / total) * 100}%` }} />
          )}
          {stat.processing > 0 && (
            <div className="bg-blue-500 h-full transition-all" style={{ width: `${(stat.processing / total) * 100}%` }} />
          )}
          {stat.failed > 0 && (
            <div className="bg-red-500 h-full transition-all" style={{ width: `${(stat.failed / total) * 100}%` }} />
          )}
        </div>
      )}
    </div>
  );
}

export default function QueueStats({ stats, isLoading }: QueueStatsProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="bg-white rounded-2xl border border-slate-200 h-44 animate-pulse" />
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {stats.map((stat) => (
        <StatCard key={stat.name} stat={stat} />
      ))}
    </div>
  );
}
