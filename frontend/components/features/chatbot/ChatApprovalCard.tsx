'use client';

import React, { useState } from 'react';
import {
  ShieldAlert,
  CheckCircle2,
  XCircle,
  Clock,
  Globe,
  Coins,
  FileText,
  Loader2,
  ExternalLink,
  Layers,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export interface PendingActionData {
  uuid: string;
  actionName: string;
  title?: string;
  targetWebsite?: string;
  targetWebsiteUrl?: string;
  targetArticleTitle?: string;
  estimatedCredits: number;
  expiresAt?: string;
  status: 'pending' | 'approved' | 'rejected' | 'expired';
  reviewedAt?: string;
  payload?: Record<string, unknown>;
}

export interface ChatApprovalCardProps {
  action: PendingActionData;
  onApprove?: (actionUuid: string) => Promise<void> | void;
  onReject?: (actionUuid: string, reason?: string) => Promise<void> | void;
  className?: string;
}

export function ChatApprovalCard({
  action,
  onApprove,
  onReject,
  className,
}: ChatApprovalCardProps) {
  const [isApproving, setIsApproving] = useState(false);
  const [isRejecting, setIsRejecting] = useState(false);

  const isSubmitting = isApproving || isRejecting;
  const isPending = action.status === 'pending';

  const handleApprove = async () => {
    if (!isPending || isSubmitting) return;

    try {
      setIsApproving(true);
      await onApprove?.(action.uuid);
    } catch {
      // Error handled by parent or toast
    } finally {
      setIsApproving(false);
    }
  };

  const handleReject = async () => {
    if (!isPending || isSubmitting) return;

    try {
      setIsRejecting(true);
      await onReject?.(action.uuid);
    } catch {
      // Error handled by parent or toast
    } finally {
      setIsRejecting(false);
    }
  };

  // Human-readable action title
  const getActionTitle = (name: string): string => {
    switch (name) {
      case 'publish_to_wordpress':
      case 'wordpress_publish':
        return 'Xuất bản bài viết lên WordPress';
      case 'push_update_wordpress':
      case 'update_wordpress_post':
      case 'update_to_wordpress':
        return 'Xác nhận cập nhật bài viết lên WordPress';
      case 'content_refresh':
        return 'Phê duyệt làm mới bài viết cũ (AI Refresh)';
      case 'start_bulk_generation':
      case 'bulk_generation':
        return 'Sản xuất nội dung hàng loạt (Bulk)';
      case 'deduct_large_credits':
        return 'Xác nhận tiêu hao Credit lớn';
      default:
        return action.title || `Hành động: ${name}`;
    }
  };

  return (
    <div
      className={cn(
        'relative my-3 overflow-hidden rounded-xl border border-amber-300/80 bg-amber-50/60 p-4 shadow-xs transition-all dark:border-amber-900/60 dark:bg-amber-950/30',
        action.status === 'approved' &&
          'border-emerald-300/80 bg-emerald-50/50 dark:border-emerald-900/60 dark:bg-emerald-950/30',
        action.status === 'rejected' &&
          'border-slate-300/80 bg-slate-50/60 opacity-80 dark:border-slate-800 dark:bg-slate-900/40',
        action.status === 'expired' &&
          'border-rose-300/80 bg-rose-50/50 opacity-75 dark:border-rose-900/60 dark:bg-rose-950/30',
        className
      )}
    >
      {/* Header bar */}
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-2">
          <div
            className={cn(
              'flex h-8 w-8 items-center justify-center rounded-lg shadow-2xs',
              action.status === 'approved'
                ? 'bg-emerald-600 text-white'
                : action.status === 'rejected'
                  ? 'bg-slate-600 text-white'
                  : action.status === 'expired'
                    ? 'bg-rose-600 text-white'
                    : 'bg-amber-600 text-white'
            )}
          >
            {action.status === 'approved' ? (
              <CheckCircle2 className="h-4 w-4" />
            ) : action.status === 'rejected' ? (
              <XCircle className="h-4 w-4" />
            ) : action.status === 'expired' ? (
              <Clock className="h-4 w-4" />
            ) : (
              <ShieldAlert className="h-4 w-4" />
            )}
          </div>
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
              Cổng Xác Nhận Hành Động
            </h4>
            <h3 className="text-sm font-semibold text-foreground">
              {getActionTitle(action.actionName)}
            </h3>
          </div>
        </div>

        {/* Status Badge */}
        <div>
          {action.status === 'pending' && (
            <Badge
              variant="outline"
              className="border-amber-400 bg-amber-100/80 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200 text-[11px]"
            >
              Chờ phê duyệt
            </Badge>
          )}
          {action.status === 'approved' && (
            <Badge
              variant="outline"
              className="border-emerald-400 bg-emerald-100/80 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200 text-[11px]"
            >
              Đã phê duyệt
            </Badge>
          )}
          {action.status === 'rejected' && (
            <Badge
              variant="outline"
              className="border-slate-300 bg-slate-200/80 text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 text-[11px]"
            >
              Đã hủy bỏ
            </Badge>
          )}
          {action.status === 'expired' && (
            <Badge
              variant="outline"
              className="border-rose-300 bg-rose-100/80 text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300 text-[11px]"
            >
              Đã quá hạn
            </Badge>
          )}
        </div>
      </div>

      {/* Details Box */}
      <div className="mt-3.5 space-y-2 rounded-lg border border-border/70 bg-background/80 p-3 text-xs shadow-2xs">
        {/* Target Article */}
        {action.targetArticleTitle && (
          <div className="flex items-center justify-between gap-2 border-b border-border/50 pb-2">
            <span className="flex items-center gap-1.5 text-muted-foreground font-medium">
              <FileText className="h-3.5 w-3.5 text-blue-600" />
              <span>Bài viết:</span>
            </span>
            <span className="font-semibold text-foreground truncate max-w-[260px] text-right">
              {action.targetArticleTitle}
            </span>
          </div>
        )}

        {/* Target Website */}
        <div className="flex items-center justify-between gap-2 border-b border-border/50 pb-2">
          <span className="flex items-center gap-1.5 text-muted-foreground font-medium">
            <Globe className="h-3.5 w-3.5 text-indigo-600" />
            <span>Website đích:</span>
          </span>
          <span className="font-semibold text-foreground truncate max-w-[260px] text-right flex items-center gap-1">
            {action.targetWebsite || 'Website mặc định'}
            {action.targetWebsiteUrl && (
              <ExternalLink className="h-3 w-3 text-muted-foreground" />
            )}
          </span>
        </div>

        {/* Estimated Credits */}
        <div className="flex items-center justify-between gap-2 border-b border-border/50 pb-2">
          <span className="flex items-center gap-1.5 text-muted-foreground font-medium">
            <Coins className="h-3.5 w-3.5 text-amber-600" />
            <span>Chi phí dự kiến:</span>
          </span>
          <span className="font-bold text-foreground">
            {action.estimatedCredits > 0 ? (
              <span className="text-amber-700 dark:text-amber-400">
                {action.estimatedCredits} Credits
              </span>
            ) : (
              <span className="text-emerald-700 dark:text-emerald-400">
                0 Credits (Miễn phí)
              </span>
            )}
          </span>
        </div>

        {/* Expiry notice */}
        <div className="flex items-center justify-between gap-2 pt-0.5 text-[11px] text-muted-foreground">
          <span className="flex items-center gap-1.5">
            <Clock className="h-3 w-3" />
            <span>Thời gian hiệu lực:</span>
          </span>
          <span>
            {action.status === 'expired'
              ? 'Hết hạn lúc: ' + (action.expiresAt || '30 phút trước')
              : 'Tự động hủy sau 30 phút'}
          </span>
        </div>
      </div>

      {/* Action Buttons with Double-Submit Protection */}
      {isPending && (
        <div className="mt-3.5 flex items-center gap-2.5">
          <Button
            type="button"
            size="sm"
            onClick={handleApprove}
            disabled={isSubmitting}
            className="flex-1 gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700 shadow-xs h-8 text-xs font-semibold"
          >
            {isApproving ? (
              <>
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                <span>Đang xử lý...</span>
              </>
            ) : (
              <>
                <CheckCircle2 className="h-3.5 w-3.5" />
                <span>Xác nhận thực hiện</span>
              </>
            )}
          </Button>

          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={handleReject}
            disabled={isSubmitting}
            className="flex-1 gap-1.5 border-border bg-background text-muted-foreground hover:bg-destructive/10 hover:text-destructive hover:border-destructive/30 shadow-2xs h-8 text-xs font-medium"
          >
            {isRejecting ? (
              <>
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                <span>Đang hủy...</span>
              </>
            ) : (
              <>
                <XCircle className="h-3.5 w-3.5" />
                <span>Hủy bỏ</span>
              </>
            )}
          </Button>
        </div>
      )}

      {/* Reviewed / Handled notice */}
      {!isPending && (
        <div className="mt-2.5 text-center text-[11px] text-muted-foreground italic">
          {action.status === 'approved' &&
            'Hành động đã được xác nhận thực hiện thành công.'}
          {action.status === 'rejected' &&
            'Hành động đã được hủy bỏ an toàn, không có thao tác nào diễn ra.'}
          {action.status === 'expired' &&
            'Yêu cầu xác nhận đã hết hạn, vui lòng ra lệnh lại nếu bạn vẫn muốn thực hiện.'}
        </div>
      )}
    </div>
  );
}
