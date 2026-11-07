"use client";

import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  useGscProperties,
  useGscActions,
  GscProperty,
} from '@/hooks/useGscIntegration';
import { toast } from 'sonner';
import {
  Search,
  Globe,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Unplug,
  RefreshCw,
  ExternalLink,
} from 'lucide-react';
import Link from 'next/link';

export interface GscConnectModalProps {
  websiteId: number;
  websiteName?: string;
  websiteDomain?: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export function GscConnectModal({
  websiteId,
  websiteName,
  websiteDomain,
  isOpen,
  onClose,
  onSuccess,
}: GscConnectModalProps) {
  const [selectedSiteUrl, setSelectedSiteUrl] = useState<string>('');

  const {
    data: gscData,
    isLoading: isLoadingProperties,
    isError,
    error,
    refetch,
  } = useGscProperties(websiteId, isOpen);

  const { connect, disconnect, testConnection, isLoading: isActionLoading } = useGscActions(websiteId);

  const connectedProperty = gscData?.connected_property;
  const properties = gscData?.properties ?? [];
  const needsGoogleConnect = gscData?.needs_google_connect;

  useEffect(() => {
    if (connectedProperty?.site_url) {
      setSelectedSiteUrl(connectedProperty.site_url);
    } else if (properties.length > 0 && !selectedSiteUrl) {
      // Auto-select matching domain if exists
      const match = properties.find((p: GscProperty) =>
        websiteDomain ? p.site_url.includes(websiteDomain) : false
      );
      if (match) {
        setSelectedSiteUrl(match.site_url);
      }
    }
  }, [connectedProperty, properties, websiteDomain, selectedSiteUrl]);

  const handleConnect = async () => {
    if (!selectedSiteUrl) {
      toast.error('Vui lòng chọn một Search Console Property để kết nối.');
      return;
    }

    const selectedProp = properties.find((p: GscProperty) => p.site_url === selectedSiteUrl);
    const propertyType = selectedProp?.property_type ?? (selectedSiteUrl.startsWith('sc-domain:') ? 'domain' : 'url_prefix');

    try {
      await connect.mutateAsync({
        site_url: selectedSiteUrl,
        property_type: propertyType,
      });
      toast.success('Kết nối Google Search Console thành công!');
      if (onSuccess) {
        onSuccess();
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Không thể kết nối Search Console Property';
      toast.error(message);
    }
  };

  const handleDisconnect = async () => {
    const confirmed = window.confirm(
      'Bạn có chắc chắn muốn ngắt kết nối Google Search Console cho website này?'
    );
    if (!confirmed) {
      return;
    }

    try {
      await disconnect.mutateAsync();
      setSelectedSiteUrl('');
      toast.success('Đã ngắt kết nối Search Console thành công.');
      if (onSuccess) {
        onSuccess();
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Không thể ngắt kết nối Search Console';
      toast.error(message);
    }
  };

  const handleTest = async () => {
    try {
      const result = await testConnection.mutateAsync();
      if (result.data.is_verified) {
        toast.success(`Xác thực thành công Property: ${result.data.site_url}`);
      } else {
        toast.warning('Không thể xác thực quyền truy cập Property này.');
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Lỗi khi kiểm tra kết nối Search Console';
      toast.error(message);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open: boolean) => !open && onClose()}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="p-2 bg-blue-500/10 text-blue-600 rounded-lg">
              <Search className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle>Kết nối Google Search Console</DialogTitle>
              <DialogDescription>
                {websiteName ? `Website: ${websiteName} (${websiteDomain || ''})` : 'Quản lý liên kết Search Console Property'}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-4 py-2">
          {needsGoogleConnect && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
              <div className="flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div className="space-y-2 text-sm">
                  <p className="font-medium">Chưa kết nối tài khoản Google</p>
                  <p className="text-amber-700 dark:text-amber-300">
                    Không gian làm việc này chưa có tài khoản Google nào được ủy quyền. Vui lòng kết nối tài khoản Google trước.
                  </p>
                  <Button asChild size="sm" variant="outline" className="border-amber-300">
                    <Link href="/settings" onClick={onClose} className="inline-flex items-center gap-1.5">
                      Đến trang Cài đặt Tích hợp <ExternalLink className="w-3.5 h-3.5" />
                    </Link>
                  </Button>
                </div>
              </div>
            </div>
          )}

          {isLoadingProperties && (
            <div className="flex flex-col items-center justify-center py-8 text-muted-foreground gap-2">
              <Loader2 className="w-6 h-6 animate-spin text-primary" />
              <p className="text-sm">Đang tải danh sách Property từ Search Console...</p>
            </div>
          )}

          {!isLoadingProperties && isError && (
            <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-4 text-destructive space-y-2">
              <div className="flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <p className="text-sm font-medium">Lỗi tải dữ liệu</p>
              </div>
              <p className="text-xs">{error?.message || 'Không thể lấy thông tin Search Console Property.'}</p>
              <Button size="sm" variant="outline" onClick={() => refetch()} className="gap-1 mt-1">
                <RefreshCw className="w-3.5 h-3.5" /> Thử lại
              </Button>
            </div>
          )}

          {!isLoadingProperties && !needsGoogleConnect && !isError && (
            <>
              {connectedProperty && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3.5 dark:border-emerald-950 dark:bg-emerald-950/20">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                      <div>
                        <p className="text-xs font-semibold text-emerald-900 dark:text-emerald-200">
                          Property đang kết nối
                        </p>
                        <p className="text-sm font-mono text-emerald-800 dark:text-emerald-300">
                          {connectedProperty.site_url}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Badge variant="outline" className="text-xs bg-emerald-100 text-emerald-800 border-emerald-300">
                        {connectedProperty.property_type === 'domain' ? 'Domain Property' : 'URL Prefix'}
                      </Badge>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={handleTest}
                        disabled={isActionLoading}
                        className="h-8 gap-1 text-xs"
                      >
                        <RefreshCw className={`w-3 h-3 ${testConnection.isPending ? 'animate-spin' : ''}`} />
                        Kiểm tra
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={handleDisconnect}
                        disabled={isActionLoading}
                        className="h-8 gap-1 text-xs"
                      >
                        <Unplug className="w-3 h-3" />
                        Ngắt
                      </Button>
                    </div>
                  </div>
                </div>
              )}

              <div className="space-y-2">
                <label className="text-sm font-medium flex items-center justify-between">
                  <span>Chọn Search Console Property ({properties.length})</span>
                  <button
                    type="button"
                    onClick={() => refetch()}
                    className="text-xs text-muted-foreground hover:text-foreground inline-flex items-center gap-1"
                  >
                    <RefreshCw className="w-3 h-3" /> Làm mới
                  </button>
                </label>

                {properties.length === 0 ? (
                  <p className="text-sm text-muted-foreground py-4 text-center border border-dashed rounded-lg">
                    Không tìm thấy Property nào trong tài khoản Google này. Hãy đảm bảo tài khoản có quyền truy cập trang web trong Search Console.
                  </p>
                ) : (
                  <div className="max-h-56 overflow-y-auto space-y-1.5 border rounded-lg p-2">
                    {properties.map((property: GscProperty) => {
                      const isSelected = selectedSiteUrl === property.site_url;
                      const isConnected = property.is_connected || connectedProperty?.site_url === property.site_url;

                      return (
                        <div
                          key={property.site_url}
                          onClick={() => setSelectedSiteUrl(property.site_url)}
                          className={`flex items-center justify-between p-2.5 rounded-md cursor-pointer border transition-colors ${
                            isSelected
                              ? 'border-primary bg-primary/5'
                              : 'border-transparent hover:bg-muted/50'
                          }`}
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <Globe className="w-4 h-4 text-muted-foreground shrink-0" />
                            <div className="min-w-0">
                              <p className="text-sm font-medium truncate font-mono">
                                {property.site_url}
                              </p>
                              <p className="text-xs text-muted-foreground">
                                Quyền: {property.permission_level}
                              </p>
                            </div>
                          </div>
                          <div className="flex items-center gap-2 shrink-0">
                            <Badge variant="secondary" className="text-[10px]">
                              {property.property_type === 'domain' ? 'Domain' : 'URL Prefix'}
                            </Badge>
                            {isConnected && (
                              <Badge className="bg-emerald-600 text-white text-[10px]">
                                Đang nối
                              </Badge>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </>
          )}
        </div>

        <DialogFooter className="gap-2 sm:gap-0">
          <Button variant="ghost" onClick={onClose} disabled={isActionLoading}>
            Đóng
          </Button>
          {!needsGoogleConnect && (
            <Button
              onClick={handleConnect}
              disabled={
                !selectedSiteUrl ||
                isActionLoading ||
                (connectedProperty?.site_url === selectedSiteUrl)
              }
              className="gap-1.5"
            >
              {connect.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Đang kết nối...
                </>
              ) : connectedProperty?.site_url === selectedSiteUrl ? (
                <>
                  <CheckCircle2 className="w-4 h-4" />
                  Đang sử dụng
                </>
              ) : (
                'Kết nối Property này'
              )}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
