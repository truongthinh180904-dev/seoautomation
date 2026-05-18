"use client";

import React from 'react';
import {
  Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend, AreaChart, Area
} from 'recharts';
import { useAICosts } from '@/hooks/useAnalytics';

interface AIUsageChartProps {
  days: number;
}

interface AICostRow {
  date?: string;
  total_cost?: number | string;
  calls?: number;
  tokens?: number;
}

interface ChartTooltipProps {
  active?: boolean;
  payload?: Array<{ value?: number | string }>;
  label?: string;
}

function CustomTooltip({ active, payload, label }: ChartTooltipProps) {
  if (!active || !payload?.length) return null;

  return (
    <div className="bg-white border border-slate-200 rounded-xl shadow-lg p-3 text-sm">
      <p className="font-bold text-slate-700 mb-1">{label}</p>
      <p className="text-blue-600">Chi phí: <strong>${payload[0]?.value}</strong></p>
      <p className="text-purple-500">Lượt gọi: <strong>{payload[1]?.value}</strong></p>
    </div>
  );
}

export default function AIUsageChart({ days }: AIUsageChartProps) {
  const { data, isLoading } = useAICosts(days);

  const chartData = ((data?.data ?? []) as AICostRow[]).map((row) => ({
    date: row.date?.slice(5), // "MM-DD"
    cost: parseFloat(Number(row.total_cost).toFixed(4)),
    calls: row.calls ?? 0,
    tokens: row.tokens ?? 0,
  }));

  if (isLoading) {
    return <div className="h-72 bg-slate-50 rounded-2xl animate-pulse flex items-center justify-center text-slate-400 font-medium">Đang tải biểu đồ...</div>;
  }

  if (!chartData.length) {
    return <div className="h-72 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400 font-medium">Chưa có dữ liệu AI trong khoảng thời gian này.</div>;
  }

  return (
    <ResponsiveContainer width="100%" height={288}>
      <AreaChart data={chartData} margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
        <defs>
          <linearGradient id="costGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.15} />
            <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
          </linearGradient>
        </defs>
        <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
        <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#94a3b8' }} />
        <YAxis tick={{ fontSize: 11, fill: '#94a3b8' }} tickFormatter={v => `$${v}`} />
        <Tooltip content={<CustomTooltip />} />
        <Legend />
        <Area type="monotone" dataKey="cost" name="Chi phí (USD)" stroke="#3b82f6" fill="url(#costGrad)" strokeWidth={2} dot={false} />
        <Line type="monotone" dataKey="calls" name="Lượt gọi" stroke="#a855f7" strokeWidth={1.5} dot={false} yAxisId={0} />
      </AreaChart>
    </ResponsiveContainer>
  );
}
