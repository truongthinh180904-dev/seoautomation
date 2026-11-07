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
  useGa4Properties,
  useGa4Actions,
  Ga4Property,
} from '@/hooks/useGa4Integration';
import { toast } from 'sonner';
import {
  BarChart3,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Unplug,
  RefreshCw,
  ExternalLink,
  Clock,
} from 'lucide-react';
import Link from 'next/link';

export interface Ga4ConnectModalProps {
  websiteId: number;
  websiteName?: string;
  websiteDomain?: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export function Ga4ConnectModal({
  websiteId,
  websiteName,
  websiteDomain,
  isOpen,
  onClose,
  onSuccess,
}: Ga4ConnectModalProps) {
  const [selectedPropertyId, setSelectedPropertyId] = useState<string>('');

  const {
    data: ga4Data,
    isLoading: isLoadingProperties,
    isError,
    error,
    refetch,
  } = useGa4Properties(websiteId, isOpen);

  const { connect, disconnect, testConnection, isLoading: isActionLoading } = useGa4Actions(websiteId);

  const connectedProperty = ga4Data?.connected_property;
  const properties = ga4Data?.properties ?? [];
  const needsGoogleConnect = ga4Data?.needs_google_connect;

  useEffect(() => {
    if (connectedProperty?.property_id) {
      setSelectedPropertyId(connectedProperty.property_id);
    } else if (properties.length > 0 && !selectedPropertyId) {
      // Auto-select matching property if domain matches
      const match = properties.find((p: Ga4Property) =>
        websiteDomain
          ? p.display_name.toLowerCase().includes(websiteDomain.toLowerCase())
          : false
      );
      if (match) {
        setSelectedPropertyId(match.property_id);
      }
    }
  }, [connectedProperty, properties, websiteDomain, selectedPropertyId]);

  const handleConnect = async () => {
    if (!selectedPropertyId) {
      toast.error('Vui lòng chọn một Google Analytics 4 Property để kết nối.');
      return;
    }

    const selectedProp = properties.find((p: Ga4Property) => p.property_id === selectedPropertyId);

    try {
      await connect.mutateAsync({
        property_id: selectedPropertyId,
        property_name: selectedProp?.display_name,
        account_id: selectedProp?.account_id,
        account_name: selectedProp?.account_name,
      });
      toast.success('Kết nối Google Analytics 4 thành công!');
      if (onSuccess) {
        onSuccess();
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Không thể kết nối GA4 Property';
      toast.error(message);
    }
  };

  const handleDisconnect = async () => {
    const confirmed = window.confirm(
      'Bạn có chắc chắn muốn ngắt kết nối Google Analytics 4 cho website này?'
    );
    if (!confirmed) {
      return;
    }

    try {
      await disconnect.mutateAsync();
      setSelectedPropertyId('');
      toast.success('Đã ngắt kết nối Google Analytics 4 thành công.');
      if (onSuccess) {
        onSuccess();
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Không thể ngắt kết nối Google Analytics 4';
      toast.error(message);
    }
  };

  const handleTest = async () => {
    try {
      const result = await testConnection.mutateAsync();
      if (result.data.is_verified) {
        toast.success(`Xác thực thành công GA4 Property: ${result.data.property_name} (${result.data.time_zone})`);
      } else {
        toast.warning('Không thể xác thực quyền truy cập GA4 Property này.');
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Lỗi khi kiểm tra kết nối Google Analytics 4';
      toast.error(message);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open: boolean) => !open && onClose()}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="p-2 bg-amber-500/10 text-amber-600 rounded-lg">
              <BarChart3 className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle>Kết nối Google Analytics 4</DialogTitle>
              <DialogDescription>
                {websiteName ? `Website: ${websiteName} (${websiteDomain || ''})` : 'Quản lý liên kết GA4 Property & Múi giờ báo cáo'}
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
              <p className="text-sm">Đang tải danh sách Property từ Google Analytics 4...</p>
            </div>
          )}

          {!isLoadingProperties && isError && (
            <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-4 text-destructive space-y-2">
              <div className="flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <p className="text-sm font-medium">Lỗi tải dữ liệu</p>
              </div>
              <p className="text-xs">{error?.message || 'Không thể lấy thông tin GA4 Property.'}</p>
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
                          GA4 Property đang kết nối
                        </p>
                        <p className="text-sm font-medium text-emerald-800 dark:text-emerald-300">
                          {connectedProperty.property_name || connectedProperty.property_id}
                        </p>
                        <div className="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                          <span className="font-mono">{connectedProperty.property_id}</span>
                          <span>•</span>
                          <span className="inline-flex items-center gap-1">
                            <Clock className="w-3 h-3" /> {connectedProperty.time_zone}
                          </span>
                          <span>•</span>
                          <span>{connectedProperty.currency_code}</span>
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-1.5">
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
                  <span>Chọn Google Analytics 4 Property ({properties.length})</span>
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
                    Không tìm thấy GA4 Property nào trong tài khoản Google này. Hãy đảm bảo tài khoản đã được cấp quyền quản lý hoặc xem Analytics.
                  </p>
                ) : (
                  <div className="max-h-56 overflow-y-auto space-y-1.5 border rounded-lg p-2">
                    {properties.map((property: Ga4Property) => {
                      const isSelected = selectedPropertyId === property.property_id;
                      const isConnected = property.is_connected || connectedProperty?.property_id === property.property_id;

                      return (
                        <div
                          key={property.property_id}
                          onClick={() => setSelectedPropertyId(property.property_id)}
                          className={`flex items-center justify-between p-2.5 rounded-md cursor-pointer border transition-colors ${
                            isSelected
                              ? 'border-primary bg-primary/5'
                              : 'border-transparent hover:bg-muted/50'
                          }`}
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <BarChart3 className="w-4 h-4 text-muted-foreground shrink-0" />
                            <div className="min-w-0">
                              <p className="text-sm font-medium truncate">
                                {property.display_name}
                              </p>
                              <p className="text-xs text-muted-foreground truncate font-mono">
                                {property.property_id} {property.account_name ? `(${property.account_name})` : ''}
                              </p>
                            </div>
                          </div>
                          <div className="flex items-center gap-2 shrink-0">
                            <Badge variant="secondary" className="text-[10px]">
                              {property.numeric_id}
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
                !selectedPropertyId ||
                isActionLoading ||
                (connectedProperty?.property_id === selectedPropertyId)
              }
              className="gap-1.5"
            >
              {connect.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Đang kết nối...
                </>
              ) : connectedProperty?.property_id === selectedPropertyId ? (
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
