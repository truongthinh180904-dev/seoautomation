import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

type MediaFilters = {
  campaign_id?: number;
  article_id?: number;
  status?: string;
  page?: number;
  per_page?: number;
};

export function useMediaAssets(filters: MediaFilters = {}) {
  return useQuery({
    queryKey: ['media-assets', filters],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiPaginatedResponse<MediaAsset>>('/media-assets', {
        params: filters,
      });
      return data;
    },
  });
}

export function useMediaAssetActions() {
  const queryClient = useQueryClient();

  return {
    retryDownload: useMutation({
      mutationFn: (id: number) => apiClient.post(`/media-assets/${id}/retry-download`),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['media-assets'] }),
    }),
    uploadToWordPress: useMutation({
      mutationFn: ({ id, wordpressSiteId }: { id: number; wordpressSiteId: number }) =>
        apiClient.post(`/media-assets/${id}/upload-wordpress`, { wordpress_site_id: wordpressSiteId }),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['media-assets'] }),
    }),
    createAsset: useMutation({
      mutationFn: (payload: Partial<MediaAsset> & { metadata?: Record<string, unknown> | null }) =>
        apiClient.post('/media-assets', payload),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['media-assets'] }),
    }),
    updateAsset: useMutation({
      mutationFn: ({ id, data }: { id: number; data: Partial<MediaAsset> }) =>
        apiClient.put(`/media-assets/${id}`, data),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['media-assets'] }),
    }),
  };
}
