'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface OutlineItem {
  id: string;
  level: 1 | 2 | 3;
  title: string;
  target_words?: number;
  keywords?: string[];
}

export interface ArticleVersionItem {
  id: number;
  article_id: number;
  version_number: number;
  title: string;
  content: string;
  seo_title?: string | null;
  seo_description?: string | null;
  word_count: number;
  seo_score?: number | null;
  changed_by?: number | {
    id: number;
    name: string;
    email: string;
  } | null;
  change_reason?: string | null;
  created_at: string;
}

export interface ArticleStudioData {
  id: number;
  title: string;
  slug?: string;
  content: string;
  outline: OutlineItem[] | null;
  seo_title?: string | null;
  seo_description?: string | null;
  focus_keyword?: string | null;
  word_count: number;
  seo_score?: number | null;
  readability_score?: number | null;
  status: string;
  website?: {
    id: number;
    name: string;
    domain: string;
    url?: string;
  } | null;
  keyword?: {
    id: number;
    keyword: string;
  } | null;
  recent_versions?: ArticleVersionItem[];
  last_saved_at?: string | null;
}

export type AiAssistAction =
  | 'rewrite'
  | 'expand'
  | 'shorten'
  | 'change_tone'
  | 'add_example'
  | 'fix_grammar'
  | 'custom';

export interface AiAssistPayload {
  article_id: number;
  action: AiAssistAction;
  selected_text: string;
  tone?: string;
  instruction?: string;
  context_before?: string;
  context_after?: string;
  idempotency_key?: string;
}

export interface AiAssistResult {
  action: string;
  original_text: string;
  result_text: string;
  credits_deducted: number;
  word_count: number;
}

export interface AutosavePayload {
  article_id: number;
  content: string;
  title?: string;
  outline?: OutlineItem[];
  seo_title?: string;
  seo_description?: string;
  create_version?: boolean;
  version_reason?: string;
}

export interface AutosaveResult {
  article_id: number;
  word_count: number;
  saved_at: string;
  version_number: number;
  has_version_created: boolean;
  status: string;
}

export function useContentStudioArticle(articleId: number) {
  return useQuery({
    queryKey: ['content-studio', articleId],
    queryFn: async (): Promise<ArticleStudioData> => {
      const response = await apiClient.get<{ success: boolean; data: ArticleStudioData }>(
        `/content-studio/${articleId}`
      );
      return response.data.data;
    },
    enabled: Number.isInteger(articleId) && articleId > 0,
    staleTime: 1000 * 60 * 5, // 5 phút
  });
}

export function useContentStudioVersions(articleId: number) {
  return useQuery({
    queryKey: ['content-studio-versions', articleId],
    queryFn: async (): Promise<ArticleVersionItem[]> => {
      const response = await apiClient.get<{ success: boolean; data: ArticleVersionItem[] }>(
        `/content-studio/${articleId}/versions`
      );
      return response.data.data;
    },
    enabled: Number.isInteger(articleId) && articleId > 0,
  });
}

export function useAutosaveArticle() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: AutosavePayload): Promise<AutosaveResult> => {
      const response = await apiClient.post<{ success: boolean; data: AutosaveResult }>(
        '/content-studio/autosave',
        payload
      );
      return response.data.data;
    },
    onSuccess: (data) => {
      if (data.has_version_created) {
        queryClient.invalidateQueries({
          queryKey: ['content-studio-versions', data.article_id],
        });
      }
    },
  });
}

export function useAiCopilotAssist() {
  return useMutation({
    mutationFn: async (payload: AiAssistPayload): Promise<AiAssistResult> => {
      const response = await apiClient.post<{ success: boolean; data: AiAssistResult }>(
        '/content-studio/assist',
        payload
      );
      return response.data.data;
    },
  });
}
