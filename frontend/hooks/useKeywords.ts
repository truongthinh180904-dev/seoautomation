import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export function useKeywords(page = 1, search = '') {
  return useQuery({
    queryKey: ['keywords', page, search],
    queryFn: async () => {
      const { data } = await apiClient.get('/keywords', { params: { page, search } });
      return data;
    },
    refetchInterval: 10000,
  });
}

export function useKeywordImport() {
  return useMutation({
    mutationFn: async (payload: { keywords: any[], tenant_id: number }) => {
      const { data } = await apiClient.post('/keywords/import', payload);
      return data;
    }
  });
}

export function useKeywordActions() {
  const queryClient = useQueryClient();

  const bulkDelete = useMutation({
    mutationFn: async (ids: number[]) => {
      await apiClient.post('/keywords/bulk-delete', { ids });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['keywords'] });
    }
  });

  return { bulkDelete };
}
