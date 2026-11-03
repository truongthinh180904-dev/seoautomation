"use client";

import React, { useState } from 'react';
import { 
  Building2, 
  Search, 
  Filter, 
  Coins, 
  Lock, 
  Unlock, 
  Globe, 
  User, 
  Loader2, 
  RefreshCw,
  Calendar
} from 'lucide-react';
import { useAdminWorkspaces } from '@/hooks/useAdminBilling';
import { AdminCreditAdjustDialog } from '@/components/features/billing/AdminCreditAdjustDialog';
import { AdminWorkspaceStatusDialog } from '@/components/features/billing/AdminWorkspaceStatusDialog';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function AdminWorkspacesPage() {
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [page, setPage] = useState(1);

  const [adjustingWorkspace, setAdjustingWorkspace] = useState<AdminWorkspaceSummary | null>(null);
  const [statusWorkspace, setStatusWorkspace] = useState<AdminWorkspaceSummary | null>(null);

  const { data, isLoading, refetch } = useAdminWorkspaces({
    status: statusFilter === 'all' ? undefined : statusFilter,
    search: searchQuery.trim() || undefined,
    page,
  });

  const workspaces = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div className="space-y-6">
      {/* Title & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-slate-900">
            Quản lý Workspaces & Ví Credit
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Giám sát toàn bộ không gian làm việc, kiểm soát số dư credit, điều chỉnh hạn mức và khóa/mở khóa workspace.
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
            Làm mới
          </Button>
        </div>
      </div>

      {/* Filter & Search */}
      <Card className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-12">
          <div className="relative sm:col-span-8">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input
              placeholder="Tìm kiếm theo tên workspace, slug, email chủ sở hữu..."
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
              <option value="active">Đang hoạt động (Active)</option>
              <option value="suspended">Tạm ngưng / Bị khóa (Suspended)</option>
            </select>
          </div>
        </div>
      </Card>

      {/* Workspaces Table */}
      <Card className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="p-12 text-center">
            <Loader2 className="mx-auto h-7 w-7 animate-spin text-blue-600" />
            <p className="mt-2 text-xs text-slate-500">Đang tải danh sách không gian làm việc...</p>
          </div>
        ) : workspaces.length === 0 ? (
          <div className="p-12 text-center">
            <Building2 className="mx-auto h-8 w-8 text-slate-300" />
            <p className="mt-2 text-sm font-semibold text-slate-700">Không tìm thấy không gian làm việc nào</p>
            <p className="text-xs text-slate-400">Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc trạng thái.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader className="bg-slate-50/70">
                <TableRow>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Workspace</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Chủ sở hữu (Owner)</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Gói hiện tại</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Số dư Credit</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Số Website WP</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Trạng thái</TableHead>
                  <TableHead className="whitespace-nowrap text-xs font-semibold text-slate-700">Ngày tạo</TableHead>
                  <TableHead className="whitespace-nowrap text-right text-xs font-semibold text-slate-700">Thao tác</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {workspaces.map((ws) => {
                  const isSuspended = ws.status === 'suspended';

                  return (
                    <TableRow key={ws.id} className="hover:bg-slate-50/50">
                      <TableCell className="whitespace-nowrap">
                        <div className="flex flex-col">
                          <span className="text-xs font-bold text-slate-900">{ws.name}</span>
                          <span className="font-mono text-[11px] text-slate-400">slug: {ws.slug}</span>
                        </div>
                      </TableCell>

                      <TableCell className="text-xs whitespace-nowrap">
                        <div className="flex items-center space-x-1.5 text-slate-700">
                          <User className="h-3.5 w-3.5 text-slate-400" />
                          <span>{ws.owner?.email ?? '—'}</span>
                        </div>
                      </TableCell>

                      <TableCell className="text-xs whitespace-nowrap">
                        <Badge variant="outline" className="border-blue-200 bg-blue-50/50 text-blue-700 font-semibold">
                          {ws.current_plan ?? 'Free'}
                        </Badge>
                      </TableCell>

                      <TableCell className="text-xs font-mono font-bold whitespace-nowrap">
                        <span className="text-purple-700">
                          {ws.available_credits.toLocaleString()} credit
                        </span>
                      </TableCell>

                      <TableCell className="text-xs whitespace-nowrap">
                        <span className="inline-flex items-center text-slate-600 font-medium">
                          <Globe className="mr-1 h-3.5 w-3.5 text-slate-400" />
                          {ws.websites_count} site
                        </span>
                      </TableCell>

                      <TableCell className="whitespace-nowrap">
                        {isSuspended ? (
                          <Badge variant="outline" className="border-rose-300 bg-rose-50 text-rose-700 font-medium">
                            <Lock className="mr-1 h-3 w-3 inline" /> Tạm ngưng
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-700 font-medium">
                            <Unlock className="mr-1 h-3 w-3 inline" /> Hoạt động
                          </Badge>
                        )}
                      </TableCell>

                      <TableCell className="text-xs text-slate-500 whitespace-nowrap">
                        <div className="flex items-center">
                          <Calendar className="mr-1 h-3.5 w-3.5 text-slate-400" />
                          {new Date(ws.created_at).toLocaleDateString('vi-VN')}
                        </div>
                      </TableCell>

                      <TableCell className="text-right whitespace-nowrap">
                        <div className="flex items-center justify-end space-x-1.5">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setAdjustingWorkspace(ws)}
                            className="h-8 border-purple-200 bg-purple-50/30 text-purple-700 hover:bg-purple-100/60 text-xs font-medium px-2.5"
                          >
                            <Coins className="mr-1 h-3.5 w-3.5" /> Chỉnh Credit
                          </Button>

                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setStatusWorkspace(ws)}
                            className={`h-8 text-xs font-medium px-2.5 ${
                              isSuspended
                                ? 'border-emerald-300 text-emerald-700 hover:bg-emerald-50'
                                : 'border-rose-300 text-rose-700 hover:bg-rose-50'
                            }`}
                          >
                            {isSuspended ? (
                              <>
                                <Unlock className="mr-1 h-3.5 w-3.5" /> Mở khóa
                              </>
                            ) : (
                              <>
                                <Lock className="mr-1 h-3.5 w-3.5" /> Khóa
                              </>
                            )}
                          </Button>
                        </div>
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
              Tổng số {meta.total} không gian làm việc
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

      {/* Credit Adjustment Dialog */}
      <AdminCreditAdjustDialog
        open={adjustingWorkspace !== null}
        onOpenChange={(open) => !open && setAdjustingWorkspace(null)}
        workspace={adjustingWorkspace}
      />

      {/* Status Update (Lock / Unlock) Dialog */}
      <AdminWorkspaceStatusDialog
        open={statusWorkspace !== null}
        onOpenChange={(open) => !open && setStatusWorkspace(null)}
        workspace={statusWorkspace}
      />
    </div>
  );
}
