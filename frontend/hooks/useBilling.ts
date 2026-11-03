import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient, { postForm } from '@/lib/api/client';
import { ENDPOINTS } from '@/lib/api/endpoints';

export function usePlans() {
  return useQuery({
    queryKey: ['billing', 'plans'],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<Plan[]>>(ENDPOINTS.BILLING.PLANS);
      return data.data;
    },
  });
}

export function useCurrentSubscription() {
  return useQuery({
    queryKey: ['billing', 'subscription'],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<SubscriptionCurrentResponse>>(ENDPOINTS.BILLING.SUBSCRIPTION);
      return data.data;
    },
  });
}

export function usePaymentOrders(page = 1) {
  return useQuery({
    queryKey: ['billing', 'orders', page],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiPaginatedResponse<PaymentOrder>>(`${ENDPOINTS.BILLING.ORDERS}?page=${page}`);
      return data;
    },
  });
}

export function useCreditHistory(page = 1) {
  return useQuery({
    queryKey: ['billing', 'credit-history', page],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiPaginatedResponse<CreditTransactionItem>>(`${ENDPOINTS.BILLING.CREDIT_HISTORY}?page=${page}`);
      return data;
    },
  });
}

export function useCreatePaymentOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: { plan_id: number; customer_note?: string }) => {
      const { data } = await apiClient.post<ApiResponse<PaymentOrder>>(ENDPOINTS.BILLING.ORDERS, payload);
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', 'orders'] });
    },
  });
}

export function useSubmitProof() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ orderId, formData }: { orderId: number; formData: FormData }) => {
      const data = await postForm<ApiResponse<PaymentOrder>>(ENDPOINTS.BILLING.SUBMIT_PROOF(orderId), formData);
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', 'orders'] });
      queryClient.invalidateQueries({ queryKey: ['billing', 'subscription'] });
    },
  });
}

export function useCancelOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ orderId, reason }: { orderId: number; reason?: string }) => {
      const { data } = await apiClient.post<ApiResponse<PaymentOrder>>(ENDPOINTS.BILLING.CANCEL_ORDER(orderId), { reason });
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', 'orders'] });
    },
  });
}
