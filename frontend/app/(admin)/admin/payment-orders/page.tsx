"use client";

import React, { useState } from 'react';
import { 
  CreditCard, 
  Search, 
  Filter, 
  CheckCircle2, 
  XCircle, 
  Clock, 
  AlertTriangle, 
  Ban, 
  Loader2, 
  RefreshCw,
  Building2,
  User,
  FileImage
} from 'lucide-react';
import { useAdminPaymentOrders } from '@/hooks/useAdminBilling';
import { AdminOrderApprovalDialog } from '@/components/features/billing/AdminOrderApprovalDialog';
import { AdminOrderRejectDialog } from '@/components/features/billing/AdminOrderRejectDialog';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function AdminPaymentOrdersPage() {
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [page, setPage] = useState(1);

  const [selectedOrderForApprove, setSelectedOrderForApprove] = useState<PaymentOrder | null>(null);
  const [selectedOrderForReject, setSelectedOrderForReject] = useState<PaymentOrder | null>(null);

  const { data, isLoading, refetch } = useAdminPaymentOrders({
    status: statusFilter === 'all' ? undefined : statusFilter,
    search: searchQuery.trim() || undefined,
    page,
  });

  const orders = data?.data ?? [];
  const meta = data?.meta;

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
          <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-800 whitespace-nowrap font-medium animate-pulse">
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
        return <Badge variant="outline" className="whitespace-nowrap font-medium">{label}</Badge>;
    }
  };

  return (
    <div className="space-y-6">
      {/* Title & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-slate-900">
            Quản lý Đơn thanh toán chuyển khoản
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Đối soát sao kê ngân hàng và kích hoạt gói subscription cho khách hàng toàn sàn.
          </p>
        </div>
        <div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => refetch()}
            disabled={isLoading}
            className="h-8 text-xs border-slate-200 font-medium"
          >
            <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
            Làm mới danh sách
          </Button>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <Card className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-12">
          <div className="relative sm:col-span-8">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input
              placeholder="Tìm kiếm theo mã tham chiếu (#SEOPRO_...), email khách hàng, hoặc tên workspace..."
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setPage(1);
              }}
              className="pl-9 text-xs"
            />
          </div>

          <div className="sm:col-span-4 flex items-center space-x-2">
            <Filter className="h-4 w-4 text-slate-400 shrink-0" />
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              <option value="all">Tất cả trạng thái</option>
              <option value="pending_verification">Chờ xác nhận (Ưu tiên duyệt)</option>
              <option value="pending_payment">Chờ khách chuyển khoản</option>
              <option value="approved">Đã duyệt thành công</option>
              <option value="rejected">Đã từ chối</option>
              <option value="cancelled">Khách đã hủy</option>
            </select>
          </div>
        </div>
      </Card>

      {/* Orders Table */}
      <Card className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="p-12 text-center">
            <Loader2 className="mx-auto h-7 w-7 animate-spin text-blue-600" />
            <p className="mt-2 text-xs text-slate-500">Đang tải danh sách đơn hàng...</p>
          </div>
        ) : orders.length === 0 ? (
          <div className="p-12 text-center">
            <CreditCard className="mx-auto h-8 w-8 text-slate-300" />
            <p className="mt-2 text-sm font-semibold text-slate-700">Không tìm thấy đơn hàng nào</p>
            <p className="text-xs text-slate-400">Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc trạng thái.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader className="bg-slate-50/70">
                <TableRow>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Mã đơn</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Không gian làm việc</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Khách hàng</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Gói đăng ký</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Số tiền</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Trạng thái</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Minh chứng</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Ngày tạo</TableHead>
                  <TableHead className="whitespace-nowrap text-right text-xs font-semibold text-slate-700">Thao tác</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orders.map((order) => {
                  const canReview = order.status === 'pending_payment' || order.status === 'pending_verification';
                  const proofUrl = order.proof_image_url || (order.proof_image_path ? `/storage/${order.proof_image_path}` : null);

                  return (
                    <TableRow key={order.id} className="hover:bg-slate-50/50">
                      <TableCell className="font-mono text-xs font-bold text-blue-700 whitespace-nowrap">
                        {order.reference_code}
                      </TableCell>

                      <TableCell className="text-xs whitespace-nowrap">
                        <div className="flex items-center space-x-1.5 font-medium text-slate-900">
                          <Building2 className="h-3.5 w-3.5 text-slate-400" />
                          <span>{order.workspace?.name ?? '—'}</span>
                        </div>
                      </TableCell>

                      <TableCell className="text-xs whitespace-nowrap">
                        <div className="flex items-center space-x-1.5 text-slate-600">
                          <User className="h-3.5 w-3.5 text-slate-400" />
                          <span>{order.user?.email ?? '—'}</span>
                        </div>
                      </TableCell>

                      <TableCell className="text-xs font-semibold text-slate-800 whitespace-nowrap">
                        {order.plan?.name ?? '—'}
                      </TableCell>

                      <TableCell className="text-xs font-mono font-bold text-slate-900 whitespace-nowrap">
                        {order.amount_formatted}
                      </TableCell>

                      <TableCell className="whitespace-nowrap">
                        {getStatusBadge(order.status, order.status_label)}
                      </TableCell>

                      <TableCell className="whitespace-nowrap text-xs">
                        {proofUrl ? (
                          <a
                            href={proofUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center text-blue-600 hover:underline font-medium"
                          >
                            <FileImage className="mr-1 h-3.5 w-3.5" /> Xem ảnh
                          </a>
                        ) : (
                          <span className="text-slate-400">Chưa có</span>
                        )}
                      </TableCell>

                      <TableCell className="text-xs text-slate-500 whitespace-nowrap">
                        {new Date(order.created_at).toLocaleString('vi-VN')}
                      </TableCell>

                      <TableCell className="text-right whitespace-nowrap">
                        {canReview ? (
                          <div className="flex items-center justify-end space-x-1.5">
                            <Button
                              size="sm"
                              onClick={() => setSelectedOrderForApprove(order)}
                              className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 shadow-xs"
                            >
                              <CheckCircle2 className="mr-1 h-3.5 w-3.5" /> Duyệt đơn
                            </Button>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => setSelectedOrderForReject(order)}
                              className="h-8 border-rose-300 text-rose-700 hover:bg-rose-50 text-xs font-medium px-2.5"
                            >
                              <XCircle className="mr-1 h-3.5 w-3.5" /> Từ chối
                            </Button>
                          </div>
                        ) : (
                          <span className="text-xs text-slate-400 italic">Đã xử lý</span>
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </div>
        )}

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-slate-100 p-4">
            <span className="text-xs text-slate-500">
              Tổng số {meta.total} đơn hàng
            </span>
            <div className="flex items-center space-x-2">
              <Button
                size="sm"
                variant="outline"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                className="h-8 text-xs"
              >
                Trang trước
              </Button>
              <span className="text-xs text-slate-600 font-medium">
                Trang {page} / {meta.last_page}
              </span>
              <Button
                size="sm"
                variant="outline"
                onClick={() => setPage((p) => p + 1)}
                disabled={page >= meta.last_page}
                className="h-8 text-xs"
              >
                Trang sau
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Approval Dialog */}
      <AdminOrderApprovalDialog
        open={selectedOrderForApprove !== null}
        onOpenChange={(open) => !open && setSelectedOrderForApprove(null)}
        order={selectedOrderForApprove}
      />

      {/* Rejection Dialog */}
      <AdminOrderRejectDialog
        open={selectedOrderForReject !== null}
        onOpenChange={(open) => !open && setSelectedOrderForReject(null)}
        order={selectedOrderForReject}
      />
    </div>
  );
}
