"use client";

import React, { useState } from 'react';
import Image from 'next/image';
import { Copy, Check, QrCode, AlertCircle, Building2, User, CreditCard } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';

interface BankTransferCardProps {
  order: PaymentOrder;
  onOpenSubmitProof?: () => void;
  onCancelOrder?: () => void;
  isCancelling?: boolean;
}

export function BankTransferCard({
  order,
  onOpenSubmitProof,
  onCancelOrder,
  isCancelling = false,
}: BankTransferCardProps) {
  const [copiedField, setCopiedField] = useState<string | null>(null);

  const bankInfo = order.bank_info_snapshot;

  if (!bankInfo) {
    return null;
  }

  const handleCopy = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    setCopiedField(label);
    toast.success(`Đã sao chép ${label}`);
    setTimeout(() => setCopiedField(null), 2000);
  };

  const isPending = order.status === 'pending_payment';
  const isAwaitingVerification = order.status === 'pending_verification';

  return (
    <Card className="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50/40 p-6 shadow-sm">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-amber-200/60 pb-4">
        <div>
          <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
            {order.status_label}
          </span>
          <h3 className="mt-1 text-lg font-bold text-slate-900">
            Thông tin chuyển khoản đơn hàng #{order.reference_code}
          </h3>
        </div>
        <div className="text-right">
          <div className="text-xs text-slate-500">Số tiền cần thanh toán</div>
          <div className="text-2xl font-black text-amber-700">
            {order.amount_formatted}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-12">
        {/* Cột trái: QR Code */}
        <div className="flex flex-col items-center justify-center rounded-xl border border-amber-200 bg-white p-4 md:col-span-4">
          {bankInfo.qr_payload ? (
            <div className="relative aspect-square w-full max-w-[220px]">
              <Image
                src={bankInfo.qr_payload}
                alt="VietQR Chuyển khoản"
                width={220}
                height={220}
                className="h-auto w-full rounded-lg object-contain"
                unoptimized
              />
            </div>
          ) : (
            <div className="flex aspect-square w-full max-w-[220px] flex-col items-center justify-center rounded-lg bg-slate-50 text-slate-400">
              <QrCode className="h-12 w-12 stroke-[1.5]" />
              <span className="mt-2 text-xs">Không có mã QR</span>
            </div>
          )}
          <p className="mt-2 text-center text-xs text-slate-500">
            Mở App ngân hàng bất kỳ để quét mã VietQR tự động điền số tiền & cú pháp
          </p>
        </div>

        {/* Cột phải: Thông tin ngân hàng & Cú pháp */}
        <div className="flex flex-col justify-between space-y-4 md:col-span-8">
          <div className="space-y-3">
            <div className="flex items-center justify-between rounded-lg border border-slate-200/80 bg-white p-3">
              <div className="flex items-center space-x-3">
                <Building2 className="h-5 w-5 text-slate-400" />
                <div>
                  <div className="text-xs text-slate-500">Ngân hàng nhận</div>
                  <div className="text-sm font-semibold text-slate-900">
                    {bankInfo.bank_name}
                  </div>
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-slate-200/80 bg-white p-3">
              <div className="flex items-center space-x-3">
                <CreditCard className="h-5 w-5 text-slate-400" />
                <div>
                  <div className="text-xs text-slate-500">Số tài khoản</div>
                  <div className="font-mono text-base font-bold text-slate-900">
                    {bankInfo.account_number}
                  </div>
                </div>
              </div>
              <Button
                size="sm"
                variant="outline"
                onClick={() => handleCopy(bankInfo.account_number, 'Số tài khoản')}
                className="h-8 border-slate-200 text-xs"
              >
                {copiedField === 'Số tài khoản' ? (
                  <Check className="mr-1 h-3.5 w-3.5 text-emerald-600" />
                ) : (
                  <Copy className="mr-1 h-3.5 w-3.5" />
                )}
                Sao chép
              </Button>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-slate-200/80 bg-white p-3">
              <div className="flex items-center space-x-3">
                <User className="h-5 w-5 text-slate-400" />
                <div>
                  <div className="text-xs text-slate-500">Chủ tài khoản</div>
                  <div className="text-sm font-semibold uppercase text-slate-900">
                    {bankInfo.account_holder}
                  </div>
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between rounded-lg border-2 border-dashed border-amber-400 bg-amber-100/50 p-3">
              <div>
                <div className="text-xs font-semibold uppercase tracking-wider text-amber-800">
                  Nội dung chuyển khoản (Bắt buộc giữ nguyên)
                </div>
                <div className="mt-0.5 font-mono text-lg font-extrabold text-blue-700">
                  {bankInfo.transfer_syntax}
                </div>
              </div>
              <Button
                size="sm"
                onClick={() => handleCopy(bankInfo.transfer_syntax, 'Nội dung chuyển khoản')}
                className="h-8 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm"
              >
                {copiedField === 'Nội dung chuyển khoản' ? (
                  <Check className="mr-1 h-3.5 w-3.5 text-white" />
                ) : (
                  <Copy className="mr-1 h-3.5 w-3.5" />
                )}
                Copy cú pháp
              </Button>
            </div>
          </div>

          <div className="flex items-start space-x-2 rounded-lg bg-amber-100/70 p-3 text-xs text-amber-900">
            <AlertCircle className="h-4 w-4 shrink-0 text-amber-700 mt-0.5" />
            <p>
              Hệ thống sẽ tự động đối soát và kích hoạt gói cước ngay khi Admin xác nhận giao dịch khớp sao kê. 
              Sau khi chuyển khoản, quý khách vui lòng bấm <strong>Tải minh chứng thanh toán</strong> để được duyệt nhanh nhất.
            </p>
          </div>

          <div className="flex flex-wrap items-center justify-end gap-3 pt-2">
            {isPending && onCancelOrder && (
              <Button
                variant="ghost"
                size="sm"
                onClick={onCancelOrder}
                disabled={isCancelling}
                className="text-xs text-slate-500 hover:text-rose-600"
              >
                Hủy đơn này
              </Button>
            )}

            {isPending && onOpenSubmitProof && (
              <Button
                size="sm"
                onClick={onOpenSubmitProof}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm"
              >
                Tải ảnh minh chứng chuyển khoản
              </Button>
            )}

            {isAwaitingVerification && (
              <div className="text-xs font-medium text-blue-700 flex items-center">
                <Check className="mr-1 h-4 w-4 text-blue-600" />
                Đã gửi minh chứng lúc {order.proof_submitted_at ? new Date(order.proof_submitted_at).toLocaleString('vi-VN') : ''}. Đang chờ Admin xác nhận sao kê.
              </div>
            )}
          </div>
        </div>
      </div>
    </Card>
  );
}
