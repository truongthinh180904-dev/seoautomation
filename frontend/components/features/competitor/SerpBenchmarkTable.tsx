"use client";

import React, { useState } from 'react';
import {
  ExternalLink,
  ChevronDown,
  ChevronUp,
  FileText,
  Heading,
  Layers,
  Image as ImageIcon,
  CheckCircle2,
  AlertTriangle,
  Sparkles,
  Info,
} from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';

export interface HeadingItem {
  tag: string;
  level: number;
  text: string;
}

export interface HeadingsCount {
  h1: number;
  h2: number;
  h3: number;
  h4: number;
  total: number;
}

export interface CompetitorItem {
  position: number;
  url: string;
  domain: string;
  title: string;
  snippet?: string;
  word_count: number;
  headings_count: HeadingsCount;
  heading_structure?: HeadingItem[];
  schemas: string[];
  keyword_density: number;
  images_count: number;
  images_with_alt?: number;
  images_without_alt?: number;
  lsi_keywords?: string[];
  is_safe?: boolean;
  error?: string;
}

export interface SchemaMetric {
  name: string;
  count: number;
  percentage: number;
}

export interface BenchmarkSummary {
  total_analyzed: number;
  avg_word_count: number;
  min_word_count: number;
  max_word_count: number;
  avg_h2_count: number;
  avg_h3_count: number;
  avg_h4_count: number;
  avg_headings_count: number;
  avg_image_count: number;
  avg_keyword_density: number;
  recommended_word_count: number;
  recommended_h2_count: number;
  recommended_image_count: number;
  common_schemas: SchemaMetric[];
  common_lsi_keywords: string[];
}

export interface SerpBenchmarkTableProps {
  benchmark: BenchmarkSummary;
  competitors: CompetitorItem[];
  isLoading?: boolean;
}

