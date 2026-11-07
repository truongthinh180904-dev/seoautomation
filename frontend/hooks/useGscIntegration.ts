import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface GscProperty {
  site_url: string;
  permission_level: string;
  property_type: 'url_prefix' | 'domain';
  is_domain: boolean;
  is_connected?: boolean;
}

export interface WebsiteGscConnection {
  id: number;
  workspace_id: number;
  website_id: number;
  google_account_id: number;
  site_url: string;
  property_type: 'url_prefix' | 'domain';
  permission_level?: string;
  is_verified: boolean;
  last_synced_at?: string | null;
  sync_status: string;
  created_at?: string;
  updated_at?: string;
}

export interface GscPropertiesResponse {
  success: boolean;
  properties: GscProperty[];
  connected_property?: WebsiteGscConnection | null;
  needs_google_connect?: boolean;
  message?: string;
}

export interface ConnectGscPayload {
  site_url: string;
  property_type?: 'url_prefix' | 'domain';
  google_account_id?: number;
}

export interface GscTestResponse {
  success: boolean;
  message: string;
  data: {
    connected: boolean;
    is_verified: boolean;
    site_url: string;
    permission_level: string;
    property_type: 'url_prefix' | 'domain';
    last_synced_at?: string | null;
    sync_status?: string;
  };
}

export function useGscProperties(websiteId: number, enabled = true) {
  return useQuery<GscPropertiesResponse, Error>({
    queryKey: ['websites', websiteId, 'gsc-properties'],
    queryFn: async () => {
      const { data } = await apiClient.get<GscPropertiesResponse>(
        `/websites/${websiteId}/gsc/properties`
      );
      return data;
    },
    enabled: Boolean(websiteId) && enabled,
    retry: 1,
  });
}

export function useGscActions(websiteId: number) {
  const queryClient = useQueryClient();

  const invalidateGsc = () => {
    queryClient.invalidateQueries({ queryKey: ['websites', websiteId, 'gsc-properties'] });
    queryClient.invalidateQueries({ queryKey: ['websites', websiteId] });
    queryClient.invalidateQueries({ queryKey: ['websites'] });
  };

  const connectMutation = useMutation<
    { success: boolean; message: string; data: WebsiteGscConnection },
    Error,
    ConnectGscPayload
  >({
    mutationFn: async (payload: ConnectGscPayload) => {
      const { data } = await apiClient.post<{ success: boolean; message: string; data: WebsiteGscConnection }>(
        `/websites/${websiteId}/gsc/connect`,
        payload
      );
      return data;
    },
    onSuccess: () => {
      invalidateGsc();
    },
  });

  const disconnectMutation = useMutation<{ success: boolean; message: string }, Error, void>({
    mutationFn: async () => {
      const { data } = await apiClient.post<{ success: boolean; message: string }>(
        `/websites/${websiteId}/gsc/disconnect`
      );
      return data;
    },
    onSuccess: () => {
      invalidateGsc();
    },
  });

  const testMutation = useMutation<GscTestResponse, Error, void>({
    mutationFn: async () => {
      const { data } = await apiClient.post<GscTestResponse>(
        `/websites/${websiteId}/gsc/test`
      );
      return data;
    },
    onSuccess: () => {
      invalidateGsc();
    },
  });

  return {
    connect: connectMutation,
    disconnect: disconnectMutation,
    testConnection: testMutation,
    isLoading: connectMutation.isPending || disconnectMutation.isPending || testMutation.isPending,
  };
}
