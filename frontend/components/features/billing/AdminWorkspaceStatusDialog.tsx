"use client";

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Loader2, Lock, Unlock, AlertTriangle } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useAdminUpdateWorkspaceStatus } from '@/hooks/useAdminBilling';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

const updateStatusSchema = z.object({
  status: z.enum(['active', 'suspended'], {
    required_error: 'Vui lòng chọn trạng thái không gian làm việc',
  }),
  reason: z
    .string()
    .min(5, 'Lý do thay đổi trạng thái phải có ít nhất 5 ký tự')
    .max(1000, 'Lý do không được vượt quá 1000 ký tự'),
});

type UpdateStatusFormData = z.infer<typeof updateStatusSchema>;

interface AdminWorkspaceStatusDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  workspace: AdminWorkspaceSummary | null;
}

export function AdminWorkspaceStatusDialog({
  open,
  onOpenChange,
  workspace,
}: AdminWorkspaceStatusDialogProps) {
  const updateStatusMutation = useAdminUpdateWorkspaceStatus();

  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<UpdateStatusFormData>({
    resolver: zodResolver(updateStatusSchema),
    defaultValues: {
      status: workspace?.status === 'suspended' ? 'active' : 'suspended',
      reason: '',
    },
  });

  useEffect(() => {
    if (workspace && open) {
      reset({
        status: workspace.status === 'suspended' ? 'active' : 'suspended',
        reason: '',
      });
    }
  }, [workspace, open, reset]);

  const selectedStatus = watch('status');

  if (!workspace) return null;

  const onSubmit = async (data: UpdateStatusFormData) => {
    try {
      await updateStatusMutation.mutateAsync({
        workspaceId: workspace.id,
        status: data.status,
        reason: data.reason,
      });

      toast.success(
        `Đã cập nhật trạng thái workspace ${workspace.name} thành ${
          data.status === 'suspended' ? 'Tạm ngưng (Khóa)' : 'Hoạt động (Mở khóa)'
        }`
      );
      reset();
      onOpenChange(false);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể cập nhật trạng thái không gian làm việc.'));
    }
  };

  const isPending = isSubmitting || updateStatusMutation.isPending;
  const isSuspending = selectedStatus === 'suspended';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md bg-white">
        <DialogHeader>
          <DialogTitle className="text-base font-bold text-slate-900 flex items-center">
            {isSuspending ? (
              <Lock className="mr-2 h-5 w-5 text-rose-600" />
            ) : (
              <Unlock className="mr-2 h-5 w-5 text-emerald-600" />
            )}
            {isSuspending ? 'Khóa (Tạm ngưng)' : 'Mở khóa'} Không gian làm việc
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            Workspace: <strong>{workspace.name}</strong> (#{workspace.id})
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 py-2 text-xs">
          {isSuspending ? (
            <div className="rounded-xl border border-rose-200 bg-rose-50/50 p-3 flex items-start space-x-2 text-rose-900">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
              <p>
                <strong>Cảnh báo:</strong> Khi tạm ngưng, tất cả thành viên trong workspace này sẽ bị từ chối truy cập API và giao diện quản lý ngay lập tức.
              </p>
            </div>
          ) : (
            <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 flex items-start space-x-2 text-emerald-900">
              <Unlock className="h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
              <p>
                Mở khóa sẽ khôi phục toàn bộ quyền truy cập và thực thi của các thành viên trong workspace này.
              </p>
            </div>
          )}

          <div className="space-y-1">
            <Label htmlFor="workspace_status_select" className="text-xs font-semibold text-slate-700">
              Trạng thái mới
            </Label>
            <select
              id="workspace_status_select"
              {...register('status')}
              disabled={isPending}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              <option value="active">Hoạt động (Active)</option>
              <option value="suspended">Tạm ngưng / Khóa (Suspended)</option>
            </select>
            {errors.status && (
              <p className="text-xs text-rose-600">{errors.status.message}</p>
            )}
          </div>

          <div className="space-y-1">
            <Label htmlFor="status_reason" className="text-xs font-semibold text-slate-700">
              Lý do thay đổi trạng thái <span className="text-rose-600">*</span>
            </Label>
            <Textarea
              id="status_reason"
              rows={3}
              placeholder="Ví dụ: Vi phạm điều khoản sử dụng, yêu cầu từ khách hàng, đã thanh toán phí nợ..."
              {...register('reason')}
              disabled={isPending}
              className="text-xs resize-none"
            />
            {errors.reason && (
              <p className="text-xs text-rose-600">{errors.reason.message}</p>
            )}
          </div>

          <DialogFooter className="pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isPending}
              className="text-xs"
            >
              Hủy
            </Button>
            <Button
              type="submit"
              disabled={isPending}
              className={`text-xs font-semibold text-white ${
                isSuspending
                  ? 'bg-rose-600 hover:bg-rose-700'
                  : 'bg-emerald-600 hover:bg-emerald-700'
              }`}
            >
              {isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang xử lý...
                </>
              ) : isSuspending ? (
                'Khóa Workspace'
              ) : (
                'Mở khóa Workspace'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
