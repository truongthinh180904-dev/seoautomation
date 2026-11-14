'use client';

import React, { useState } from 'react';
import {
  TrackedKeywordItem,
  useDeleteTrackedKeyword,
  useRefreshTrackedKeyword,
} from '@/hooks/useRankTracker';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  TrendingUp,
  TrendingDown,
  Minus,
  RefreshCw,
  Trash2,
  LineChart,
  Search,
  Monitor,
  Smartphone,
  ExternalLink,
} from 'lucide-react';

interface RankKeywordListProps {
  keywords: TrackedKeywordItem[];
  isLoading?: boolean;
  selectedKeywordId?: number | null;
  onSelectKeyword: (keyword: TrackedKeywordItem) => void;
  searchQuery: string;
  onSearchChange: (query: string) => void;
  deviceFilter: string;
  onDeviceFilterChange: (device: string) => void;
}

export default function RankKeywordList({
  keywords,
  isLoading,
  selectedKeywordId,
  onSelectKeyword,
  searchQuery,
  onSearchChange,
  deviceFilter,
  onDeviceFilterChange,
}: RankKeywordListProps) {
  const [refreshingId, setRefreshingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const refreshMutation = useRefreshTrackedKeyword();
  const deleteMutation = useDeleteTrackedKeyword();

  const handleRefresh = async (e: React.MouseEvent, id: number) => {
    e.stopPropagation();
    try {
      setRefreshingId(id);
      await refreshMutation.mutateAsync(id);
    } finally {
      setRefreshingId(null);
    }
  };

  const handleDelete = async (e: React.MouseEvent, id: number, kw: string) => {
    e.stopPropagation();
    if (!window.confirm(`Bạn có chắc muốn xóa từ khóa "${kw}" khỏi danh sách theo dõi?`)) {
      return;
    }
    try {
      setDeletingId(id);
      await deleteMutation.mutateAsync(id);
    } finally {
      setDeletingId(null);
    }
  };

  const renderDelta = (delta: number | null) => {
    if (delta === null || delta === 0) {
      return (
        <span className="flex items-center text-slate-400 font-medium text-xs whitespace-nowrap">
          <Minus className="w-3.5 h-3.5 mr-0.5" /> 0
        </span>
      );
    }
    if (delta > 0) {
      return (
        <span className="flex items-center text-emerald-600 font-bold text-xs whitespace-nowrap">
          <TrendingUp className="w-3.5 h-3.5 mr-0.5" /> +{delta}
        </span>
      );
    }
    return (
      <span className="flex items-center text-rose-600 font-bold text-xs whitespace-nowrap">
        <TrendingDown className="w-3.5 h-3.5 mr-0.5" /> {delta}
      </span>
    );
  };

  return (
    <div className="space-y-4">
      {/* Search & Filter Toolbar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3 rounded-xl border border-slate-200">
        <div className="relative flex-1">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <Input
            placeholder="Tìm kiếm từ khóa..."
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-9 text-xs sm:text-sm h-9"
          />
        </div>

        <div className="flex items-center gap-2">
          <Select
            value={deviceFilter}
            onValueChange={(val) => {
              if (val) onDeviceFilterChange(val);
            }}
          >
            <SelectTrigger className="w-[140px] h-9 text-xs">
              <SelectValue placeholder="Thiết bị" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Tất cả thiết bị</SelectItem>
              <SelectItem value="desktop">Máy tính (Desktop)</SelectItem>
              <SelectItem value="mobile">Điện thoại (Mobile)</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Responsive Table Container (Reviewer Rule 12) */}
      <div className="border border-slate-200 rounded-xl bg-white shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <Table>
            <TableHeader className="bg-slate-50">
              <TableRow>
                <TableHead className="font-bold text-slate-700 text-xs">Từ khóa theo dõi</TableHead>
                <TableHead className="font-bold text-slate-700 text-xs text-center whitespace-nowrap">
                  Thiết bị
                </TableHead>
                <TableHead className="font-bold text-slate-700 text-xs text-center whitespace-nowrap">
                  Vị trí
                </TableHead>
                <TableHead className="font-bold text-slate-700 text-xs text-center whitespace-nowrap">
                  Biến động
                </TableHead>
                <TableHead className="font-bold text-slate-700 text-xs text-center whitespace-nowrap">
                  Tốt nhất
                </TableHead>
                <TableHead className="font-bold text-slate-700 text-xs whitespace-nowrap">
                  Kiểm tra lần cuối
                </TableHead>
                <TableHead className="font-bold text-slate-700 text-xs text-right whitespace-nowrap">
                  Thao tác
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                [...Array(5)].map((_, i) => (
                  <TableRow key={i}>
                    <TableCell colSpan={7} className="py-4">
                      <div className="h-6 bg-slate-100 rounded-md animate-pulse" />
                    </TableCell>
                  </TableRow>
                ))
              ) : keywords.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="py-12 text-center text-slate-500 text-sm">
                    Không tìm thấy từ khóa theo dõi nào phù hợp.
                  </TableCell>
                </TableRow>
              ) : (
                keywords.map((item) => {
                  const isSelected = selectedKeywordId === item.id;
                  const isRefreshing = refreshingId === item.id;
                  const isDeleting = deletingId === item.id;

                  return (
                    <TableRow
                      key={item.id}
                      onClick={() => onSelectKeyword(item)}
                      className={`cursor-pointer transition-colors ${
                        isSelected ? 'bg-indigo-50/70 hover:bg-indigo-50' : 'hover:bg-slate-50/80'
                      }`}
                    >
                      {/* Keyword Title & Target URL */}
                      <TableCell className="max-w-xs">
                        <div className="font-bold text-slate-900 text-sm">{item.keyword}</div>
                        {item.target_url ? (
                          <div className="text-xs text-slate-400 truncate flex items-center gap-1 mt-0.5">
                            <span className="truncate">{item.target_url}</span>
                            <ExternalLink className="w-3 h-3 shrink-0" />
                          </div>
                        ) : (
                          <div className="text-[11px] text-slate-400 italic mt-0.5">
                            Khớp toàn bộ domain
                          </div>
                        )}
                      </TableCell>

                      {/* Device & Country */}
                      <TableCell className="text-center whitespace-nowrap">
                        <Badge
                          variant="secondary"
                          className="text-[11px] font-medium gap-1 uppercase"
                        >
                          {item.device === 'mobile' ? (
                            <Smartphone className="w-3 h-3 text-slate-500" />
                          ) : (
                            <Monitor className="w-3 h-3 text-slate-500" />
                          )}
                          {item.device} • {item.country}
                        </Badge>
                      </TableCell>

                      {/* Current Position */}
                      <TableCell className="text-center whitespace-nowrap">
                        {item.current_position ? (
                          <Badge
                            className={`font-extrabold text-xs px-2.5 py-0.5 ${
                              item.current_position <= 3
                                ? 'bg-emerald-600 text-white'
                                : item.current_position <= 10
                                ? 'bg-indigo-600 text-white'
                                : item.current_position <= 30
                                ? 'bg-blue-500 text-white'
                                : 'bg-slate-200 text-slate-800'
                            }`}
                          >
                            #{item.current_position}
                          </Badge>
                        ) : (
                          <span className="text-slate-400 font-semibold text-xs">&gt;100</span>
                        )}
                      </TableCell>

                      {/* Delta Rank */}
                      <TableCell className="text-center whitespace-nowrap">
                        <div className="flex justify-center">{renderDelta(item.delta_rank)}</div>
                      </TableCell>

                      {/* Best Position */}
                      <TableCell className="text-center whitespace-nowrap">
                        <span className="text-xs font-semibold text-slate-700">
                          {item.best_position ? `#${item.best_position}` : '-'}
                        </span>
                      </TableCell>

                      {/* Last Checked */}
                      <TableCell className="text-xs text-slate-500 whitespace-nowrap">
                        {item.last_checked_at
                          ? new Date(item.last_checked_at).toLocaleDateString('vi-VN', {
                              hour: '2-digit',
                              minute: '2-digit',
                              day: '2-digit',
                              month: '2-digit',
                            })
                          : 'Chưa kiểm tra'}
                      </TableCell>

                      {/* Actions with Double-Submit Protection */}
                      <TableCell className="text-right whitespace-nowrap">
                        <div
                          className="flex items-center justify-end gap-1"
                          onClick={(e) => e.stopPropagation()}
                        >
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-slate-500 hover:text-indigo-600"
                            title="Xem biểu đồ"
                            onClick={() => onSelectKeyword(item)}
                          >
                            <LineChart className="w-4 h-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-slate-500 hover:text-emerald-600"
                            title="Làm mới thứ hạng"
                            disabled={isRefreshing || isDeleting}
                            onClick={(e) => handleRefresh(e, item.id)}
                          >
                            <RefreshCw
                              className={`w-4 h-4 ${isRefreshing ? 'animate-spin text-emerald-600' : ''}`}
                            />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-slate-500 hover:text-rose-600"
                            title="Xóa từ khóa"
                            disabled={isDeleting || isRefreshing}
                            onClick={(e) => handleDelete(e, item.id, item.keyword)}
                          >
                            <Trash2 className="w-4 h-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}
