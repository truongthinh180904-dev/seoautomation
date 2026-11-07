"use client";

import React, { useState } from 'react';
import {
  ComposedChart,
  Area,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';
import {
  TrendingUp,
  TrendingDown,
  MousePointerClick,
  Eye,
  Percent,
  Search,
  Activity,
  Users,
  Target,
  Clock,
} from 'lucide-react';
import { SeoPerformanceOverviewData } from '@/hooks/useSeoPerformance';

interface SeoPerformanceOverviewProps {
  data?: SeoPerformanceOverviewData;
  isLoading?: boolean;
}

interface ChartTooltipPayloadItem {
  value?: number | string;
  name?: string;
  color?: string;
}

interface CustomChartTooltipProps {
  active?: boolean;
  payload?: ChartTooltipPayloadItem[];
  label?: string;
}

function CustomTooltip({ active, payload, label }: CustomChartTooltipProps) {
  if (!active || !payload?.length) return null;

  return (
    <div className="bg-white border border-slate-200 rounded-xl shadow-lg p-3 text-xs">
      <p className="font-bold text-slate-800 mb-1.5 border-b border-slate-100 pb-1">{label}</p>
      <div className="space-y-1">
        {payload.map((item, idx) => (
          <div key={idx} className="flex items-center justify-between gap-4">
            <span className="flex items-center gap-1.5 text-slate-600">
              <span className="w-2 h-2 rounded-full" style={{ backgroundColor: item.color }} />
              {item.name}:
            </span>
            <span className="font-bold text-slate-900">
              {typeof item.value === 'number' ? item.value.toLocaleString() : item.value}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}

export default function SeoPerformanceOverview({ data, isLoading }: SeoPerformanceOverviewProps) {
  const [metricTab, setMetricTab] = useState<'gsc' | 'ga4'>('gsc');

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-28 bg-slate-100 rounded-2xl animate-pulse" />
          ))}
        </div>
        <div className="h-80 bg-slate-100 rounded-2xl animate-pulse" />
      </div>
    );
  }

  const summary = data?.summary;
  const timeseries = data?.timeseries ?? [];

  const chartData = timeseries.map((pt) => ({
    date: pt.date.slice(5), // MM-DD
    clicks: pt.clicks,
    impressions: pt.impressions,
    ctr: Number((pt.ctr * 100).toFixed(2)),
    position: pt.position,
    sessions: pt.sessions,
    active_users: pt.active_users,
    conversions: pt.conversions,
  }));

  const renderDelta = (delta?: number, isPositiveGood = true) => {
    if (delta === undefined || isNaN(delta)) return null;

    const isPositive = delta > 0;
    const isZero = delta === 0;

    let isFavorable = isPositive;
    if (!isPositiveGood) {
      isFavorable = !isPositive;
    }

    const colorClass = isZero
      ? 'text-slate-500 bg-slate-100'
      : isFavorable
      ? 'text-emerald-700 bg-emerald-50'
      : 'text-rose-700 bg-rose-50';

    return (
      <span className={`inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold ${colorClass}`}>
        {!isZero && (isPositive ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />)}
        {isPositive ? `+${delta}%` : `${delta}%`}
      </span>
    );
  };

  interface KpiCardItem {
    label: string;
    value: string;
    delta?: number;
    icon: React.ReactNode;
    bg: string;
    isPosition?: boolean;
  }

  const gscKpiCards: KpiCardItem[] = [
    {
      label: 'Tổng lượt nhấp (Clicks)',
      value: summary?.clicks.current.toLocaleString() ?? '0',
      delta: summary?.clicks.delta_percent,
      icon: <MousePointerClick className="w-5 h-5 text-blue-600" />,
      bg: 'bg-blue-50/70 border-blue-100',
    },
    {
      label: 'Tổng lượt hiển thị (Impressions)',
      value: summary?.impressions.current.toLocaleString() ?? '0',
      delta: summary?.impressions.delta_percent,
      icon: <Eye className="w-5 h-5 text-indigo-600" />,
      bg: 'bg-indigo-50/70 border-indigo-100',
    },
    {
      label: 'Tỷ lệ CTR trung bình',
      value: summary ? `${(summary.ctr.current * 100).toFixed(2)}%` : '0.00%',
      delta: summary?.ctr.delta_percent,
      icon: <Percent className="w-5 h-5 text-emerald-600" />,
      bg: 'bg-emerald-50/70 border-emerald-100',
    },
    {
      label: 'Vị trí Google TB',
      value: summary?.position.current ? summary.position.current.toFixed(1) : '0.0',
      delta: summary?.position.delta_value,
      isPosition: true,
      icon: <Search className="w-5 h-5 text-amber-600" />,
      bg: 'bg-amber-50/70 border-amber-100',
    },
  ];

  const ga4KpiCards: KpiCardItem[] = [
    {
      label: 'Phiên truy cập (Sessions)',
      value: summary?.sessions.current.toLocaleString() ?? '0',
      delta: summary?.sessions.delta_percent,
      icon: <Activity className="w-5 h-5 text-cyan-600" />,
      bg: 'bg-cyan-50/70 border-cyan-100',
    },
    {
      label: 'Người dùng hoạt động',
      value: summary?.active_users.current.toLocaleString() ?? '0',
      delta: summary?.active_users.delta_percent,
      icon: <Users className="w-5 h-5 text-violet-600" />,
      bg: 'bg-violet-50/70 border-violet-100',
    },
    {
      label: 'Tỷ lệ tương tác',
      value: summary ? `${(summary.engagement_rate.current * 100).toFixed(1)}%` : '0.0%',
      delta: summary?.engagement_rate.delta_percent,
      icon: <Clock className="w-5 h-5 text-emerald-600" />,
      bg: 'bg-emerald-50/70 border-emerald-100',
    },
    {
      label: 'Lượt chuyển đổi',
      value: summary?.conversions.current.toLocaleString() ?? '0',
      delta: summary?.conversions.delta_percent,
      icon: <Target className="w-5 h-5 text-pink-600" />,
      bg: 'bg-pink-50/70 border-pink-100',
    },
  ];

  const activeCards = metricTab === 'gsc' ? gscKpiCards : ga4KpiCards;

  return (
    <div className="space-y-6">
      {/* Switcher & Period Indicator */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div className="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200">
          <button
            type="button"
            onClick={() => setMetricTab('gsc')}
            className={`px-4 py-2 rounded-xl text-xs font-extrabold transition-all ${
              metricTab === 'gsc'
                ? 'bg-white text-blue-600 shadow-sm'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Google Search Console
          </button>
          <button
            type="button"
            onClick={() => setMetricTab('ga4')}
            className={`px-4 py-2 rounded-xl text-xs font-extrabold transition-all ${
              metricTab === 'ga4'
                ? 'bg-white text-blue-600 shadow-sm'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Google Analytics 4
          </button>
        </div>

        {data?.compare && data.compare_date_from && (
          <span className="text-xs font-medium text-slate-500 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl">
            Đối chiếu với kỳ trước ({data.compare_date_from} → {data.compare_date_to})
          </span>
        )}
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {activeCards.map((card, idx) => (
          <div
            key={idx}
            className={`rounded-2xl border p-5 transition-shadow hover:shadow-md ${card.bg}`}
          >
            <div className="flex items-center justify-between mb-3">
              <span className="p-2.5 bg-white rounded-xl shadow-xs border border-slate-100">
                {card.icon}
              </span>
              {card.isPosition ? (
                card.delta !== undefined ? (
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${
                      card.delta < 0
                        ? 'text-emerald-700 bg-emerald-50'
                        : card.delta > 0
                        ? 'text-rose-700 bg-rose-50'
                        : 'text-slate-500 bg-slate-100'
                    }`}
                  >
                    {card.delta < 0 ? `▲ ${Math.abs(card.delta)}` : card.delta > 0 ? `▼ ${card.delta}` : '0.0'}
                  </span>
                ) : null
              ) : (
                renderDelta(card.delta)
              )}
            </div>
            <div className="text-2xl font-black text-slate-900 tracking-tight">{card.value}</div>
            <div className="text-xs font-bold text-slate-500 mt-1 uppercase tracking-wide">
              {card.label}
            </div>
          </div>
        ))}
      </div>

      {/* Recharts Area Chart */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h3 className="text-base font-extrabold text-slate-900">
              {metricTab === 'gsc'
                ? 'Biểu đồ Hiệu suất Tìm kiếm (Clicks & Impressions)'
                : 'Biểu đồ Truy cập Website (Sessions & Active Users)'}
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              Dữ liệu chuỗi thời gian phân tách theo từng ngày
            </p>
          </div>
        </div>

        {chartData.length > 0 ? (
          <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
              {metricTab === 'gsc' ? (
                <ComposedChart data={chartData} margin={{ top: 8, right: 12, left: -10, bottom: 0 }}>
                  <defs>
                    <linearGradient id="clicksGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#2563eb" stopOpacity={0.25} />
                      <stop offset="95%" stopColor="#2563eb" stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="impGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#8b5cf6" stopOpacity={0.15} />
                      <stop offset="95%" stopColor="#8b5cf6" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                  <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#64748b' }} />
                  <YAxis yAxisId="left" tick={{ fontSize: 11, fill: '#64748b' }} />
                  <YAxis
                    yAxisId="right"
                    orientation="right"
                    tick={{ fontSize: 11, fill: '#8b5cf6' }}
                  />
                  <Tooltip content={<CustomTooltip />} />
                  <Legend />
                  <Area
                    yAxisId="left"
                    type="monotone"
                    dataKey="clicks"
                    name="Lượt nhấp (Clicks)"
                    stroke="#2563eb"
                    fill="url(#clicksGrad)"
                    strokeWidth={2.5}
                  />
                  <Line
                    yAxisId="right"
                    type="monotone"
                    dataKey="impressions"
                    name="Lượt hiển thị (Impressions)"
                    stroke="#8b5cf6"
                    strokeWidth={2}
                    dot={false}
                  />
                </ComposedChart>
              ) : (
                <ComposedChart data={chartData} margin={{ top: 8, right: 12, left: -10, bottom: 0 }}>
                  <defs>
                    <linearGradient id="sessionsGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#0891b2" stopOpacity={0.25} />
                      <stop offset="95%" stopColor="#0891b2" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                  <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#64748b' }} />
                  <YAxis tick={{ fontSize: 11, fill: '#64748b' }} />
                  <Tooltip content={<CustomTooltip />} />
                  <Legend />
                  <Area
                    type="monotone"
                    dataKey="sessions"
                    name="Phiên truy cập (Sessions)"
                    stroke="#0891b2"
                    fill="url(#sessionsGrad)"
                    strokeWidth={2.5}
                  />
                  <Line
                    type="monotone"
                    dataKey="active_users"
                    name="Người dùng hoạt động"
                    stroke="#7c3aed"
                    strokeWidth={2}
                    dot={false}
                  />
                </ComposedChart>
              )}
            </ResponsiveContainer>
          </div>
        ) : (
          <div className="h-64 flex items-center justify-center text-slate-400 text-sm font-medium">
            Chưa có dữ liệu chuỗi thời gian cho khoảng ngày này.
          </div>
        )}
      </div>
    </div>
  );
}
