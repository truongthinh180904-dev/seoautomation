"use client";

import React, { useState } from 'react';
import {
  TrendingDown,
  Sparkles,
  AlertTriangle,
  Layers,
  MousePointerClick,
  Activity,
  ArrowUpRight,
  CheckCircle2,
  X,
  Loader2,
  ChevronRight
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  SeoInsightItem,
  InsightType,
  InsightSeverity,
  useDismissInsight,
  useActionInsight
} from '@/hooks/useSeoInsights';
import Link from 'next/link';

interface InsightCardProps {
  insight: SeoInsightItem;
  onOptimized?: (insight: SeoInsightItem) => void;
}

const TYPE_CONFIG: Record<
  InsightType,
  { label: string; icon: React.ComponentType<{ className?: string }>; colorClass: string; bgClass: string }
> = {
  ranking_drop: {
    label: 'Tụt Hạng SERP',
    icon: TrendingDown,
    colorClass: 'text-rose-600',
    bgClass: 'bg-rose-50 border-rose-200',
  },
  clicks_drop: {
    label: 'Sụt Giảm Clicks',
    icon: AlertTriangle,
    colorClass: 'text-amber-600',
    bgClass: 'bg-amber-50 border-amber-200',
  },
  high_imp_low_ctr: {
    label: 'Cơ Hội Tăng CTR',
    icon: MousePointerClick,
    colorClass: 'text-violet-600',
    bgClass: 'bg-violet-50 border-violet-200',
  },
  traffic_decay: {
    label: 'Suy Giảm Traffic',
    icon: Activity,
    colorClass: 'text-orange-600',
    bgClass: 'bg-orange-50 border-orange-200',
  },
  striking_distance: {
    label: 'Cơ Hội Bứt Phá Top 3',
    icon: Sparkles,
    colorClass: 'text-emerald-600',
    bgClass: 'bg-emerald-50 border-emerald-200',
  },
  cannibalization: {
    label: 'Xung Đột Từ Khóa',
    icon: Layers,
    colorClass: 'text-indigo-600',
    bgClass: 'bg-indigo-50 border-indigo-200',
  },
  high_traffic_low_engagement: {
    label: 'Tương Tác Thấp',
    icon: AlertTriangle,
    colorClass: 'text-yellow-600',
    bgClass: 'bg-yellow-50 border-yellow-200',
  },
  stale_content: {
    label: 'Nội Dung Cũ',
    icon: Activity,
    colorClass: 'text-slate-600',
    bgClass: 'bg-slate-50 border-slate-200',
  },
};

const SEVERITY_CONFIG: Record<InsightSeverity, { label: string; badgeClass: string }> = {
  critical: {
    label: 'Khẩn Cấp',
    badgeClass: 'bg-rose-100 text-rose-700 border-rose-200 hover:bg-rose-100',
  },
  warning: {
    label: 'Cảnh Báo',
    badgeClass: 'bg-amber-100 text-amber-800 border-amber-200 hover:bg-amber-100',
  },
  info: {
    label: 'Cơ Hội Vàng',
    badgeClass: 'bg-blue-100 text-blue-700 border-blue-200 hover:bg-blue-100',
  },
};

