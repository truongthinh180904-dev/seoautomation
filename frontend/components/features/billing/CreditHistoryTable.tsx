"use client";

import React from 'react';
import { Loader2, PlusCircle, MinusCircle, RefreshCcw, ShieldCheck } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface CreditHistoryTableProps {
  transactions: CreditTransactionItem[];
  isLoading?: boolean;
}

export function CreditHistoryTable({
  transactions = [],
  isLoading = false,
}: CreditHistoryTableProps) {
  if (isLoading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
        <Loader2 className="mx-auto h-6 w-6 animate-spin text-blue-600" />
        <p className="mt-2 text-xs text-slate-500">Đang tải lịch sử giao dịch credit...</p>
      </div>
    );
  }

  if (transactions.length === 0) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
        <p className="text-sm text-slate-500">Chưa có giao dịch biến động credit nào.</p>
      </div>
    );
  }

  const getTypeBadge = (type: string, label: string) => {
    switch (type) {
      case 'subscription_grant':
        return (
          <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-800 font-medium whitespace-nowrap">
            <RefreshCcw className="mr-1 h-3 w-3 text-emerald-600 inline" /> {label}
          </Badge>
        );
      case 'admin_adjust':
        return (
          <Badge variant="outline" className="border-purple-300 bg-purple-50 text-purple-800 font-medium whitespace-nowrap">
            <ShieldCheck className="mr-1 h-3 w-3 text-purple-600 inline" /> {label}
          </Badge>
        );
      case 'usage_deduction':
        return (
          <Badge variant="outline" className="border-slate-300 bg-slate-50 text-slate-700 font-medium whitespace-nowrap">
            <MinusCircle className="mr-1 h-3 w-3 text-slate-500 inline" /> {label}
          </Badge>
        );
      case 'refund':
        return (
          <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-800 font-medium whitespace-nowrap">
            <PlusCircle className="mr-1 h-3 w-3 text-blue-600 inline" /> {label}
          </Badge>
        );
      default:
        return (
          <Badge variant="outline" className="font-medium whitespace-nowrap">
            {label}
          </Badge>
        );
    }
  };

  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
      <Table>
        <TableHeader className="bg-slate-50/70">
          <TableRow>
            <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Thời gian</TableHead>
            <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Loại giao dịch</TableHead>
            <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Biến động</TableHead>
            <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Số dư sau</TableHead>
            <TableHead className="text-xs font-semibold text-slate-700 min-w-[200px]">Diễn giải</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {transactions.map((tx) => {
            const isPositive = tx.amount > 0;

            return (
              <TableRow key={tx.id} className="hover:bg-slate-50/50">
                <TableCell className="text-xs text-slate-500 whitespace-nowrap">
                  {new Date(tx.created_at).toLocaleString('vi-VN')}
                </TableCell>
                <TableCell className="whitespace-nowrap">
                  {getTypeBadge(tx.type, tx.type_label)}
                </TableCell>
                <TableCell className="whitespace-nowrap">
                  <span
                    className={`inline-flex items-center font-mono text-xs font-bold ${
                      isPositive ? 'text-emerald-600' : 'text-rose-600'
                    }`}
                  >
                    {isPositive ? `+${tx.amount.toLocaleString()}` : tx.amount.toLocaleString()} credit
                  </span>
                </TableCell>
                <TableCell className="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">
                  {tx.balance_after.toLocaleString()} credit
                </TableCell>
                <TableCell className="text-xs text-slate-600">
                  {tx.description || '—'}
                </TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </div>
  );
}
