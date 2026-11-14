import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export type InsightType =
  | 'ranking_drop'
  | 'clicks_drop'
  | 'high_imp_low_ctr'
  | 'traffic_decay'
  | 'cannibalization'
  | 'stale_content'
  | 'striking_distance'
  | 'high_traffic_low_engagement';

export type InsightSeverity = 'info' | 'warning' | 'critical';
export type InsightStatus = 'detected' | 'in_progress' | 'resolved' | 'dismissed';

export interface CompetingPageItem {
  url: string;
  avg_position: number;
  clicks: number;
  impressions: number;
}

export interface SeoInsightMetricsSnapshot {
  target_key?: string;
  scope?: string;
  keyword?: string;
  keyword_id?: number;
  current_position?: number | null;
  previous_position?: number | null;
  drop_amount?: number;
  dropped_out_of_top_100?: boolean;
  impressions?: number;
  clicks?: number;
  ctr?: number;
  ctr_percent?: number;
  avg_position?: number;
  page_url?: string;
  page_hash?: string;
  landing_page?: string;
  sessions?: number;
  active_users?: number;
  engagement_rate?: number;
  engagement_rate_percent?: number;
  avg_duration_seconds?: number;
  recent_clicks?: number;
  previous_clicks?: number;
  drop_percent?: number;
  decay_percent?: number;
  w1_clicks?: number;
  w2_clicks?: number;
  w3_clicks?: number;
  w4_clicks?: number;
  is_monotonic_decay?: boolean;
  competing_urls_count?: number;
  competing_pages?: CompetingPageItem[];
  opportunity_score?: number;
  article_id?: number | null;
  target_url?: string | null;
  country?: string;
  device?: string;
  [key: string]: unknown;
}

export interface SeoInsightItem {
  id: number;
  workspace_id: number;
  website_id: number;
  article_id: number | null;
  type: InsightType;
  severity: InsightSeverity;
  opportunity_score: number;
  title: string;
  description: string;
  metrics_snapshot: SeoInsightMetricsSnapshot;
  suggested_action: string;
  status: InsightStatus;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
  article?: {
    id: number;
    title: string;
    slug: string;
  } | null;
  website?: {
    id: number;
    name: string;
    domain: string;
  } | null;
}

export interface SeoInsightsSummary {
  total_active: number;
  critical_count: number;
  warning_count: number;
  info_count: number;
  opportunity_count: number;
  resolved_count: number;
  dismissed_count: number;
  avg_opportunity_score: number;
  by_type: Record<string, number>;
}

export interface SeoInsightsResponse {
  success: boolean;
  data: SeoInsightItem[];
  summary: SeoInsightsSummary;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface SeoInsightsParams {
  website_id?: number;
  status?: string;
  severity?: string;
  type?: string;
  article_id?: number;
  page?: number;
  per_page?: number;
}

/**
 * Hook to fetch paginated SEO intelligence insights.
 */
export function useSeoInsights(params: SeoInsightsParams = {}) {
  return useQuery<SeoInsightsResponse>({
    queryKey: ['seo-insights', params],
    queryFn: async () => {
      const response = await apiClient.get<SeoInsightsResponse>('/api/v1/seo-insights', {
        params,
      });
      return response.data;
    },
    enabled: params.website_id !== undefined ? params.website_id > 0 : true,
    staleTime: 60 * 1000,
  });
}

/**
 * Hook to fetch SEO intelligence summary metrics.
 */
export function useSeoInsightsSummary(websiteId?: number) {
  return useQuery<{ success: boolean; data: SeoInsightsSummary }>({
    queryKey: ['seo-insights-summary', websiteId],
    queryFn: async () => {
      const response = await apiClient.get<{ success: boolean; data: SeoInsightsSummary }>(
        '/api/v1/seo-insights/summary',
        {
          params: { website_id: websiteId },
        }
      );
      return response.data;
    },
    enabled: websiteId !== undefined ? websiteId > 0 : true,
    staleTime: 60 * 1000,
  });
}

/**
 * Hook to trigger instant heuristic scan (0 Credit).
 */
export function useTriggerSeoScan() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (websiteId: number) => {
      const response = await apiClient.post<{
        success: boolean;
        message: string;
        total_detected: number;
        data: SeoInsightItem[];
      }>('/api/v1/seo-insights/scan', {
        website_id: websiteId,
      });
      return response.data;
    },
    onSuccess: (_, websiteId) => {
      queryClient.invalidateQueries({ queryKey: ['seo-insights'] });
      queryClient.invalidateQueries({ queryKey: ['seo-insights-summary', websiteId] });
    },
  });
}

/**
 * Hook to dismiss an insight.
 */
export function useDismissInsight() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (insightId: number) => {
      const response = await apiClient.post<{
        success: boolean;
        message: string;
        data: SeoInsightItem;
      }>(`/api/v1/seo-insights/${insightId}/dismiss`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['seo-insights'] });
      queryClient.invalidateQueries({ queryKey: ['seo-insights-summary'] });
    },
  });
}

/**
 * Hook to apply action on an insight.
 */
export function useActionInsight() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ insightId, action }: { insightId: number; action: string }) => {
      const response = await apiClient.post<{
        success: boolean;
        message: string;
        data: SeoInsightItem;
      }>(`/api/v1/seo-insights/${insightId}/action`, {
        action,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['seo-insights'] });
      queryClient.invalidateQueries({ queryKey: ['seo-insights-summary'] });
    },
  });
}
