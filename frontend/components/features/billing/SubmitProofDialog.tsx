"use client";

import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Loader2, Upload, AlertCircle, FileImage } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useSubmitProof } from '@/hooks/useBilling';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

const submitProofSchema = z.object({
  customer_note: z.string().max(1000, 'Ghi chú không được vượt quá 1000 ký tự').optional(),
  proof_image_path: z
    .string()
    .optional()
    .refine((val) => {
      if (!val) return true;
      if (val.includes('..')) return false;
      return /^https?:\/\/.+/.test(val) || /^payment_proofs\/.+/.test(val);
    }, 'Đường dẫn minh chứng phải là URL http/https hợp lệ hoặc thuộc thư mục payment_proofs/ và không chứa ..'),
});

type SubmitProofFormData = z.infer<typeof submitProofSchema>;

interface SubmitProofDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  order: PaymentOrder | null;
}

export function SubmitProofDialog({
  open,
  onOpenChange,
  order,
}: SubmitProofDialogProps) {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const submitProofMutation = useSubmitProof();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SubmitProofFormData>({
    resolver: zodResolver(submitProofSchema),
    defaultValues: {
      customer_note: '',
      proof_image_path: '',
    },
  });

  useEffect(() => {
    if (open) {
      reset({
        customer_note: '',
        proof_image_path: '',
      });
      setSelectedFile(null);
      setFileError(null);
    }
  }, [open, order, reset]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) {
      setSelectedFile(null);
      return;
    }

    // Kiểm tra định dạng
    const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!validTypes.includes(file.type)) {
      setFileError('Vui lòng chọn tệp ảnh có định dạng JPG, PNG hoặc WebP.');
      setSelectedFile(null);
      return;
    }

    // Kiểm tra dung lượng (5MB)
    if (file.size > 5 * 1024 * 1024) {
      setFileError('Dung lượng tệp không được vượt quá 5MB.');
      setSelectedFile(null);
      return;
    }

    setFileError(null);
    setSelectedFile(file);
  };

  const onSubmit = async (data: SubmitProofFormData) => {
    if (!order) return;

    if (!selectedFile && !data.proof_image_path?.trim()) {
      setFileError('Vui lòng tải lên tệp ảnh biên lai hoặc cung cấp đường dẫn ảnh minh chứng.');
      return;
    }

    const formData = new FormData();
    if (selectedFile) {
      formData.append('proof_image', selectedFile);
    }
    if (data.proof_image_path?.trim()) {
      formData.append('proof_image_path', data.proof_image_path.trim());
    }
    if (data.customer_note?.trim()) {
      formData.append('customer_note', data.customer_note.trim());
    }

    try {
      await submitProofMutation.mutateAsync({
        orderId: order.id,
        formData,
      });

      toast.success('Đã gửi ảnh minh chứng thành công! Hệ thống đang chờ Admin xác nhận.');
      reset();
      setSelectedFile(null);
      setFileError(null);
      onOpenChange(false);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể gửi minh chứng. Vui lòng thử lại sau.'));
    }
  };

  const isPending = isSubmitting || submitProofMutation.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md bg-white">
        <DialogHeader>
          <DialogTitle className="text-lg font-bold text-slate-900">
            Tải ảnh minh chứng chuyển khoản
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            Đơn hàng #{order?.reference_code} — {order?.amount_formatted}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 py-2">
          {/* Chọn file ảnh */}
          <div className="space-y-2">
            <Label className="text-xs font-semibold text-slate-700">
              Ảnh chụp màn hình biên lai chuyển khoản (Tối đa 5MB)
            </Label>
            <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 transition-colors hover:bg-slate-100/70">
              <input
                type="file"
                id="proof_image_input"
                accept="image/jpeg,image/png,image/jpg,image/webp"
                onChange={handleFileChange}
                disabled={isPending}
                className="hidden"
              />
              <label
                htmlFor="proof_image_input"
                className="flex cursor-pointer flex-col items-center justify-center text-center"
              >
                {selectedFile ? (
                  <div className="flex items-center space-x-2 text-emerald-600">
                    <FileImage className="h-6 w-6" />
                    <span className="text-xs font-semibold">{selectedFile.name}</span>
                  </div>
                ) : (
                  <>
                    <Upload className="h-6 w-6 text-slate-400" />
                    <span className="mt-1 text-xs font-medium text-blue-600 hover:underline">
                      Bấm để chọn ảnh từ thiết bị
                    </span>
                    <span className="text-[11px] text-slate-400">JPG, PNG, WebP (Tối đa 5MB)</span>
                  </>
                )}
              </label>
            </div>
            {fileError && (
              <p className="flex items-center text-xs text-rose-600">
                <AlertCircle className="mr-1 h-3.5 w-3.5" />
                {fileError}
              </p>
            )}
          </div>

          <div className="relative flex items-center justify-center py-1">
            <div className="absolute inset-0 flex items-center">
              <span className="w-full border-t border-slate-200" />
            </div>
            <span className="relative bg-white px-2 text-[11px] uppercase tracking-wider text-slate-400">
              Hoặc nhập đường dẫn ảnh
            </span>
          </div>

          {/* Nhập URL ảnh */}
          <div className="space-y-1">
            <Label htmlFor="proof_image_path" className="text-xs font-medium text-slate-700">
              Đường dẫn URL ảnh minh chứng (tùy chọn)
            </Label>
            <Input
              id="proof_image_path"
              placeholder="https://example.com/receipt.jpg"
              {...register('proof_image_path')}
              disabled={isPending || selectedFile !== null}
              className="text-xs"
            />
            {errors.proof_image_path && (
              <p className="text-xs text-rose-600">{errors.proof_image_path.message}</p>
            )}
          </div>

          {/* Ghi chú */}
          <div className="space-y-1">
            <Label htmlFor="customer_note" className="text-xs font-medium text-slate-700">
              Ghi chú thêm cho Admin (tùy chọn)
            </Label>
            <Textarea
              id="customer_note"
              rows={3}
              placeholder="Ví dụ: Chuyển khoản từ Techcombank Nguyen Van A lúc 14h30..."
              {...register('customer_note')}
              disabled={isPending}
              className="text-xs resize-none"
            />
            {errors.customer_note && (
              <p className="text-xs text-rose-600">{errors.customer_note.message}</p>
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
              className="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold"
            >
              {isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang tải lên...
                </>
              ) : (
                'Gửi minh chứng'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
