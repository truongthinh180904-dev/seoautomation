import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

type CampaignFilters = {
  page?: number;
  status?: string;
  search?: string;
};

export function useCampaigns(filters: CampaignFilters = {}) {
  return useQuery({
    queryKey: ['campaigns', filters],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiPaginatedResponse<Campaign>>('/campaigns', {
        params: filters,
      });
      return data;
    },
  });
}

export function useCampaign(id: number) {
  return useQuery({
    queryKey: ['campaigns', id],
    enabled: Number.isFinite(id) && id > 0,
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<Campaign>>(`/campaigns/${id}`);
      return data;
    },
  });
}

export function useCampaignStats(id: number) {
  return useQuery({
    queryKey: ['campaigns', id, 'stats'],
    enabled: Number.isFinite(id) && id > 0,
    queryFn: async () => {
      const { data } = await apiClient.get<CampaignStats>(`/campaigns/${id}/stats`);
      return data;
    },
  });
}

export function useCampaignActions(id: number) {
  const queryClient = useQueryClient();

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['campaigns'] });
    queryClient.invalidateQueries({ queryKey: ['campaigns', id] });
    queryClient.invalidateQueries({ queryKey: ['campaigns', id, 'stats'] });
  };

  return {
    start: useMutation({
      mutationFn: () => apiClient.post(`/campaigns/${id}/start`),
      onSuccess: invalidate,
    }),
    pause: useMutation({
      mutationFn: () => apiClient.post(`/campaigns/${id}/pause`),
      onSuccess: invalidate,
    }),
    resume: useMutation({
      mutationFn: () => apiClient.post(`/campaigns/${id}/resume`),
      onSuccess: invalidate,
    }),
  };
}
