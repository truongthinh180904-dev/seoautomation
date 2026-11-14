"use client";

import React, { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import {
  Layers,
  PlusCircle,
  Sparkles,
  Clock,
  Coins,
  AlertCircle,
  CheckCircle2,
  Globe,
  RefreshCw,
  Sliders,
  FileText,
  Loader2,
  History,
  Info,
  Check,
  Upload,
  FolderTree,
  FileSpreadsheet,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';
import apiClient, { get, post } from '@/lib/api/client';
import { BulkProgressMonitor, BulkBatchProgress } from '@/components/features/bulk/BulkProgressMonitor';

interface WebsiteItem {
  id: number;
  name: string;
  domain: string;
  url?: string;
}

interface ApiResponseEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
}

interface BatchListResponse {
  success: boolean;
  data: BulkBatchProgress[];
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
  };
}

interface TopicClusterItem {
  id: number;
  topic: string;
  pillar_keyword?: { id: number; keyword: string };
  keywords?: Array<{ id: number; keyword: string }>;
}

export default function BulkGeneratorPage() {
  const [websites, setWebsites] = useState<WebsiteItem[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [isLoadingWebsites, setIsLoadingWebsites] = useState(true);

  // Topic Clusters State
  const [topicClusters, setTopicClusters] = useState<TopicClusterItem[]>([]);
  const [isLoadingClusters, setIsLoadingClusters] = useState(false);

  // Form State
  const [batchName, setBatchName] = useState('');
  const [keywordsText, setKeywordsText] = useState('');
  const [tone, setTone] = useState<'professional' | 'conversational' | 'authoritative' | 'persuasive'>('professional');
  const [targetWordCount, setTargetWordCount] = useState<number>(1500);
  const [includeImages, setIncludeImages] = useState<boolean>(true);
  const [autoPublish, setAutoPublish] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Monitor & History State
  const [activeBatch, setActiveBatch] = useState<BulkBatchProgress | null>(null);
  const [pastBatches, setPastBatches] = useState<BulkBatchProgress[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(false);
  const [isCancelling, setIsCancelling] = useState(false);
  const [activeTab, setActiveTab] = useState<'create' | 'monitor' | 'history'>('create');

  // Parse keywords list
  const parsedKeywords = keywordsText
    .split(/[\r\n,]+/)
    .map((k) => k.trim())
    .filter((k) => k.length > 0);

  const keywordCount = parsedKeywords.length;
  const isTierEligible = keywordCount >= 10;
  const unitCost = isTierEligible ? 18 : 20;
  const estimatedCredits = keywordCount * unitCost;

  // Load websites on mount
  const fetchWebsites = useCallback(async () => {
    try {
      setIsLoadingWebsites(true);
      const res = await get<ApiResponseEnvelope<WebsiteItem[]>>('/websites');
      if (res && res.data) {
        setWebsites(res.data);
        if (res.data.length > 0 && !selectedWebsiteId) {
          setSelectedWebsiteId(res.data[0].id);
        }
      }
    } catch {
      // Fallback fallback or silently catch
    } finally {
      setIsLoadingWebsites(false);
    }
  }, [selectedWebsiteId]);

  // Load topic clusters
  const fetchTopicClusters = useCallback(async () => {
    try {
      setIsLoadingClusters(true);
      const res = await get<ApiResponseEnvelope<TopicClusterItem[]>>('/topic-clusters');
      if (res && res.data) {
        setTopicClusters(res.data);
      }
    } catch {
      // Silently catch
    } finally {
      setIsLoadingClusters(false);
    }
  }, []);

  // Load batch history
  const fetchBatchHistory = useCallback(async () => {
    try {
      setIsLoadingHistory(true);
      const res = await get<BatchListResponse>('/bulk-batches');
      if (res && res.data) {
        setPastBatches(res.data);
      }
    } catch {
      // Silently catch
    } finally {
      setIsLoadingHistory(false);
    }
  }, []);

  useEffect(() => {
    fetchWebsites();
    fetchBatchHistory();
    fetchTopicClusters();
  }, [fetchWebsites, fetchBatchHistory, fetchTopicClusters]);

  const handleSelectCluster = (clusterId: number) => {
    const cluster = topicClusters.find((c) => c.id === clusterId);
    if (!cluster) return;

    const list: string[] = [];
    if (cluster.pillar_keyword?.keyword) {
      list.push(cluster.pillar_keyword.keyword);
    }
    if (cluster.keywords && cluster.keywords.length > 0) {
      cluster.keywords.forEach((k) => {
        if (k.keyword && !list.includes(k.keyword)) {
          list.push(k.keyword);
        }
      });
    }

    if (list.length > 0) {
      setKeywordsText((prev) => {
        const existing = prev.trim();
        return existing ? `${existing}\n${list.join('\n')}` : list.join('\n');
      });
      if (!batchName) {
        setBatchName(`Lô bài viết Cụm: ${cluster.topic}`);
      }
      toast.success(`Đã thêm ${list.length} từ khóa từ Cụm chủ đề "${cluster.topic}"`);
    } else {
      toast.info('Cụm chủ đề chưa có từ khóa con.');
    }
  };

  const handleInsertSampleKeywords = () => {
    const sample = [
      'bọc răng sứ thẩm mỹ giá bao nhiêu',
      'trồng răng implant toàn hàm all on 4',
      'chi phí niềng răng trong suốt invisalign',
      'dán sứ veneer không mài răng uy tín',
      'cấy ghép răng implant có đau không',
      'tẩy trắng răng bằng laser công nghệ cao',
      'nhổ răng khôn số 8 không đau giá rẻ',
      'chữa tủy răng công nghệ vi phẫu',
      'viêm nha chu triệu chứng và cách điều trị',
      'bảng giá dịch vụ nha khoa tổng quát 2026',
    ];
    setKeywordsText(sample.join('\n'));
    if (!batchName) {
      setBatchName('Lô bài viết SEO Mẫu - Nha Khoa 10 Bài');
    }
    toast.success('Đã tải 10 từ khóa chuẩn SEO nha khoa mẫu!');
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      const text = event.target?.result as string;
      if (!text) return;

      const lines = text
        .split(/[\r\n]+/)
        .map((l) => l.trim().replace(/^["']|["']$/g, '').split(/[,;\t]/)[0]?.trim())
        .filter((l) => l && l.length > 2 && !l.toLowerCase().startsWith('keyword') && !l.toLowerCase().startsWith('từ khóa'));

      if (lines.length > 0) {
        setKeywordsText((prev) => {
          const existing = prev.trim();
          return existing ? `${existing}\n${lines.join('\n')}` : lines.join('\n');
        });
        toast.success(`Đã nhập thành công ${lines.length} từ khóa từ tệp ${file.name}`);
      } else {
        toast.error('Không tìm thấy từ khóa hợp lệ trong tệp.');
      }
    };
    reader.readAsText(file);
    e.target.value = '';
  };

  // Real-time polling for active batch
  useEffect(() => {
    if (!activeBatch || !['pending', 'processing'].includes(activeBatch.status)) {
      return;
    }

    const interval = setInterval(async () => {
      try {
        const res = await get<ApiResponseEnvelope<BulkBatchProgress>>(
          `/bulk-batches/${activeBatch.uuid}/progress`
        );
        if (res && res.data) {
          setActiveBatch(res.data);
          if (['completed', 'partially_failed', 'failed', 'cancelled'].includes(res.data.status)) {
            toast.success(`Lô bài viết "${res.data.name}" đã hoàn tất!`);
            fetchBatchHistory();
          }
        }
      } catch {
        // Silently skip poll error
      }
    }, 2500);

    return () => clearInterval(interval);
  }, [activeBatch, fetchBatchHistory]);

  // Refresh active batch manually
  const handleRefreshActive = async () => {
    if (!activeBatch) return;
    try {
      const res = await get<ApiResponseEnvelope<BulkBatchProgress>>(
        `/bulk-batches/${activeBatch.uuid}/progress`
      );
      if (res && res.data) {
        setActiveBatch(res.data);
        toast.info('Đã cập nhật tiến độ mới nhất');
      }
    } catch {
      toast.error('Không thể cập nhật tiến độ lô bài viết');
    }
  };

  // Cancel batch
  const handleCancelBatch = async (uuid: string) => {
    try {
      setIsCancelling(true);
      const res = await post<ApiResponseEnvelope<BulkBatchProgress>>(
        `/bulk-batches/${uuid}/cancel`,
        { reason: 'Người dùng hủy thao tác từ Dashboard' }
      );
      if (res && res.data) {
        setActiveBatch(res.data);
        toast.success('Đã hủy lô bài viết và hoàn trả credit thành công.');
        fetchBatchHistory();
      }
    } catch {
      toast.error('Lỗi khi hủy lô bài viết.');
    } finally {
      setIsCancelling(false);
    }
  };

  // Submit new bulk batch
  const handleSubmitBatch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedWebsiteId) {
      toast.error('Vui lòng chọn Website mục tiêu');
      return;
    }

    if (keywordCount === 0) {
      toast.error('Vui lòng nhập ít nhất 1 từ khóa');
      return;
    }

    try {
      setIsSubmitting(true);
      const payload = {
        website_id: selectedWebsiteId,
        name: batchName.trim() || `Lô bài viết SEO - ${new Date().toLocaleDateString('vi-VN')}`,
        keywords: parsedKeywords,
        tone,
        target_word_count: targetWordCount,
        include_images: includeImages,
        auto_publish: autoPublish,
      };

      const res = await post<ApiResponseEnvelope<BulkBatchProgress>>('/bulk-batches', payload);

      if (res && res.data) {
        toast.success('Khởi tạo lô bài viết thành công! Đang tiến hành sản xuất ngầm.');
        setActiveBatch(res.data);
        setActiveTab('monitor');
        setKeywordsText('');
        setBatchName('');
        fetchBatchHistory();
      }
    } catch (err: unknown) {
      const errorObj = err as { message?: string; error_code?: string; required_credits?: number };
      if (errorObj?.error_code === 'INSUFFICIENT_CREDITS') {
        toast.error(`Số dư credit không đủ! Cần tối thiểu ${errorObj.required_credits || estimatedCredits} credits.`);
      } else {
        toast.error(errorObj?.message || 'Có lỗi xảy ra khi tạo lô bài viết.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600">
              <Layers className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                Sản Xuất Bài Viết Hàng Loạt (Bulk Content Generator)
              </h1>
              <p className="text-sm text-slate-500 mt-0.5">
                Tạo 10–100 bài viết chuẩn SEO cùng lúc theo lô với cơ chế giữ và tự động hoàn credit an toàn.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {activeBatch && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setActiveTab('monitor')}
              className="flex items-center gap-1.5"
            >
              <Loader2 className={`w-3.5 h-3.5 ${activeBatch.status === 'processing' ? 'animate-spin text-blue-600' : ''}`} />
              Lô đang chạy ({activeBatch.progress_percent}%)
            </Button>
          )}
        </div>
      </div>

      {/* Main Tabs Navigation */}
      <Tabs value={activeTab} onValueChange={(val) => setActiveTab(val as 'create' | 'monitor' | 'history')}>
        <TabsList className="bg-slate-100 dark:bg-slate-900 p-1 rounded-xl">
          <TabsTrigger value="create" className="flex items-center gap-2 text-xs sm:text-sm">
            <PlusCircle className="w-4 h-4" />
            Tạo Lô Mới
          </TabsTrigger>
          <TabsTrigger value="monitor" className="flex items-center gap-2 text-xs sm:text-sm">
            <Clock className="w-4 h-4" />
            Tiến Độ Realtime
            {activeBatch && activeBatch.status === 'processing' && (
              <span className="w-2 h-2 rounded-full bg-blue-600 animate-pulse ml-1" />
            )}
          </TabsTrigger>
          <TabsTrigger value="history" className="flex items-center gap-2 text-xs sm:text-sm">
            <History className="w-4 h-4" />
            Lịch Sử Lô ({pastBatches.length})
          </TabsTrigger>
        </TabsList>

        {/* TAB 1: FORM TẠO LÔ MỚI */}
        <TabsContent value="create" className="mt-6 space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Left 2 Cols: Form Inputs */}
            <div className="lg:col-span-2 space-y-6">
              <Card className="border border-slate-200 dark:border-slate-800">
                <CardHeader>
                  <CardTitle className="text-lg font-bold flex items-center gap-2">
                    <Sparkles className="w-5 h-5 text-indigo-500" />
                    Cấu Hình Lô Bài Viết
                  </CardTitle>
                  <CardDescription>
                    Nhập danh sách từ khóa và thiết lập quy chuẩn phong cách bài viết.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <form onSubmit={handleSubmitBatch} className="space-y-5">
                    {/* Website Selector */}
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <Globe className="w-3.5 h-3.5 text-blue-500" />
                        Website xuất bản
                      </label>
                      {isLoadingWebsites ? (
                        <Skeleton className="h-10 w-full rounded-md" />
                      ) : (
                        <select
                          value={selectedWebsiteId || ''}
                          onChange={(e) => setSelectedWebsiteId(Number(e.target.value))}
                          className="w-full h-10 px-3 py-2 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          {websites.map((w) => (
                            <option key={w.id} value={w.id}>
                              {w.name} ({w.domain})
                            </option>
                          ))}
                        </select>
                      )}
                    </div>

                    {/* Batch Name */}
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Tên đợt sản xuất (Batch Name)
                      </label>
                      <Input
                        placeholder="VD: Chiến dịch SEO Dịch vụ Tháng 11..."
                        value={batchName}
                        onChange={(e) => setBatchName(e.target.value)}
                        className="text-sm"
                      />
                    </div>

                    {/* Keywords Textarea */}
                    <div className="space-y-2">
                      <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                          <FileText className="w-3.5 h-3.5 text-indigo-500" />
                          Danh sách từ khóa SEO (Mỗi từ khóa 1 dòng)
                        </label>
                        <span className="text-xs font-medium text-slate-500">
                          Đã nhập: <strong className="text-blue-600">{keywordCount}</strong> từ khóa
                        </span>
                      </div>

                      {/* Quick Import Tools: Topic Clusters, Excel/CSV, Sample Preset */}
                      <div className="flex flex-wrap items-center gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                        {topicClusters.length > 0 && (
                          <div className="flex items-center gap-1.5">
                            <FolderTree className="w-3.5 h-3.5 text-purple-600" />
                            <select
                              onChange={(e) => {
                                if (e.target.value) {
                                  handleSelectCluster(Number(e.target.value));
                                  e.target.value = '';
                                }
                              }}
                              defaultValue=""
                              className="text-xs h-7 px-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-300 font-medium"
                            >
                              <option value="">+ Chọn từ Cụm chủ đề ({topicClusters.length})</option>
                              {topicClusters.map((c) => (
                                <option key={c.id} value={c.id}>
                                  {c.topic} ({(c.keywords?.length ?? 0) + (c.pillar_keyword ? 1 : 0)} từ khóa)
                                </option>
                              ))}
                            </select>
                          </div>
                        )}

                        <label className="inline-flex items-center gap-1.5 text-xs h-7 px-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-300 font-medium cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
                          <Upload className="w-3 h-3 text-sky-600" />
                          <span>Nhập file Excel/CSV/TXT</span>
                          <input
                            type="file"
                            accept=".csv,.txt"
                            onChange={handleFileUpload}
                            className="hidden"
                          />
                        </label>

                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={handleInsertSampleKeywords}
                          className="h-7 text-xs px-2 text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                        >
                          <FileSpreadsheet className="w-3 h-3 mr-1" />
                          Mẫu 10 từ khóa Nha khoa
                        </Button>
                      </div>

                      <Textarea
                        rows={8}
                        placeholder={"dịch vụ seo tổng thể 2026\ncách tối ưu h1 h4 chuẩn seo\nchiến lược entity seo cho doanh nghiệp\n..."}
                        value={keywordsText}
                        onChange={(e) => setKeywordsText(e.target.value)}
                        className="font-mono text-sm leading-relaxed resize-y"
                      />
                    </div>

                    {/* Options Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                      <div className="space-y-1.5">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                          <Sliders className="w-3.5 h-3.5 text-slate-500" />
                          Giọng văn (Tone of Voice)
                        </label>
                        <select
                          value={tone}
                          onChange={(e) => setTone(e.target.value as 'professional' | 'conversational' | 'authoritative' | 'persuasive')}
                          className="w-full h-10 px-3 py-2 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <option value="professional">Chuyên nghiệp (Professional)</option>
                          <option value="authoritative">Chuyên gia đầu ngành (Authoritative)</option>
                          <option value="conversational">Thân thiện, gần gũi (Conversational)</option>
                          <option value="persuasive">Thuyết phục chuyển đổi (Persuasive)</option>
                        </select>
                      </div>

                      <div className="space-y-1.5">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                          Độ dài bài viết mục tiêu
                        </label>
                        <select
                          value={targetWordCount}
                          onChange={(e) => setTargetWordCount(Number(e.target.value))}
                          className="w-full h-10 px-3 py-2 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <option value={1000}>Tiêu chuẩn (1,000 từ)</option>
                          <option value={1500}>Chuyên sâu (1,500 từ)</option>
                          <option value={2000}>Pillar Page dài (2,000 từ)</option>
                        </select>
                      </div>
                    </div>

                    {/* Checkboxes */}
                    <div className="pt-2 flex flex-col sm:flex-row gap-4">
                      <label className="flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={includeImages}
                          onChange={(e) => setIncludeImages(e.target.checked)}
                          className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        Tự động sinh ảnh minh họa AI theo ngữ cảnh H2
                      </label>

                      <label className="flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={autoPublish}
                          onChange={(e) => setAutoPublish(e.target.checked)}
                          className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        Tự động xuất bản lên WordPress khi hoàn tất
                      </label>
                    </div>

                    {/* Double-Submit Protected Submit Button */}
                    <div className="pt-4 border-t border-slate-100 dark:border-slate-800">
                      <Button
                        type="submit"
                        disabled={isSubmitting || keywordCount === 0 || !selectedWebsiteId}
                        className="w-full sm:w-auto min-w-[200px] bg-blue-600 hover:bg-blue-700 text-white font-medium flex items-center justify-center gap-2"
                      >
                        {isSubmitting ? (
                          <Loader2 className="w-4 h-4 animate-spin" />
                        ) : (
                          <Sparkles className="w-4 h-4" />
                        )}
                        {isSubmitting ? 'Đang khởi tạo lô...' : `Khởi tạo & Giữ ${estimatedCredits} Credits`}
                      </Button>
                    </div>
                  </form>
                </CardContent>
              </Card>
            </div>

            {/* Right 1 Col: Real-time Credit Estimator & Policies */}
            <div className="space-y-6">
              <Card className="border border-slate-200 dark:border-slate-800 bg-gradient-to-br from-slate-50 to-blue-50/30 dark:from-slate-900 dark:to-blue-950/20">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-bold flex items-center gap-2">
                    <Coins className="w-4 h-4 text-amber-500" />
                    Dự Toán Phí Credit
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex justify-between items-center text-sm py-1 border-b border-slate-200/60 dark:border-slate-800">
                    <span className="text-slate-600 dark:text-slate-400">Số lượng từ khóa:</span>
                    <span className="font-bold text-slate-900 dark:text-slate-100">{keywordCount} bài</span>
                  </div>

                  <div className="flex justify-between items-center text-sm py-1 border-b border-slate-200/60 dark:border-slate-800">
                    <span className="text-slate-600 dark:text-slate-400">Đơn giá áp dụng:</span>
                    <div className="text-right">
                      <span className="font-bold text-blue-600 dark:text-blue-400">{unitCost} credit / bài</span>
                      {isTierEligible ? (
                        <p className="text-[10px] text-emerald-600 font-medium">✓ Đã áp dụng ưu đãi sỉ (≥ 10 bài)</p>
                      ) : (
                        <p className="text-[10px] text-slate-400">Nhập ≥ 10 bài để giảm còn 18 credit</p>
                      )}
                    </div>
                  </div>

                  <div className="flex justify-between items-center text-base pt-2">
                    <span className="font-semibold text-slate-800 dark:text-slate-200">Tổng credit giữ trước:</span>
                    <span className="font-extrabold text-amber-600 text-lg">{estimatedCredits} Credits</span>
                  </div>

                  {/* Safety Policy Notice */}
                  <div className="p-3 rounded-lg bg-blue-100/60 dark:bg-blue-950/40 text-[11px] text-slate-700 dark:text-slate-300 space-y-1">
                    <div className="flex items-center gap-1.5 font-bold text-blue-700 dark:text-blue-400">
                      <Check className="w-3.5 h-3.5" />
                      Cam Kết Bảo Vệ Số Dư (Zero Loss):
                    </div>
                    <p>• Credit chỉ được trừ thật khi từng bài viết hoàn thành thành công.</p>
                    <p>• Nếu bất kỳ bài viết nào gặp lỗi, hệ thống tự động hoàn lại 100% credit ngay lập tức.</p>
                  </div>
                </CardContent>
              </Card>

              {/* Tips Card */}
              <Card className="border border-slate-200 dark:border-slate-800">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                    <Info className="w-3.5 h-3.5 text-indigo-500" />
                    Lời khuyên tối ưu
                  </CardTitle>
                </CardHeader>
                <CardContent className="text-xs text-slate-600 dark:text-slate-400 space-y-2">
                  <p>• Gom các từ khóa có cùng Search Intent vào các Topic Cluster trước khi tạo hàng loạt.</p>
                  <p>• Cơ chế Throttling sẽ tự động dãn cách các bài viết 2 giây để tránh chạm ngưỡng Rate Limit.</p>
                  <p>• Bạn có thể đóng trình duyệt, hệ thống Queue Worker sẽ tiếp tục xử lý ngầm và gửi thông báo.</p>
                </CardContent>
              </Card>
            </div>
          </div>
        </TabsContent>

        {/* TAB 2: THEO DÕI TIẾN ĐỘ REALTIME */}
        <TabsContent value="monitor" className="mt-6 space-y-6">
          {activeBatch ? (
            <BulkProgressMonitor
              batch={activeBatch}
              onRefresh={handleRefreshActive}
              onCancel={handleCancelBatch}
              isCancelling={isCancelling}
            />
          ) : (
            <Card className="border border-dashed border-slate-200 dark:border-slate-800 py-12 text-center">
              <CardContent className="space-y-3">
                <Clock className="w-10 h-10 text-slate-300 mx-auto" />
                <h3 className="text-base font-semibold text-slate-700 dark:text-slate-300">
                  Chưa có lô bài viết nào được chọn
                </h3>
                <p className="text-xs text-slate-500 max-w-sm mx-auto">
                  Hãy khởi tạo lô mới từ tab &quot;Tạo Lô Mới&quot; hoặc chọn một lô đã có trong tab &quot;Lịch Sử Lô&quot;.
                </p>
                <Button size="sm" onClick={() => setActiveTab('create')} className="mt-2 text-xs">
                  Tạo lô bài viết ngay
                </Button>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* TAB 3: LỊCH SỬ CÁC LÔ ĐÃ TẠO */}
        <TabsContent value="history" className="mt-6 space-y-6">
          <Card className="border border-slate-200 dark:border-slate-800">
            <CardHeader className="pb-3">
              <div className="flex justify-between items-center">
                <div>
                  <CardTitle className="text-base font-bold">Lịch Sử Các Đợt Sản Xuất Hàng Loạt</CardTitle>
                  <CardDescription className="text-xs">Theo dõi và truy cập lại các lô bài viết đã thực hiện.</CardDescription>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={fetchBatchHistory}
                  disabled={isLoadingHistory}
                  className="flex items-center gap-1.5 text-xs"
                >
                  <RefreshCw className={`w-3.5 h-3.5 ${isLoadingHistory ? 'animate-spin' : ''}`} />
                  Làm mới
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {/* Responsive Table (Reviewer criteria 12) */}
              <div className="w-full overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900">
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Tên lô bài viết</th>
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Số lượng</th>
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Trạng thái</th>
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Tiến độ</th>
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Credit quyết toán</th>
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Thời gian</th>
                      <th className="p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap text-right">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-950">
                    {pastBatches.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="p-8 text-center text-slate-500 text-xs">
                          Chưa có đợt sản xuất bài viết nào được ghi nhận.
                        </td>
                      </tr>
                    ) : (
                      pastBatches.map((batch) => (
                        <tr key={batch.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-900/40 transition-colors">
                          <td className="p-3 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                            {batch.name}
                          </td>
                          <td className="p-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                            {batch.total_items} bài
                          </td>
                          <td className="p-3 whitespace-nowrap">
                            {batch.status === 'completed' && (
                              <Badge className="bg-emerald-500 text-white text-[11px] py-0">Hoàn thành</Badge>
                            )}
                            {batch.status === 'processing' && (
                              <Badge className="bg-blue-600 text-white text-[11px] py-0 animate-pulse">Đang xử lý</Badge>
                            )}
                            {batch.status === 'partially_failed' && (
                              <Badge className="bg-amber-500 text-white text-[11px] py-0">Một phần lỗi</Badge>
                            )}
                            {batch.status === 'cancelled' && (
                              <Badge variant="outline" className="text-slate-500 text-[11px] py-0">Đã hủy</Badge>
                            )}
                            {batch.status === 'pending' && (
                              <Badge variant="secondary" className="text-[11px] py-0">Chờ xử lý</Badge>
                            )}
                          </td>
                          <td className="p-3 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <span className="text-xs font-semibold">{batch.progress_percent || 0}%</span>
                              <div className="w-16 h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div
                                  className="h-full bg-blue-600 rounded-full"
                                  style={{ width: `${batch.progress_percent || 0}%` }}
                                />
                              </div>
                            </div>
                          </td>
                          <td className="p-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                            {batch.credits_settled} credit
                          </td>
                          <td className="p-3 text-xs text-slate-500 whitespace-nowrap">
                            {batch.created_at ? new Date(batch.created_at).toLocaleDateString('vi-VN') : '—'}
                          </td>
                          <td className="p-3 text-right whitespace-nowrap">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => {
                                setActiveBatch(batch);
                                setActiveTab('monitor');
                              }}
                              className="text-xs text-blue-600 hover:text-blue-700 h-8 px-2"
                            >
                              Chi tiết tiến độ
                            </Button>
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
      </Tabs>
    </div>
  );
}
