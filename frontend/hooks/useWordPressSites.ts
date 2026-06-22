import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export function useWordPressSites() {
  return useQuery({
    queryKey: ['wordpress-sites'],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiPaginatedResponse<WordPressSite>>('/wordpress-sites');
      return data;
    },
  });
}

export function useWordPressSiteActions() {
  const queryClient = useQueryClient();

  return {
    create: useMutation({
      mutationFn: async (payload: WordPressSitePayload) => {
        const { data } = await apiClient.post<ApiResponse<WordPressSite>>('/wordpress-sites', payload);
        return data;
      },
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['wordpress-sites'] }),
    }),
    test: useMutation({
      mutationFn: async (id: number) => {
        const { data } = await apiClient.post<WordPressConnectionResult>(`/wordpress-sites/${id}/test`);
        return data;
      },
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['wordpress-sites'] }),
    }),
    remove: useMutation({
      mutationFn: async (id: number) => {
        await apiClient.delete(`/wordpress-sites/${id}`);
      },
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['wordpress-sites'] }),
    }),
  };
}
