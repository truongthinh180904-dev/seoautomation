"use client";

import { Suspense, useMemo, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import {
  Search,
  Sparkles,
  Layers,
  FolderPlus,
  ArrowUpDown,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Coins,
  Filter,
  Check,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { toast } from 'sonner';
import {
  useKeywordResearch,
  useKeywordCluster,
  useSaveKeywordsToCampaign,
  type ResearchedKeyword,
  type KeywordCluster,
  type SearchIntent,
} from '@/hooks/useKeywordResearch';
import { useCampaigns } from '@/hooks/useCampaigns';

function KeywordResearchContent() {
  const searchParams = useSearchParams();
  const initialQuery = searchParams.get('q') || '';
  const initialCampaignId = Number(searchParams.get('campaign_id')) || undefined;

  // Search parameters state
  const [query, setQuery] = useState(initialQuery);
  const [language, setLanguage] = useState<'vi' | 'en'>('vi');
  const [limit, setLimit] = useState<number>(50);

  // Results state
  const [keywords, setKeywords] = useState<ResearchedKeyword[]>([]);
  const [clusters, setClusters] = useState<KeywordCluster[]>([]);
  const [activeTab, setActiveTab] = useState<'table' | 'clusters'>('table');
  const [selectedKeywords, setSelectedKeywords] = useState<Set<string>>(new Set());

  // Filter & Sort state
  const [intentFilter, setIntentFilter] = useState<string>('all');
  const [difficultyFilter, setDifficultyFilter] = useState<string>('all');
  const [filterSearch, setFilterSearch] = useState<string>('');
  const [sortBy, setSortBy] = useState<'volume_desc' | 'volume_asc' | 'diff_asc' | 'diff_desc'>('volume_desc');

  // Save Dialog state
  const [isSaveOpen, setIsSaveOpen] = useState(false);
  const [targetCampaignId, setTargetCampaignId] = useState<number | undefined>(initialCampaignId);

  // React Query Hooks
  const researchMutation = useKeywordResearch();
  const clusterMutation = useKeywordCluster();
  const saveMutation = useSaveKeywordsToCampaign();
  const { data: campaignsData } = useCampaigns({ page: 1 });

  // Handle Search Execution
  const handleResearch = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = query.trim();
    if (!trimmed) {
      toast.error('Vui lòng nhập từ khóa hạt giống (Seed Keyword)');
      return;
    }

    try {
      const result = await researchMutation.mutateAsync({
        query: trimmed,
        language,
        country: language === 'vi' ? 'vn' : 'us',
        limit,
        campaign_id: targetCampaignId,
      });

      setKeywords(result.keywords);
      setSelectedKeywords(new Set(result.keywords.map((k) => k.keyword)));
      setClusters([]);
      setActiveTab('table');
      toast.success(`Đã tìm thấy ${result.total_keywords} từ khóa liên quan (-${result.credits_deducted} credits)`);
    } catch (err: unknown) {
      const errMsg = err instanceof Error ? err.message : 'Có lỗi xảy ra khi nghiên cứu từ khóa';
      toast.error(errMsg);
    }
  };

  // Handle Clustering Execution
  const handleCluster = async () => {
    if (keywords.length < 2) {
      toast.error('Cần ít nhất 2 từ khóa để thực hiện gom cụm semantic');
      return;
    }

    try {
      const result = await clusterMutation.mutateAsync({
        keywords,
        language,
      });

      setClusters(result.clusters);
      setActiveTab('clusters');
      toast.success(`Đã gom thành công ${result.total_clusters} cụm chủ đề semantic`);
    } catch (err: unknown) {
      const errMsg = err instanceof Error ? err.message : 'Có lỗi xảy ra khi gom cụm từ khóa';
      toast.error(errMsg);
    }
  };

  // Selection helpers
  const handleToggleSelectAll = (filteredList: ResearchedKeyword[]) => {
    if (selectedKeywords.size === filteredList.length && filteredList.length > 0) {
      setSelectedKeywords(new Set());
    } else {
      setSelectedKeywords(new Set(filteredList.map((k) => k.keyword)));
    }
  };

  const handleToggleSelectOne = (keywordName: string) => {
    const next = new Set(selectedKeywords);
    if (next.has(keywordName)) {
      next.delete(keywordName);
    } else {
      next.add(keywordName);
    }
    setSelectedKeywords(next);
  };

  // Handle Save to Campaign
  const handleSaveToCampaign = async () => {
    const selectedList = keywords.filter((k) => selectedKeywords.has(k.keyword));
    if (selectedList.length === 0) {
      toast.error('Vui lòng chọn ít nhất 1 từ khóa để lưu vào chiến dịch');
      return;
    }

    try {
      const result = await saveMutation.mutateAsync({
        keywords: selectedList,
        campaign_id: targetCampaignId,
      });

      setIsSaveOpen(false);
      toast.success(`Đã lưu thành công ${result.saved_count} từ khóa vào chiến dịch`);
    } catch (err: unknown) {
      const errMsg = err instanceof Error ? err.message : 'Không thể lưu từ khóa vào chiến dịch';
      toast.error(errMsg);
    }
  };

  // Filtered and Sorted Keywords
  const filteredKeywords = useMemo(() => {
    return keywords
      .filter((kw) => {
        // Intent filter
        if (intentFilter !== 'all' && kw.search_intent !== intentFilter) {
          return false;
        }

        // Difficulty filter
        if (difficultyFilter === 'easy' && kw.difficulty > 30) return false;
        if (difficultyFilter === 'medium' && (kw.difficulty <= 30 || kw.difficulty > 60)) return false;
        if (difficultyFilter === 'hard' && kw.difficulty <= 60) return false;

        // Search text filter
        if (filterSearch.trim() && !kw.keyword.toLowerCase().includes(filterSearch.toLowerCase())) {
          return false;
        }

        return true;
      })
      .sort((a, b) => {
        if (sortBy === 'volume_desc') return b.search_volume - a.search_volume;
        if (sortBy === 'volume_asc') return a.search_volume - b.search_volume;
        if (sortBy === 'diff_asc') return a.difficulty - b.difficulty;
        if (sortBy === 'diff_desc') return b.difficulty - a.difficulty;
        return 0;
      });
  }, [keywords, intentFilter, difficultyFilter, filterSearch, sortBy]);

  // Intent badge styling
  const getIntentBadge = (intent: SearchIntent) => {
    switch (intent) {
      case 'informational':
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
            Thông tin
          </span>
        );
      case 'commercial':
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
            Đánh giá / So sánh
          </span>
        );
      case 'transactional':
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
            Mua sắm / Báo giá
          </span>
        );
      case 'navigational':
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
            Điều hướng
          </span>
        );
      default:
        return <Badge variant="outline">{intent}</Badge>;
    }
  };

  // Difficulty badge styling
  const getDifficultyBadge = (diff: number) => {
    let colorClass = 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-800';
    let label = 'Dễ';
    if (diff > 60) {
      colorClass = 'text-rose-600 bg-rose-50 border-rose-200 dark:bg-rose-950/30 dark:border-rose-800';
      label = 'Khó';
    } else if (diff > 30) {
      colorClass = 'text-amber-600 bg-amber-50 border-amber-200 dark:bg-amber-950/30 dark:border-amber-800';
      label = 'Vừa';
    }

    return (
      <div className="flex items-center gap-2">
        <div className="w-12 bg-muted rounded-full h-1.5 overflow-hidden">
          <div
            className={`h-full ${diff > 60 ? 'bg-rose-500' : diff > 30 ? 'bg-amber-500' : 'bg-emerald-500'}`}
            style={{ width: `${diff}%` }}
          />
        </div>
        <span className={`px-1.5 py-0.2 rounded border text-[11px] font-medium ${colorClass}`}>
          {diff} ({label})
        </span>
      </div>
    );
  };

  return (
    <div className="space-y-6">
      {/* Top Header & Search Bar */}
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
              <Sparkles className="w-6 h-6 text-primary" />
              Nghiên cứu & Gom cụm Từ khóa (Keyword Intelligence)
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Khám phá từ khóa hạt giống, phân loại ý định tìm kiếm (Search Intent) và nhóm cụm chủ đề bằng AI.
            </p>
          </div>
          <div className="flex items-center gap-2 bg-muted/60 px-3 py-1.5 rounded-lg border border-border text-xs text-muted-foreground">
            <Coins className="w-4 h-4 text-amber-500" />
            <span>Phí nghiên cứu: <strong>2 credits</strong> / 50 từ khóa</span>
          </div>
        </div>

        {/* Search Input Box */}
        <Card className="mt-2">
          <CardContent className="pt-6">
            <form onSubmit={handleResearch} className="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Nhập từ khóa hạt giống (VD: tai nghe bluetooth, phần mềm crm, thực phẩm chức năng...)"
                  className="pl-9 text-sm"
                  disabled={researchMutation.isPending}
                />
              </div>

              <div className="flex items-center gap-2">
                <select
                  value={language}
                  onChange={(e) => setLanguage(e.target.value as 'vi' | 'en')}
                  className="h-8 rounded-lg border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  disabled={researchMutation.isPending}
                >
                  <option value="vi">Tiếng Việt (VN)</option>
                  <option value="en">English (US)</option>
                </select>

                <select
                  value={limit}
                  onChange={(e) => setLimit(Number(e.target.value))}
                  className="h-8 rounded-lg border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  disabled={researchMutation.isPending}
                >
                  <option value={25}>25 từ khóa</option>
                  <option value={50}>50 từ khóa</option>
                  <option value={100}>100 từ khóa</option>
                </select>

                <Button
                  type="submit"
                  disabled={researchMutation.isPending || !query.trim()}
                  className="whitespace-nowrap"
                >
                  {researchMutation.isPending ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-1.5 animate-spin" />
                      Đang phân tích...
                    </>
                  ) : (
                    <>
                      <Search className="w-4 h-4 mr-1.5" />
                      Khám phá từ khóa
                    </>
                  )}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      {/* Main Results Container */}
      {keywords.length > 0 && (
        <div className="space-y-4">
          {/* Action & Filter Toolbar */}
          <div className="flex flex-col lg:flex-row gap-3 justify-between items-start lg:items-center bg-card p-3 rounded-xl border border-border">
            {/* View Switcher Tabs */}
            <div className="flex items-center gap-1 bg-muted p-1 rounded-lg">
              <button
                type="button"
                onClick={() => setActiveTab('table')}
                className={`px-3 py-1 text-xs font-medium rounded-md transition-all ${
                  activeTab === 'table'
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                Danh sách từ khóa ({filteredKeywords.length})
              </button>
              <button
                type="button"
                onClick={() => {
                  if (clusters.length === 0) {
                    handleCluster();
                  } else {
                    setActiveTab('clusters');
                  }
                }}
                className={`px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1 ${
                  activeTab === 'clusters'
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Layers className="w-3.5 h-3.5" />
                Cụm chủ đề {clusters.length > 0 ? `(${clusters.length})` : ''}
              </button>
            </div>

            {/* Quick Actions */}
            <div className="flex items-center gap-2 w-full lg:w-auto justify-end">
              <Button
                variant="outline"
                size="sm"
                onClick={handleCluster}
                disabled={clusterMutation.isPending || keywords.length < 2}
                className="text-xs"
              >
                {clusterMutation.isPending ? (
                  <>
                    <Loader2 className="w-3.5 h-3.5 mr-1 animate-spin" />
                    Đang gom cụm...
                  </>
                ) : (
                  <>
                    <Sparkles className="w-3.5 h-3.5 mr-1 text-amber-500" />
                    Gom cụm Semantic bằng AI
                  </>
                )}
              </Button>

              <Button
                size="sm"
                onClick={() => setIsSaveOpen(true)}
                disabled={selectedKeywords.size === 0 || saveMutation.isPending}
                className="text-xs"
              >
                <FolderPlus className="w-3.5 h-3.5 mr-1" />
                Lưu vào Chiến dịch ({selectedKeywords.size})
              </Button>
            </div>
          </div>

          {/* Filtering Bar for Table View */}
          {activeTab === 'table' && (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 bg-muted/30 p-3 rounded-lg border border-border">
              {/* Search in results */}
              <div className="relative">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
                <Input
                  value={filterSearch}
                  onChange={(e) => setFilterSearch(e.target.value)}
                  placeholder="Lọc từ khóa trong kết quả..."
                  className="pl-8 h-8 text-xs bg-background"
                />
              </div>

              {/* Filter by Intent */}
              <div className="flex items-center gap-1.5">
                <Filter className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                <select
                  value={intentFilter}
                  onChange={(e) => setIntentFilter(e.target.value)}
                  className="w-full h-8 rounded-md border border-input bg-background px-2 text-xs focus-visible:outline-none"
                >
                  <option value="all">Tất cả ý định (Search Intent)</option>
                  <option value="informational">Thông tin (Informational)</option>
                  <option value="commercial">Đánh giá / So sánh (Commercial)</option>
                  <option value="transactional">Mua sắm / Báo giá (Transactional)</option>
                  <option value="navigational">Điều hướng (Navigational)</option>
                </select>
              </div>

              {/* Filter by Difficulty */}
              <div className="flex items-center gap-1.5">
                <ArrowUpDown className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                <select
                  value={difficultyFilter}
                  onChange={(e) => setDifficultyFilter(e.target.value)}
                  className="w-full h-8 rounded-md border border-input bg-background px-2 text-xs focus-visible:outline-none"
                >
                  <option value="all">Độ khó từ khóa: Tất cả</option>
                  <option value="easy">Dễ (KD 0 - 30)</option>
                  <option value="medium">Trung bình (KD 31 - 60)</option>
                  <option value="hard">Khó (KD 61 - 100)</option>
                </select>
              </div>

              {/* Sort By */}
              <div>
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value as typeof sortBy)}
                  className="w-full h-8 rounded-md border border-input bg-background px-2 text-xs focus-visible:outline-none"
                >
                  <option value="volume_desc">Search Volume: Cao đến thấp</option>
                  <option value="volume_asc">Search Volume: Thấp đến cao</option>
                  <option value="diff_asc">Độ khó KD: Dễ đến khó</option>
                  <option value="diff_desc">Độ khó KD: Khó đến dễ</option>
                </select>
              </div>
            </div>
          )}

          {/* TAB 1: Table View */}
          {activeTab === 'table' && (
            <Card>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border bg-muted/40 text-muted-foreground text-xs font-semibold">
                        <th className="py-3 px-4 w-10">
                          <input
                            type="checkbox"
                            checked={
                              filteredKeywords.length > 0 &&
                              filteredKeywords.every((k) => selectedKeywords.has(k.keyword))
                            }
                            onChange={() => handleToggleSelectAll(filteredKeywords)}
                            className="rounded border-border text-primary focus:ring-primary h-4 w-4"
                          />
                        </th>
                        <th className="py-3 px-4">Từ khóa liên quan</th>
                        <th className="py-3 px-4 whitespace-nowrap">Ý định tìm kiếm</th>
                        <th className="py-3 px-4 whitespace-nowrap">Search Volume</th>
                        <th className="py-3 px-4 whitespace-nowrap">Độ khó (KD)</th>
                        <th className="py-3 px-4 whitespace-nowrap">Giá CPC ước tính</th>
                        <th className="py-3 px-4">Từ khóa LSI ngữ nghĩa</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {filteredKeywords.length === 0 ? (
                        <tr>
                          <td colSpan={7} className="text-center py-8 text-muted-foreground text-sm">
                            Không tìm thấy từ khóa nào phù hợp với bộ lọc.
                          </td>
                        </tr>
                      ) : (
                        filteredKeywords.map((item) => {
                          const isSelected = selectedKeywords.has(item.keyword);
                          return (
                            <tr
                              key={item.keyword}
                              className={`hover:bg-muted/30 transition-colors ${
                                isSelected ? 'bg-primary/5 dark:bg-primary/10' : ''
                              }`}
                            >
                              <td className="py-3 px-4">
                                <input
                                  type="checkbox"
                                  checked={isSelected}
                                  onChange={() => handleToggleSelectOne(item.keyword)}
                                  className="rounded border-border text-primary focus:ring-primary h-4 w-4"
                                />
                              </td>
                              <td className="py-3 px-4 font-medium text-foreground">
                                {item.keyword}
                              </td>
                              <td className="py-3 px-4 whitespace-nowrap">
                                {getIntentBadge(item.search_intent)}
                              </td>
                              <td className="py-3 px-4 whitespace-nowrap font-mono text-xs">
                                {item.search_volume.toLocaleString()} /tháng
                              </td>
                              <td className="py-3 px-4 whitespace-nowrap">
                                {getDifficultyBadge(item.difficulty)}
                              </td>
                              <td className="py-3 px-4 whitespace-nowrap font-mono text-xs text-muted-foreground">
                                ${item.cpc.toFixed(2)}
                              </td>
                              <td className="py-3 px-4">
                                <div className="flex flex-wrap gap-1 max-w-xs">
                                  {item.lsi_keywords && item.lsi_keywords.length > 0 ? (
                                    item.lsi_keywords.map((lsi) => (
                                      <span
                                        key={lsi}
                                        className="text-[11px] bg-muted px-1.5 py-0.5 rounded text-muted-foreground"
                                      >
                                        {lsi}
                                      </span>
                                    ))
                                  ) : (
                                    <span className="text-xs text-muted-foreground/50">—</span>
                                  )}
                                </div>
                              </td>
                            </tr>
                          );
                        })
                      )}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          )}

          {/* TAB 2: Cluster View */}
          {activeTab === 'clusters' && (
            <div className="space-y-4">
              {clusters.length === 0 ? (
                <Card>
                  <CardContent className="py-12 text-center text-muted-foreground">
                    <Layers className="w-10 h-10 mx-auto text-muted-foreground/40 mb-3" />
                    <p className="text-sm font-medium">Chưa có cụm từ khóa nào được tạo.</p>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={handleCluster}
                      disabled={clusterMutation.isPending}
                      className="mt-3"
                    >
                      {clusterMutation.isPending ? (
                        <>
                          <Loader2 className="w-4 h-4 mr-1.5 animate-spin" />
                          Đang phân tích...
                        </>
                      ) : (
                        'Gom cụm bằng AI ngay'
                      )}
                    </Button>
                  </CardContent>
                </Card>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {clusters.map((cluster, idx) => (
                    <Card key={idx} className="border border-border shadow-xs">
                      <CardHeader className="pb-3 border-b border-border/60">
                        <div className="flex items-start justify-between gap-2">
                          <div>
                            <CardTitle className="text-base text-foreground font-semibold flex items-center gap-2">
                              <Sparkles className="w-4 h-4 text-primary" />
                              {cluster.cluster_name}
                            </CardTitle>
                            <CardDescription className="text-xs mt-0.5">
                              Trụ cột: <strong>{cluster.pillar_topic}</strong>
                            </CardDescription>
                          </div>
                          {getIntentBadge(cluster.search_intent)}
                        </div>
                        <div className="flex items-center gap-4 text-xs text-muted-foreground mt-2">
                          <span>Tổng Volume: <strong>{cluster.total_search_volume.toLocaleString()}</strong></span>
                          <span>Độ khó TB: <strong>{cluster.avg_difficulty}/100</strong></span>
                        </div>
                      </CardHeader>
                      <CardContent className="pt-3">
                        <div className="text-xs font-semibold text-muted-foreground mb-1.5">
                          Từ khóa chính (Primary):
                        </div>
                        <div className="text-xs font-medium bg-primary/10 text-primary px-2.5 py-1 rounded-md mb-3 flex items-center gap-1.5">
                          <Check className="w-3.5 h-3.5" />
                          {cluster.primary_keyword}
                        </div>

                        <div className="text-xs font-semibold text-muted-foreground mb-1.5">
                          Các từ khóa vệ tinh ({cluster.keywords.length}):
                        </div>
                        <div className="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                          {cluster.keywords.map((kw, kwIdx) => (
                            <div
                              key={kwIdx}
                              className="flex items-center justify-between text-xs p-1.5 rounded hover:bg-muted/40"
                            >
                              <span className="text-foreground">{kw.keyword}</span>
                              <span className="font-mono text-muted-foreground text-[11px]">
                                {kw.search_volume.toLocaleString()} vol
                              </span>
                            </div>
                          ))}
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* Save to Campaign Dialog */}
      <Dialog open={isSaveOpen} onOpenChange={setIsSaveOpen}>
        <DialogContent className="sm:max-w-[460px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FolderPlus className="w-5 h-5 text-primary" />
              Lưu từ khóa vào Chiến dịch
            </DialogTitle>
            <DialogDescription>
              Lưu <strong>{selectedKeywords.size}</strong> từ khóa đã chọn vào chiến dịch SEO để lên lịch viết bài tự động.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div>
              <label className="text-xs font-medium text-foreground block mb-1.5">
                Chọn Chiến dịch đích
              </label>
              <select
                value={targetCampaignId || ''}
                onChange={(e) => setTargetCampaignId(e.target.value ? Number(e.target.value) : undefined)}
                className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                disabled={saveMutation.isPending}
              >
                <option value="">-- Không gắn chiến dịch (Lưu vào kho từ khóa chung) --</option>
                {campaignsData?.data?.map((camp) => (
                  <option key={camp.id} value={camp.id}>
                    {camp.name} ({camp.status})
                  </option>
                ))}
              </select>
            </div>

            <div className="p-3 bg-muted/40 rounded-lg text-xs text-muted-foreground space-y-1">
              <div className="flex items-center gap-1.5 font-medium text-foreground">
                <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                Dữ liệu được lưu trữ tự động:
              </div>
              <ul className="list-disc list-inside space-y-0.5 pl-1 text-[11px]">
                <li>Ý định tìm kiếm (Search Intent: Thông tin, Thương mại, Giao dịch)</li>
                <li>Khối lượng tìm kiếm ước tính & Độ khó KD</li>
                <li>Từ khóa ngữ nghĩa LSI để tối ưu heading và nội dung</li>
              </ul>
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setIsSaveOpen(false)}
              disabled={saveMutation.isPending}
            >
              Hủy
            </Button>
            <Button
              size="sm"
              onClick={handleSaveToCampaign}
              disabled={saveMutation.isPending || selectedKeywords.size === 0}
            >
              {saveMutation.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 mr-1.5 animate-spin" />
                  Đang lưu...
                </>
              ) : (
                'Xác nhận Lưu'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

export default function KeywordResearchPage() {
  return (
    <Suspense
      fallback={
        <div className="flex items-center justify-center min-h-[400px]">
          <Loader2 className="w-8 h-8 animate-spin text-primary" />
        </div>
      }
    >
      <KeywordResearchContent />
    </Suspense>
  );
}
