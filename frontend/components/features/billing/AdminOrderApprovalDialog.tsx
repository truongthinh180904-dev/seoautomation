"use client";

import React, { useEffect } from 'react';
import Image from 'next/image';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Loader2, CheckCircle2, AlertCircle, Building2, User, FileImage } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useAdminApproveOrder } from '@/hooks/useAdminBilling';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

const approveOrderSchema = z.object({
  admin_note: z.string().max(1000, 'Ghi chú không được vượt quá 1000 ký tự').optional(),
});

type ApprovalFormData = z.infer<typeof approveOrderSchema>;

interface AdminOrderApprovalDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  order: PaymentOrder | null;
}

export function AdminOrderApprovalDialog({
  open,
  onOpenChange,
  order,
}: AdminOrderApprovalDialogProps) {
  const approveMutation = useAdminApproveOrder();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ApprovalFormData>({
    resolver: zodResolver(approveOrderSchema),
    defaultValues: {
      admin_note: '',
    },
  });

  useEffect(() => {
    if (open) {
      reset({ admin_note: '' });
    }
  }, [open, order, reset]);

  if (!order) return null;

  const onSubmit = async (data: ApprovalFormData) => {
    try {
      await approveMutation.mutateAsync({
        orderId: order.id,
        adminNote: data.admin_note?.trim() || undefined,
      });

      toast.success(`Đã duyệt đơn #${order.reference_code} và kích hoạt gói ${order.plan?.name} thành công!`);
      reset();
      onOpenChange(false);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể duyệt đơn hàng. Vui lòng kiểm tra lại.'));
    }
  };

  const isPending = isSubmitting || approveMutation.isPending;
  const proofUrl = order.proof_image_url || (order.proof_image_path ? `/storage/${order.proof_image_path}` : null);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg bg-white">
        <DialogHeader>
          <DialogTitle className="text-lg font-bold text-slate-900 flex items-center">
            <CheckCircle2 className="mr-2 h-5 w-5 text-emerald-600" />
            Duyệt đơn thanh toán #{order.reference_code}
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            Xác nhận số tiền chuyển khoản thực tế đã khớp với sao kê ngân hàng trước khi kích hoạt gói cước.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 py-2 text-xs">
          {/* Tóm tắt thông tin */}
          <div className="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
            <div>
              <span className="text-slate-400">Không gian làm việc:</span>
              <p className="font-semibold text-slate-900 flex items-center mt-0.5">
                <Building2 className="mr-1 h-3.5 w-3.5 text-slate-400" />
                {order.workspace?.name}
              </p>
            </div>
            <div>
              <span className="text-slate-400">Khách hàng:</span>
              <p className="font-semibold text-slate-900 flex items-center mt-0.5">
                <User className="mr-1 h-3.5 w-3.5 text-slate-400" />
                {order.user?.email}
              </p>
            </div>
            <div>
              <span className="text-slate-400">Gói cước đăng ký:</span>
              <p className="font-bold text-blue-700 mt-0.5">
                {order.plan?.name} ({order.plan?.credits_per_cycle.toLocaleString()} credit)
              </p>
            </div>
            <div>
              <span className="text-slate-400">Số tiền xác nhận:</span>
              <p className="font-black text-emerald-700 text-sm mt-0.5">
                {order.amount_formatted}
              </p>
            </div>
          </div>

          {/* Minh chứng khách hàng tải lên */}
          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-slate-700">
              Minh chứng khách hàng tải lên
            </Label>
            {proofUrl ? (
              <div className="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 flex flex-col items-center p-2">
                <div className="relative aspect-video w-full max-h-[220px]">
                  <Image
                    src={proofUrl}
                    alt="Biên lai chuyển khoản"
                    fill
                    className="object-contain rounded-lg"
                    unoptimized
                  />
                </div>
                <a
                  href={proofUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="mt-2 text-xs font-medium text-blue-600 hover:underline"
                >
                  Xem ảnh gốc kích thước đầy đủ ↗
                </a>
              </div>
            ) : (
              <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-center text-slate-400">
                <FileImage className="mx-auto h-6 w-6" />
                <span className="mt-1 block text-xs">Khách hàng chưa tải lên ảnh minh chứng</span>
              </div>
            )}
            {order.customer_note && (
              <p className="rounded-lg bg-amber-50 p-2 text-amber-900 border border-amber-200">
                <strong>Ghi chú của khách:</strong> {order.customer_note}
              </p>
            )}
          </div>

          {/* Ô nhập ghi chú Admin */}
          <div className="space-y-1.5">
            <Label htmlFor="admin_note" className="text-xs font-semibold text-slate-700">
              Ghi chú nội bộ Admin (tùy chọn)
            </Label>
            <Textarea
              id="admin_note"
              rows={3}
              placeholder="Ví dụ: Đã đối soát khớp sao kê Techcombank 450.000đ lúc 15h12..."
              {...register('admin_note')}
              disabled={isPending}
              className="text-xs resize-none"
            />
            {errors.admin_note && (
              <p className="text-xs text-rose-600">{errors.admin_note.message}</p>
            )}
          </div>

          <div className="flex items-start space-x-2 rounded-lg bg-emerald-50 p-2.5 text-[11px] text-emerald-900 border border-emerald-200">
            <AlertCircle className="h-4 w-4 shrink-0 text-emerald-700 mt-0.5" />
            <p>
              Khi duyệt, hệ thống sẽ tự động kích hoạt subscription, cấp credit ngay lập tức và ghi log kiểm toán Admin ID kèm số tiền {order.amount_formatted}.
            </p>
          </div>

          <DialogFooter className="pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isPending}
              className="text-xs"
            >
              Đóng
            </Button>
            <Button
              type="submit"
              disabled={isPending}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm"
            >
              {isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang duyệt & kích hoạt...
                </>
              ) : (
                'Xác nhận duyệt & Kích hoạt gói'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