export function SerpBenchmarkTable({
  benchmark,
  competitors = [],
  isLoading = false,
}: SerpBenchmarkTableProps) {
  const [selectedCompetitor, setSelectedCompetitor] = useState<CompetitorItem | null>(null);

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-28 rounded-xl border border-slate-200 bg-slate-50/80 animate-pulse" />
          ))}
        </div>
        <div className="h-80 rounded-xl border border-slate-200 bg-slate-50/80 animate-pulse" />
      </div>
    );
  }

  if (competitors.length === 0) {
    return (
      <Card className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <Info className="mx-auto h-8 w-8 text-slate-400" />
        <CardTitle className="mt-3 text-base text-slate-700">Chưa có dữ liệu phân tích đối thủ</CardTitle>
        <CardDescription className="mt-1 text-xs text-slate-500">
          Hãy nhập từ khóa tìm kiếm và nhấn &quot;Phân tích Đối thủ&quot; để bóc tách Top 10 Google.
        </CardDescription>
      </Card>
    );
  }

  const getRankBadge = (pos: number) => {
    switch (pos) {
      case 1:
        return (
          <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-800 border border-amber-300 shadow-xs">
            1
          </span>
        );
      case 2:
        return (
          <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-800 border border-slate-300">
            2
          </span>
        );
      case 3:
        return (
          <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-orange-100 text-xs font-bold text-orange-800 border border-orange-300">
            3
          </span>
        );
      default:
        return (
          <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-600 border border-slate-200">
            {pos}
          </span>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* KPI Cards: Benchmark Overview */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Card 1: Độ dài bài viết */}
        <Card className="border-slate-200 bg-white shadow-xs">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs font-medium text-slate-500 flex items-center justify-between">
              <span>Độ dài từ khóa Top 10</span>
              <FileText className="h-4 w-4 text-blue-600" />
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-slate-900">
              {benchmark.avg_word_count.toLocaleString()} <span className="text-xs font-normal text-slate-500">từ TB</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-xs text-slate-600">
            <div className="flex items-center justify-between border-t border-slate-100 pt-2">
              <span>Khuyến nghị mục tiêu:</span>
              <span className="font-semibold text-emerald-600">≥ {benchmark.recommended_word_count.toLocaleString()} từ</span>
            </div>
          </CardContent>
        </Card>

        {/* Card 2: Cấu trúc Headings */}
        <Card className="border-slate-200 bg-white shadow-xs">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs font-medium text-slate-500 flex items-center justify-between">
              <span>Số thẻ Heading H2</span>
              <Heading className="h-4 w-4 text-indigo-600" />
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-slate-900">
              {benchmark.avg_h2_count} <span className="text-xs font-normal text-slate-500">thẻ H2 TB</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-xs text-slate-600">
            <div className="flex items-center justify-between border-t border-slate-100 pt-2">
              <span>Khuyên dùng bài viết:</span>
              <span className="font-semibold text-indigo-600">≥ {benchmark.recommended_h2_count} mục H2</span>
            </div>
          </CardContent>
        </Card>

        {/* Card 3: Mật độ từ khóa */}
        <Card className="border-slate-200 bg-white shadow-xs">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs font-medium text-slate-500 flex items-center justify-between">
              <span>Mật độ từ khóa TB</span>
              <Sparkles className="h-4 w-4 text-amber-500" />
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-slate-900">
              {benchmark.avg_keyword_density}%
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-xs text-slate-600">
            <div className="flex items-center justify-between border-t border-slate-100 pt-2">
              <span>Khoảng an toàn:</span>
              <span className="font-semibold text-slate-700">1.0% – 2.0%</span>
            </div>
          </CardContent>
        </Card>

        {/* Card 4: Số lượng hình ảnh */}
        <Card className="border-slate-200 bg-white shadow-xs">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs font-medium text-slate-500 flex items-center justify-between">
              <span>Hình ảnh minh họa</span>
              <ImageIcon className="h-4 w-4 text-teal-600" />
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-slate-900">
              {benchmark.avg_image_count} <span className="text-xs font-normal text-slate-500">ảnh TB</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-xs text-slate-600">
            <div className="flex items-center justify-between border-t border-slate-100 pt-2">
              <span>Khuyên dùng bài viết:</span>
              <span className="font-semibold text-teal-600">≥ {benchmark.recommended_image_count} ảnh có ALT</span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Benchmark Matrix Data Table */}
      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <Table>
          <TableHeader className="bg-slate-50/80">
            <TableRow>
              <TableHead className="w-14 whitespace-nowrap text-center text-xs font-semibold text-slate-700">Hạng</TableHead>
              <TableHead className="min-w-[260px] text-xs font-semibold text-slate-700">Trang web đối thủ</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Số từ</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Cấu trúc Headings</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Mật độ KW</TableHead>
              <TableHead className="min-w-[150px] text-xs font-semibold text-slate-700">Schema phát hiện</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Hình ảnh</TableHead>
              <TableHead className="whitespace-nowrap text-right text-xs font-semibold text-slate-700">Thao tác</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {competitors.map((comp) => {
              const diffWords = comp.word_count - benchmark.avg_word_count;
              const isAboveAvg = diffWords >= 0;

              return (
                <TableRow key={`${comp.position}-${comp.url}`} className="hover:bg-slate-50/60 transition-colors">
                  {/* Position Badge */}
                  <TableCell className="text-center whitespace-nowrap">
                    {getRankBadge(comp.position)}
                  </TableCell>

                  {/* Domain & Title */}
                  <TableCell>
                    <div className="flex flex-col space-y-1">
                      <div className="flex items-center space-x-1.5">
                        <span className="text-xs font-semibold text-slate-900 hover:text-blue-600">
                          {comp.domain}
                        </span>
                        <a
                          href={comp.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-slate-400 hover:text-blue-600"
                          title="Mở liên kết đối thủ"
                        >
                          <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                      </div>
                      <p className="line-clamp-1 text-xs text-slate-500 font-normal" title={comp.title}>
                        {comp.title || comp.url}
                      </p>
                    </div>
                  </TableCell>

                  {/* Word Count */}
                  <TableCell className="whitespace-nowrap">
                    <div className="flex flex-col">
                      <span className="font-mono text-xs font-bold text-slate-800">
                        {comp.word_count.toLocaleString()} từ
                      </span>
                      <span
                        className={`text-[10px] font-medium ${
                          isAboveAvg ? 'text-emerald-600' : 'text-slate-400'
                        }`}
                      >
                        {isAboveAvg ? `+${diffWords.toLocaleString()}` : `${diffWords.toLocaleString()}`} so với TB
                      </span>
                    </div>
                  </TableCell>

                  {/* Headings Structure Breakdown */}
                  <TableCell className="whitespace-nowrap">
                    <div className="flex items-center space-x-1 text-xs">
                      <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700" title="Thẻ H1">
                        H1: <strong>{comp.headings_count.h1}</strong>
                      </span>
                      <span className="rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-[11px] text-indigo-700 font-semibold" title="Thẻ H2">
                        H2: {comp.headings_count.h2}
                      </span>
                      <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600" title="Thẻ H3">
                        H3: {comp.headings_count.h3}
                      </span>
                    </div>
                  </TableCell>

                  {/* Keyword Density */}
                  <TableCell className="whitespace-nowrap">
                    <span
                      className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold ${
                        comp.keyword_density >= 1.0 && comp.keyword_density <= 2.2
                          ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                          : comp.keyword_density > 2.2
                          ? 'bg-amber-50 text-amber-700 border border-amber-200'
                          : 'bg-slate-100 text-slate-600 border border-slate-200'
                      }`}
                    >
                      {comp.keyword_density}%
                    </span>
                  </TableCell>

                  {/* Schemas */}
                  <TableCell>
                    <div className="flex flex-wrap gap-1">
                      {comp.schemas.length > 0 ? (
                        comp.schemas.slice(0, 2).map((s) => (
                          <Badge
                            key={s}
                            variant="outline"
                            className="text-[10px] bg-slate-50 border-slate-200 text-slate-700 px-1.5 py-0"
                          >
                            {s}
                          </Badge>
                        ))
                      ) : (
                        <span className="text-[11px] text-slate-400 italic">Không có</span>
                      )}
                      {comp.schemas.length > 2 && (
                        <Badge variant="outline" className="text-[10px] text-slate-500 px-1 py-0">
                          +{comp.schemas.length - 2}
                        </Badge>
                      )}
                    </div>
                  </TableCell>

                  {/* Images Count */}
                  <TableCell className="whitespace-nowrap">
                    <span className="font-mono text-xs text-slate-700 flex items-center space-x-1">
                      <ImageIcon className="h-3 w-3 text-slate-400" />
                      <span>{comp.images_count} ảnh</span>
                    </span>
                  </TableCell>

                  {/* View Details Action */}
                  <TableCell className="text-right whitespace-nowrap">
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-8 text-xs font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                      onClick={() => setSelectedCompetitor(comp)}
                    >
                      Chi tiết
                    </Button>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>

      {/* Competitor Heading Map & Details Modal */}
      <Dialog open={!!selectedCompetitor} onOpenChange={(open) => !open && setSelectedCompetitor(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="text-base font-bold flex items-center space-x-2">
              <span>Bóc tách nội dung: {selectedCompetitor?.domain}</span>
            </DialogTitle>
            <DialogDescription className="text-xs text-slate-500 line-clamp-1">
              {selectedCompetitor?.url}
            </DialogDescription>
          </DialogHeader>

          {selectedCompetitor && (
            <div className="space-y-4 pt-2">
              {/* Quick stats in modal */}
              <div className="grid grid-cols-3 gap-2 rounded-lg bg-slate-50 p-3 text-center text-xs">
                <div>
                  <span className="text-slate-500">Số từ:</span>
                  <p className="font-bold text-slate-800">{selectedCompetitor.word_count.toLocaleString()}</p>
                </div>
                <div>
                  <span className="text-slate-500">Mật độ KW:</span>
                  <p className="font-bold text-slate-800">{selectedCompetitor.keyword_density}%</p>
                </div>
                <div>
                  <span className="text-slate-500">Số ảnh:</span>
                  <p className="font-bold text-slate-800">{selectedCompetitor.images_count}</p>
                </div>
              </div>

              {/* LSI Entities tags */}
              {selectedCompetitor.lsi_keywords && selectedCompetitor.lsi_keywords.length > 0 && (
                <div>
                  <h4 className="text-xs font-semibold text-slate-700 mb-1.5 flex items-center space-x-1">
                    <Sparkles className="h-3.5 w-3.5 text-amber-500" />
                    <span>Thực thể ngữ nghĩa LSI tìm thấy:</span>
                  </h4>
                  <div className="flex flex-wrap gap-1.5">
                    {selectedCompetitor.lsi_keywords.map((lsi) => (
                      <Badge key={lsi} variant="secondary" className="text-xs bg-slate-100 text-slate-700 font-normal">
                        {lsi}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}

              {/* Heading Map Tree */}
              <div>
                <h4 className="text-xs font-semibold text-slate-700 mb-1.5 flex items-center space-x-1">
                  <Heading className="h-3.5 w-3.5 text-indigo-600" />
                  <span>Cấu trúc Headings (Heading Map):</span>
                </h4>
                <ScrollArea className="h-56 rounded-md border border-slate-200 bg-white p-3">
                  {selectedCompetitor.heading_structure && selectedCompetitor.heading_structure.length > 0 ? (
                    <div className="space-y-1.5">
                      {selectedCompetitor.heading_structure.map((h, idx) => (
                        <div
                          key={`${h.tag}-${idx}`}
                          className={`text-xs flex items-start space-x-2 ${
                            h.level === 1
                              ? 'font-bold text-slate-900 border-b border-slate-100 pb-1'
                              : h.level === 2
                              ? 'font-semibold text-indigo-700 pl-3'
                              : h.level === 3
                              ? 'text-slate-600 pl-6'
                              : 'text-slate-500 pl-9 italic'
                          }`}
                        >
                          <span className="rounded bg-slate-100 px-1 py-0.2 text-[10px] font-mono uppercase text-slate-500">
                            {h.tag}
                          </span>
                          <span className="leading-snug">{h.text}</span>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-xs text-slate-400 italic text-center py-4">Không trích xuất được heading</p>
                  )}
                </ScrollArea>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
