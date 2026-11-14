'use client';

import React, { useState, useEffect } from 'react';
import {
  useRankWebsitesList,
  useTrackedKeywords,
  useKeywordHistory,
  TrackedKeywordItem,
} from '@/hooks/useRankTracker';
import AddTrackedKeywordModal from '@/components/features/rank-tracker/AddTrackedKeywordModal';
import RankHistoryChart from '@/components/features/rank-tracker/RankHistoryChart';
import RankKeywordList from '@/components/features/rank-tracker/RankKeywordList';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Plus,
  TrendingUp,
  TrendingDown,
  Trophy,
  Target,
  BarChart3,
  Globe,
  Loader2,
} from 'lucide-react';

export default function RankTrackerPage() {
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [selectedKeyword, setSelectedKeyword] = useState<TrackedKeywordItem | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [deviceFilter, setDeviceFilter] = useState('all');

  // Fetch websites list
  const { data: websitesData, isLoading: isLoadingWebsites } = useRankWebsitesList();
  const websites = websitesData?.data || [];

  // Automatically select first website if none selected
  useEffect(() => {
    if (!selectedWebsiteId && websites.length > 0) {
      setSelectedWebsiteId(websites[0].id);
    }
  }, [websites, selectedWebsiteId]);

  const activeWebsite = websites.find((w) => w.id === selectedWebsiteId);

  // Fetch tracked keywords for active website
  const { data: keywordsData, isLoading: isLoadingKeywords } = useTrackedKeywords(
    {
      website_id: selectedWebsiteId || undefined,
      search: searchQuery || undefined,
      device: deviceFilter !== 'all' ? deviceFilter : undefined,
    },
    Boolean(selectedWebsiteId)
  );

  const keywords = keywordsData?.data || [];
  const summary = keywordsData?.summary || {
    total_keywords: 0,
    top_3: 0,
    top_10: 0,
    top_30: 0,
    top_100: 0,
    out_of_100: 0,
    improved: 0,
    declined: 0,
    unchanged: 0,
  };

  // Sync selected keyword when keywords change
  useEffect(() => {
    if (keywords.length > 0) {
      if (!selectedKeyword || !keywords.find((k) => k.id === selectedKeyword.id)) {
        setSelectedKeyword(keywords[0]);
      } else {
        const updated = keywords.find((k) => k.id === selectedKeyword.id);
        if (updated) setSelectedKeyword(updated);
      }
    } else {
      setSelectedKeyword(null);
    }
  }, [keywords, selectedKeyword]);

  // Fetch history for selected keyword
  const { data: historyData, isLoading: isLoadingHistory } = useKeywordHistory(
    selectedKeyword?.id || null,
    30
  );

  return (
    <div className="space-y-6">
      {/* Top Header Bar */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
            <Trophy className="w-6 h-6 text-indigo-600" />
            Rank Tracking (Google SERP)
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Theo dõi thứ hạng Top 100 từ khóa tự động qua Serper.dev, phân tích biến động theo ngày.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          {/* Website Selector */}
          <div className="w-56">
            <Select
              value={selectedWebsiteId ? String(selectedWebsiteId) : ''}
              onValueChange={(val) => {
                if (val) setSelectedWebsiteId(Number(val));
              }}
              disabled={isLoadingWebsites || websites.length === 0}
            >
              <SelectTrigger className="h-10 text-xs sm:text-sm bg-white">
                <SelectValue placeholder={isLoadingWebsites ? 'Đang tải...' : 'Chọn website'} />
              </SelectTrigger>
              <SelectContent>
                {websites.map((w) => (
                  <SelectItem key={w.id} value={String(w.id)}>
                    <span className="flex items-center gap-2">
                      <Globe className="w-3.5 h-3.5 text-slate-500" />
                      {w.name} ({w.domain})
                    </span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            onClick={() => setIsAddModalOpen(true)}
            disabled={!selectedWebsiteId}
            className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5 h-10 shadow-xs"
          >
            <Plus className="w-4 h-4" />
            Thêm từ khóa
          </Button>
        </div>
      </div>

      {/* Summary KPI Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
        <Card className="border border-slate-200 shadow-xs">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-slate-500">Tổng từ khóa</span>
              <Target className="w-4 h-4 text-slate-400" />
            </div>
            <div className="text-2xl font-extrabold text-slate-900 mt-2">
              {summary.total_keywords}
            </div>
            <div className="text-[11px] text-slate-400 mt-1">Đang theo dõi</div>
          </CardContent>
        </Card>

        <Card className="border border-emerald-100 bg-emerald-50/30 shadow-xs">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-emerald-800">Top 3 Google</span>
              <Trophy className="w-4 h-4 text-emerald-600" />
            </div>
            <div className="text-2xl font-extrabold text-emerald-700 mt-2">{summary.top_3}</div>
            <div className="text-[11px] text-emerald-600/80 mt-1">Vị trí #1 - #3</div>
          </CardContent>
        </Card>

        <Card className="border border-indigo-100 bg-indigo-50/30 shadow-xs">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-indigo-800">Top 10 Google</span>
              <BarChart3 className="w-4 h-4 text-indigo-600" />
            </div>
            <div className="text-2xl font-extrabold text-indigo-700 mt-2">{summary.top_10}</div>
            <div className="text-[11px] text-indigo-600/80 mt-1">Trang 1 tìm kiếm</div>
          </CardContent>
        </Card>

        <Card className="border border-blue-100 bg-blue-50/30 shadow-xs">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-blue-800">Top 30 Google</span>
              <BarChart3 className="w-4 h-4 text-blue-600" />
            </div>
            <div className="text-2xl font-extrabold text-blue-700 mt-2">{summary.top_30}</div>
            <div className="text-[11px] text-blue-600/80 mt-1">Trang 2 - 3</div>
          </CardContent>
        </Card>

        <Card className="border border-slate-200 shadow-xs">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-emerald-700">Tăng hạng</span>
              <TrendingUp className="w-4 h-4 text-emerald-600" />
            </div>
            <div className="text-2xl font-extrabold text-emerald-600 mt-2">+{summary.improved}</div>
            <div className="text-[11px] text-slate-400 mt-1">So với kỳ trước</div>
          </CardContent>
        </Card>

        <Card className="border border-slate-200 shadow-xs">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-rose-700">Tụt hạng</span>
              <TrendingDown className="w-4 h-4 text-rose-600" />
            </div>
            <div className="text-2xl font-extrabold text-rose-600 mt-2">-{summary.declined}</div>
            <div className="text-[11px] text-slate-400 mt-1">So với kỳ trước</div>
          </CardContent>
        </Card>
      </div>

      {/* Selected Keyword History Chart */}
      {selectedKeyword && (
        <RankHistoryChart
          keyword={selectedKeyword}
          historyData={historyData?.data || []}
          isLoading={isLoadingHistory}
        />
      )}

      {/* Tracked Keywords List Table */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-slate-900">
            Danh sách từ khóa ({keywords.length})
          </h2>
          {isLoadingKeywords && (
            <span className="flex items-center gap-1.5 text-xs text-slate-400">
              <Loader2 className="w-3.5 h-3.5 animate-spin" />
              Đang tải dữ liệu...
            </span>
          )}
        </div>

        <RankKeywordList
          keywords={keywords}
          isLoading={isLoadingKeywords}
          selectedKeywordId={selectedKeyword?.id}
          onSelectKeyword={(kw) => setSelectedKeyword(kw)}
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          deviceFilter={deviceFilter}
          onDeviceFilterChange={setDeviceFilter}
        />
      </div>

      {/* Modal Add Tracked Keyword */}
      {activeWebsite && (
        <AddTrackedKeywordModal
          isOpen={isAddModalOpen}
          onClose={() => setIsAddModalOpen(false)}
          websiteId={activeWebsite.id}
          websiteDomain={activeWebsite.domain || activeWebsite.name}
        />
      )}
    </div>
  );
}
