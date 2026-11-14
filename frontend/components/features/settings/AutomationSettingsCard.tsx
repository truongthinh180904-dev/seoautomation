"use client";

import React, { useState, useEffect, useCallback } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { get, put, post } from '@/lib/api/client';
import { toast } from 'sonner';
import {
  Clock,
  ShieldCheck,
  Mail,
  RefreshCw,
  Loader2,
  CheckCircle2,
  Sliders,
  BellRing
} from 'lucide-react';

export interface WebsiteAutomationRulesData {
  id?: number;
  workspace_id?: number;
  website_id?: number;
  sync_gsc_enabled: boolean;
  sync_ga4_enabled: boolean;
  rank_tracking_enabled: boolean;
  anomaly_scan_enabled: boolean;
  alert_ranking_drop_threshold: number;
  alert_traffic_drop_percentage: number;
  email_notifications_enabled: boolean;
  updated_at?: string;
}

interface AutomationSettingsCardProps {
  websiteId: number;
  websiteName: string;
  domain: string;
  className?: string;
}

export function AutomationSettingsCard({
  websiteId,
  websiteName,
  domain,
  className = '',
}: AutomationSettingsCardProps) {
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [triggering, setTriggering] = useState<boolean>(false);

  const [rules, setRules] = useState<WebsiteAutomationRulesData>({
    sync_gsc_enabled: true,
    sync_ga4_enabled: true,
    rank_tracking_enabled: true,
    anomaly_scan_enabled: true,
    alert_ranking_drop_threshold: 3,
    alert_traffic_drop_percentage: 20,
    email_notifications_enabled: true,
  });

  const fetchRules = useCallback(async () => {
    try {
      setLoading(true);
      const res = await get<{ success: boolean; data: WebsiteAutomationRulesData }>(
        `/websites/${websiteId}/automation-rules`
      );
      if (res && res.data) {
        setRules(res.data);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Không thể tải quy tắc tự động hóa';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [websiteId]);

  useEffect(() => {
    if (websiteId) {
      fetchRules();
    }
  }, [websiteId, fetchRules]);

  const handleSave = async () => {
    try {
      setSaving(true);
      const res = await put<{ success: boolean; message: string; data: WebsiteAutomationRulesData }>(
        `/websites/${websiteId}/automation-rules`,
        rules
      );
      if (res && res.data) {
        setRules(res.data);
        toast.success(res.message || 'Cập nhật cấu hình tự động hóa thành công');
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi khi lưu cấu hình';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleManualTrigger = async () => {
    try {
      setTriggering(true);
      const res = await post<{ success: boolean; message: string }>(
        `/websites/${websiteId}/automation-rules/trigger`,
        { service: 'all' }
      );
      toast.success(res.message || 'Đã kích hoạt tác vụ đồng bộ SEO trong nền.');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi khi kích hoạt đồng bộ';
      toast.error(msg);
    } finally {
      setTriggering(false);
    }
  };

  if (loading) {
    return (
      <Card className={`border-slate-200 shadow-sm ${className}`}>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600 mb-2" />
          <p className="text-sm text-slate-500">Đang tải cấu hình tự động hóa...</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className={`border-slate-200 shadow-sm overflow-hidden ${className}`}>
      <CardHeader className="bg-slate-50/60 border-b border-slate-100 pb-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <CardTitle className="text-lg font-semibold text-slate-900">
                Quy Tắc Tự Động Hóa: {websiteName}
              </CardTitle>
              <Badge variant="outline" className="text-xs font-medium text-slate-600">
                {domain}
              </Badge>
            </div>
            <CardDescription className="text-xs text-slate-500">
              Quản lý bật/tắt lịch trình quét định kỳ, ngưỡng kích hoạt cảnh báo thông minh và kênh thông báo.
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 flex items-center gap-1.5 py-1 px-2.5">
              <ShieldCheck className="h-3.5 w-3.5" />
              <span className="whitespace-nowrap text-xs">Distributed Lock (60m)</span>
            </Badge>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6 pt-6">
        {/* Section 1: Schedule Toggles */}
        <div className="space-y-3">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Clock className="h-4 w-4 text-blue-600" />
            <span>Lịch Trình Tự Động Định Kỳ (Daily Automated Tasks)</span>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            {/* GSC Task */}
            <div className="flex items-start justify-between p-3.5 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-slate-800">Đồng bộ Search Console</span>
                  <Badge variant="secondary" className="text-[10px] px-1.5 py-0 font-mono">
                    02:00
                  </Badge>
                </div>
                <p className="text-xs text-slate-500">
                  Tự động đồng bộ Clicks, Impressions, CTR, Vị trí từ GSC API (0 AI credit).
                </p>
              </div>
              <input
                type="checkbox"
                id="sync_gsc"
                checked={rules.sync_gsc_enabled}
                onChange={(e) => setRules({ ...rules, sync_gsc_enabled: e.target.checked })}
                className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
              />
            </div>

            {/* GA4 Task */}
            <div className="flex items-start justify-between p-3.5 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-slate-800">Đồng bộ Google Analytics 4</span>
                  <Badge variant="secondary" className="text-[10px] px-1.5 py-0 font-mono">
                    03:00
                  </Badge>
                </div>
                <p className="text-xs text-slate-500">
                  Cập nhật Sessions, Tỷ lệ tương tác, Thời lượng truy cập theo bài viết.
                </p>
              </div>
              <input
                type="checkbox"
                id="sync_ga4"
                checked={rules.sync_ga4_enabled}
                onChange={(e) => setRules({ ...rules, sync_ga4_enabled: e.target.checked })}
                className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
              />
            </div>

            {/* Rank Tracker Task */}
            <div className="flex items-start justify-between p-3.5 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-slate-800">Kiểm tra Thứ Hạng (Serper)</span>
                  <Badge variant="secondary" className="text-[10px] px-1.5 py-0 font-mono">
                    04:00
                  </Badge>
                </div>
                <p className="text-xs text-slate-500">
                  Cào Top 100 Google SERP cho các từ khóa theo dõi và lưu vết lịch sử.
                </p>
              </div>
              <input
                type="checkbox"
                id="rank_tracking"
                checked={rules.rank_tracking_enabled}
                onChange={(e) => setRules({ ...rules, rank_tracking_enabled: e.target.checked })}
                className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
              />
            </div>

            {/* Anomaly Scan Task */}
            <div className="flex items-start justify-between p-3.5 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-slate-800">Quét Bất Thường & Cơ Hội</span>
                  <Badge variant="secondary" className="text-[10px] px-1.5 py-0 font-mono">
                    05:00
                  </Badge>
                </div>
                <p className="text-xs text-slate-500">
                  Chạy Heuristics phát hiện tụt hạng, traffic decay, cannibalization (0 credit).
                </p>
              </div>
              <input
                type="checkbox"
                id="anomaly_scan"
                checked={rules.anomaly_scan_enabled}
                onChange={(e) => setRules({ ...rules, anomaly_scan_enabled: e.target.checked })}
                className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
              />
            </div>
          </div>
        </div>

        {/* Section 2: Alert Thresholds */}
        <div className="space-y-3 pt-2 border-t border-slate-100">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Sliders className="h-4 w-4 text-amber-600" />
            <span>Ngưỡng Kích Hoạt Cảnh Báo Nghiêm Trọng (Alert Thresholds)</span>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="drop_threshold" className="text-xs font-medium text-slate-700">
                Ngưỡng tụt hạng từ khóa (Ranking Drop Threshold)
              </Label>
              <div className="flex items-center gap-2">
                <Input
                  id="drop_threshold"
                  type="number"
                  min={1}
                  max={50}
                  value={rules.alert_ranking_drop_threshold}
                  onChange={(e) =>
                    setRules({
                      ...rules,
                      alert_ranking_drop_threshold: parseInt(e.target.value, 10) || 1,
                    })
                  }
                  className="w-24 text-center font-mono text-sm"
                />
                <span className="text-xs text-slate-500">
                  bậc (Cảnh báo khi từ khóa tụt quá mức này trong 7 ngày)
                </span>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="traffic_drop" className="text-xs font-medium text-slate-700">
                Ngưỡng suy giảm lưu lượng (Traffic Decay Threshold)
              </Label>
              <div className="flex items-center gap-2">
                <Input
                  id="traffic_drop"
                  type="number"
                  min={5}
                  max={90}
                  value={rules.alert_traffic_drop_percentage}
                  onChange={(e) =>
                    setRules({
                      ...rules,
                      alert_traffic_drop_percentage: parseInt(e.target.value, 10) || 5,
                    })
                  }
                  className="w-24 text-center font-mono text-sm"
                />
                <span className="text-xs text-slate-500">
                  % (Cảnh báo khi clicks hoặc sessions giảm vượt mức này)
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Section 3: Notification Channels */}
        <div className="space-y-3 pt-2 border-t border-slate-100">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <BellRing className="h-4 w-4 text-indigo-600" />
            <span>Kênh Nhận Cảnh Báo Tự Động (Notification Channels)</span>
          </div>
          <div className="p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 flex items-center justify-between">
            <div className="space-y-0.5">
              <div className="flex items-center gap-2 text-sm font-medium text-slate-800">
                <Mail className="h-4 w-4 text-slate-500" />
                <span>Gửi thông báo qua Email</span>
              </div>
              <p className="text-xs text-slate-500">
                Gửi email trực tiếp đến chủ sở hữu Workspace khi phát hiện các cảnh báo mức độ Critical.
              </p>
            </div>
            <input
              type="checkbox"
              id="email_notif"
              checked={rules.email_notifications_enabled}
              onChange={(e) => setRules({ ...rules, email_notifications_enabled: e.target.checked })}
              className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
            />
          </div>
        </div>
      </CardContent>

      <CardFooter className="bg-slate-50/40 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 py-3.5">
        <Button
          variant="outline"
          size="sm"
          onClick={handleManualTrigger}
          disabled={triggering || saving}
          className="text-xs text-slate-700"
        >
          {triggering ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" />
          ) : (
            <RefreshCw className="h-3.5 w-3.5 mr-1.5" />
          )}
          Chạy Đồng Bộ Ngay
        </Button>

        <Button
          size="sm"
          onClick={handleSave}
          disabled={saving || triggering}
          className="bg-blue-600 hover:bg-blue-700 text-white text-xs px-4"
        >
          {saving ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" />
          ) : (
            <CheckCircle2 className="h-3.5 w-3.5 mr-1.5" />
          )}
          Lưu Cấu Hình
        </Button>
      </CardFooter>
    </Card>
  );
}
