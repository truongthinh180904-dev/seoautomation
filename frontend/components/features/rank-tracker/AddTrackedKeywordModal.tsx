'use client';

import React, { useState } from 'react';
import { useAddTrackedKeyword, CreateTrackedKeywordPayload } from '@/hooks/useRankTracker';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Loader2, Plus, Globe, Monitor, Smartphone } from 'lucide-react';

interface AddTrackedKeywordModalProps {
  isOpen: boolean;
  onClose: () => void;
  websiteId: number;
  websiteDomain: string;
}

export default function AddTrackedKeywordModal({
  isOpen,
  onClose,
  websiteId,
  websiteDomain,
}: AddTrackedKeywordModalProps) {
  const [keyword, setKeyword] = useState('');
  const [targetUrl, setTargetUrl] = useState('');
  const [country, setCountry] = useState('vn');
  const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
  const [trackingFrequency, setTrackingFrequency] = useState<'daily' | 'weekly'>('daily');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const addMutation = useAddTrackedKeyword();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!keyword.trim()) {
      setErrorMessage('Vui lòng nhập từ khóa mục tiêu.');
      return;
    }

    setErrorMessage(null);

    const payload: CreateTrackedKeywordPayload = {
      website_id: websiteId,
      keyword: keyword.trim(),
      target_url: targetUrl.trim() || undefined,
      country,
      device,
      tracking_frequency: trackingFrequency,
    };

    try {
      await addMutation.mutateAsync(payload);
      setKeyword('');
      setTargetUrl('');
      setErrorMessage(null);
      onClose();
    } catch (err: unknown) {
      const errorMsg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        (err as Error).message ||
        'Có lỗi xảy ra khi thêm từ khóa. Vui lòng thử lại.';
      setErrorMessage(errorMsg);
    }
  };

  const handleClose = () => {
    if (!addMutation.isPending) {
      setErrorMessage(null);
      onClose();
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-900">
            <Plus className="w-5 h-5 text-indigo-600" />
            Thêm từ khóa theo dõi thứ hạng
          </DialogTitle>
          <DialogDescription className="text-sm text-slate-500">
            Theo dõi vị trí Top 100 Google Search hàng ngày cho website{' '}
            <span className="font-semibold text-slate-700">{websiteDomain}</span>.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 py-2">
          {errorMessage && (
            <div className="p-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
              {errorMessage}
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="keyword" className="text-xs font-semibold text-slate-700">
              Từ khóa theo dõi <span className="text-red-500">*</span>
            </Label>
            <Input
              id="keyword"
              placeholder="VD: bọc răng sứ thẩm mỹ"
              value={keyword}
              onChange={(e) => setKeyword(e.target.value)}
              disabled={addMutation.isPending}
              required
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="targetUrl" className="text-xs font-semibold text-slate-700">
              URL bài viết mục tiêu (Tùy chọn)
            </Label>
            <Input
              id="targetUrl"
              placeholder={`VD: https://${websiteDomain}/boc-rang-su`}
              value={targetUrl}
              onChange={(e) => setTargetUrl(e.target.value)}
              disabled={addMutation.isPending}
            />
            <p className="text-[11px] text-slate-500">
              Để trống nếu muốn tự động khớp bất kỳ URL nào thuộc domain.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-slate-700">Quốc gia (Vị trí)</Label>
              <Select
                value={country}
                onValueChange={(val) => {
                  if (val) setCountry(val);
                }}
                disabled={addMutation.isPending}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Chọn quốc gia" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="vn">
                    <span className="flex items-center gap-1.5">
                      <Globe className="w-3.5 h-3.5 text-slate-500" />
                      Việt Nam (vn)
                    </span>
                  </SelectItem>
                  <SelectItem value="us">
                    <span className="flex items-center gap-1.5">
                      <Globe className="w-3.5 h-3.5 text-slate-500" />
                      Hoa Kỳ (us)
                    </span>
                  </SelectItem>
                  <SelectItem value="uk">
                    <span className="flex items-center gap-1.5">
                      <Globe className="w-3.5 h-3.5 text-slate-500" />
                      Vương Quốc Anh (uk)
                    </span>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-slate-700">Thiết bị</Label>
              <Select
                value={device}
                onValueChange={(val) => {
                  if (val === 'desktop' || val === 'mobile') {
                    setDevice(val);
                  }
                }}
                disabled={addMutation.isPending}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Chọn thiết bị" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="desktop">
                    <span className="flex items-center gap-1.5">
                      <Monitor className="w-3.5 h-3.5 text-slate-500" />
                      Máy tính (Desktop)
                    </span>
                  </SelectItem>
                  <SelectItem value="mobile">
                    <span className="flex items-center gap-1.5">
                      <Smartphone className="w-3.5 h-3.5 text-slate-500" />
                      Điện thoại (Mobile)
                    </span>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-slate-700">Tần suất kiểm tra</Label>
            <Select
              value={trackingFrequency}
              onValueChange={(val) => {
                if (val === 'daily' || val === 'weekly') {
                  setTrackingFrequency(val);
                }
              }}
              disabled={addMutation.isPending}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Tần suất kiểm tra" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="daily">Hàng ngày (Daily - Khuyến nghị)</SelectItem>
                <SelectItem value="weekly">Hàng tuần (Weekly)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <DialogFooter className="pt-2 flex items-center justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={addMutation.isPending}
            >
              Hủy
            </Button>
            <Button
              type="submit"
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
              disabled={addMutation.isPending || !keyword.trim()}
            >
              {addMutation.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 mr-1.5 animate-spin" />
                  Đang thêm...
                </>
              ) : (
                'Thêm từ khóa'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
