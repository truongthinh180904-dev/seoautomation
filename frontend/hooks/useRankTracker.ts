import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface RankTrackingHistoryPoint {
  id: number;
  workspace_id?: number;
  website_id?: number;
  rank_tracked_keyword_id: number;
  check_date: string;
  position: number | null;
  found_url: string | null;
  serp_features?: {
    has_answer_box?: boolean;
    has_people_also_ask?: boolean;
    has_knowledge_graph?: boolean;
    has_top_stories?: boolean;
    has_sitelinks?: boolean;
  } | null;
  created_at?: string;
}

export interface TrackedKeywordItem {
  id: number;
  workspace_id: number;
  website_id: number;
  keyword_id: number | null;
  keyword: string;
  target_url: string | null;
  country: string;
  device: 'desktop' | 'mobile';
  tracking_frequency: 'daily' | 'weekly';
  current_position: number | null;
  previous_position: number | null;
  best_position: number | null;
  delta_rank: number | null;
  last_checked_at: string | null;
  is_active: boolean;
  history?: RankTrackingHistoryPoint[];
  created_at: string;
  updated_at: string;
}

export interface RankSummaryData {
  total_keywords: number;
  top_3: number;
  top_10: number;
  top_30: number;
  top_100: number;
  out_of_100: number;
  improved: number;
  declined: number;
  unchanged: number;
}

export interface TrackedKeywordsResponse {
  success: boolean;
  data: TrackedKeywordItem[];
  summary: RankSummaryData;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface KeywordHistoryResponse {
  success: boolean;
  keyword: TrackedKeywordItem;
  data: RankTrackingHistoryPoint[];
}

export interface CreateTrackedKeywordPayload {
  website_id: number;
  keyword: string;
  target_url?: string;
  country?: string;
  device?: 'desktop' | 'mobile';
  tracking_frequency?: 'daily' | 'weekly';
}

export interface RankQueryParams {
  website_id?: number;
  search?: string;
  device?: string;
  page?: number;
  per_page?: number;
}

export interface WebsiteItem {
  id: number;
  name: string;
  domain: string;
  url?: string;
  status?: string;
}

export interface WebsitesListResponse {
  data: WebsiteItem[];
}

export function useRankWebsitesList() {
  return useQuery<WebsitesListResponse, Error>({
    queryKey: ['websites', 'list'],
    queryFn: async () => {
      const { data } = await apiClient.get<WebsitesListResponse>('/websites');
      return data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useTrackedKeywords(params: RankQueryParams, enabled = true) {
  return useQuery<TrackedKeywordsResponse, Error>({
    queryKey: ['rank-tracker', 'keywords', params],
    queryFn: async () => {
      const { data } = await apiClient.get<TrackedKeywordsResponse>('/rank-tracker/keywords', {
        params,
      });
      return data;
    },
    enabled: enabled && Boolean(params.website_id),
    staleTime: 30 * 1000,
  });
}

export function useKeywordHistory(keywordId: number | null, days: number = 30) {
  return useQuery<KeywordHistoryResponse, Error>({
    queryKey: ['rank-tracker', 'history', keywordId, days],
    queryFn: async () => {
      if (!keywordId) {
        throw new Error('Keyword ID is required');
      }
      const { data } = await apiClient.get<KeywordHistoryResponse>(
        `/rank-tracker/keywords/${keywordId}/history`,
        {
          params: { days },
        }
      );
      return data;
    },
    enabled: Boolean(keywordId),
    staleTime: 60 * 1000,
  });
}

export function useAddTrackedKeyword() {
  const queryClient = useQueryClient();

  return useMutation<{ success: boolean; data: TrackedKeywordItem; message?: string }, Error, CreateTrackedKeywordPayload>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ success: boolean; data: TrackedKeywordItem; message?: string }>(
        '/rank-tracker/keywords',
        payload
      );
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rank-tracker', 'keywords'] });
    },
  });
}

export function useDeleteTrackedKeyword() {
  const queryClient = useQueryClient();

  return useMutation<{ success: boolean; message: string }, Error, number>({
    mutationFn: async (keywordId) => {
      const { data } = await apiClient.delete<{ success: boolean; message: string }>(
        `/rank-tracker/keywords/${keywordId}`
      );
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rank-tracker', 'keywords'] });
    },
  });
}

export function useRefreshTrackedKeyword() {
  const queryClient = useQueryClient();

  return useMutation<{ success: boolean; data: TrackedKeywordItem; message?: string }, Error, number>({
    mutationFn: async (keywordId) => {
      const { data } = await apiClient.post<{ success: boolean; data: TrackedKeywordItem; message?: string }>(
        `/rank-tracker/keywords/${keywordId}/refresh`
      );
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rank-tracker', 'keywords'] });
      queryClient.invalidateQueries({ queryKey: ['rank-tracker', 'history'] });
    },
  });
}
