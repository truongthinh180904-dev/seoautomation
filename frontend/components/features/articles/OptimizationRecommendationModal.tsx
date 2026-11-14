'use client';

import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ContentDiffViewer } from './ContentDiffViewer';
import {
  ContentDiffData,
  useApplyOptimization,
  useDiffPreview,
  useGenerateOptimizationPlan,
  usePushUpdateWordPress,
} from '@/hooks/useContentOptimization';
import {
  Sparkles,
  Loader2,
  RefreshCw,
  CheckCircle2,
  ExternalLink,
  ShieldAlert,
  Save,
  Globe,
} from 'lucide-react';

interface OptimizationRecommendationModalProps {
  articleId: number;
  articleTitle?: string;
  focusKeyword?: string;
  insightId?: number;
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export const OptimizationRecommendationModal: React.FC<OptimizationRecommendationModalProps> = ({
  articleId,
  articleTitle = 'Bài viết cần tối ưu',
  focusKeyword = 'Từ khóa chính',
  insightId,
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [activePlan, setActivePlan] = useState<ContentDiffData | null>(null);
  const [appliedSuccess, setAppliedSuccess] = useState(false);
  const [wpUpdateResult, setWpUpdateResult] = useState<{ post_id?: number; url?: string; edit_url?: string } | null>(null);
  const [confirmWpOpen, setConfirmWpOpen] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // 1. Hook fetch cached diff preview
  const { data: cachedDiff, isLoading: isDiffLoading } = useDiffPreview(articleId, isOpen);

  // 2. Mutations
  const generateMutation = useGenerateOptimizationPlan();
  const applyMutation = useApplyOptimization();
  const pushWpMutation = usePushUpdateWordPress();

  // Hiển thị plan từ mutation hoặc cached preview
  const currentPlan = activePlan || cachedDiff || null;

  // Xử lý tạo kế hoạch (trừ 2 credits)
  const handleGeneratePlan = async () => {
    setErrorMessage(null);
    try {
      const plan = await generateMutation.mutateAsync({
        articleId,
        insightId,
      });
      setActivePlan(plan);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Có lỗi xảy ra khi tạo kế hoạch làm mới nội dung.';
      setErrorMessage(msg);
    }
  };

  // Xử lý lưu bản thảo & tạo version (không push WP)
  const handleApplyToStudio = async () => {
    if (!currentPlan) return;
    setErrorMessage(null);
    try {
      await applyMutation.mutateAsync({
        articleId,
        proposedTitle: currentPlan.proposedTitle,
        proposedContent: currentPlan.proposedContent,
        proposedSeoTitle: currentPlan.proposedSeoTitle || undefined,
        proposedSeoDescription: currentPlan.proposedSeoDescription || undefined,
        insightId,
      });
      setAppliedSuccess(true);
      if (onSuccess) onSuccess();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Không thể áp dụng bản tối ưu.';
      setErrorMessage(msg);
    }
  };

  // Xử lý Cổng Human-in-the-Loop cập nhật lên WordPress
  const handlePushToWordPress = async () => {
    setErrorMessage(null);
    try {
      const res = await pushWpMutation.mutateAsync({
        articleId,
        confirm: true,
        insightId,
      });
      setWpUpdateResult(res);
      setConfirmWpOpen(false);
      if (onSuccess) onSuccess();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi cập nhật bài viết lên WordPress.';
      setErrorMessage(msg);
    }
  };

  const handleModalClose = () => {
    setActivePlan(null);
    setAppliedSuccess(false);
    setWpUpdateResult(null);
    setConfirmWpOpen(false);
    setErrorMessage(null);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && handleModalClose()}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto sm:max-w-4xl">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
              <Sparkles className="h-4 w-4" />
            </span>
            <div>
              <DialogTitle className="text-lg font-bold text-slate-900">
                AI Content Refresh & Tối Ưu Hóa Bài Viết
              </DialogTitle>
              <DialogDescription className="text-xs text-slate-500">
                Làm mới nội dung dựa trên số liệu thực tế GSC, GA4 và SERP Ranking
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        {/* Error Alert */}
        {errorMessage && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Không thể tiếp tục</AlertTitle>
            <AlertDescription className="text-xs">{errorMessage}</AlertDescription>
          </Alert>
        )}

        {/* Success Alert WordPress */}
        {wpUpdateResult && (
          <Alert className="border-emerald-300 bg-emerald-50 text-emerald-900">
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            <AlertTitle className="font-semibold text-emerald-800">
              Cập nhật WordPress thành công!
            </AlertTitle>
            <AlertDescription className="text-xs text-emerald-700 flex flex-wrap items-center gap-4 mt-2">
              <span>Mã bài viết WP: #{wpUpdateResult.post_id}</span>
              {wpUpdateResult.url && (
                <a
                  href={wpUpdateResult.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-medium underline inline-flex items-center gap-1 hover:text-emerald-950"
                >
                  Xem bài viết trên web <ExternalLink className="h-3 w-3" />
                </a>
              )}
              {wpUpdateResult.edit_url && (
                <a
                  href={wpUpdateResult.edit_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-medium underline inline-flex items-center gap-1 hover:text-emerald-950"
                >
                  Mở trình soạn WP Admin <ExternalLink className="h-3 w-3" />
                </a>
              )}
            </AlertDescription>
          </Alert>
        )}

        {/* Success Alert Applied to Studio */}
        {appliedSuccess && !wpUpdateResult && (
          <Alert className="border-blue-300 bg-blue-50 text-blue-900">
            <CheckCircle2 className="h-4 w-4 text-blue-600" />
            <AlertTitle className="font-semibold text-blue-800">
              Đã cập nhật bài viết trong Content Studio!
            </AlertTitle>
            <AlertDescription className="text-xs text-blue-700">
              Phiên bản cũ đã được sao lưu an toàn trong lịch sử phiên bản (HasVersions). Bạn có thể tiếp tục chỉnh sửa hoặc duyệt xuất bản lên WordPress bên dưới.
            </AlertDescription>
          </Alert>
        )}

        {/* State 1: Chưa có kế hoạch -> Hiển thị Pre-flight Overview */}
        {!currentPlan ? (
          <div className="space-y-4 py-2">
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Thông Tin Bài Viết
                </span>
                <Badge variant="outline" className="border-slate-300 bg-white">
                  Bài viết #{articleId}
                </Badge>
              </div>
              <h4 className="text-base font-bold text-slate-900">{articleTitle}</h4>
              <div className="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <span className="font-semibold">Từ khóa trọng tâm:</span>
                <Badge className="bg-slate-200 text-slate-800 hover:bg-slate-200">
                  {focusKeyword}
                </Badge>
                {insightId && (
                  <Badge variant="secondary" className="border border-amber-300 bg-amber-50 text-amber-800">
                    Liên kết cảnh báo SEO #{insightId}
                  </Badge>
                )}
              </div>
            </div>

            <div className="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 space-y-2 text-xs text-indigo-900">
              <h5 className="font-semibold flex items-center gap-1.5 text-indigo-950">
                <Sparkles className="h-4 w-4 text-indigo-600" />
                Quy trình làm mới nội dung khép kín (AI Closed-Loop)
              </h5>
              <ul className="list-disc list-inside space-y-1 text-slate-600">
                <li>Truy vấn các Search Queries có lượt hiển thị cao từ Google Search Console (GSC).</li>
                <li>Đo lường thời lượng phiên và tỷ lệ tương tác người dùng thực tế từ GA4.</li>
                <li>Đối chiếu thứ hạng SERP và phân tích các đoạn nội dung còn thiếu hụt.</li>
                <li>Đề xuất cập nhật tiêu đề, tối ưu Meta Description và thêm các đề mục H2/H3 giá trị cao.</li>
                <li>Gợi ý chèn liên kết nội bộ (Internal Links) tới các bài viết mới xuất bản.</li>
              </ul>
              <div className="pt-2 flex items-center justify-between font-medium text-slate-700">
                <span>Chi phí thực thi:</span>
                <Badge className="bg-indigo-600 text-white hover:bg-indigo-700">
                  2 AI Credits
                </Badge>
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <Button
                onClick={handleGeneratePlan}
                disabled={generateMutation.isPending || isDiffLoading}
                className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
              >
                {generateMutation.isPending ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Đang phân tích GSC/GA4 & khởi tạo...
                  </>
                ) : (
                  <>
                    <Sparkles className="h-4 w-4" />
                    Khởi tạo kế hoạch tối ưu (2 Credits)
                  </>
                )}
              </Button>
            </div>
          </div>
        ) : (
          /* State 2: Đã có kế hoạch -> Hiển thị Diff Viewer Trước/Sau */
          <div className="space-y-6 py-2">
            <ContentDiffViewer data={currentPlan} />

            {/* Confirmation Dialog before pushing to WordPress */}
            {confirmWpOpen && (
              <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                <div className="flex items-center gap-2 text-amber-900 font-semibold text-sm">
                  <ShieldAlert className="h-4 w-4 text-amber-600" />
                  Xác nhận cập nhật bài viết lên WordPress
                </div>
                <p className="text-xs text-amber-800">
                  Hành động này sẽ ghi đè nội dung mới lên bài viết đang hoạt động trên website WordPress của bạn. Bản sao lưu phiên bản cũ đã được tạo an toàn trong hệ thống.
                </p>
                <div className="flex items-center justify-end gap-2 pt-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setConfirmWpOpen(false)}
                    disabled={pushWpMutation.isPending}
                    className="text-xs"
                  >
                    Hủy bỏ
                  </Button>
                  <Button
                    size="sm"
                    onClick={handlePushToWordPress}
                    disabled={pushWpMutation.isPending}
                    className="bg-amber-600 hover:bg-amber-700 text-white text-xs flex items-center gap-1.5"
                  >
                    {pushWpMutation.isPending && <Loader2 className="h-3 w-3 animate-spin" />}
                    Xác nhận xuất bản ngay
                  </Button>
                </div>
              </div>
            )}
          </div>
        )}

        <DialogFooter className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-between items-center">
          <Button
            variant="outline"
            onClick={handleModalClose}
            disabled={generateMutation.isPending || applyMutation.isPending || pushWpMutation.isPending}
            className="w-full sm:w-auto"
          >
            Đóng
          </Button>

          {currentPlan && (
            <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
              <Button
                variant="outline"
                onClick={handleGeneratePlan}
                disabled={generateMutation.isPending || applyMutation.isPending}
                className="flex items-center gap-1.5 text-xs"
              >
                <RefreshCw className={`h-3.5 w-3.5 ${generateMutation.isPending ? 'animate-spin' : ''}`} />
                Tạo lại kế hoạch
              </Button>

              <Button
                variant="secondary"
                onClick={handleApplyToStudio}
                disabled={applyMutation.isPending || appliedSuccess}
                className="flex items-center gap-1.5 text-xs border border-slate-200"
              >
                {applyMutation.isPending ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Save className="h-3.5 w-3.5 text-slate-600" />
                )}
                {appliedSuccess ? 'Đã lưu bản thảo' : 'Áp dụng vào Studio'}
              </Button>

              {!wpUpdateResult && !confirmWpOpen && (
                <Button
                  onClick={() => setConfirmWpOpen(true)}
                  disabled={pushWpMutation.isPending}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1.5 text-xs"
                >
                  <Globe className="h-3.5 w-3.5" />
                  Duyệt & Đăng WordPress
                </Button>
              )}
            </div>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
