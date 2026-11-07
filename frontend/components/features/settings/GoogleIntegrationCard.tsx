"use client";

import React, { useEffect, useState, useCallback } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { get, del } from '@/lib/api/client';
import { toast } from 'sonner';
import {
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Unplug,
  ExternalLink,
  ShieldCheck,
  Search,
  BarChart3,
  Loader2,
} from 'lucide-react';

export interface GoogleAccountInfo {
  id: number;
  email: string;
  google_id: string;
  scopes: string[];
  status: string;
  is_expired: boolean;
  token_expires_at: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface GoogleIntegrationStatus {
  connected: boolean;
  account: GoogleAccountInfo | null;
}

interface GoogleIntegrationCardProps {
  className?: string;
  onStatusChange?: (status: GoogleIntegrationStatus) => void;
}

export function GoogleIntegrationCard({
  className,
  onStatusChange,
}: GoogleIntegrationCardProps) {
  const [loading, setLoading] = useState<boolean>(true);
  const [isConnecting, setIsConnecting] = useState<boolean>(false);
  const [isDisconnecting, setIsDisconnecting] = useState<boolean>(false);
  const [status, setStatus] = useState<GoogleIntegrationStatus>({
    connected: false,
    account: null,
  });

  const fetchStatus = useCallback(async () => {
    try {
      setLoading(true);
      const data = await get<GoogleIntegrationStatus>('/integrations/google/status');
      setStatus(data);
      if (onStatusChange) {
        onStatusChange(data);
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Không thể tải trạng thái kết nối Google';
      toast.error(message);
    } finally {
      setLoading(false);
    }
  }, [onStatusChange]);

  useEffect(() => {
    fetchStatus();
  }, [fetchStatus]);

  const handleConnect = async () => {
    try {
      setIsConnecting(true);
      const response = await get<{ auth_url: string; state: string }>('/integrations/google/auth-url');
      if (response && response.auth_url) {
        window.location.href = response.auth_url;
      } else {
        toast.error('Không nhận được đường dẫn xác thực Google.');
        setIsConnecting(false);
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Lỗi khi khởi tạo xác thực Google';
      toast.error(message);
      setIsConnecting(false);
    }
  };

  const handleDisconnect = async () => {
    if (!status.account?.id) {
      return;
    }

    const confirmed = window.confirm(
      'Bạn có chắc chắn muốn ngắt kết nối tài khoản Google? Việc này sẽ tạm ngưng đồng bộ Google Search Console và Google Analytics 4.'
    );
    if (!confirmed) {
      return;
    }

    try {
      setIsDisconnecting(true);
      await del(`/integrations/google/${status.account.id}`);
      toast.success('Đã ngắt kết nối tài khoản Google thành công.');
      await fetchStatus();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Không thể ngắt kết nối';
      toast.error(message);
    } finally {
      setIsDisconnecting(false);
    }
  };

  const hasSearchConsole = status.account?.scopes?.some((s) => s.includes('webmasters')) ?? false;
  const hasAnalytics = status.account?.scopes?.some((s) => s.includes('analytics')) ?? false;

  return (
    <Card className={`overflow-hidden border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 ${className ?? ''}`}>
      <CardHeader className="border-b border-slate-100 bg-slate-50/50 pb-4 dark:border-slate-800 dark:bg-slate-900/50">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-xs dark:border-slate-700 dark:bg-slate-800">
              <svg className="h-5 w-5" viewBox="0 0 24 24">
                <path
                  fill="#4285F4"
                  d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                />
                <path
                  fill="#34A853"
                  d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                />
                <path
                  fill="#FBBC05"
                  d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                />
                <path
                  fill="#EA4335"
                  d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                />
              </svg>
            </div>
            <div>
              <CardTitle className="text-base font-semibold text-slate-900 dark:text-slate-100">
                Tài khoản Google (GSC & GA4)
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Ủy quyền kết nối an toàn chuẩn OAuth 2.0 để đồng bộ dữ liệu SEO
              </CardDescription>
            </div>
          </div>

          <div>
            {loading ? (
              <Badge variant="outline" className="text-xs text-slate-400">
                <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                Đang kiểm tra...
              </Badge>
            ) : status.connected && status.account ? (
              status.account.is_expired ? (
                <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                  <AlertTriangle className="h-3 w-3" />
                  Token hết hạn
                </Badge>
              ) : (
                <Badge className="flex items-center gap-1 bg-emerald-500/15 text-xs text-emerald-700 dark:text-emerald-400">
                  <CheckCircle2 className="h-3 w-3" />
                  Đã kết nối
                </Badge>
              )
            ) : (
              <Badge variant="secondary" className="text-xs text-slate-500">
                Chưa kết nối
              </Badge>
            )}
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-6">
        {loading ? (
          <div className="flex h-32 items-center justify-center space-x-2 text-sm text-slate-500">
            <Loader2 className="h-5 w-5 animate-spin text-blue-600" />
            <span>Đang tải thông tin tích hợp Google...</span>
          </div>
        ) : status.connected && status.account ? (
          <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
              <div>
                <div className="text-xs font-medium text-slate-500">Email kết nối</div>
                <div className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                  {status.account.email}
                </div>
                {status.account.token_expires_at && (
                  <div className="mt-1 text-xs text-slate-400">
                    Hạn token: {new Date(status.account.token_expires_at).toLocaleString('vi-VN')}
                  </div>
                )}
              </div>

              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={fetchStatus}
                  disabled={loading}
                  className="h-8 text-xs"
                >
                  <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                  Làm mới
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={handleDisconnect}
                  disabled={isDisconnecting}
                  className="h-8 border-rose-200 text-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:border-rose-900 dark:hover:bg-rose-950"
                >
                  {isDisconnecting ? (
                    <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Unplug className="mr-1.5 h-3.5 w-3.5" />
                  )}
                  Ngắt kết nối
                </Button>
              </div>
            </div>

            <div>
              <div className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                Phân quyền dịch vụ đã cấp
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="flex items-start space-x-3 rounded-lg border border-slate-200/80 p-3 dark:border-slate-800">
                  <div className="mt-0.5 rounded-md bg-blue-50 p-1.5 text-blue-600 dark:bg-blue-950/50">
                    <Search className="h-4 w-4" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold text-slate-900 dark:text-slate-100">
                        Google Search Console
                      </span>
                      {hasSearchConsole ? (
                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                      ) : (
                        <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                      )}
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                      Đồng bộ Clicks, Impressions, CTR và Vị trí thứ hạng từ khóa.
                    </p>
                  </div>
                </div>

                <div className="flex items-start space-x-3 rounded-lg border border-slate-200/80 p-3 dark:border-slate-800">
                  <div className="mt-0.5 rounded-md bg-amber-50 p-1.5 text-amber-600 dark:bg-amber-950/50">
                    <BarChart3 className="h-4 w-4" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold text-slate-900 dark:text-slate-100">
                        Google Analytics 4
                      </span>
                      {hasAnalytics ? (
                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                      ) : (
                        <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                      )}
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                      Đồng bộ Sessions, Users, Engagement Rate và Conversions bài viết.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex items-center space-x-2 text-xs text-slate-500">
              <ShieldCheck className="h-4 w-4 text-emerald-600" />
              <span>
                Toàn bộ OAuth Tokens được mã hóa AES-256 an toàn trong Token Vault và bảo vệ theo từng Workspace.
              </span>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <p className="text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              Khi kết nối tài khoản Google, hệ thống AuraSEO có thể tự động truy xuất dữ liệu tìm kiếm thực tế
              từ <strong>Google Search Console</strong> và lưu lượng truy cập từ <strong>Google Analytics 4</strong>.
              Dữ liệu được dùng để phát hiện tụt hạng, cơ hội cải thiện CTR và tự động đề xuất làm mới nội dung.
            </p>

            <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/40 dark:bg-blue-950/20">
              <div className="flex items-start space-x-3">
                <ShieldCheck className="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                <div className="text-xs text-slate-700 dark:text-slate-300">
                  <span className="font-semibold text-blue-900 dark:text-blue-300">Cam kết bảo mật & Quyền riêng tư:</span>
                  <ul className="mt-1 list-disc space-y-0.5 pl-4 text-slate-600 dark:text-slate-400">
                    <li>Chỉ yêu cầu quyền đọc dữ liệu báo cáo (read-only), không có quyền chỉnh sửa tài khoản.</li>
                    <li>Tokens được mã hóa AES-256 và cô lập theo Workspace độc quyền của bạn.</li>
                    <li>Đồng bộ dữ liệu hoàn toàn <strong>0 Credit</strong> (miễn phí theo SaaS nền tảng).</li>
                  </ul>
                </div>
              </div>
            </div>

            <div className="pt-2">
              <Button
                onClick={handleConnect}
                disabled={isConnecting}
                className="bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
              >
                {isConnecting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Đang chuyển hướng sang Google...
                  </>
                ) : (
                  <>
                    <ExternalLink className="mr-2 h-4 w-4" />
                    Kết nối tài khoản Google
                  </>
                )}
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
