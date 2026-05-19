"use client";

import React from 'react';
import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid } from 'recharts';
import { useAnalyticsSummary, useFailingAgents } from '@/hooks/useAnalytics';

const STATUS_COLORS: Record<string, string> = {
  draft:      '#94a3b8',
  review:     '#3b82f6',
  approved:   '#10b981',
  publishing: '#a855f7',
  published:  '#22c55e',
  rejected:   '#f97316',
  failed:     '#ef4444',
};

const STATUS_LABELS: Record<string, string> = {
  draft: 'Nháp', review: 'Chờ duyệt', approved: 'Đã duyệt',
  publishing: 'Đang đăng', published: 'Đã đăng', rejected: 'Từ chối', failed: 'Lỗi',
};

interface PublishingStatsProps {
  days: number;
}

interface FailingAgentRow {
  agent_type?: string;
  count?: number;
}

export default function PublishingStats({ days }: PublishingStatsProps) {
  const summary = useAnalyticsSummary(days);
  const agents = useFailingAgents(days);

  const donutData = Object.entries(summary.data?.by_status ?? {}).map(([status, count]) => ({
    name: STATUS_LABELS[status] ?? status,
    value: count as number,
    color: STATUS_COLORS[status] ?? '#cbd5e1',
  })).filter(d => d.value > 0);

  const agentData = ((agents.data?.data ?? []) as FailingAgentRow[]).map((row) => ({
    agent: row.agent_type?.replace(/_/g, ' '),
    lỗi: row.count ?? 0,
  }));

  const kpiCards = [
    { label: 'Tổng bài viết', value: summary.data?.total ?? '--', color: 'bg-blue-50 text-blue-700' },
    { label: 'Điểm SEO TB', value: summary.data?.avg_seo ? `${summary.data.avg_seo}` : '--', color: 'bg-emerald-50 text-emerald-700' },
    { label: 'Số từ TB', value: summary.data?.avg_words ? `${(summary.data.avg_words).toLocaleString()}` : '--', color: 'bg-purple-50 text-purple-700' },
    { label: 'Chi phí AI', value: summary.data?.total_cost ? `$${summary.data.total_cost}` : '$0', color: 'bg-amber-50 text-amber-700' },
  ];

  return (
    <div className="space-y-6">
      {/* KPI cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {kpiCards.map((kpi) => (
          <div key={kpi.label} className={`${kpi.color} rounded-2xl border p-5`} style={{ borderColor: 'rgba(0,0,0,0.06)' }}>
            {summary.isLoading
              ? <div className="h-8 bg-white/60 rounded animate-pulse mb-2 w-16" />
              : <div className="text-3xl font-black">{kpi.value}</div>
            }
            <div className="text-xs font-bold mt-1.5 opacity-80 uppercase tracking-wide">{kpi.label}</div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Donut chart */}
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
          <h3 className="font-extrabold text-slate-900 mb-4">Bài viết theo trạng thái</h3>
          {donutData.length > 0 ? (
            <ResponsiveContainer width="100%" height={240}>
              <PieChart>
                <Pie data={donutData} cx="50%" cy="50%" innerRadius={60} outerRadius={95} paddingAngle={3} dataKey="value">
                  {donutData.map((entry, i) => (
                    <Cell key={i} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip formatter={(v: unknown) => [`${v} bài`, '']} />
                <Legend iconType="circle" iconSize={10} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-60 flex items-center justify-center text-slate-400 font-medium">Chưa có dữ liệu</div>
          )}
        </div>

        {/* Failing agents */}
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
          <h3 className="font-extrabold text-slate-900 mb-4">Agent hay thất bại nhất</h3>
          {agentData.length > 0 ? (
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={agentData} layout="vertical" margin={{ left: 16 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                <YAxis type="category" dataKey="agent" tick={{ fontSize: 11, fill: '#64748b' }} width={110} />
                <Tooltip />
                <Bar dataKey="lỗi" fill="#ef4444" radius={[0, 6, 6, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-60 flex items-center justify-center text-slate-400 font-medium">🎉 Không có agent lỗi!</div>
          )}
        </div>
      </div>
    </div>
  );
}
