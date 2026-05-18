import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient, { postForm } from '@/lib/api/client';

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
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append('file', file);

      return postForm<KeywordImportResult>('/keywords/import', formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['keywords'] });
    },
  });
}

export function useKeywordActions() {
  const queryClient = useQueryClient();

  const bulkDelete = useMutation({
    mutationFn: async (ids: number[]) => {
      await apiClient.post('/keywords/bulk-destroy', { ids });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['keywords'] });
    }
  });

  return { bulkDelete };
}
