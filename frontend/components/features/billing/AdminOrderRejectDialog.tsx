"use client";

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Loader2, XCircle, AlertTriangle } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useAdminRejectOrder } from '@/hooks/useAdminBilling';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

const rejectOrderSchema = z.object({
  rejection_reason: z
    .string()
    .min(5, 'Lý do từ chối phải có ít nhất 5 ký tự')
    .max(1000, 'Lý do không được vượt quá 1000 ký tự'),
  admin_note: z.string().max(1000, 'Ghi chú không được vượt quá 1000 ký tự').optional(),
});

type RejectOrderFormData = z.infer<typeof rejectOrderSchema>;

interface AdminOrderRejectDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  order: PaymentOrder | null;
}

export function AdminOrderRejectDialog({
  open,
  onOpenChange,
  order,
}: AdminOrderRejectDialogProps) {
  const rejectMutation = useAdminRejectOrder();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<RejectOrderFormData>({
    resolver: zodResolver(rejectOrderSchema),
    defaultValues: {
      rejection_reason: '',
      admin_note: '',
    },
  });

  useEffect(() => {
    if (open) {
      reset({
        rejection_reason: '',
        admin_note: '',
      });
    }
  }, [open, order, reset]);

  if (!order) return null;

  const onSubmit = async (data: RejectOrderFormData) => {
    try {
      await rejectMutation.mutateAsync({
        orderId: order.id,
        rejectionReason: data.rejection_reason,
        adminNote: data.admin_note?.trim() || undefined,
      });

      toast.success(`Đã từ chối đơn hàng #${order.reference_code}`);
      reset();
      onOpenChange(false);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể từ chối đơn hàng. Vui lòng kiểm tra lại.'));
    }
  };

  const isPending = isSubmitting || rejectMutation.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md bg-white">
        <DialogHeader>
          <DialogTitle className="text-base font-bold text-slate-900 flex items-center text-rose-600">
            <XCircle className="mr-2 h-5 w-5" />
            Từ chối đơn thanh toán #{order.reference_code}
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            Khách hàng sẽ nhận được thông báo từ chối kèm lý do bạn cung cấp.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 py-2 text-xs">
          <div className="rounded-xl border border-rose-200 bg-rose-50/50 p-3 flex items-start space-x-2 text-rose-900">
            <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
            <p>
              Đơn hàng này có số tiền <strong>{order.amount_formatted}</strong> của khách hàng <strong>{order.user?.email}</strong>. 
              Sau khi từ chối, đơn hàng sẽ chuyển sang trạng thái <strong>Từ chối</strong>.
            </p>
          </div>

          <div className="space-y-1">
            <Label htmlFor="rejection_reason" className="text-xs font-semibold text-slate-700">
              Lý do từ chối thanh toán <span className="text-rose-600">*</span>
            </Label>
            <Textarea
              id="rejection_reason"
              rows={3}
              placeholder="Ví dụ: Chưa nhận được tiền trong tài khoản sau 24h, sai số tiền chuyển khoản..."
              {...register('rejection_reason')}
              disabled={isPending}
              className="text-xs resize-none"
            />
            {errors.rejection_reason && (
              <p className="text-xs text-rose-600">{errors.rejection_reason.message}</p>
            )}
          </div>

          <div className="space-y-1">
            <Label htmlFor="admin_note" className="text-xs font-semibold text-slate-700">
              Ghi chú nội bộ (tùy chọn)
            </Label>
            <Textarea
              id="admin_note"
              rows={2}
              placeholder="Ghi chú nội bộ cho ban quản trị..."
              {...register('admin_note')}
              disabled={isPending}
              className="text-xs resize-none"
            />
            {errors.admin_note && (
              <p className="text-xs text-rose-600">{errors.admin_note.message}</p>
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
              className="bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold"
            >
              {isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang từ chối...
                </>
              ) : (
                'Xác nhận từ chối đơn'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
