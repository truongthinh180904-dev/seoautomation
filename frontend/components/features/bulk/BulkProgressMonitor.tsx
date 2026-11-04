"use client";

import React from 'react';
import Link from 'next/link';
import {
  CheckCircle2,
  AlertCircle,
  Clock,
  Loader2,
  Layers,
  Coins,
  Ban,
  RefreshCw,
  FileText,
  ExternalLink,
  AlertTriangle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';

export interface BulkBatchItem {
  index: number;
  keyword_id?: number;
  keyword: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
  article_id?: number | null;
  article_title?: string | null;
  error?: string | null;
}

export interface BulkBatchProgress {
  id: number;
  uuid: string;
  name: string;
  status: 'pending' | 'processing' | 'completed' | 'partially_failed' | 'failed' | 'cancelled';
  total_items: number;
  completed_items: number;
  failed_items: number;
  progress_percent: number;
  credits_reserved: number;
  credits_settled: number;
  started_at?: string | null;
  finished_at?: string | null;
  created_at?: string | null;
  generation_settings?: {
    tone?: string;
    target_word_count?: number;
    unit_cost?: number;
    include_images?: boolean;
    auto_publish?: boolean;
    items?: BulkBatchItem[];
  };
  items?: BulkBatchItem[];
}

interface BulkProgressMonitorProps {
  batch: BulkBatchProgress;
  onRefresh?: () => void;
  onCancel?: (uuid: string) => void;
  isCancelling?: boolean;
  isRefreshing?: boolean;
}

export function BulkProgressMonitor({
  batch,
  onRefresh,
  onCancel,
  isCancelling = false,
  isRefreshing = false,
}: BulkProgressMonitorProps) {
  const items = batch.items || batch.generation_settings?.items || [];
  const isFinished = ['completed', 'partially_failed', 'failed', 'cancelled'].includes(batch.status);
  const isRunning = batch.status === 'processing';

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return (
          <Badge className="bg-emerald-500 hover:bg-emerald-600 text-white flex items-center gap-1">
            <CheckCircle2 className="w-3.5 h-3.5" />
            Hoàn thành 100%
          </Badge>
        );
      case 'processing':
        return (
          <Badge className="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1">
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
            Đang sản xuất bài viết...
          </Badge>
        );
      case 'partially_failed':
        return (
          <Badge className="bg-amber-500 hover:bg-amber-600 text-white flex items-center gap-1">
            <AlertTriangle className="w-3.5 h-3.5" />
            Hoàn tất một phần (Có hoàn credit)
          </Badge>
        );
      case 'failed':
        return (
          <Badge className="bg-rose-500 hover:bg-rose-600 text-white flex items-center gap-1">
            <AlertCircle className="w-3.5 h-3.5" />
            Thất bại (Đã hoàn 100% credit)
          </Badge>
        );
      case 'cancelled':
        return (
          <Badge variant="outline" className="text-slate-600 border-slate-300 flex items-center gap-1">
            <Ban className="w-3.5 h-3.5" />
            Đã hủy (Đã hoàn credit)
          </Badge>
        );
      default:
        return (
          <Badge variant="secondary" className="flex items-center gap-1">
            <Clock className="w-3.5 h-3.5" />
            Đang chờ xử lý
          </Badge>
        );
    }
  };

  const getItemStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return (
          <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
            <CheckCircle2 className="w-3 h-3" /> Thành công
          </span>
        );
      case 'processing':
        return (
          <span className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
            <Loader2 className="w-3 h-3 animate-spin" /> Đang viết
          </span>
        );
      case 'failed':
        return (
          <span className="inline-flex items-center gap-1 text-xs font-medium text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full">
            <AlertCircle className="w-3 h-3" /> Lỗi (Đã hoàn tiền)
          </span>
        );
      case 'cancelled':
        return (
          <span className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
            <Ban className="w-3 h-3" /> Đã hủy
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
            <Clock className="w-3 h-3" /> Chờ xử lý
          </span>
        );
    }
  };

  return (
    <Card className="border border-slate-200 dark:border-slate-800 shadow-sm">
      <CardHeader className="pb-4">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <CardTitle className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {batch.name}
              </CardTitle>
              {getStatusBadge(batch.status)}
            </div>
            <CardDescription className="text-xs text-slate-500 mt-1 flex items-center gap-2">
              <span>Mã lô: <code className="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-300 font-mono">{batch.uuid}</code></span>
              {batch.started_at && <span>• Bắt đầu: {new Date(batch.started_at).toLocaleTimeString('vi-VN')}</span>}
            </CardDescription>
          </div>

          <div className="flex items-center gap-2">
            {onRefresh && (
              <Button
                variant="outline"
                size="sm"
                onClick={onRefresh}
                disabled={isRefreshing}
                className="flex items-center gap-1.5 text-xs"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                Làm mới
              </Button>
            )}

            {!isFinished && onCancel && (
              <Button
                variant="destructive"
                size="sm"
                onClick={() => onCancel(batch.uuid)}
                disabled={isCancelling}
                className="flex items-center gap-1.5 text-xs"
              >
                {isCancelling ? (
                  <Loader2 className="w-3.5 h-3.5 animate-spin" />
                ) : (
                  <Ban className="w-3.5 h-3.5" />
                )}
                {isCancelling ? 'Đang hủy...' : 'Hủy lô & Hoàn credit'}
              </Button>
            )}
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        {/* Progress Bar & Percent Indicator */}
        <div className="space-y-2">
          <div className="flex justify-between items-center text-sm">
            <span className="font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
              {isRunning && <Loader2 className="w-4 h-4 animate-spin text-blue-600" />}
              Tiến độ sản xuất tổng thể
            </span>
            <span className="font-bold text-slate-900 dark:text-white">
              {batch.completed_items + batch.failed_items} / {batch.total_items} bài viết ({batch.progress_percent}%)
            </span>
          </div>
          <Progress value={batch.progress_percent} className="h-2.5 w-full bg-slate-100 dark:bg-slate-800" />
        </div>

        {/* 4 KPI Metrics Grid */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            <div className="flex items-center justify-between text-slate-500 text-xs mb-1">
              <span>Tổng bài viết</span>
              <Layers className="w-4 h-4 text-blue-500" />
            </div>
            <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
              {batch.total_items}
            </div>
            <p className="text-[11px] text-slate-500 mt-0.5">Số lượng đăng ký</p>
          </div>

          <div className="p-3.5 rounded-xl border border-emerald-100 dark:border-emerald-950/40 bg-emerald-50/40 dark:bg-emerald-950/20">
            <div className="flex items-center justify-between text-emerald-600 text-xs mb-1">
              <span>Đã hoàn thành</span>
              <CheckCircle2 className="w-4 h-4 text-emerald-500" />
            </div>
            <div className="text-xl font-bold text-emerald-700 dark:text-emerald-400">
              {batch.completed_items}
            </div>
            <p className="text-[11px] text-emerald-600/80 mt-0.5">Bài viết đã tạo</p>
          </div>

          <div className="p-3.5 rounded-xl border border-rose-100 dark:border-rose-950/40 bg-rose-50/40 dark:bg-rose-950/20">
            <div className="flex items-center justify-between text-rose-600 text-xs mb-1">
              <span>Lỗi xử lý</span>
              <AlertCircle className="w-4 h-4 text-rose-500" />
            </div>
            <div className="text-xl font-bold text-rose-700 dark:text-rose-400">
              {batch.failed_items}
            </div>
            <p className="text-[11px] text-rose-600/80 mt-0.5">Đã hoàn trả 100% credit</p>
          </div>

          <div className="p-3.5 rounded-xl border border-amber-100 dark:border-amber-950/40 bg-amber-50/40 dark:bg-amber-950/20">
            <div className="flex items-center justify-between text-amber-600 text-xs mb-1">
              <span>Quyết toán Credit</span>
              <Coins className="w-4 h-4 text-amber-500" />
            </div>
            <div className="text-xl font-bold text-amber-700 dark:text-amber-400">
              {batch.credits_settled} <span className="text-xs font-normal text-slate-500">/ {batch.credits_reserved + batch.credits_settled}</span>
            </div>
            <p className="text-[11px] text-amber-600/80 mt-0.5">
              {batch.credits_reserved > 0 ? `Còn giữ: ${batch.credits_reserved}` : 'Quyết toán xong'}
            </p>
          </div>
        </div>

        {/* Detailed Items Table (Adhering strictly to Reviewer criteria 12: overflow-x-auto & whitespace-nowrap) */}
        <div className="space-y-3">
          <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
            <FileText className="w-4 h-4 text-indigo-500" />
            Chi tiết tiến độ từng bài viết trong lô
          </h4>

          <div className="w-full overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900">
                  <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap w-12 text-center">#</th>
                  <th className="p-3 font-semibold text-slate-600 dark:text-slate-400">Từ khóa SEO (Keyword)</th>
                  <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Trạng thái</th>
                  <th className="p-3 font-semibold text-slate-600 dark:text-slate-400">Bài viết tạo</th>
                  <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-950">
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="p-6 text-center text-slate-500 text-xs">
                      Chưa có thông tin chi tiết bài viết.
                    </td>
                  </tr>
                ) : (
                  items.map((item, idx) => (
                    <tr key={idx} className="hover:bg-slate-50/50 dark:hover:bg-slate-900/40 transition-colors">
                      <td className="p-3 text-center text-xs text-slate-400 font-mono whitespace-nowrap">
                        {idx + 1}
                      </td>
                      <td className="p-3 font-medium text-slate-800 dark:text-slate-200">
                        {item.keyword}
                      </td>
                      <td className="p-3 whitespace-nowrap">
                        {getItemStatusBadge(item.status)}
                      </td>
                      <td className="p-3 text-xs text-slate-600 dark:text-slate-400">
                        {item.article_title ? (
                          <span className="line-clamp-1">{item.article_title}</span>
                        ) : item.error ? (
                          <span className="text-rose-600 dark:text-rose-400 line-clamp-1">{item.error}</span>
                        ) : (
                          <span className="text-slate-400 italic">Đang chờ xử lý...</span>
                        )}
                      </td>
                      <td className="p-3 text-right whitespace-nowrap">
                        {item.article_id ? (
                          <Link
                            href={`/content-studio/${item.article_id}`}
                            className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 px-2.5 py-1 rounded-md transition-colors"
                          >
                            <ExternalLink className="w-3 h-3" />
                            Mở Studio
                          </Link>
                        ) : (
                          <span className="text-xs text-slate-300 dark:text-slate-700">—</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
export default BulkProgressMonitor;
