"use client";

import React, { useEffect, useState, useTransition } from 'react';
import Link from 'next/link';
import {
  Link2,
  ExternalLink,
  Search,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Network,
  Sparkles,
  ArrowRight,
  Layers,
  Globe,
  Loader2,
  FileText,
  Check,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';

interface WebsiteOption {
  id: number;
  name: string;
  domain: string;
}

interface ArticleRef {
  id: number;
  title: string;
  slug: string;
  focus_keyword?: string;
  status: string;
}

interface LinkSuggestion {
  id: number;
  workspace_id: number;
  website_id: number;
  source_article_id: number;
  target_article_id: number;
  suggested_anchor_text: string;
  context_sentence?: string;
  relevance_score: number;
  status: 'pending' | 'accepted' | 'rejected' | 'applied';
  created_at: string;
  source_article?: ArticleRef;
  target_article?: ArticleRef;
}

interface GraphNode {
  id: number;
  title: string;
  slug: string;
  status: string;
  focus_keyword?: string;
  inbound_count: number;
  outbound_count: number;
}

interface GraphEdge {
  id: number;
  source: number;
  target: number;
  anchor_text: string;
  relevance_score: number;
  status: string;
}

interface GraphData {
  nodes: GraphNode[];
  edges: GraphEdge[];
}

interface OrphanArticle {
  id: number;
  title: string;
  slug: string;
  status: string;
  focus_keyword?: string;
  created_at: string;
}

export default function InternalLinksPage() {
  const [websites, setWebsites] = useState<WebsiteOption[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [isLoadingWebsites, setIsLoadingWebsites] = useState<boolean>(true);

  // Suggestions state
  const [suggestions, setSuggestions] = useState<LinkSuggestion[]>([]);
  const [isLoadingSuggestions, setIsLoadingSuggestions] = useState<boolean>(false);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Graph state
  const [graphData, setGraphData] = useState<GraphData>({ nodes: [], edges: [] });
  const [isLoadingGraph, setIsLoadingGraph] = useState<boolean>(false);
  const [selectedNode, setSelectedNode] = useState<GraphNode | null>(null);

  // Orphans state
  const [orphans, setOrphans] = useState<OrphanArticle[]>([]);
  const [isLoadingOrphans, setIsLoadingOrphans] = useState<boolean>(false);

  // Actions
  const [isScanning, setIsScanning] = useState<boolean>(false);
  const [applyingId, setApplyingId] = useState<number | null>(null);
  const [isPending, startTransition] = useTransition();

  // Load Websites
  useEffect(() => {
    async function loadWebsites() {
      try {
        setIsLoadingWebsites(true);
        const res = await apiClient.get<{ data: WebsiteOption[] }>('/websites');
        const list = res.data?.data || [];
        setWebsites(list);
        if (list.length > 0) {
          setSelectedWebsiteId(list[0].id);
        }
      } catch (err: unknown) {
        toast.error('Không thể tải danh sách websites');
      } finally {
        setIsLoadingWebsites(false);
      }
    }
    loadWebsites();
  }, []);

  // Load data when selectedWebsiteId changes
  useEffect(() => {
    if (!selectedWebsiteId) return;

    loadSuggestions(selectedWebsiteId);
    loadGraph(selectedWebsiteId);
    loadOrphans(selectedWebsiteId);
  }, [selectedWebsiteId]);

  async function loadSuggestions(websiteId: number) {
    try {
      setIsLoadingSuggestions(true);
      const res = await apiClient.get<{ data: { data: LinkSuggestion[] } }>(
        `/internal-links?website_id=${websiteId}&per_page=50`
      );
      setSuggestions(res.data?.data?.data || []);
    } catch {
      toast.error('Lỗi tải danh sách gợi ý liên kết');
    } finally {
      setIsLoadingSuggestions(false);
    }
  }

  async function loadGraph(websiteId: number) {
    try {
      setIsLoadingGraph(true);
      const res = await apiClient.get<{ data: GraphData }>(
        `/internal-links/graph?website_id=${websiteId}`
      );
      setGraphData(res.data?.data || { nodes: [], edges: [] });
    } catch {
      // Graph might be empty initially
    } finally {
      setIsLoadingGraph(false);
    }
  }

  async function loadOrphans(websiteId: number) {
    try {
      setIsLoadingOrphans(true);
      const res = await apiClient.get<{ data: OrphanArticle[] }>(
        `/internal-links/orphans?website_id=${websiteId}`
      );
      setOrphans(res.data?.data || []);
    } catch {
      toast.error('Lỗi tải danh sách bài viết mồ côi');
    } finally {
      setIsLoadingOrphans(false);
    }
  }

  // Handle Scan
  async function handleScanWebsite() {
    if (!selectedWebsiteId) return;
    try {
      setIsScanning(true);
      const res = await apiClient.post<{ message: string; data: { suggestions_found: number } }>(
        '/internal-links/scan',
        { website_id: selectedWebsiteId }
      );
      toast.success(res.data?.message || 'Quét thành công');
      loadSuggestions(selectedWebsiteId);
      loadGraph(selectedWebsiteId);
      loadOrphans(selectedWebsiteId);
    } catch (err: unknown) {
      toast.error('Không thể quét liên kết nội bộ');
    } finally {
      setIsScanning(false);
    }
  }

  // Handle Apply
  async function handleApply(id: number) {
    try {
      setApplyingId(id);
      const res = await apiClient.post<{ message: string }>(`/internal-links/${id}/apply`);
      toast.success(res.data?.message || 'Đã chèn liên kết vào bài viết');
      setSuggestions((prev) =>
        prev.map((s) => (s.id === id ? { ...s, status: 'applied' } : s))
      );
      if (selectedWebsiteId) {
        loadGraph(selectedWebsiteId);
        loadOrphans(selectedWebsiteId);
      }
    } catch {
      toast.error('Lỗi khi chèn liên kết');
    } finally {
      setApplyingId(null);
    }
  }

  // Handle Accept
  async function handleAccept(id: number) {
    try {
      await apiClient.post(`/internal-links/${id}/accept`);
      toast.success('Đã chấp nhận đề xuất');
      setSuggestions((prev) =>
        prev.map((s) => (s.id === id ? { ...s, status: 'accepted' } : s))
      );
    } catch {
      toast.error('Lỗi khi chấp nhận');
    }
  }

  // Handle Reject
  async function handleReject(id: number) {
    try {
      await apiClient.post(`/internal-links/${id}/reject`);
      toast.success('Đã từ chối đề xuất');
      setSuggestions((prev) =>
        prev.map((s) => (s.id === id ? { ...s, status: 'rejected' } : s))
      );
    } catch {
      toast.error('Lỗi khi từ chối');
    }
  }

  // Filtered suggestions
  const filteredSuggestions = suggestions.filter((s) => {
    if (statusFilter !== 'all' && s.status !== statusFilter) return false;
    if (!searchQuery) return true;
    const query = searchQuery.toLowerCase();
    return (
      s.suggested_anchor_text.toLowerCase().includes(query) ||
      s.source_article?.title.toLowerCase().includes(query) ||
      s.target_article?.title.toLowerCase().includes(query)
    );
  });

  const pendingCount = suggestions.filter((s) => s.status === 'pending').length;
  const appliedCount = suggestions.filter((s) => s.status === 'applied').length;

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight text-slate-900 flex items-center gap-3">
            <Link2 className="h-8 w-8 text-blue-600" />
            Đồ Thị Liên Kết Nội Bộ & Đề Xuất Anchor Text
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            Tự động phát hiện cơ hội liên kết theo ngữ cảnh, loại bỏ bài viết mồ côi và tối ưu hóa cấu trúc website.
          </p>
        </div>

        {/* Website Selector & Scan Action */}
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-sm">
            <Globe className="h-4 w-4 text-slate-400" />
            <select
              value={selectedWebsiteId || ''}
              onChange={(e) => setSelectedWebsiteId(Number(e.target.value))}
              disabled={isLoadingWebsites}
              className="bg-transparent text-sm font-semibold text-slate-800 focus:outline-none"
            >
              {websites.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name} ({w.domain})
                </option>
              ))}
            </select>
          </div>

          <Button
            onClick={handleScanWebsite}
            disabled={isScanning || !selectedWebsiteId}
            className="gap-2 bg-blue-600 font-bold hover:bg-blue-700"
          >
            {isScanning ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Đang quét liên kết...
              </>
            ) : (
              <>
                <Sparkles className="h-4 w-4" />
                Quét cơ hội liên kết
              </>
            )}
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="border-slate-200 shadow-sm">
          <CardContent className="p-5 flex items-center justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Tổng đề xuất</p>
              <h3 className="mt-1 text-2xl font-black text-slate-900">{suggestions.length}</h3>
            </div>
            <div className="rounded-xl bg-blue-50 p-3 text-blue-600">
              <Link2 className="h-6 w-6" />
            </div>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardContent className="p-5 flex items-center justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Chờ duyệt (Pending)</p>
              <h3 className="mt-1 text-2xl font-black text-amber-600">{pendingCount}</h3>
            </div>
            <div className="rounded-xl bg-amber-50 p-3 text-amber-600">
              <AlertTriangle className="h-6 w-6" />
            </div>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardContent className="p-5 flex items-center justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Đã chèn (Applied)</p>
              <h3 className="mt-1 text-2xl font-black text-emerald-600">{appliedCount}</h3>
            </div>
            <div className="rounded-xl bg-emerald-50 p-3 text-emerald-600">
              <CheckCircle2 className="h-6 w-6" />
            </div>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardContent className="p-5 flex items-center justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Bài viết mồ côi</p>
              <h3 className="mt-1 text-2xl font-black text-rose-600">{orphans.length}</h3>
            </div>
            <div className="rounded-xl bg-rose-50 p-3 text-rose-600">
              <Layers className="h-6 w-6" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Main Tabs Container */}
      <Tabs defaultValue="suggestions" className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <TabsList className="bg-slate-100 p-1">
            <TabsTrigger value="suggestions" className="gap-2 font-bold data-[state=active]:bg-white">
              <Link2 className="h-4 w-4" />
              Cơ hội liên kết ({filteredSuggestions.length})
            </TabsTrigger>
            <TabsTrigger value="orphans" className="gap-2 font-bold data-[state=active]:bg-white">
              <Layers className="h-4 w-4" />
              Bài viết mồ côi ({orphans.length})
            </TabsTrigger>
            <TabsTrigger value="graph" className="gap-2 font-bold data-[state=active]:bg-white">
              <Network className="h-4 w-4" />
              Đồ thị liên kết ({graphData.nodes.length} bài)
            </TabsTrigger>
          </TabsList>
        </div>

        {/* TAB 1: SUGGESTIONS */}
        <TabsContent value="suggestions" className="space-y-4">
          <Card className="border-slate-200 shadow-sm">
            <CardHeader className="pb-3">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="text-lg font-bold text-slate-900">Danh sách đề xuất Anchor Text</CardTitle>
                  <CardDescription className="text-xs text-slate-500">
                    Thuật toán tự động tìm câu văn phù hợp trong bài nguồn để chèn link tới bài đích.
                  </CardDescription>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <div className="relative w-64">
                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <Input
                      placeholder="Tìm từ khóa / bài viết..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="pl-9 text-sm"
                    />
                  </div>

                  <div className="flex items-center gap-1">
                    {['all', 'pending', 'accepted', 'applied', 'rejected'].map((st) => (
                      <Button
                        key={st}
                        variant={statusFilter === st ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setStatusFilter(st)}
                        className="text-xs font-bold capitalize"
                      >
                        {st === 'all' ? 'Tất cả' : st}
                      </Button>
                    ))}
                  </div>
                </div>
              </div>
            </CardHeader>

            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm text-slate-600">
                  <thead className="bg-slate-50 text-xs uppercase text-slate-400 font-bold">
                    <tr>
                      <th className="px-6 py-3">Bài viết nguồn</th>
                      <th className="px-6 py-3">Bài viết đích</th>
                      <th className="px-6 py-3">Anchor Text đề xuất</th>
                      <th className="px-6 py-3">Ngữ cảnh chèn câu</th>
                      <th className="px-6 py-3 whitespace-nowrap">Độ liên quan</th>
                      <th className="px-6 py-3 whitespace-nowrap">Trạng thái</th>
                      <th className="px-6 py-3 text-right whitespace-nowrap">Hành động</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {isLoadingSuggestions ? (
                      <tr>
                        <td colSpan={7} className="px-6 py-8 text-center text-slate-400">
                          <Loader2 className="mx-auto h-6 w-6 animate-spin text-blue-500" />
                          <p className="mt-2 text-xs">Đang tải đề xuất...</p>
                        </td>
                      </tr>
                    ) : filteredSuggestions.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="px-6 py-12 text-center text-slate-400">
                          <Link2 className="mx-auto h-8 w-8 text-slate-300" />
                          <p className="mt-2 font-medium">Chưa có đề xuất nào</p>
                          <p className="text-xs">Bấm "Quét cơ hội liên kết" để AI phân tích toàn bộ website.</p>
                        </td>
                      </tr>
                    ) : (
                      filteredSuggestions.map((item) => (
                        <tr key={item.id} className="hover:bg-slate-50/50">
                          <td className="px-6 py-4 max-w-xs">
                            <div className="font-bold text-slate-900 line-clamp-2">
                              {item.source_article?.title || `#${item.source_article_id}`}
                            </div>
                            <span className="text-xs text-slate-400">
                              Slug: {item.source_article?.slug}
                            </span>
                          </td>

                          <td className="px-6 py-4 max-w-xs">
                            <div className="font-bold text-blue-700 line-clamp-2 flex items-center gap-1">
                              {item.target_article?.title || `#${item.target_article_id}`}
                              <ArrowRight className="h-3 w-3 shrink-0 text-slate-400" />
                            </div>
                            <span className="text-xs text-slate-400">
                              Keyword: {item.target_article?.focus_keyword || 'N/A'}
                            </span>
                          </td>

                          <td className="px-6 py-4">
                            <Badge variant="secondary" className="font-bold text-blue-600 bg-blue-50">
                              {item.suggested_anchor_text}
                            </Badge>
                          </td>

                          <td className="px-6 py-4 max-w-md">
                            <p className="text-xs text-slate-600 italic bg-slate-50 p-2 rounded-lg border border-slate-100 line-clamp-3">
                              "{item.context_sentence || 'N/A'}"
                            </p>
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-xs text-slate-800">
                                {Number(item.relevance_score).toFixed(0)}%
                              </span>
                              <div className="h-1.5 w-16 bg-slate-100 rounded-full overflow-hidden">
                                <div
                                  className="h-full bg-emerald-500 rounded-full"
                                  style={{ width: `${Math.min(100, item.relevance_score)}%` }}
                                />
                              </div>
                            </div>
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap">
                            {item.status === 'applied' && (
                              <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 font-bold text-xs gap-1">
                                <Check className="h-3 w-3" /> Đã chèn
                              </Badge>
                            )}
                            {item.status === 'accepted' && (
                              <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-100 font-bold text-xs">
                                Đã chấp nhận
                              </Badge>
                            )}
                            {item.status === 'pending' && (
                              <Badge className="bg-amber-100 text-amber-700 hover:bg-amber-100 font-bold text-xs">
                                Chờ duyệt
                              </Badge>
                            )}
                            {item.status === 'rejected' && (
                              <Badge className="bg-slate-100 text-slate-500 hover:bg-slate-100 font-bold text-xs">
                                Từ chối
                              </Badge>
                            )}
                          </td>

                          <td className="px-6 py-4 text-right whitespace-nowrap">
                            <div className="flex items-center justify-end gap-1.5">
                              {item.status !== 'applied' && (
                                <Button
                                  size="sm"
                                  variant="default"
                                  disabled={applyingId === item.id}
                                  onClick={() => handleApply(item.id)}
                                  className="h-8 gap-1 bg-emerald-600 font-bold text-xs hover:bg-emerald-700"
                                >
                                  {applyingId === item.id ? (
                                    <Loader2 className="h-3 w-3 animate-spin" />
                                  ) : (
                                    <CheckCircle2 className="h-3 w-3" />
                                  )}
                                  Áp dụng
                                </Button>
                              )}

                              {item.status === 'pending' && (
                                <>
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => handleAccept(item.id)}
                                    className="h-8 text-xs font-bold"
                                  >
                                    Chấp nhận
                                  </Button>
                                  <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => handleReject(item.id)}
                                    className="h-8 text-xs text-rose-600 hover:bg-rose-50"
                                  >
                                    Từ chối
                                  </Button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* TAB 2: ORPHAN ARTICLES */}
        <TabsContent value="orphans" className="space-y-4">
          <Card className="border-slate-200 shadow-sm">
            <CardHeader>
              <CardTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                <AlertTriangle className="h-5 w-5 text-amber-500" />
                Cảnh Báo: Các Bài Viết Chưa Được Liên Kết Nội Bộ (Orphan Articles)
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Bài viết mồ côi là những bài không có bất kỳ liên kết nội bộ nào trỏ về từ các bài viết khác trong website.
                Google bot khó thu thập dữ liệu và xếp hạng những trang này.
              </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm text-slate-600">
                  <thead className="bg-slate-50 text-xs uppercase text-slate-400 font-bold">
                    <tr>
                      <th className="px-6 py-3">Tiêu đề bài viết</th>
                      <th className="px-6 py-3">Slug</th>
                      <th className="px-6 py-3">Từ khóa chính</th>
                      <th className="px-6 py-3">Trạng thái bài</th>
                      <th className="px-6 py-3 text-right whitespace-nowrap">Hành động</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {isLoadingOrphans ? (
                      <tr>
                        <td colSpan={5} className="px-6 py-8 text-center text-slate-400">
                          <Loader2 className="mx-auto h-6 w-6 animate-spin text-blue-500" />
                          <p className="mt-2 text-xs">Đang kiểm tra bài viết mồ côi...</p>
                        </td>
                      </tr>
                    ) : orphans.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                          <CheckCircle2 className="mx-auto h-8 w-8 text-emerald-500" />
                          <p className="mt-2 font-bold text-slate-800">Tuyệt vời! Không có bài viết mồ côi nào.</p>
                          <p className="text-xs text-slate-400">Tất cả các bài viết đều có ít nhất 1 internal link trỏ về.</p>
                        </td>
                      </tr>
                    ) : (
                      orphans.map((art) => (
                        <tr key={art.id} className="hover:bg-slate-50/50">
                          <td className="px-6 py-4 font-bold text-slate-900">
                            {art.title}
                          </td>
                          <td className="px-6 py-4 text-xs font-mono text-slate-500">
                            /{art.slug}
                          </td>
                          <td className="px-6 py-4">
                            <Badge variant="outline" className="font-semibold text-slate-700">
                              {art.focus_keyword || 'Chưa đặt'}
                            </Badge>
                          </td>
                          <td className="px-6 py-4">
                            <Badge className="capitalize text-xs font-bold">
                              {art.status}
                            </Badge>
                          </td>
                          <td className="px-6 py-4 text-right whitespace-nowrap">
                            <Link href={`/content-studio/${art.id}`}>
                              <Button size="sm" variant="outline" className="text-xs font-bold gap-1">
                                <ExternalLink className="h-3 w-3" />
                                Mở trong Studio
                              </Button>
                            </Link>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* TAB 3: LINK GRAPH */}
        <TabsContent value="graph" className="space-y-4">
          <Card className="border-slate-200 shadow-sm">
            <CardHeader>
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <Network className="h-5 w-5 text-blue-600" />
                    Bản Đồ Mạng Lưới Liên Kết Nội Bộ (Knowledge Graph)
                  </CardTitle>
                  <CardDescription className="text-xs text-slate-500">
                    Trực quan hóa cấu trúc liên kết bài viết: Vòng tròn biểu diễn bài viết, đường nối là liên kết nội bộ.
                  </CardDescription>
                </div>

                <Badge variant="secondary" className="font-bold text-xs text-slate-600">
                  {graphData.nodes.length} Nodes • {graphData.edges.length} Edges
                </Badge>
              </div>
            </CardHeader>

            <CardContent className="p-6">
              {isLoadingGraph ? (
                <div className="h-96 flex flex-col items-center justify-center text-slate-400">
                  <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                  <p className="mt-2 text-sm font-medium">Đang kết xuất đồ thị liên kết...</p>
                </div>
              ) : graphData.nodes.length === 0 ? (
                <div className="h-96 flex flex-col items-center justify-center text-slate-400 border border-dashed rounded-xl">
                  <Network className="h-10 w-10 text-slate-300" />
                  <p className="mt-2 font-bold text-slate-700">Chưa có bài viết nào trên website này</p>
                  <p className="text-xs text-slate-400">Tạo thêm bài viết để xem bản đồ liên kết.</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                  {/* Visual Node Grid */}
                  <div className="lg:col-span-2 rounded-2xl border border-slate-200 bg-slate-900 p-6 shadow-inner min-h-[460px] relative overflow-hidden flex flex-wrap gap-4 items-center justify-center">
                    <div className="absolute top-3 left-4 text-xs font-mono text-slate-400 uppercase tracking-widest">
                      Visual Cluster Graph
                    </div>

                    {graphData.nodes.map((node) => {
                      const isSelected = selectedNode?.id === node.id;
                      const hasInbound = node.inbound_count > 0;
                      return (
                        <div
                          key={node.id}
                          onClick={() => setSelectedNode(node)}
                          className={`cursor-pointer rounded-2xl p-4 transition-all duration-200 border ${
                            isSelected
                              ? 'bg-blue-600 border-white text-white shadow-lg scale-105 ring-4 ring-blue-400/30'
                              : hasInbound
                              ? 'bg-slate-800 border-slate-700 text-slate-200 hover:border-blue-400'
                              : 'bg-rose-950/40 border-rose-800 text-rose-200 hover:border-rose-400'
                          }`}
                        >
                          <div className="flex items-center gap-2">
                            <FileText className="h-4 w-4 shrink-0 opacity-70" />
                            <h4 className="font-bold text-xs max-w-[180px] truncate">
                              {node.title}
                            </h4>
                          </div>

                          <div className="mt-3 flex items-center justify-between text-[11px] opacity-80">
                            <span className="flex items-center gap-1 font-mono">
                              📥 {node.inbound_count} in
                            </span>
                            <span className="flex items-center gap-1 font-mono">
                              📤 {node.outbound_count} out
                            </span>
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {/* Node Inspector Details */}
                  <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <h3 className="font-bold text-slate-900 text-sm uppercase tracking-wider text-slate-400">
                      Chi tiết bài viết đã chọn
                    </h3>

                    {selectedNode ? (
                      <div className="space-y-4">
                        <div>
                          <h4 className="text-base font-black text-slate-900">{selectedNode.title}</h4>
                          <p className="text-xs text-slate-400 font-mono mt-0.5">/{selectedNode.slug}</p>
                        </div>

                        <div className="grid grid-cols-2 gap-2 text-center">
                          <div className="rounded-xl bg-slate-50 p-3">
                            <p className="text-[11px] font-bold uppercase text-slate-400">Liên kết trỏ vào</p>
                            <p className="text-xl font-black text-blue-600 mt-1">{selectedNode.inbound_count}</p>
                          </div>
                          <div className="rounded-xl bg-slate-50 p-3">
                            <p className="text-[11px] font-bold uppercase text-slate-400">Liên kết trỏ ra</p>
                            <p className="text-xl font-black text-emerald-600 mt-1">{selectedNode.outbound_count}</p>
                          </div>
                        </div>

                        {selectedNode.inbound_count === 0 && (
                          <div className="rounded-xl bg-rose-50 p-3 text-xs text-rose-700 font-medium flex items-center gap-2">
                            <AlertTriangle className="h-4 w-4 shrink-0 text-rose-500" />
                            Bài viết này chưa có liên kết nào trỏ vào!
                          </div>
                        )}

                        <div className="pt-2">
                          <Link href={`/content-studio/${selectedNode.id}`} className="w-full">
                            <Button className="w-full gap-2 font-bold bg-blue-600 hover:bg-blue-700 text-xs">
                              <ExternalLink className="h-3.5 w-3.5" />
                              Mở bài viết trong Studio
                            </Button>
                          </Link>
                        </div>
                      </div>
                    ) : (
                      <div className="p-8 text-center text-slate-400">
                        <FileText className="mx-auto h-8 w-8 text-slate-300" />
                        <p className="mt-2 text-xs">Click vào bất kỳ bài viết nào trong đồ thị để xem chi tiết liên kết.</p>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
