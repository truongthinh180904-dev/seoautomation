import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface MetricSummaryItem {
  current: number;
  previous: number;
  delta_percent?: number;
  delta_value?: number;
}

export interface SeoPerformanceSummary {
  clicks: MetricSummaryItem;
  impressions: MetricSummaryItem;
  ctr: MetricSummaryItem;
  position: MetricSummaryItem;
  sessions: MetricSummaryItem;
  active_users: MetricSummaryItem;
  engagement_rate: MetricSummaryItem;
  conversions: MetricSummaryItem;
}

export interface SeoTimeseriesPoint {
  date: string;
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
  sessions: number;
  active_users: number;
  engagement_rate: number;
  conversions: number;
}

export interface SeoPerformanceOverviewData {
  date_from: string;
  date_to: string;
  compare: boolean;
  compare_date_from: string | null;
  compare_date_to: string | null;
  summary: SeoPerformanceSummary;
  timeseries: SeoTimeseriesPoint[];
}

export interface SeoPerformanceOverviewResponse {
  success: boolean;
  data: SeoPerformanceOverviewData;
  message?: string;
}

export interface TopQueryItem {
  query: string;
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
}

export interface TopQueriesResponse {
  success: boolean;
  total: number;
  data: TopQueryItem[];
}

export interface TopPageItem {
  page_hash: string;
  page_url: string;
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
  sessions: number;
  active_users: number;
  engagement_rate: number;
  conversions: number;
}

export interface TopPagesResponse {
  success: boolean;
  total: number;
  data: TopPageItem[];
}

export interface SeoQueryParams {
  website_id?: number | null;
  date_from?: string;
  date_to?: string;
  compare?: boolean;
  device?: string;
  country?: string;
  limit?: number;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  search?: string;
}

export interface SyncNowPayload {
  website_id: number;
  days?: number;
  start_date?: string;
  end_date?: string;
}

export interface SyncNowResponse {
  success: boolean;
  message: string;
}

export interface WebsiteItem {
  id: number;
  name: string;
  domain: string;
  status?: string;
}

export interface WebsitesListResponse {
  data: WebsiteItem[];
}

export function useWebsitesList() {
  return useQuery<WebsitesListResponse, Error>({
    queryKey: ['websites', 'list'],
    queryFn: async () => {
      const { data } = await apiClient.get<WebsitesListResponse>('/websites');
      return data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useSeoPerformanceOverview(params: SeoQueryParams, enabled = true) {
  return useQuery<SeoPerformanceOverviewResponse, Error>({
    queryKey: ['analytics', 'seo', 'performance', params],
    queryFn: async () => {
      const { data } = await apiClient.get<SeoPerformanceOverviewResponse>(
        '/analytics/seo/performance',
        { params }
      );
      return data;
    },
    enabled,
    staleTime: 60 * 1000,
  });
}

export function useTopQueries(params: SeoQueryParams, enabled = true) {
  return useQuery<TopQueriesResponse, Error>({
    queryKey: ['analytics', 'seo', 'top-queries', params],
    queryFn: async () => {
      const { data } = await apiClient.get<TopQueriesResponse>(
        '/analytics/seo/top-queries',
        { params }
      );
      return data;
    },
    enabled,
    staleTime: 60 * 1000,
  });
}

export function useTopPages(params: SeoQueryParams, enabled = true) {
  return useQuery<TopPagesResponse, Error>({
    queryKey: ['analytics', 'seo', 'top-pages', params],
    queryFn: async () => {
      const { data } = await apiClient.get<TopPagesResponse>(
        '/analytics/seo/top-pages',
        { params }
      );
      return data;
    },
    enabled,
    staleTime: 60 * 1000,
  });
}

export function useTriggerSeoSync() {
  const queryClient = useQueryClient();

  return useMutation<SyncNowResponse, Error, SyncNowPayload>({
    mutationFn: async (payload: SyncNowPayload) => {
      const { data } = await apiClient.post<SyncNowResponse>(
        '/analytics/seo/sync-now',
        payload
      );
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['analytics', 'seo'] });
    },
  });
}
