'use client';

import React from 'react';
import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from 'recharts';
import { RankTrackingHistoryPoint, TrackedKeywordItem } from '@/hooks/useRankTracker';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TrendingUp, TrendingDown, Minus, ExternalLink } from 'lucide-react';

interface RankHistoryChartProps {
  keyword: TrackedKeywordItem;
  historyData: RankTrackingHistoryPoint[];
  isLoading?: boolean;
}

interface TooltipPayloadItem {
  value?: number | string | null;
  name?: string;
  payload?: {
    check_date: string;
    position: number | null;
    found_url: string | null;
  };
}

interface CustomTooltipProps {
  active?: boolean;
  payload?: TooltipPayloadItem[];
  label?: string;
}

function CustomChartTooltip({ active, payload }: CustomTooltipProps) {
  if (!active || !payload?.length) return null;

  const point = payload[0]?.payload;
  if (!point) return null;

  return (
    <div className="bg-white border border-slate-200 rounded-xl shadow-lg p-3 text-xs min-w-[200px]">
      <div className="font-bold text-slate-800 border-b border-slate-100 pb-1 mb-1.5 flex justify-between items-center">
        <span>{point.check_date}</span>
        <Badge
          variant="outline"
          className={
            point.position && point.position <= 10
              ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold'
              : 'bg-slate-50 text-slate-700 font-semibold'
          }
        >
          {point.position ? `Hạng #${point.position}` : 'Ngoài Top 100'}
        </Badge>
      </div>
      {point.found_url && (
        <div className="text-slate-500 truncate max-w-[240px]">
          <span className="font-semibold text-slate-700">URL:</span> {point.found_url}
        </div>
      )}
    </div>
  );
}

export default function RankHistoryChart({
  keyword,
  historyData,
  isLoading,
}: RankHistoryChartProps) {
  const chartPoints = historyData.map((item) => ({
    check_date: item.check_date,
    position: item.position ?? null,
    found_url: item.found_url,
  }));

  const delta = keyword.delta_rank;

  return (
    <Card className="border border-slate-200 shadow-xs">
      <CardHeader className="pb-3 border-b border-slate-100">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <div className="flex items-center gap-2">
              <CardTitle className="text-base font-bold text-slate-900">
                {keyword.keyword}
              </CardTitle>
              <Badge variant="secondary" className="text-xs uppercase font-semibold">
                {keyword.device} • {keyword.country}
              </Badge>
            </div>
            <CardDescription className="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
              <span>Mục tiêu:</span>
              {keyword.target_url ? (
                <a
                  href={keyword.target_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-indigo-600 hover:underline flex items-center gap-1 truncate max-w-sm"
                >
                  {keyword.target_url}
                  <ExternalLink className="w-3 h-3 inline" />
                </a>
              ) : (
                <span className="italic text-slate-400">Toàn bộ domain</span>
              )}
            </CardDescription>
          </div>

          <div className="flex items-center gap-3">
            <div className="text-right">
              <div className="text-xs text-slate-500 font-medium">Hiện tại</div>
              <div className="text-xl font-extrabold text-slate-900">
                {keyword.current_position ? `#${keyword.current_position}` : '>100'}
              </div>
            </div>

            <div className="text-right pl-3 border-l border-slate-200">
              <div className="text-xs text-slate-500 font-medium">Biến động</div>
              <div className="flex items-center justify-end font-bold text-sm">
                {delta !== null && delta > 0 && (
                  <span className="text-emerald-600 flex items-center">
                    <TrendingUp className="w-4 h-4 mr-0.5" />
                    +{delta}
                  </span>
                )}
                {delta !== null && delta < 0 && (
                  <span className="text-rose-600 flex items-center">
                    <TrendingDown className="w-4 h-4 mr-0.5" />
                    {delta}
                  </span>
                )}
                {(delta === 0 || delta === null) && (
                  <span className="text-slate-400 flex items-center">
                    <Minus className="w-4 h-4 mr-0.5" />0
                  </span>
                )}
              </div>
            </div>
          </div>
        </div>
      </CardHeader>

      <CardContent className="pt-4">
        {isLoading ? (
          <div className="h-64 bg-slate-50 rounded-xl animate-pulse flex items-center justify-center text-slate-400 text-sm">
            Đang tải dữ liệu biểu đồ...
          </div>
        ) : chartPoints.length === 0 ? (
          <div className="h-64 flex flex-col items-center justify-center text-center p-6 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
            <p className="text-sm font-semibold text-slate-600">
              Chưa có dữ liệu lịch sử cho từ khóa này.
            </p>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">
              Hệ thống sẽ tự động cập nhật vị trí hàng ngày hoặc bạn có thể bấm &quot;Làm mới&quot;
              để kiểm tra ngay lập tức.
            </p>
          </div>
        ) : (
          <div className="w-full h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart
                data={chartPoints}
                margin={{ top: 10, right: 20, left: -10, bottom: 0 }}
              >
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E2E8F0" />
                <XAxis
                  dataKey="check_date"
                  tickLine={false}
                  axisLine={{ stroke: '#CBD5E1' }}
                  tick={{ fill: '#64748B', fontSize: 11 }}
                  dy={5}
                />
                <YAxis
                  reversed={true}
                  domain={[1, 100]}
                  tickLine={false}
                  axisLine={{ stroke: '#CBD5E1' }}
                  tick={{ fill: '#64748B', fontSize: 11 }}
                  tickFormatter={(val: number) => `#${val}`}
                />
                <Tooltip content={<CustomChartTooltip />} />
                <Line
                  type="monotone"
                  dataKey="position"
                  stroke="#4F46E5"
                  strokeWidth={2.5}
                  dot={{ r: 4, fill: '#4F46E5', strokeWidth: 2, stroke: '#FFFFFF' }}
                  activeDot={{ r: 6, fill: '#4338CA' }}
                  connectNulls={false}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
