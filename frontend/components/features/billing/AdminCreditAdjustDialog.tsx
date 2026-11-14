"use client";

import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Loader2, Plus, Minus, AlertCircle, Coins } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useAdminAdjustCredit } from '@/hooks/useAdminBilling';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

const adjustCreditSchema = z.object({
  amount: z.coerce
    .number({ invalid_type_error: 'Vui lòng nhập số credit hợp lệ' })
    .int('Số credit phải là số nguyên')
    .min(1, 'Số lượng credit phải lớn hơn 0'),
  reason: z
    .string()
    .min(5, 'Lý do điều chỉnh phải có ít nhất 5 ký tự')
    .max(1000, 'Lý do không được vượt quá 1000 ký tự'),
});

type AdjustCreditFormData = z.infer<typeof adjustCreditSchema>;

interface AdminCreditAdjustDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  workspace: AdminWorkspaceSummary | null;
}

export function AdminCreditAdjustDialog({
  open,
  onOpenChange,
  workspace,
}: AdminCreditAdjustDialogProps) {
  const [direction, setDirection] = useState<'add' | 'deduct'>('add');
  const adjustMutation = useAdminAdjustCredit();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<AdjustCreditFormData>({
    resolver: zodResolver(adjustCreditSchema),
    defaultValues: {
      amount: 100,
      reason: '',
    },
  });

  useEffect(() => {
    if (open) {
      reset({
        amount: 100,
        reason: '',
      });
      setDirection('add');
    }
  }, [open, workspace, reset]);

  const watchAmount = watch('amount') || 0;
  const currentCredits = workspace?.available_credits || 0;
  const delta = direction === 'add' ? watchAmount : -watchAmount;
  const estimatedNewBalance = Math.max(0, currentCredits + delta);

  if (!workspace) return null;

  const onSubmit = async (data: AdjustCreditFormData) => {
    const finalAmount = direction === 'add' ? data.amount : -data.amount;

    try {
      await adjustMutation.mutateAsync({
        workspaceId: workspace.id,
        amount: finalAmount,
        reason: data.reason,
      });

      toast.success(
        `Đã điều chỉnh ${finalAmount > 0 ? '+' : ''}${finalAmount.toLocaleString()} credit cho workspace ${workspace.name}`
      );
      reset();
      onOpenChange(false);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể điều chỉnh credit. Vui lòng kiểm tra lại.'));
    }
  };

  const isPending = isSubmitting || adjustMutation.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md bg-white">
        <DialogHeader>
          <DialogTitle className="text-base font-bold text-slate-900 flex items-center">
            <Coins className="mr-2 h-5 w-5 text-purple-600" />
            Điều chỉnh credit thủ công
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            Workspace: <strong>{workspace.name}</strong> (#{workspace.id})
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 py-2 text-xs">
          {/* Thông tin số dư hiện tại & dự kiến */}
          <div className="grid grid-cols-2 gap-3 rounded-xl border border-purple-200 bg-purple-50/50 p-3">
            <div>
              <span className="text-slate-500">Số dư hiện tại:</span>
              <p className="font-mono text-base font-bold text-slate-900 mt-0.5">
                {currentCredits.toLocaleString()} credit
              </p>
            </div>
            <div>
              <span className="text-slate-500">Dự kiến sau điều chỉnh:</span>
              <p className="font-mono text-base font-bold text-purple-700 mt-0.5">
                {estimatedNewBalance.toLocaleString()} credit
              </p>
            </div>
          </div>

          {/* Chọn hướng điều chỉnh */}
          <div className="space-y-1.5">
            <Label className="text-xs font-semibold text-slate-700">Thao tác điều chỉnh</Label>
            <div className="grid grid-cols-2 gap-2">
              <Button
                type="button"
                variant={direction === 'add' ? 'default' : 'outline'}
                onClick={() => setDirection('add')}
                className={`h-9 text-xs font-semibold ${
                  direction === 'add'
                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                    : 'border-slate-200 text-slate-700'
                }`}
              >
                <Plus className="mr-1 h-3.5 w-3.5" /> Cộng thêm credit
              </Button>
              <Button
                type="button"
                variant={direction === 'deduct' ? 'default' : 'outline'}
                onClick={() => setDirection('deduct')}
                className={`h-9 text-xs font-semibold ${
                  direction === 'deduct'
                    ? 'bg-rose-600 hover:bg-rose-700 text-white'
                    : 'border-slate-200 text-slate-700'
                }`}
              >
                <Minus className="mr-1 h-3.5 w-3.5" /> Trừ bớt credit
              </Button>
            </div>
          </div>

          {/* Nhập số credit */}
          <div className="space-y-1">
            <Label htmlFor="amount" className="text-xs font-semibold text-slate-700">
              Số lượng credit cần {direction === 'add' ? 'cộng' : 'trừ'} <span className="text-rose-600">*</span>
            </Label>
            <Input
              id="amount"
              type="number"
              min="1"
              step="1"
              placeholder="VD: 500"
              {...register('amount')}
              disabled={isPending}
              className="text-xs"
            />
            {errors.amount && (
              <p className="text-xs text-rose-600">{errors.amount.message}</p>
            )}
          </div>

          {/* Lý do điều chỉnh */}
          <div className="space-y-1">
            <Label htmlFor="reason" className="text-xs font-semibold text-slate-700">
              Lý do điều chỉnh (ghi vào nhật ký kiểm toán) <span className="text-rose-600">*</span>
            </Label>
            <Textarea
              id="reason"
              rows={3}
              placeholder="Ví dụ: Tặng bù credit do sự cố server ngày 02/11, thu hồi credit cấp nhầm..."
              {...register('reason')}
              disabled={isPending}
              className="text-xs resize-none"
            />
            {errors.reason && (
              <p className="text-xs text-rose-600">{errors.reason.message}</p>
            )}
          </div>

          <div className="flex items-start space-x-2 rounded-lg bg-slate-100 p-2 text-[11px] text-slate-600">
            <AlertCircle className="h-3.5 w-3.5 shrink-0 text-slate-500 mt-0.5" />
            <p>
              Mọi thay đổi credit thủ công đều được lưu vết nguyên tử vào sổ cái `credit_transactions` và `audit_logs` của Admin.
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
              Hủy
            </Button>
            <Button
              type="submit"
              disabled={isPending}
              className="bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold"
            >
              {isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang xử lý...
                </>
              ) : (
                'Xác nhận điều chỉnh'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
