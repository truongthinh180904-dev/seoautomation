"use client";

import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Eye, XCircle, UploadCloud, Loader2, Clock, CheckCircle2, AlertTriangle, Ban } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useCancelOrder } from '@/hooks/useBilling';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

const cancelOrderSchema = z.object({
  reason: z.string().max(500, 'Lý do hủy đơn không được vượt quá 500 ký tự').optional(),
});

type CancelOrderFormData = z.infer<typeof cancelOrderSchema>;

interface PaymentOrderListProps {
  orders: PaymentOrder[];
  onSelectOrder: (order: PaymentOrder) => void;
  onOpenSubmitProof: (order: PaymentOrder) => void;
  isLoading?: boolean;
}

export function PaymentOrderList({
  orders,
  onSelectOrder,
  onOpenSubmitProof,
  isLoading = false,
}: PaymentOrderListProps) {
  const [cancellingOrder, setCancellingOrder] = useState<PaymentOrder | null>(null);
  const cancelOrderMutation = useCancelOrder();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CancelOrderFormData>({
    resolver: zodResolver(cancelOrderSchema),
    defaultValues: {
      reason: '',
    },
  });

  useEffect(() => {
    if (cancellingOrder) {
      reset({ reason: '' });
    }
  }, [cancellingOrder, reset]);

  const onCancelSubmit = async (data: CancelOrderFormData) => {
    if (!cancellingOrder) return;

    try {
      await cancelOrderMutation.mutateAsync({
        orderId: cancellingOrder.id,
        reason: data.reason?.trim() || 'Khách hàng yêu cầu hủy đơn',
      });
      toast.success('Đã hủy đơn hàng thành công');
      setCancellingOrder(null);
      reset({ reason: '' });
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể hủy đơn hàng. Vui lòng thử lại sau.'));
    }
  };

  const getStatusBadge = (status: PaymentOrderStatusType, label: string) => {
    switch (status) {
      case 'pending_payment':
        return (
          <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-800 whitespace-nowrap font-medium">
            <Clock className="mr-1 h-3 w-3 text-amber-600 inline" /> {label}
          </Badge>
        );
      case 'pending_verification':
        return (
          <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-800 whitespace-nowrap font-medium">
            <Clock className="mr-1 h-3 w-3 text-blue-600 inline" /> {label}
          </Badge>
        );
      case 'approved':
        return (
          <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-800 whitespace-nowrap font-medium">
            <CheckCircle2 className="mr-1 h-3 w-3 text-emerald-600 inline" /> {label}
          </Badge>
        );
      case 'rejected':
        return (
          <Badge variant="outline" className="border-rose-300 bg-rose-50 text-rose-800 whitespace-nowrap font-medium">
            <AlertTriangle className="mr-1 h-3 w-3 text-rose-600 inline" /> {label}
          </Badge>
        );
      case 'cancelled':
        return (
          <Badge variant="outline" className="border-slate-300 bg-slate-100 text-slate-600 whitespace-nowrap font-medium">
            <Ban className="mr-1 h-3 w-3 text-slate-500 inline" /> {label}
          </Badge>
        );
      default:
        return (
          <Badge variant="outline" className="whitespace-nowrap font-medium">
            {label}
          </Badge>
        );
    }
  };

  if (isLoading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
        <Loader2 className="mx-auto h-6 w-6 animate-spin text-blue-600" />
        <p className="mt-2 text-xs text-slate-500">Đang tải danh sách đơn thanh toán...</p>
      </div>
    );
  }

  if (orders.length === 0) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
        <p className="text-sm text-slate-500">Chưa có đơn hàng thanh toán chuyển khoản nào.</p>
      </div>
    );
  }

  return (
    <>
      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <Table>
          <TableHeader className="bg-slate-50/70">
            <TableRow>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Mã tham chiếu</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Gói cước</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Số tiền</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Trạng thái</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Minh chứng</TableHead>
              <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Ngày tạo</TableHead>
              <TableHead className="whitespace-nowrap text-right text-xs font-semibold text-slate-700">Thao tác</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {orders.map((order) => {
              const isPending = order.status === 'pending_payment';
              const hasProof = !!order.proof_image_path || !!order.proof_image_url;

              return (
                <TableRow key={order.id} className="hover:bg-slate-50/50">
                  <TableCell className="font-mono text-xs font-bold text-blue-700 whitespace-nowrap">
                    {order.reference_code}
                  </TableCell>
                  <TableCell className="text-xs font-medium text-slate-900 whitespace-nowrap">
                    {order.plan?.name ?? '—'}
                  </TableCell>
                  <TableCell className="text-xs font-semibold text-slate-900 whitespace-nowrap">
                    {order.amount_formatted}
                  </TableCell>
                  <TableCell className="whitespace-nowrap">
                    {getStatusBadge(order.status, order.status_label)}
                  </TableCell>
                  <TableCell className="text-xs text-slate-500 whitespace-nowrap">
                    {hasProof ? (
                      <span className="inline-flex items-center text-emerald-600 font-medium">
                        <CheckCircle2 className="mr-1 h-3.5 w-3.5" /> Đã gửi
                      </span>
                    ) : (
                      <span className="text-slate-400">Chưa tải</span>
                    )}
                  </TableCell>
                  <TableCell className="text-xs text-slate-500 whitespace-nowrap">
                    {new Date(order.created_at).toLocaleString('vi-VN')}
                  </TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <div className="flex items-center justify-end space-x-1.5">
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => onSelectOrder(order)}
                        className="h-8 px-2 text-xs text-slate-600 hover:text-blue-600"
                        title="Xem chi tiết đơn"
                      >
                        <Eye className="h-3.5 w-3.5 mr-1" /> Chi tiết
                      </Button>

                      {isPending && (
                        <>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => onOpenSubmitProof(order)}
                            className="h-8 border-emerald-300 text-xs text-emerald-700 hover:bg-emerald-50"
                            title="Tải ảnh biên lai"
                          >
                            <UploadCloud className="h-3.5 w-3.5 mr-1" /> Tải minh chứng
                          </Button>
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setCancellingOrder(order)}
                            className="h-8 px-2 text-xs text-rose-600 hover:bg-rose-50"
                            title="Hủy đơn hàng"
                          >
                            <XCircle className="h-3.5 w-3.5 mr-1" /> Hủy
                          </Button>
                        </>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>

      {/* Dialog Hủy đơn */}
      <Dialog open={cancellingOrder !== null} onOpenChange={(open) => !open && setCancellingOrder(null)}>
        <DialogContent className="max-w-md bg-white">
          <DialogHeader>
            <DialogTitle className="text-base font-bold text-slate-900">
              Xác nhận hủy đơn hàng #{cancellingOrder?.reference_code}
            </DialogTitle>
            <DialogDescription className="text-xs text-slate-500">
              Bạn có chắc chắn muốn hủy đơn hàng chuyển khoản này không? Thao tác này không thể hoàn tác.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit(onCancelSubmit)} className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="cancel_reason" className="text-xs font-semibold text-slate-700">
                Lý do hủy đơn (tùy chọn)
              </Label>
              <Textarea
                id="cancel_reason"
                rows={3}
                placeholder="Ví dụ: Đổi gói dịch vụ khác, nhập sai thông tin..."
                {...register('reason')}
                disabled={cancelOrderMutation.isPending || isSubmitting}
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
                onClick={() => setCancellingOrder(null)}
                disabled={cancelOrderMutation.isPending || isSubmitting}
                className="text-xs"
              >
                Quay lại
              </Button>
              <Button
                type="submit"
                disabled={cancelOrderMutation.isPending || isSubmitting}
                className="bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold"
              >
                {cancelOrderMutation.isPending || isSubmitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Đang hủy...
                  </>
                ) : (
                  'Xác nhận hủy đơn'
                )}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}
