import { useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export type SearchIntent = 'informational' | 'navigational' | 'commercial' | 'transactional';

export interface ResearchedKeyword {
  keyword: string;
  search_intent: SearchIntent;
  search_volume: number;
  difficulty: number;
  cpc: number;
  lsi_keywords?: string[];
  pillar_topic?: string;
  content_cluster?: string;
}

export interface KeywordResearchResult {
  seed_keyword: string;
  language: string;
  country: string;
  credits_deducted: number;
  total_keywords: number;
  keywords: ResearchedKeyword[];
}

export interface KeywordCluster {
  cluster_name: string;
  pillar_topic: string;
  primary_keyword: string;
  search_intent: SearchIntent;
  total_search_volume: number;
  avg_difficulty: number;
  keywords: ResearchedKeyword[];
}

export interface KeywordClusterResult {
  total_clusters: number;
  total_keywords: number;
  clusters: KeywordCluster[];
}

export interface KeywordResearchParams {
  query: string;
  language?: string;
  country?: string;
  limit?: number;
  website_id?: number;
  campaign_id?: number;
}

export interface SaveKeywordsParams {
  keywords: ResearchedKeyword[];
  campaign_id?: number;
  website_id?: number;
}

export function useKeywordResearch() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (params: KeywordResearchParams): Promise<KeywordResearchResult> => {
      const response = await apiClient.get<{ success: boolean; data: KeywordResearchResult }>(
        '/keywords/research',
        {
          params: {
            query: params.query,
            language: params.language || 'vi',
            country: params.country || 'vn',
            limit: params.limit || 50,
            website_id: params.website_id,
            campaign_id: params.campaign_id,
          },
        }
      );
      return response.data.data;
    },
    onSuccess: () => {
      // Làm mới số dư credit sau khi bị trừ 2 credits
      queryClient.invalidateQueries({ queryKey: ['billing', 'balance'] });
      queryClient.invalidateQueries({ queryKey: ['credit-balance'] });
    },
  });
}

export function useKeywordCluster() {
  return useMutation({
    mutationFn: async (params: { keywords: (string | ResearchedKeyword)[]; language?: string }): Promise<KeywordClusterResult> => {
      const response = await apiClient.post<{ success: boolean; data: KeywordClusterResult }>(
        '/keywords/cluster',
        {
          keywords: params.keywords,
          language: params.language || 'vi',
        }
      );
      return response.data.data;
    },
  });
}

export function useSaveKeywordsToCampaign() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (params: SaveKeywordsParams): Promise<{ saved_count: number }> => {
      const response = await apiClient.post<{ success: boolean; data: { saved_count: number } }>(
        '/keywords/research/save',
        params
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['keywords'] });
      queryClient.invalidateQueries({ queryKey: ['campaigns'] });
    },
  });
}
