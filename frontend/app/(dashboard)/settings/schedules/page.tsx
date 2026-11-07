"use client";

import React, { useState, useEffect, useCallback } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AutomationSettingsCard } from '@/components/features/settings/AutomationSettingsCard';
import { get } from '@/lib/api/client';
import { toast } from 'sonner';
import {
  CalendarClock,
  ShieldCheck,
  Globe,
  Loader2
} from 'lucide-react';

export interface WebsiteItem {
  id: number;
  name: string;
  domain: string;
  status: string;
}

export interface AutomationOverviewData {
  workspace_id: number;
  schedules: Array<{
    name: string;
    cron: string;
    time: string;
    status: string;
  }>;
  distributed_locking: {
    driver: string;
    lock_timeout_minutes: number;
    prevent_overlapping: boolean;
  };
  websites: Array<{
    website_id: number;
    website_name: string;
    domain: string;
    has_gsc: boolean;
    has_ga4: boolean;
    active_keywords_count: number;
  }>;
}

export default function AutomationSchedulesPage() {
  const [loading, setLoading] = useState<boolean>(true);
  const [websites, setWebsites] = useState<WebsiteItem[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [overview, setOverview] = useState<AutomationOverviewData | null>(null);

  const fetchInitialData = useCallback(async () => {
    try {
      setLoading(true);
      const [websitesRes, schedulesRes] = await Promise.allSettled([
        get<{ data: WebsiteItem[] }>('/websites'),
        get<{ success: boolean; data: AutomationOverviewData }>('/settings/automation-schedules'),
      ]);

      let siteList: WebsiteItem[] = [];
      if (websitesRes.status === 'fulfilled' && websitesRes.value?.data) {
        siteList = websitesRes.value.data;
        setWebsites(siteList);
      }

      if (schedulesRes.status === 'fulfilled' && schedulesRes.value?.data) {
        setOverview(schedulesRes.value.data);
      }

      if (siteList.length > 0) {
        setSelectedWebsiteId(siteList[0].id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi khi tải thông tin lịch trình';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchInitialData();
  }, [fetchInitialData]);

  const selectedSite = websites.find((s) => s.id === selectedWebsiteId);

  return (
    <div className="space-y-6 pb-12">
      {/* Top Banner Header */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <CalendarClock className="h-6 w-6 text-blue-600" />
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">
              Lập Lịch & Tự Động Hóa SEO (Automation Engine)
            </h1>
          </div>
          <p className="text-sm text-slate-500">
            Cấu hình thời gian chạy tự động hàng ngày với khóa phân tán chống xung đột (Distributed Locking), bật/tắt theo Website và quản lý ngưỡng cảnh báo.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge className="bg-blue-50 text-blue-700 border-blue-200 py-1.5 px-3 flex items-center gap-1.5">
            <ShieldCheck className="h-4 w-4" />
            <span className="text-xs font-semibold whitespace-nowrap">
              Lock: {overview?.distributed_locking?.driver ?? 'Redis'} ({overview?.distributed_locking?.lock_timeout_minutes ?? 60}m)
            </span>
          </Badge>
        </div>
      </div>

      {/* 4 Daily Scheduled Pipelines Overview */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-slate-200 shadow-xs">
          <CardHeader className="p-4 pb-2">
            <div className="flex items-center justify-between">
              <Badge variant="outline" className="text-slate-600 font-mono text-[11px]">
                02:00 AM
              </Badge>
              <div className="h-2 w-2 rounded-full bg-emerald-500" />
            </div>
            <CardTitle className="text-sm font-semibold text-slate-900 mt-2">
              1. Đồng Bộ GSC Daily
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-0">
            <p className="text-xs text-slate-500">
              Đồng bộ Clicks, Impressions, CTR và Vị trí theo Query/Page từ Search Console API.
            </p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-xs">
          <CardHeader className="p-4 pb-2">
            <div className="flex items-center justify-between">
              <Badge variant="outline" className="text-slate-600 font-mono text-[11px]">
                03:00 AM
              </Badge>
              <div className="h-2 w-2 rounded-full bg-emerald-500" />
            </div>
            <CardTitle className="text-sm font-semibold text-slate-900 mt-2">
              2. Đồng Bộ GA4 Daily
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-0">
            <p className="text-xs text-slate-500">
              Đồng bộ Sessions, Users, Tỷ lệ tương tác và Thời lượng theo landing page URL.
            </p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-xs">
          <CardHeader className="p-4 pb-2">
            <div className="flex items-center justify-between">
              <Badge variant="outline" className="text-slate-600 font-mono text-[11px]">
                04:00 AM
              </Badge>
              <div className="h-2 w-2 rounded-full bg-emerald-500" />
            </div>
            <CardTitle className="text-sm font-semibold text-slate-900 mt-2">
              3. Serper Rank Tracking
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-0">
            <p className="text-xs text-slate-500">
              Cào Top 100 kết quả Google Search cho từ khóa theo dõi, tính độ biến động hạng.
            </p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-xs">
          <CardHeader className="p-4 pb-2">
            <div className="flex items-center justify-between">
              <Badge variant="outline" className="text-slate-600 font-mono text-[11px]">
                05:00 AM
              </Badge>
              <div className="h-2 w-2 rounded-full bg-emerald-500" />
            </div>
            <CardTitle className="text-sm font-semibold text-slate-900 mt-2">
              4. Quét Bất Thường SEO
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-0">
            <p className="text-xs text-slate-500">
              Thuật toán Heuristics phát hiện tụt hạng, traffic decay và bắn thông báo cảnh báo.
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Website Selection & Rules Configuration */}
      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs">
          <div className="flex items-center gap-2 text-sm font-medium text-slate-700">
            <Globe className="h-4 w-4 text-blue-600" />
            <span>Chọn website cần cấu hình:</span>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {loading ? (
              <div className="flex items-center gap-2 text-xs text-slate-500">
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                <span>Đang tải danh sách...</span>
              </div>
            ) : websites.length === 0 ? (
              <span className="text-xs text-slate-400">Chưa có website nào trong Workspace.</span>
            ) : (
              websites.map((site) => (
                <Button
                  key={site.id}
                  variant={selectedWebsiteId === site.id ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setSelectedWebsiteId(site.id)}
                  className={`text-xs h-8 ${
                    selectedWebsiteId === site.id
                      ? 'bg-blue-600 hover:bg-blue-700 text-white font-medium'
                      : 'text-slate-700'
                  }`}
                >
                  {site.name}
                </Button>
              ))
            )}
          </div>
        </div>

        {/* Selected Website Automation Card */}
        {selectedSite ? (
          <AutomationSettingsCard
            key={selectedSite.id}
            websiteId={selectedSite.id}
            websiteName={selectedSite.name}
            domain={selectedSite.domain}
          />
        ) : !loading && websites.length > 0 ? (
          <Card className="border-slate-200 p-8 text-center">
            <p className="text-sm text-slate-500">Vui lòng chọn một website bên trên để cấu hình.</p>
          </Card>
        ) : null}
      </div>

      {/* Distributed Lock & Execution Architecture Note */}
      <Card className="border-slate-200 bg-slate-50/50 shadow-none">
        <CardContent className="p-4 sm:p-5">
          <div className="flex items-start gap-3">
            <ShieldCheck className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
            <div className="space-y-1 text-xs text-slate-600 leading-relaxed">
              <p className="font-semibold text-slate-800">
                Cơ chế Bảo vệ Chống Xung Đột (Distributed Locking Architecture)
              </p>
              <p>
                Mọi lệnh lập lịch (<code className="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">seo:sync-all</code>, <code className="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">seo:track-rankings</code>, <code className="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">seo:detect-anomalies</code>) đều vận hành qua cơ chế <strong className="text-slate-700">withoutOverlapping(60)</strong> của Laravel Scheduler và Redis Cache Lock.
              </p>
              <p>
                Nếu tác vụ trước chưa kết thúc do lượng dữ liệu lớn, tiến trình tiếp theo sẽ tự động hoãn lại, loại trừ triệt để tình trạng Race Conditions, ghi đè dữ liệu hoặc làm cạn kiệt tài nguyên máy chủ.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
