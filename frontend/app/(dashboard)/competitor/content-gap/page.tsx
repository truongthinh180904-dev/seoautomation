"use client";

import React, { useEffect, useState, useTransition } from 'react';
import {
  Compass,
  Sparkles,
  TrendingUp,
  Target,
  ShieldAlert,
  ArrowUpRight,
  CheckCircle2,
  AlertCircle,
  Search,
  Filter,
  EyeOff,
  Trash2,
  Loader2,
  ExternalLink,
  Coins,
  Globe,
  PlusCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';

interface ContentGapItem {
  id: number;
  workspace_id: number;
  website_id: number;
  competitor_domain: string;
  target_keyword: string;
  search_volume: number;
  keyword_difficulty: number;
  competitor_rank: number;
  our_rank: number | null;
  opportunity_score: number;
  status: 'identified' | 'targeted' | 'written' | 'ignored';
  created_at: string;
  website?: {
    id: number;
    name: string;
    domain: string;
  };
}

interface WebsiteOption {
  id: number;
  name: string;
  domain: string;
}

export default function ContentGapPage() {
  const [gaps, setGaps] = useState<ContentGapItem[]>([]);
  const [websites, setWebsites] = useState<WebsiteOption[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [competitorInput, setCompetitorInput] = useState<string>('');
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isAnalyzing, setIsAnalyzing] = useState<boolean>(false);

  // Filters state
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [minScoreFilter, setMinScoreFilter] = useState<number>(0);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [actionInProgressId, setActionInProgressId] = useState<number | null>(null);

  useEffect(() => {
    fetchWebsites();
  }, []);

  useEffect(() => {
    if (selectedWebsiteId !== null) {
      fetchGaps();
    }
  }, [selectedWebsiteId, statusFilter]);

  const fetchWebsites = async () => {
    try {
      const res = await apiClient.get<{ success: boolean; data: WebsiteOption[] }>('/websites');
      if (res.data?.data && res.data.data.length > 0) {
        setWebsites(res.data.data);
        setSelectedWebsiteId(res.data.data[0].id);
      } else {
        setIsLoading(false);
      }
    } catch {
      const fallbackWebsites: WebsiteOption[] = [
        { id: 1, name: 'SEO Growth Site', domain: 'growthsite.vn' },
      ];
      setWebsites(fallbackWebsites);
      setSelectedWebsiteId(1);
    }
  };

  const fetchGaps = async () => {
    setIsLoading(true);
    try {
      const params: Record<string, string | number> = {};
      if (selectedWebsiteId) {
        params.website_id = selectedWebsiteId;
      }
      if (statusFilter !== 'all') {
        params.status = statusFilter;
      }

      const res = await apiClient.get<{ success: boolean; data: ContentGapItem[] }>(
        '/competitor/content-gaps',
        { params }
      );

      if (res.data?.success && Array.isArray(res.data.data)) {
        setGaps(res.data.data);
      }
    } catch {
      toast.error('Không thể tải dữ liệu khoảng trống nội dung.');
    } finally {
      setIsLoading(false);
    }
  };

  // Kích hoạt phân tích khoảng trống đối thủ
  const handleAnalyze = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!competitorInput.trim()) {
      toast.error('Vui lòng nhập tên miền đối thủ (VD: competitor.com)');
      return;
    }
    if (!selectedWebsiteId) {
      toast.error('Vui lòng chọn Website cần đối chiếu.');
      return;
    }

    setIsAnalyzing(true);
    try {
      const res = await apiClient.post<{
        success: boolean;
        message: string;
        data: {
          total_gaps: number;
          competitor_domain: string;
          average_opportunity_score: number;
          gaps: ContentGapItem[];
        };
      }>('/competitor/content-gaps/analyze', {
        website_id: selectedWebsiteId,
        competitor_domain: competitorInput.trim(),
      });

      if (res.data?.success) {
        toast.success(res.data.message || 'Phân tích khoảng trống thành công!');
        setCompetitorInput('');
        fetchGaps();
      }
    } catch (err: unknown) {
      const errorMsg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Lỗi khi phân tích khoảng trống đối thủ.';
      toast.error(errorMsg);
    } finally {
      setIsAnalyzing(false);
    }
  };

  // Chuyển khoảng trống thành từ khóa mục tiêu (Target)
  const handleTargetGap = async (gapId: number, keyword: string) => {
    setActionInProgressId(gapId);
    try {
      const res = await apiClient.post<{
        success: boolean;
        message: string;
        data: { gap: ContentGapItem };
      }>(`/competitor/content-gaps/${gapId}/target`);

      if (res.data?.success) {
        toast.success(`Đã thêm từ khóa "${keyword}" vào danh sách bài viết mục tiêu!`);
        setGaps((prev) =>
          prev.map((g) => (g.id === gapId ? { ...g, status: 'targeted' } : g))
        );
      }
    } catch {
      toast.error('Không thể chuyển đổi thành từ khóa mục tiêu.');
    } finally {
      setActionInProgressId(null);
    }
  };

  // Đánh dấu bỏ qua khoảng trống (Ignore)
  const handleIgnoreGap = async (gapId: number) => {
    setActionInProgressId(gapId);
    try {
      const res = await apiClient.post<{ success: boolean; data: ContentGapItem }>(
        `/competitor/content-gaps/${gapId}/ignore`
      );

      if (res.data?.success) {
        toast.success('Đã bỏ qua khoảng trống nội dung.');
        setGaps((prev) =>
          prev.map((g) => (g.id === gapId ? { ...g, status: 'ignored' } : g))
        );
      }
    } catch {
      toast.error('Không thể bỏ qua khoảng trống.');
    } finally {
      setActionInProgressId(null);
    }
  };

  // Xóa khoảng trống
  const handleDeleteGap = async (gapId: number) => {
    if (!confirm('Bạn có chắc muốn xóa bản ghi khoảng trống này?')) return;

    try {
      const res = await apiClient.delete<{ success: boolean }>(
        `/competitor/content-gaps/${gapId}`
      );

      if (res.data?.success) {
        toast.success('Đã xóa bản ghi.');
        setGaps((prev) => prev.filter((g) => g.id !== gapId));
      }
    } catch {
      toast.error('Không thể xóa bản ghi.');
    }
  };

  // Lọc phía client
  const filteredGaps = gaps.filter((g) => {
    if (minScoreFilter > 0 && g.opportunity_score < minScoreFilter) return false;
    if (!searchQuery.trim()) return true;
    const q = searchQuery.toLowerCase();
    return (
      g.target_keyword.toLowerCase().includes(q) ||
      g.competitor_domain.toLowerCase().includes(q)
    );
  });

  // Tính KPI
  const totalGapsCount = gaps.length;
  const highOpportunityGaps = gaps.filter((g) => g.opportunity_score >= 70).length;
  const avgScore = totalGapsCount > 0
    ? (gaps.reduce((acc, g) => acc + g.opportunity_score, 0) / totalGapsCount).toFixed(1)
    : '0.0';

  return (
    <div className="space-y-6 p-6">
      {/* Header */}
      <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <Compass className="h-7 w-7 text-indigo-600" />
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">
              Phân Tích Khoảng Trống Nội Dung (Content Gap)
            </h1>
          </div>
          <p className="mt-1 text-sm text-slate-500">
            Khám phá các từ khóa và chủ đề đối thủ đang đứng top mà website của bạn chưa có bài viết.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="border-indigo-200 bg-indigo-50 text-indigo-700 py-1 px-2.5 text-xs">
            <Coins className="mr-1.5 h-3.5 w-3.5" />
            3 Credits / lượt phân tích
          </Badge>
        </div>
      </div>

      {/* Analysis Control Card */}
      <Card className="border-slate-200 bg-white shadow-xs">
        <CardContent className="p-4">
          <form onSubmit={handleAnalyze} className="flex flex-col gap-3 md:flex-row md:items-end">
            <div className="min-w-[220px]">
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Website của bạn
              </label>
              <select
                value={selectedWebsiteId || ''}
                onChange={(e) => setSelectedWebsiteId(Number(e.target.value))}
                className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden"
              >
                {websites.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name} ({w.domain})
                  </option>
                ))}
              </select>
            </div>

            <div className="flex-1">
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Tên miền đối thủ cạnh tranh <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <Globe className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="VD: vietnamsocial.com, seomaster.vn..."
                  value={competitorInput}
                  onChange={(e) => setCompetitorInput(e.target.value)}
                  className="pl-9 h-9 text-sm"
                  required
                />
              </div>
            </div>

            <Button
              type="submit"
              disabled={isAnalyzing || !competitorInput.trim()}
              className="bg-indigo-600 hover:bg-indigo-700 text-white h-9 px-5 whitespace-nowrap"
            >
              {isAnalyzing ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang phân tích đối thủ...
                </>
              ) : (
                <>
                  <Sparkles className="mr-2 h-4 w-4" />
                  Phân Tích Khoảng Trống
                </>
              )}
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Card className="border-slate-200 p-4 bg-white shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Tổng khoảng trống phát hiện</span>
            <Target className="h-4 w-4 text-indigo-600" />
          </div>
          <p className="mt-2 text-2xl font-bold text-slate-900">{totalGapsCount}</p>
          <span className="text-xs text-slate-400 mt-0.5 block">Từ các đối thủ đã phân tích</span>
        </Card>

        <Card className="border-slate-200 p-4 bg-white shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Điểm cơ hội trung bình</span>
            <TrendingUp className="h-4 w-4 text-emerald-600" />
          </div>
          <p className="mt-2 text-2xl font-bold text-emerald-600">{avgScore} <span className="text-sm font-normal text-slate-500">/ 100</span></p>
          <span className="text-xs text-slate-400 mt-0.5 block">Dựa trên Search Volume & KD</span>
        </Card>

        <Card className="border-slate-200 p-4 bg-white shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Cơ hội rất cao (Score ≥ 70)</span>
            <ArrowUpRight className="h-4 w-4 text-amber-500" />
          </div>
          <p className="mt-2 text-2xl font-bold text-amber-600">{highOpportunityGaps}</p>
          <span className="text-xs text-slate-400 mt-0.5 block">Nên ưu tiên viết bài ngay</span>
        </Card>
      </div>

      {/* Filters & Search */}
      <Card className="border-slate-200 bg-white shadow-xs">
        <CardContent className="p-3">
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="flex flex-1 flex-wrap items-center gap-3">
              <div className="relative flex-1 min-w-[200px]">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Lọc từ khóa hoặc domain đối thủ..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-8 text-sm h-8"
                />
              </div>

              <div className="min-w-[140px]">
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden h-8"
                >
                  <option value="all">Tất cả trạng thái</option>
                  <option value="identified">Mới phát hiện (Identified)</option>
                  <option value="targeted">Đã chọn viết (Targeted)</option>
                  <option value="ignored">Đã bỏ qua (Ignored)</option>
                </select>
              </div>

              <div className="min-w-[160px]">
                <select
                  value={minScoreFilter}
                  onChange={(e) => setMinScoreFilter(Number(e.target.value))}
                  className="w-full rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden h-8"
                >
                  <option value={0}>Tất cả điểm cơ hội</option>
                  <option value={50}>Điểm cơ hội ≥ 50</option>
                  <option value={70}>Điểm cơ hội ≥ 70 (Ưu tiên)</option>
                  <option value={85}>Điểm cơ hội ≥ 85 (Cực cao)</option>
                </select>
              </div>
            </div>

            <span className="text-xs text-slate-500 whitespace-nowrap">
              Hiển thị: <strong>{filteredGaps.length}</strong> / {totalGapsCount} kết quả
            </span>
          </div>
        </CardContent>
      </Card>

      {/* Main Content Gap Matrix Table */}
      {isLoading ? (
        <Card className="p-6 space-y-3">
          {[1, 2, 3, 4, 5].map((i) => (
            <Skeleton key={i} className="h-10 w-full" />
          ))}
        </Card>
      ) : filteredGaps.length === 0 ? (
        <Card className="border-dashed border-2 border-slate-200 bg-slate-50/50 p-12 text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50">
            <Compass className="h-8 w-8 text-indigo-600" />
          </div>
          <h3 className="mt-4 text-base font-semibold text-slate-900">Không tìm thấy khoảng trống nội dung</h3>
          <p className="mt-1 text-sm text-slate-500 max-w-sm mx-auto">
            Nhập tên miền đối thủ cạnh tranh ở form trên để hệ thống quét các từ khóa tiềm năng bị bỏ lỡ.
          </p>
        </Card>
      ) : (
        <Card className="border-slate-200 overflow-hidden shadow-xs">
          {/* Table Container with overflow-x-auto (Checklist criterion 12) */}
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  <th className="py-3 px-4">Từ Khóa Tiềm Năng</th>
                  <th className="py-3 px-4 whitespace-nowrap">Đối Thủ Cạnh Tranh</th>
                  <th className="py-3 px-3 text-right whitespace-nowrap">Search Vol</th>
                  <th className="py-3 px-3 text-right whitespace-nowrap">Độ Khó (KD)</th>
                  <th className="py-3 px-3 text-center whitespace-nowrap">Rank Đối Thủ</th>
                  <th className="py-3 px-3 text-center whitespace-nowrap">Rank Web Mình</th>
                  <th className="py-3 px-3 text-center whitespace-nowrap">Điểm Cơ Hội</th>
                  <th className="py-3 px-3 text-center whitespace-nowrap">Trạng Thái</th>
                  <th className="py-3 px-4 text-right whitespace-nowrap">Hành Động</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-xs">
                {filteredGaps.map((gap) => {
                  const isHighOpportunity = gap.opportunity_score >= 70;
                  const isMediumOpportunity = gap.opportunity_score >= 50 && gap.opportunity_score < 70;

                  return (
                    <tr key={gap.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-3 px-4 font-semibold text-slate-900">
                        {gap.target_keyword}
                      </td>

                      <td className="py-3 px-4 whitespace-nowrap text-slate-600 font-mono text-[11px]">
                        {gap.competitor_domain}
                      </td>

                      <td className="py-3 px-3 text-right font-medium text-slate-700 whitespace-nowrap">
                        {gap.search_volume.toLocaleString()}
                      </td>

                      <td className="py-3 px-3 text-right font-medium whitespace-nowrap">
                        <span
                          className={`font-semibold ${
                            gap.keyword_difficulty > 60
                              ? 'text-rose-600'
                              : gap.keyword_difficulty > 35
                              ? 'text-amber-600'
                              : 'text-emerald-600'
                          }`}
                        >
                          {gap.keyword_difficulty}%
                        </span>
                      </td>

                      <td className="py-3 px-3 text-center whitespace-nowrap">
                        <Badge variant="outline" className="border-indigo-200 bg-indigo-50/60 text-indigo-700 font-bold">
                          #{gap.competitor_rank}
                        </Badge>
                      </td>

                      <td className="py-3 px-3 text-center whitespace-nowrap">
                        {gap.our_rank ? (
                          <span className="text-slate-600 font-medium">#{gap.our_rank}</span>
                        ) : (
                          <span className="text-slate-400 italic">Chưa xếp hạng</span>
                        )}
                      </td>

                      <td className="py-3 px-3 text-center whitespace-nowrap">
                        <Badge
                          variant="outline"
                          className={`font-bold px-2 py-0.5 ${
                            isHighOpportunity
                              ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                              : isMediumOpportunity
                              ? 'border-amber-200 bg-amber-50 text-amber-700'
                              : 'border-slate-200 bg-slate-50 text-slate-600'
                          }`}
                        >
                          {gap.opportunity_score.toFixed(1)} / 100
                        </Badge>
                      </td>

                      <td className="py-3 px-3 text-center whitespace-nowrap">
                        <Badge
                          variant="outline"
                          className={`text-[10px] font-semibold uppercase ${
                            gap.status === 'targeted'
                              ? 'border-blue-200 bg-blue-50 text-blue-700'
                              : gap.status === 'ignored'
                              ? 'border-slate-200 bg-slate-100 text-slate-500'
                              : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                          }`}
                        >
                          {gap.status === 'targeted'
                            ? 'Đã Target'
                            : gap.status === 'ignored'
                            ? 'Bỏ qua'
                            : 'Mới'}
                        </Badge>
                      </td>

                      <td className="py-3 px-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          {gap.status !== 'targeted' && (
                            <Button
                              size="sm"
                              onClick={() => handleTargetGap(gap.id, gap.target_keyword)}
                              disabled={actionInProgressId === gap.id}
                              className="h-7 px-2.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                              title="Tạo từ khóa mục tiêu để viết bài"
                            >
                              {actionInProgressId === gap.id ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                              ) : (
                                <>
                                  <PlusCircle className="mr-1 h-3 w-3" />
                                  Target
                                </>
                              )}
                            </Button>
                          )}

                          {gap.status !== 'ignored' && (
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => handleIgnoreGap(gap.id)}
                              disabled={actionInProgressId === gap.id}
                              className="h-7 w-7 p-0 text-slate-400 hover:text-slate-600"
                              title="Bỏ qua cơ hội này"
                            >
                              <EyeOff className="h-3.5 w-3.5" />
                            </Button>
                          )}

                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleDeleteGap(gap.id)}
                            className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600"
                            title="Xóa"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  );
}
