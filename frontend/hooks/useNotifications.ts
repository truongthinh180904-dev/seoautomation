import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export function useNotifications(unread = false) {
  return useQuery({
    queryKey: ['notifications', unread],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiPaginatedResponse<AppNotification>>('/notifications', {
        params: { unread },
      });
      return data;
    },
  });
}

export function useNotificationActions() {
  const queryClient = useQueryClient();

  return {
    markRead: useMutation({
      mutationFn: (id: number) => apiClient.post(`/notifications/${id}/read`),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
    }),
  };
}
