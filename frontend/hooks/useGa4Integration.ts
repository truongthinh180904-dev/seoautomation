import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface Ga4Property {
  property_id: string;
  numeric_id: string;
  display_name: string;
  property_type: string;
  account_id?: string;
  account_name?: string;
  is_connected?: boolean;
}

export interface WebsiteGa4Connection {
  id: number;
  workspace_id: number;
  website_id: number;
  google_account_id: number;
  property_id: string;
  property_name?: string | null;
  account_id?: string | null;
  account_name?: string | null;
  measurement_id?: string | null;
  time_zone: string;
  currency_code: string;
  is_verified: boolean;
  last_synced_at?: string | null;
  sync_status: string;
  created_at?: string;
  updated_at?: string;
}

export interface Ga4PropertiesResponse {
  success: boolean;
  properties: Ga4Property[];
  connected_property?: WebsiteGa4Connection | null;
  needs_google_connect?: boolean;
  message?: string;
}

export interface ConnectGa4Payload {
  property_id: string;
  property_name?: string;
  account_id?: string;
  account_name?: string;
  measurement_id?: string;
  google_account_id?: number;
  time_zone?: string;
  currency_code?: string;
}

export interface Ga4TestResponse {
  success: boolean;
  message: string;
  data: {
    connected: boolean;
    is_verified: boolean;
    property_id: string;
    numeric_id: string;
    property_name: string;
    time_zone: string;
    currency_code: string;
    account_id?: string;
    last_synced_at?: string | null;
    sync_status?: string;
  };
}

export function useGa4Properties(websiteId: number, enabled = true) {
  return useQuery<Ga4PropertiesResponse, Error>({
    queryKey: ['websites', websiteId, 'ga4-properties'],
    queryFn: async () => {
      const { data } = await apiClient.get<Ga4PropertiesResponse>(
        `/websites/${websiteId}/ga4/properties`
      );
      return data;
    },
    enabled: Boolean(websiteId) && enabled,
    retry: 1,
  });
}

export function useGa4Actions(websiteId: number) {
  const queryClient = useQueryClient();

  const invalidateGa4 = () => {
    queryClient.invalidateQueries({ queryKey: ['websites', websiteId, 'ga4-properties'] });
    queryClient.invalidateQueries({ queryKey: ['websites', websiteId] });
    queryClient.invalidateQueries({ queryKey: ['websites'] });
  };

  const connectMutation = useMutation<
    { success: boolean; message: string; data: WebsiteGa4Connection },
    Error,
    ConnectGa4Payload
  >({
    mutationFn: async (payload: ConnectGa4Payload) => {
      const { data } = await apiClient.post<{ success: boolean; message: string; data: WebsiteGa4Connection }>(
        `/websites/${websiteId}/ga4/connect`,
        payload
      );
      return data;
    },
    onSuccess: () => {
      invalidateGa4();
    },
  });

  const disconnectMutation = useMutation<{ success: boolean; message: string }, Error, void>({
    mutationFn: async () => {
      const { data } = await apiClient.post<{ success: boolean; message: string }>(
        `/websites/${websiteId}/ga4/disconnect`
      );
      return data;
    },
    onSuccess: () => {
      invalidateGa4();
    },
  });

  const testConnectionMutation = useMutation<Ga4TestResponse, Error, void>({
    mutationFn: async () => {
      const { data } = await apiClient.post<Ga4TestResponse>(
        `/websites/${websiteId}/ga4/test`
      );
      return data;
    },
    onSuccess: () => {
      invalidateGa4();
    },
  });

  return {
    connect: connectMutation,
    disconnect: disconnectMutation,
    testConnection: testConnectionMutation,
    isLoading: connectMutation.isPending || disconnectMutation.isPending || testConnectionMutation.isPending,
  };
}
