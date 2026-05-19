import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient, { postForm } from '@/lib/api/client';

export function useKeywords(page = 1, search = '', campaignId?: number) {
  return useQuery({
    queryKey: ['keywords', page, search, campaignId],
    queryFn: async () => {
      const { data } = await apiClient.get('/keywords', { params: { page, search, campaign_id: campaignId } });
      return data;
    },
    refetchInterval: 10000,
  });
}

export function useKeywordImport() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ file, campaignId }: { file: File; campaignId?: number }) => {
      const formData = new FormData();
      formData.append('file', file);
      if (campaignId) {
        formData.append('campaign_id', String(campaignId));
      }

      return postForm<KeywordImportResult>('/keywords/import', formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['keywords'] });
    },
  });
}

export function useKeywordImportPreview() {
  return useMutation({
    mutationFn: async ({ file, campaignId }: { file: File; campaignId?: number }) => {
      const formData = new FormData();
      formData.append('file', file);
      if (campaignId) {
        formData.append('campaign_id', String(campaignId));
      }

      return postForm<KeywordImportPreviewResult>('/keywords/import/preview', formData);
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