export function InsightCard({ insight, onOptimized }: InsightCardProps) {
  const [isDismissing, setIsDismissing] = useState(false);
  const [isActioning, setIsActioning] = useState(false);

  const dismissMutation = useDismissInsight();
  const actionMutation = useActionInsight();

  const typeConfig = TYPE_CONFIG[insight.type] ?? {
    label: 'Phân Tích SEO',
    icon: Sparkles,
    colorClass: 'text-blue-600',
    bgClass: 'bg-blue-50 border-blue-200',
  };

  const severityConfig = SEVERITY_CONFIG[insight.severity] ?? {
    label: 'Thông Báo',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
  };

  const Icon = typeConfig.icon;
  const snapshot = insight.metrics_snapshot;
  const score = insight.opportunity_score;

  // Determine score color
  const scoreColor =
    score >= 85
      ? 'from-emerald-500 to-teal-600 text-emerald-700 bg-emerald-50 border-emerald-200'
      : score >= 70
        ? 'from-blue-500 to-indigo-600 text-blue-700 bg-blue-50 border-blue-200'
        : 'from-amber-500 to-orange-600 text-amber-700 bg-amber-50 border-amber-200';

  const handleDismiss = async () => {
    try {
      setIsDismissing(true);
      await dismissMutation.mutateAsync(insight.id);
    } finally {
      setIsDismissing(false);
    }
  };

  const handleAction = async () => {
    try {
      setIsActioning(true);
      await actionMutation.mutateAsync({
        insightId: insight.id,
        action: 'in_progress',
      });
      if (onOptimized) {
        onOptimized(insight);
      }
    } finally {
      setIsActioning(false);
    }
  };

  return (
    <Card className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white transition-all duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
      <CardContent className="p-5">
        {/* Header Badges & Type */}
        <div className="flex items-start justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <span
              className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold ${typeConfig.bgClass} ${typeConfig.colorClass}`}
            >
              <Icon className="h-3.5 w-3.5" />
              {typeConfig.label}
            </span>

            <Badge variant="outline" className={`text-[11px] font-bold ${severityConfig.badgeClass}`}>
              {severityConfig.label}
            </Badge>

            {insight.status === 'in_progress' && (
              <Badge className="bg-purple-100 text-[11px] font-bold text-purple-700 hover:bg-purple-100">
                Đang xử lý
              </Badge>
            )}
          </div>

          {/* Opportunity Score Indicator */}
          <div className="flex items-center gap-1.5 text-right">
            <div
              className={`flex items-center gap-1 rounded-xl border px-2.5 py-1 text-xs font-black ${scoreColor}`}
              title="Điểm tiềm năng tăng trưởng SEO (0 - 100)"
            >
              <ArrowUpRight className="h-3.5 w-3.5" />
              <span>Tiềm năng {score}/100</span>
            </div>
          </div>
        </div>

        {/* Title & Description */}
        <div className="mt-3">
          <h4 className="text-base font-bold text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-1">
            {insight.title}
          </h4>
          <p className="mt-1.5 text-xs text-slate-600 leading-relaxed line-clamp-2">
            {insight.description}
          </p>
        </div>

        {/* Metrics Highlights Bar */}
        <div className="mt-3.5 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-2.5 text-xs text-slate-700">
          {snapshot.current_position !== undefined && snapshot.previous_position !== undefined && (
            <div className="flex items-center gap-1">
              <span className="font-semibold text-slate-500">Thứ hạng:</span>
              <span className="font-black text-rose-600">#{snapshot.current_position}</span>
              <span className="text-slate-400">←</span>
              <span className="font-semibold text-slate-600">#{snapshot.previous_position}</span>
              <span className="text-rose-500 text-[11px] font-bold">(-{snapshot.drop_amount} bậc)</span>
            </div>
          )}

          {snapshot.impressions !== undefined && (
            <div className="flex items-center gap-1 border-l border-slate-200 pl-2">
              <span className="font-semibold text-slate-500">Hiển thị:</span>
              <span className="font-black text-slate-800">
                {Number(snapshot.impressions).toLocaleString()}
              </span>
            </div>
          )}

          {snapshot.ctr_percent !== undefined && (
            <div className="flex items-center gap-1 border-l border-slate-200 pl-2">
              <span className="font-semibold text-slate-500">CTR:</span>
              <span className="font-black text-violet-600">{snapshot.ctr_percent}%</span>
            </div>
          )}

          {snapshot.avg_position !== undefined && snapshot.current_position === undefined && (
            <div className="flex items-center gap-1 border-l border-slate-200 pl-2">
              <span className="font-semibold text-slate-500">Hạng TB:</span>
              <span className="font-black text-emerald-600">#{snapshot.avg_position}</span>
            </div>
          )}

          {snapshot.decay_percent !== undefined && (
            <div className="flex items-center gap-1 border-l border-slate-200 pl-2">
              <span className="font-semibold text-slate-500">Giảm 4 tuần:</span>
              <span className="font-black text-orange-600">-{snapshot.decay_percent}%</span>
            </div>
          )}

          {snapshot.competing_urls_count !== undefined && (
            <div className="flex items-center gap-1 border-l border-slate-200 pl-2">
              <span className="font-semibold text-slate-500">Trang xung đột:</span>
              <span className="font-black text-indigo-600">{snapshot.competing_urls_count} trang</span>
            </div>
          )}

          {snapshot.engagement_rate_percent !== undefined && (
            <div className="flex items-center gap-1 border-l border-slate-200 pl-2">
              <span className="font-semibold text-slate-500">Tương tác:</span>
              <span className="font-black text-amber-600">{snapshot.engagement_rate_percent}%</span>
            </div>
          )}
        </div>

        {/* Action Buttons Footer */}
        <div className="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
          <div className="text-[11px] font-medium text-slate-400">
            {insight.suggested_action === 'optimize_title_meta' && 'Gợi ý: Tối ưu Title & Meta Description'}
            {insight.suggested_action === 'refresh_content' && 'Gợi ý: Làm mới bài viết với AI Copilot'}
            {insight.suggested_action === 'add_internal_links' && 'Gợi ý: Thêm 2-3 liên kết nội bộ để đẩy Top 3'}
            {insight.suggested_action === 'canonicalize_or_merge' && 'Gợi ý: Hợp nhất nội dung hoặc đặt thẻ Canonical'}
            {insight.suggested_action === 'investigate_drop' && 'Gợi ý: Kiểm tra đối thủ và từ khóa phụ'}
            {insight.suggested_action === 'improve_content_engagement' && 'Gợi ý: Thêm mục lục và lời kêu gọi hành động'}
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              disabled={isDismissing || isActioning}
              onClick={handleDismiss}
              className="h-8 rounded-lg px-2.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            >
              {isDismissing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <X className="mr-1 h-3.5 w-3.5" />}
              Bỏ qua
            </Button>

            {insight.article_id ? (
              <Button
                asChild
                size="sm"
                className="h-8 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white shadow-sm hover:bg-blue-700"
              >
                <Link href={`/content-studio/${insight.article_id}`}>
                  Tối ưu ngay
                  <ChevronRight className="ml-1 h-3.5 w-3.5" />
                </Link>
              </Button>
            ) : (
              <Button
                size="sm"
                disabled={isActioning}
                onClick={handleAction}
                className="h-8 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white shadow-sm hover:bg-blue-700"
              >
                {isActioning ? (
                  <Loader2 className="mr-1 h-3.5 w-3.5 animate-spin" />
                ) : (
                  <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                )}
                Xử lý
              </Button>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
