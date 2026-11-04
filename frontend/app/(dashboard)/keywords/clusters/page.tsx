"use client";

import React, { useEffect, useState, useTransition } from 'react';
import {
  Layers,
  Sparkles,
  Plus,
  FolderPlus,
  Search,
  CheckCircle2,
  Clock,
  FileText,
  BarChart3,
  Target,
  ArrowRight,
  Trash2,
  Network,
  LayoutGrid,
  Loader2,
  ExternalLink,
  ShieldAlert,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';

interface SupportingKeyword {
  id: number;
  topic_cluster_id: number | null;
  keyword: string;
  search_volume: number | null;
  difficulty: number | null;
  search_intent: string | null;
  status: string | null;
}

interface PillarKeyword {
  id: number;
  keyword: string;
  search_volume: number | null;
  difficulty: number | null;
  search_intent: string | null;
  status: string | null;
}

interface PillarArticle {
  id: number;
  title: string;
  slug: string;
  status: string | null;
}

interface TopicCluster {
  id: number;
  workspace_id: number;
  website_id: number;
  pillar_keyword_id: number | null;
  pillar_article_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  target_audience: string | null;
  total_search_volume: number;
  avg_difficulty: number;
  status: 'planning' | 'in_progress' | 'completed';
  created_at: string;
  website?: {
    id: number;
    name: string;
    domain: string;
  };
  pillar_keyword?: PillarKeyword | null;
  pillar_article?: PillarArticle | null;
  keywords?: SupportingKeyword[];
}

interface AiPillarKeyword {
  keyword: string;
  search_volume: number;
  difficulty: number;
  search_intent: string;
}

interface AiSupportingKeyword {
  keyword: string;
  search_volume: number;
  difficulty: number;
  search_intent: string;
}

interface AiClusterSuggestion {
  name: string;
  slug: string;
  description: string;
  target_audience: string;
  pillar_keyword: AiPillarKeyword;
  supporting_keywords: AiSupportingKeyword[];
}

interface WebsiteOption {
  id: number;
  name: string;
  domain: string;
}

export default function TopicClustersPage() {
  const [clusters, setClusters] = useState<TopicCluster[]>([]);
  const [websites, setWebsites] = useState<WebsiteOption[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [viewMode, setViewMode] = useState<'cards' | 'map'>('cards');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Dialog States
  const [isCreateOpen, setIsCreateOpen] = useState<boolean>(false);
  const [isAiOpen, setIsAiOpen] = useState<boolean>(false);
  const [isAssignOpen, setIsAssignOpen] = useState<boolean>(false);
  const [activeCluster, setActiveCluster] = useState<TopicCluster | null>(null);

  // Form States & Submitting
  const [isSubmitting, startTransition] = useTransition();
  const [createForm, setCreateForm] = useState({
    name: '',
    description: '',
    target_audience: '',
    status: 'planning',
  });
  const [seedTopic, setSeedTopic] = useState<string>('');
  const [aiSuggestions, setAiSuggestions] = useState<AiClusterSuggestion[]>([]);
  const [isGeneratingAi, setIsGeneratingAi] = useState<boolean>(false);
  const [assignKeywordInput, setAssignKeywordInput] = useState<string>('');

  // Load danh sách Websites và Clusters ban đầu
  useEffect(() => {
    fetchWebsites();
  }, []);

  useEffect(() => {
    if (selectedWebsiteId !== null) {
      fetchClusters();
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
      // Fallback nếu api /websites không có data
      const defaultWebsites: WebsiteOption[] = [
        { id: 1, name: 'SEO Growth Site', domain: 'growthsite.vn' },
      ];
      setWebsites(defaultWebsites);
      setSelectedWebsiteId(1);
    }
  };

  const fetchClusters = async () => {
    setIsLoading(true);
    try {
      const params: Record<string, string | number> = {};
      if (selectedWebsiteId) {
        params.website_id = selectedWebsiteId;
      }
      if (statusFilter !== 'all') {
        params.status = statusFilter;
      }
      if (searchQuery.trim()) {
        params.search = searchQuery.trim();
      }

      const res = await apiClient.get<{ success: boolean; data: TopicCluster[] }>('/topic-clusters', {
        params,
      });

      if (res.data?.success && Array.isArray(res.data.data)) {
        setClusters(res.data.data);
      }
    } catch {
      toast.error('Không thể tải danh sách Cụm chủ đề.');
    } finally {
      setIsLoading(false);
    }
  };

  // Tạo cụm chủ đề thủ công
  const handleCreateCluster = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!createForm.name.trim()) {
      toast.error('Vui lòng nhập tên cụm chủ đề.');
      return;
    }
    if (!selectedWebsiteId) {
      toast.error('Vui lòng chọn website.');
      return;
    }

    startTransition(async () => {
      try {
        const payload = {
          website_id: selectedWebsiteId,
          name: createForm.name.trim(),
          description: createForm.description.trim() || undefined,
          target_audience: createForm.target_audience.trim() || undefined,
          status: createForm.status,
        };

        const res = await apiClient.post<{ success: boolean; data: TopicCluster }>('/topic-clusters', payload);

        if (res.data?.success) {
          toast.success('Tạo cụm chủ đề thành công!');
          setIsCreateOpen(false);
          setCreateForm({ name: '', description: '', target_audience: '', status: 'planning' });
          fetchClusters();
        }
      } catch (err: unknown) {
        const errorMsg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Lỗi khi tạo cụm chủ đề.';
        toast.error(errorMsg);
      }
    });
  };

  // Sinh cụm chủ đề bằng AI
  const handleGenerateAi = async () => {
    if (!seedTopic.trim()) {
      toast.error('Vui lòng nhập chủ đề gốc (Seed Topic).');
      return;
    }
    if (!selectedWebsiteId) {
      toast.error('Vui lòng chọn website.');
      return;
    }

    setIsGeneratingAi(true);
    setAiSuggestions([]);

    try {
      const res = await apiClient.post<{
        success: boolean;
        data: {
          total_clusters: number;
          clusters: AiClusterSuggestion[];
        };
      }>('/topic-clusters/generate-ai', {
        website_id: selectedWebsiteId,
        seed_topic: seedTopic.trim(),
      });

      if (res.data?.success && res.data.data?.clusters) {
        setAiSuggestions(res.data.data.clusters);
        toast.success(`AI đã sinh ${res.data.data.clusters.length} gợi ý cụm chủ đề!`);
      }
    } catch (err: unknown) {
      const errorMsg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Lỗi khi gợi ý cụm chủ đề AI.';
      toast.error(errorMsg);
    } finally {
      setIsGeneratingAi(false);
    }
  };

  // Chấp nhận và lưu cụm chủ đề từ AI
  const handleAdoptAiCluster = async (sugg: AiClusterSuggestion) => {
    if (!selectedWebsiteId) return;

    startTransition(async () => {
      try {
        const res = await apiClient.post<{ success: boolean }>('/topic-clusters', {
          website_id: selectedWebsiteId,
          name: sugg.name,
          slug: sugg.slug,
          description: sugg.description,
          target_audience: sugg.target_audience,
          status: 'planning',
        });

        if (res.data?.success) {
          toast.success(`Đã thêm cụm chủ đề: "${sugg.name}"`);
          fetchClusters();
        }
      } catch {
        toast.error('Không thể lưu cụm chủ đề từ AI.');
      }
    });
  };

  // Xóa cụm chủ đề
  const handleDeleteCluster = async (clusterId: number, clusterName: string) => {
    if (!confirm(`Bạn có chắc muốn xóa cụm chủ đề "${clusterName}"? Các từ khóa sẽ được giữ lại.`)) {
      return;
    }

    try {
      const res = await apiClient.delete<{ success: boolean }>(`/topic-clusters/${clusterId}`);
      if (res.data?.success) {
        toast.success('Đã xóa cụm chủ đề thành công.');
        setClusters((prev) => prev.filter((c) => c.id !== clusterId));
      }
    } catch {
      toast.error('Không thể xóa cụm chủ đề.');
    }
  };

  // Gỡ từ khóa ra khỏi cụm
  const handleRemoveKeyword = async (clusterId: number, keywordId: number) => {
    try {
      const res = await apiClient.post<{ success: boolean }>(`/topic-clusters/${clusterId}/remove-keyword`, {
        keyword_id: keywordId,
      });

      if (res.data?.success) {
        toast.success('Đã gỡ từ khóa khỏi cụm.');
        fetchClusters();
      }
    } catch {
      toast.error('Không thể gỡ từ khóa.');
    }
  };

  // Lọc danh sách cụm theo tìm kiếm client-side
  const filteredClusters = clusters.filter((c) => {
    if (!searchQuery.trim()) return true;
    const q = searchQuery.toLowerCase();
    return (
      c.name.toLowerCase().includes(q) ||
      c.slug.toLowerCase().includes(q) ||
      (c.description && c.description.toLowerCase().includes(q))
    );
  });

  return (
    <div className="space-y-6 p-6">
      {/* Top Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <Layers className="h-7 w-7 text-indigo-600" />
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">
              Cụm Chủ Đề SEO (Topic Clusters)
            </h1>
          </div>
          <p className="mt-1 text-sm text-slate-500">
            Mô hình cấu trúc Silo & Pillar giúp tối ưu thẩm quyền chuyên đề (Topical Authority) và leo top bền vững.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            onClick={() => setIsAiOpen(true)}
            className="border-indigo-200 bg-indigo-50/50 text-indigo-700 hover:bg-indigo-100/70"
          >
            <Sparkles className="mr-2 h-4 w-4 text-indigo-600" />
            Sinh Cụm bằng AI
          </Button>

          <Button
            onClick={() => setIsCreateOpen(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white"
          >
            <Plus className="mr-2 h-4 w-4" />
            Tạo Cụm Mới
          </Button>
        </div>
      </div>

      {/* Control Bar: Website selector, Filters, View toggle */}
      <Card className="border-slate-200 bg-white shadow-xs">
        <CardContent className="p-4">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            {/* Website Picker & Search */}
            <div className="flex flex-1 flex-wrap items-center gap-3">
              <div className="min-w-[200px]">
                <label className="text-xs font-semibold text-slate-500 mb-1 block">Chọn Website</label>
                <select
                  value={selectedWebsiteId || ''}
                  onChange={(e) => setSelectedWebsiteId(Number(e.target.value))}
                  className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden"
                >
                  {websites.map((w) => (
                    <option key={w.id} value={w.id}>
                      {w.name} ({w.domain})
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex-1 min-w-[220px]">
                <label className="text-xs font-semibold text-slate-500 mb-1 block">Tìm kiếm</label>
                <div className="relative">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                  <Input
                    placeholder="Tìm theo tên cụm, slug..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-8 text-sm h-9"
                  />
                </div>
              </div>

              <div className="min-w-[150px]">
                <label className="text-xs font-semibold text-slate-500 mb-1 block">Trạng thái</label>
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden"
                >
                  <option value="all">Tất cả trạng thái</option>
                  <option value="planning">Lập kế hoạch (Planning)</option>
                  <option value="in_progress">Đang viết (In Progress)</option>
                  <option value="completed">Đã hoàn thành (Completed)</option>
                </select>
              </div>
            </div>

            {/* View Mode Toggle */}
            <div className="flex items-center gap-1 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
              <label className="text-xs font-semibold text-slate-500 mr-2 hidden md:inline">Chế độ xem:</label>
              <div className="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                <Button
                  size="sm"
                  variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                  onClick={() => setViewMode('cards')}
                  className="h-8 px-3 text-xs"
                >
                  <LayoutGrid className="mr-1.5 h-3.5 w-3.5" />
                  Thẻ Cụm
                </Button>
                <Button
                  size="sm"
                  variant={viewMode === 'map' ? 'secondary' : 'ghost'}
                  onClick={() => setViewMode('map')}
                  className="h-8 px-3 text-xs"
                >
                  <Network className="mr-1.5 h-3.5 w-3.5" />
                  Bản Đồ Silo
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Main Content Area */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-6 space-y-4">
              <Skeleton className="h-6 w-3/4" />
              <Skeleton className="h-4 w-full" />
              <div className="grid grid-cols-2 gap-2 pt-2">
                <Skeleton className="h-12 w-full" />
                <Skeleton className="h-12 w-full" />
              </div>
              <Skeleton className="h-20 w-full" />
            </Card>
          ))}
        </div>
      ) : filteredClusters.length === 0 ? (
        <Card className="border-dashed border-2 border-slate-200 bg-slate-50/50 p-12 text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50">
            <Layers className="h-8 w-8 text-indigo-600" />
          </div>
          <h3 className="mt-4 text-lg font-semibold text-slate-900">Chưa có Cụm chủ đề nào</h3>
          <p className="mt-1 text-sm text-slate-500 max-w-md mx-auto">
            Xây dựng các cụm chủ đề giúp các công cụ tìm kiếm hiểu sâu về chuyên môn website của bạn.
          </p>
          <div className="mt-6 flex justify-center gap-3">
            <Button
              variant="outline"
              onClick={() => setIsAiOpen(true)}
              className="border-indigo-200 text-indigo-700 bg-white"
            >
              <Sparkles className="mr-2 h-4 w-4 text-indigo-600" />
              Sinh Gợi Ý Bằng AI
            </Button>
            <Button
              onClick={() => setIsCreateOpen(true)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
            >
              <Plus className="mr-2 h-4 w-4" />
              Tạo Cụm Đầu Tiên
            </Button>
          </div>
        </Card>
      ) : viewMode === 'cards' ? (
        /* Card Mode View */
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredClusters.map((cluster) => {
            const keywords = cluster.keywords || [];
            const totalKeywords = keywords.length;
            const completedKeywords = keywords.filter((k) => k.status === 'completed').length;
            const progress = totalKeywords > 0 ? Math.round((completedKeywords / totalKeywords) * 100) : 0;

            return (
              <Card key={cluster.id} className="border-slate-200 hover:shadow-md transition-shadow flex flex-col justify-between">
                <div>
                  <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <Badge
                          variant="outline"
                          className={`mb-2 text-xs font-semibold ${
                            cluster.status === 'completed'
                              ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                              : cluster.status === 'in_progress'
                              ? 'border-amber-200 bg-amber-50 text-amber-700'
                              : 'border-slate-200 bg-slate-100 text-slate-700'
                          }`}
                        >
                          {cluster.status === 'completed'
                            ? 'Hoàn thành'
                            : cluster.status === 'in_progress'
                            ? 'Đang triển khai'
                            : 'Lập kế hoạch'}
                        </Badge>
                        <CardTitle className="text-lg font-bold text-slate-900 line-clamp-1">
                          {cluster.name}
                        </CardTitle>
                        <CardDescription className="text-xs text-slate-500 font-mono mt-0.5">
                          /{cluster.slug}
                        </CardDescription>
                      </div>

                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDeleteCluster(cluster.id, cluster.name)}
                        className="h-8 w-8 p-0 text-slate-400 hover:text-rose-600"
                        title="Xóa cụm chủ đề"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>

                    {cluster.description && (
                      <p className="mt-2 text-xs text-slate-600 line-clamp-2">
                        {cluster.description}
                      </p>
                    )}
                  </CardHeader>

                  <CardContent className="space-y-4">
                    {/* Metrics Banner */}
                    <div className="grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-2.5 text-center border border-slate-100">
                      <div>
                        <span className="text-[11px] font-medium text-slate-400 block">Tổng Search Volume</span>
                        <span className="text-sm font-bold text-slate-800">
                          {Number(cluster.total_search_volume).toLocaleString()}
                        </span>
                      </div>
                      <div>
                        <span className="text-[11px] font-medium text-slate-400 block">Độ Khó TB (KD)</span>
                        <span className="text-sm font-bold text-indigo-600">
                          {Number(cluster.avg_difficulty).toFixed(1)}%
                        </span>
                      </div>
                    </div>

                    {/* Pillar Section */}
                    <div className="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3">
                      <span className="text-[11px] font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-1 mb-1">
                        <Target className="h-3.5 w-3.5" />
                        Trụ cột (Pillar Target)
                      </span>
                      {cluster.pillar_keyword ? (
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-semibold text-slate-800 line-clamp-1">
                            {cluster.pillar_keyword.keyword}
                          </span>
                          <span className="text-slate-500 whitespace-nowrap ml-2">
                            Vol: {cluster.pillar_keyword.search_volume?.toLocaleString() ?? 0}
                          </span>
                        </div>
                      ) : (
                        <span className="text-xs italic text-slate-400">Chưa gán từ khóa trụ cột</span>
                      )}
                    </div>

                    {/* Completion Progress */}
                    <div>
                      <div className="flex justify-between text-xs text-slate-600 mb-1">
                        <span>Tiến độ bài viết</span>
                        <span className="font-semibold">{completedKeywords}/{totalKeywords} ({progress}%)</span>
                      </div>
                      <Progress value={progress} className="h-1.5" />
                    </div>

                    {/* Supporting Keywords List */}
                    <div>
                      <span className="text-xs font-semibold text-slate-700 block mb-2">
                        Từ khóa vệ tinh ({totalKeywords}):
                      </span>

                      {totalKeywords === 0 ? (
                        <p className="text-xs text-slate-400 italic">Chưa có từ khóa vệ tinh nào.</p>
                      ) : (
                        <div className="max-h-40 overflow-y-auto space-y-1.5 pr-1">
                          {keywords.map((kw) => (
                            <div
                              key={kw.id}
                              className="flex items-center justify-between rounded bg-slate-50 px-2.5 py-1.5 text-xs border border-slate-100 group"
                            >
                              <span className="text-slate-800 line-clamp-1">{kw.keyword}</span>
                              <div className="flex items-center gap-2">
                                {kw.search_intent && (
                                  <Badge variant="outline" className="text-[10px] py-0 px-1 font-mono">
                                    {kw.search_intent}
                                  </Badge>
                                )}
                                <button
                                  onClick={() => handleRemoveKeyword(cluster.id, kw.id)}
                                  className="text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                  title="Gỡ khỏi cụm"
                                >
                                  <Trash2 className="h-3 w-3" />
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </CardContent>
                </div>
              </Card>
            );
          })}
        </div>
      ) : (
        /* Mindmap Visual Hierarchy View */
        <div className="space-y-6">
          {filteredClusters.map((cluster) => {
            const keywords = cluster.keywords || [];

            return (
              <Card key={cluster.id} className="border-slate-200 bg-white p-6 shadow-xs">
                <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 font-bold">
                      <Network className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="text-base font-bold text-slate-900">{cluster.name}</h3>
                      <p className="text-xs text-slate-500">Mô hình liên kết cụm Silo ngữ nghĩa</p>
                    </div>
                  </div>
                  <Badge variant="outline" className="capitalize text-xs font-semibold">
                    {cluster.status}
                  </Badge>
                </div>

                <div className="mt-6 flex flex-col items-center justify-center">
                  {/* Pillar Node (Center) */}
                  <div className="rounded-xl border-2 border-indigo-600 bg-indigo-50 p-4 text-center shadow-sm max-w-sm w-full">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-600 block mb-1">
                      PILLAR PAGE
                    </span>
                    <h4 className="text-sm font-bold text-slate-900">
                      {cluster.pillar_keyword ? cluster.pillar_keyword.keyword : cluster.name}
                    </h4>
                    <p className="text-xs text-slate-500 mt-1">
                      Volume: {Number(cluster.total_search_volume).toLocaleString()} | KD: {Number(cluster.avg_difficulty).toFixed(0)}%
                    </p>
                  </div>

                  {/* Connect Line */}
                  <div className="h-8 w-0.5 bg-indigo-200 my-1" />

                  {/* Supporting Cluster Sub-nodes */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 w-full">
                    {keywords.map((kw) => (
                      <div
                        key={kw.id}
                        className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center shadow-xs hover:border-indigo-300 transition-colors"
                      >
                        <span className="text-[10px] font-medium text-slate-400 block mb-1">
                          Supporting Article
                        </span>
                        <p className="text-xs font-semibold text-slate-800 line-clamp-1">{kw.keyword}</p>
                        <div className="mt-2 flex items-center justify-center gap-2">
                          <span className="text-[11px] text-slate-500">
                            Vol: {kw.search_volume?.toLocaleString() ?? 0}
                          </span>
                          <span className="text-[11px] text-indigo-600 font-medium">
                            KD: {kw.difficulty ?? 0}%
                          </span>
                        </div>
                      </div>
                    ))}

                    {keywords.length === 0 && (
                      <div className="col-span-full text-center py-4 text-xs italic text-slate-400">
                        Chưa có bài viết vệ tinh nào được gán vào cụm này.
                      </div>
                    )}
                  </div>
                </div>
              </Card>
            );
          })}
        </div>
      )}

      {/* Modal: Tạo Cụm Chủ Đề Mới */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>Tạo Cụm Chủ Đề Mới</DialogTitle>
            <DialogDescription>
              Tạo cấu trúc cụm chủ đề cho website để lên kế hoạch bài viết chiến lược.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleCreateCluster} className="space-y-4 py-2">
            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Tên Cụm Chủ Đề <span className="text-rose-500">*</span>
              </label>
              <Input
                placeholder="VD: Cẩm nang SEO cho người mới bắt đầu"
                value={createForm.name}
                onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })}
                required
              />
            </div>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Mô tả mục tiêu cụm
              </label>
              <Input
                placeholder="VD: Nhóm bài viết bao phủ kiến thức SEO Onpage từ cơ bản đến nâng cao"
                value={createForm.description}
                onChange={(e) => setCreateForm({ ...createForm, description: e.target.value })}
              />
            </div>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Đối tượng độc giả mục tiêu
              </label>
              <Input
                placeholder="VD: Marketers, Chủ shop online, Lập trình viên"
                value={createForm.target_audience}
                onChange={(e) => setCreateForm({ ...createForm, target_audience: e.target.value })}
              />
            </div>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">Trạng thái</label>
              <select
                value={createForm.status}
                onChange={(e) => setCreateForm({ ...createForm, status: e.target.value })}
                className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden"
              >
                <option value="planning">Lập kế hoạch (Planning)</option>
                <option value="in_progress">Đang triển khai (In Progress)</option>
                <option value="completed">Đã hoàn thành (Completed)</option>
              </select>
            </div>

            <DialogFooter className="pt-2">
              <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                Hủy
              </Button>
              <Button
                type="submit"
                disabled={isSubmitting}
                className="bg-indigo-600 hover:bg-indigo-700 text-white"
              >
                {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Tạo Cụm
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Modal: Sinh Cụm Bằng AI */}
      <Dialog open={isAiOpen} onOpenChange={setIsAiOpen}>
        <DialogContent className="sm:max-w-[700px] max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-indigo-600" />
              Sinh Cụm Chủ Đề Bằng AI
            </DialogTitle>
            <DialogDescription>
              Nhập chủ đề cốt lõi, AI sẽ tự động phân tích và kiến trúc 3-4 Topic Clusters chuẩn SEO Silo.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="flex gap-2">
              <Input
                placeholder="Nhập chủ đề gốc, ví dụ: 'Dịch vụ kế toán', 'Chăm sóc da mụn'..."
                value={seedTopic}
                onChange={(e) => setSeedTopic(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleGenerateAi()}
              />
              <Button
                onClick={handleGenerateAi}
                disabled={isGeneratingAi || !seedTopic.trim()}
                className="bg-indigo-600 hover:bg-indigo-700 text-white whitespace-nowrap"
              >
                {isGeneratingAi ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Đang phân tích...
                  </>
                ) : (
                  <>
                    <Sparkles className="mr-2 h-4 w-4" />
                    Sinh Cụm
                  </>
                )}
              </Button>
            </div>

            {/* AI Results Preview */}
            {aiSuggestions.length > 0 && (
              <div className="space-y-3 pt-2">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                  Gợi ý từ AI ({aiSuggestions.length} cụm):
                </h4>

                <div className="space-y-3">
                  {aiSuggestions.map((sugg, idx) => (
                    <div
                      key={idx}
                      className="rounded-lg border border-indigo-100 bg-indigo-50/30 p-4 space-y-2 hover:border-indigo-200 transition-colors"
                    >
                      <div className="flex items-start justify-between">
                        <div>
                          <h5 className="text-sm font-bold text-slate-900">{sugg.name}</h5>
                          <p className="text-xs text-slate-600 mt-0.5">{sugg.description}</p>
                        </div>
                        <Button
                          size="sm"
                          onClick={() => handleAdoptAiCluster(sugg)}
                          disabled={isSubmitting}
                          className="h-8 bg-indigo-600 hover:bg-indigo-700 text-white text-xs ml-3 whitespace-nowrap"
                        >
                          <FolderPlus className="mr-1 h-3.5 w-3.5" />
                          Thêm Cụm Này
                        </Button>
                      </div>

                      <div className="text-xs text-slate-700 pt-1">
                        <span className="font-semibold text-indigo-700">Pillar: </span>
                        {sugg.pillar_keyword.keyword} (Vol: {sugg.pillar_keyword.search_volume.toLocaleString()})
                      </div>

                      <div className="flex flex-wrap gap-1.5 pt-1">
                        {sugg.supporting_keywords.map((sk, kIdx) => (
                          <span
                            key={kIdx}
                            className="inline-flex items-center rounded bg-white px-2 py-0.5 text-[11px] text-slate-700 border border-slate-200"
                          >
                            {sk.keyword}
                          </span>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setIsAiOpen(false)}>
              Đóng
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
