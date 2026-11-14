import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';
import { ENDPOINTS } from '@/lib/api/endpoints';

export function useAdminPaymentOrders(params?: { status?: string; search?: string; page?: number }) {
  return useQuery({
    queryKey: ['admin', 'payment-orders', params],
    queryFn: async () => {
      const searchParams = new URLSearchParams();
      if (params?.status) searchParams.set('status', params.status);
      if (params?.search) searchParams.set('search', params.search);
      if (params?.page) searchParams.set('page', params.page.toString());

      const url = `${ENDPOINTS.ADMIN.PAYMENT_ORDERS}?${searchParams.toString()}`;
      const { data } = await apiClient.get<ApiPaginatedResponse<PaymentOrder>>(url);
      return data;
    },
  });
}

export function useAdminWorkspaces(params?: { status?: string; search?: string; page?: number }) {
  return useQuery({
    queryKey: ['admin', 'workspaces', params],
    queryFn: async () => {
      const searchParams = new URLSearchParams();
      if (params?.status) searchParams.set('status', params.status);
      if (params?.search) searchParams.set('search', params.search);
      if (params?.page) searchParams.set('page', params.page.toString());

      const url = `${ENDPOINTS.ADMIN.WORKSPACES}?${searchParams.toString()}`;
      const { data } = await apiClient.get<ApiPaginatedResponse<AdminWorkspaceSummary>>(url);
      return data;
    },
  });
}

export function useAdminApproveOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ orderId, adminNote }: { orderId: number; adminNote?: string }) => {
      const { data } = await apiClient.post<ApiResponse<PaymentOrder>>(
        ENDPOINTS.ADMIN.APPROVE_ORDER(orderId),
        { admin_note: adminNote }
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'payment-orders'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'workspaces'] });
    },
  });
}

export function useAdminRejectOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      orderId,
      rejectionReason,
      adminNote,
    }: {
      orderId: number;
      rejectionReason: string;
      adminNote?: string;
    }) => {
      const { data } = await apiClient.post<ApiResponse<PaymentOrder>>(
        ENDPOINTS.ADMIN.REJECT_ORDER(orderId),
        {
          rejection_reason: rejectionReason,
          admin_note: adminNote,
        }
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'payment-orders'] });
    },
  });
}

export function useAdminAdjustCredit() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      workspaceId,
      amount,
      reason,
    }: {
      workspaceId: number;
      amount: number;
      reason: string;
    }) => {
      const { data } = await apiClient.post<ApiResponse<{ transaction: CreditTransactionItem }>>(
        ENDPOINTS.ADMIN.ADJUST_CREDIT(workspaceId),
        { amount, reason }
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'workspaces'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'payment-orders'] });
    },
  });
}

export function useAdminUpdateWorkspaceStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      workspaceId,
      status,
      reason,
    }: {
      workspaceId: number;
      status: 'active' | 'suspended';
      reason?: string;
    }) => {
      const { data } = await apiClient.post<ApiResponse<{ id: number; status: string }>>(
        ENDPOINTS.ADMIN.UPDATE_WORKSPACE_STATUS(workspaceId),
        { status, reason }
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'workspaces'] });
    },
  });
}
