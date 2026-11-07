'use client';

import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ContentDiffData } from '@/hooks/useContentOptimization';
import {
  ArrowRight,
  TrendingUp,
  FileText,
  Sparkles,
  Link2,
  CheckCircle2,
  Columns,
  List,
} from 'lucide-react';

interface ContentDiffViewerProps {
  data: ContentDiffData;
  className?: string;
}

export const ContentDiffViewer: React.FC<ContentDiffViewerProps> = ({
  data,
  className = '',
}) => {
  const [viewMode, setViewMode] = useState<'side-by-side' | 'inline'>('side-by-side');

  const {
    originalTitle,
    proposedTitle,
    originalSeoTitle,
    proposedSeoTitle,
    originalSeoDescription,
    proposedSeoDescription,
    scoreBefore,
    scoreAfter,
    scoreDelta,
    wordCountBefore,
    wordCountAfter,
    wordCountDelta,
    addedHeadings = [],
    missingIntentsAddressed = [],
    internalLinkSuggestions = [],
    diffSegments = [],
    originalContent,
    proposedContent,
  } = data;

  const isTitleChanged = originalTitle !== proposedTitle;

  return (
    <div className={`space-y-6 ${className}`}>
      {/* 1. Header Metrics Comparison Card */}
      <Card className="border border-slate-200 bg-slate-50/70 shadow-sm">
        <CardContent className="p-4 sm:p-6">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {/* Score Metric */}
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
              <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                Điểm SEO Dự Kiến
              </span>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-bold text-slate-700">{scoreBefore}</span>
                <ArrowRight className="h-4 w-4 text-slate-400" />
                <span className="text-2xl font-extrabold text-emerald-600">{scoreAfter}</span>
                <Badge
                  variant="outline"
                  className="ml-auto border-emerald-300 bg-emerald-50 text-emerald-700"
                >
                  <TrendingUp className="mr-1 h-3 w-3" />
                  +{scoreDelta} điểm
                </Badge>
              </div>
            </div>

            {/* Word Count Metric */}
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
              <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                Độ Dài Nội Dung
              </span>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-bold text-slate-700">{wordCountBefore}</span>
                <ArrowRight className="h-4 w-4 text-slate-400" />
                <span className="text-2xl font-extrabold text-blue-600">{wordCountAfter}</span>
                <Badge
                  variant="outline"
                  className="ml-auto border-blue-300 bg-blue-50 text-blue-700"
                >
                  <FileText className="mr-1 h-3 w-3" />
                  +{wordCountDelta} từ
                </Badge>
              </div>
            </div>

            {/* Headings Added Metric */}
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
              <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                Đề Mục Bổ Sung
              </span>
              <div className="mt-2 flex items-baseline justify-between">
                <span className="text-2xl font-extrabold text-indigo-600">
                  +{addedHeadings.length}
                </span>
                <Badge
                  variant="outline"
                  className="border-indigo-300 bg-indigo-50 text-indigo-700"
                >
                  <Sparkles className="mr-1 h-3 w-3" />
                  H2 & H3
                </Badge>
              </div>
            </div>

            {/* Internal Links Suggested Metric */}
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
              <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                Liên Kết Nội Bộ
              </span>
              <div className="mt-2 flex items-baseline justify-between">
                <span className="text-2xl font-extrabold text-violet-600">
                  +{internalLinkSuggestions.length}
                </span>
                <Badge
                  variant="outline"
                  className="border-violet-300 bg-violet-50 text-violet-700"
                >
                  <Link2 className="mr-1 h-3 w-3" />
                  Internal Link
                </Badge>
              </div>
            </div>
          </div>

          {/* Missing Intents Badges */}
          {missingIntentsAddressed.length > 0 && (
            <div className="mt-4 pt-4 border-t border-slate-200">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">
                Ý Định Tìm Kiếm Mới Được Bổ Sung (Search Intents):
              </span>
              <div className="flex flex-wrap gap-2">
                {missingIntentsAddressed.map((intent, idx) => (
                  <Badge
                    key={idx}
                    variant="secondary"
                    className="border border-slate-200 bg-white text-slate-700 shadow-2xs"
                  >
                    <CheckCircle2 className="mr-1 h-3 w-3 text-emerald-500" />
                    {intent}
                  </Badge>
                ))}
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* 2. Mode Selector Bar */}
      <div className="flex items-center justify-between">
        <h3 className="text-base font-semibold text-slate-900">
          So Sánh Chi Tiết Trước & Sau Tối Ưu
        </h3>
        <Tabs
          value={viewMode}
          onValueChange={(val) => setViewMode(val as 'side-by-side' | 'inline')}
          className="w-auto"
        >
          <TabsList className="bg-slate-100 p-1">
            <TabsTrigger value="side-by-side" className="flex items-center gap-1.5 text-xs">
              <Columns className="h-3.5 w-3.5" />
              2 Cột Song Song
            </TabsTrigger>
            <TabsTrigger value="inline" className="flex items-center gap-1.5 text-xs">
              <List className="h-3.5 w-3.5" />
              Xem Hợp Nhất (Inline)
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      {/* 3. Title & SEO Meta Diff */}
      <Card className="border border-slate-200 shadow-sm overflow-hidden">
        <CardHeader className="bg-slate-50/70 border-b border-slate-200 py-3 px-4 sm:px-6">
          <CardTitle className="text-sm font-semibold text-slate-800">
            Tiêu Đề & Thẻ Meta SEO
          </CardTitle>
        </CardHeader>
        <CardContent className="p-4 sm:p-6 space-y-4">
          {/* Title comparison */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">
                Tiêu đề hiện tại:
              </span>
              <p className="text-sm text-slate-800 font-medium">{originalTitle}</p>
            </div>
            <div
              className={`rounded-lg border p-3 ${
                isTitleChanged
                  ? 'border-emerald-200 bg-emerald-50/40 text-emerald-900'
                  : 'border-slate-200 bg-white text-slate-800'
              }`}
            >
              <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wider block mb-1">
                Tiêu đề tối ưu mới:
              </span>
              <p className="text-sm font-medium">{proposedTitle}</p>
            </div>
          </div>

          {/* Meta Description comparison */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">
                Meta Description gốc:
              </span>
              <p className="text-xs text-slate-600 line-clamp-3">
                {originalSeoDescription || 'Chưa thiết lập'}
              </p>
            </div>
            <div className="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3 text-emerald-900">
              <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wider block mb-1">
                Meta Description mới ({proposedSeoDescription?.length || 0} ký tự):
              </span>
              <p className="text-xs text-emerald-800 line-clamp-3 font-medium">
                {proposedSeoDescription}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* 4. Main Content Comparison */}
      {viewMode === 'side-by-side' ? (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          {/* Left Column: Original */}
          <Card className="border border-slate-200 shadow-sm flex flex-col h-[520px]">
            <CardHeader className="bg-slate-50 border-b border-slate-200 py-3 px-4 flex flex-row items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="h-2.5 w-2.5 rounded-full bg-slate-400" />
                <span className="text-sm font-semibold text-slate-700">
                  Bản Gốc (Trước Tối Ưu)
                </span>
              </div>
              <Badge variant="outline" className="text-xs">
                {wordCountBefore} từ
              </Badge>
            </CardHeader>
            <CardContent className="p-4 flex-1 overflow-y-auto">
              <div
                className="prose prose-sm max-w-none text-slate-700"
                dangerouslySetInnerHTML={{ __html: originalContent }}
              />
            </CardContent>
          </Card>

          {/* Right Column: Proposed */}
          <Card className="border border-emerald-200 shadow-sm flex flex-col h-[520px] bg-emerald-50/10">
            <CardHeader className="bg-emerald-50/60 border-b border-emerald-200 py-3 px-4 flex flex-row items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse" />
                <span className="text-sm font-semibold text-emerald-900">
                  Bản Đề Xuất Tối Ưu Mới
                </span>
              </div>
              <Badge className="bg-emerald-600 hover:bg-emerald-700 text-xs">
                +{wordCountDelta} từ
              </Badge>
            </CardHeader>
            <CardContent className="p-4 flex-1 overflow-y-auto">
              <div
                className="prose prose-sm max-w-none text-slate-900"
                dangerouslySetInnerHTML={{ __html: proposedContent }}
              />
            </CardContent>
          </Card>
        </div>
      ) : (
        /* Inline Diff View */
        <Card className="border border-slate-200 shadow-sm">
          <CardHeader className="bg-slate-50 border-b border-slate-200 py-3 px-4 flex flex-row items-center justify-between">
            <span className="text-sm font-semibold text-slate-700">
              Lược Đồ Thay Đổi Dòng (Diff Log)
            </span>
            <div className="flex items-center gap-2 text-xs">
              <span className="inline-flex items-center gap-1 text-emerald-700">
                <span className="h-2 w-2 rounded-full bg-emerald-500" /> Bổ sung mới
              </span>
              <span className="inline-flex items-center gap-1 text-rose-700">
                <span className="h-2 w-2 rounded-full bg-rose-500" /> Nội dung cũ
              </span>
            </div>
          </CardHeader>
          <CardContent className="p-4 max-h-[500px] overflow-y-auto font-mono text-xs space-y-1">
            {diffSegments.map((segment, idx) => {
              if (segment.type === 'added') {
                return (
                  <div
                    key={idx}
                    className="flex items-start gap-2 bg-emerald-50 border-l-4 border-emerald-500 p-2 text-emerald-900 rounded-r"
                  >
                    <span className="font-bold select-none text-emerald-600">+</span>
                    <span className="break-all">{segment.value}</span>
                  </div>
                );
              }
              if (segment.type === 'removed') {
                return (
                  <div
                    key={idx}
                    className="flex items-start gap-2 bg-rose-50 border-l-4 border-rose-500 p-2 text-rose-900 rounded-r line-through opacity-75"
                  >
                    <span className="font-bold select-none text-rose-600">-</span>
                    <span className="break-all">{segment.value}</span>
                  </div>
                );
              }
              return (
                <div key={idx} className="flex items-start gap-2 p-1.5 text-slate-600">
                  <span className="font-normal select-none text-slate-300"> </span>
                  <span className="break-all">{segment.value}</span>
                </div>
              );
            })}
          </CardContent>
        </Card>
      )}
    </div>
  );
};
