import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface DiffSegment {
  type: 'unchanged' | 'added' | 'removed';
  value: string;
}

export interface InternalLinkItem {
  id?: number;
  target_id?: number;
  target_title?: string;
  target_url?: string;
  anchor_text?: string;
  relevance_score?: number;
}

export interface ContentDiffData {
  articleId: number;
  insightId?: number | null;
  originalTitle: string;
  proposedTitle: string;
  originalSeoTitle?: string | null;
  proposedSeoTitle?: string | null;
  originalSeoDescription?: string | null;
  proposedSeoDescription?: string | null;
  focusKeyword?: string | null;
  originalContent: string;
  proposedContent: string;
  wordCountBefore: number;
  wordCountAfter: number;
  wordCountDelta: number;
  scoreBefore: number;
  scoreAfter: number;
  scoreDelta: number;
  criteriaBefore?: Record<string, unknown>;
  criteriaAfter?: Record<string, unknown>;
  addedHeadings?: string[];
  missingIntentsAddressed?: string[];
  internalLinkSuggestions?: InternalLinkItem[];
  diffSegments?: DiffSegment[];
  optimizationNotes?: string | null;
  createdAt?: string | null;
}

export interface GeneratePlanPayload {
  articleId: number;
  insightId?: number;
  targetQueries?: string[];
  idempotencyKey?: string;
}

export interface ApplyOptimizationPayload {
  articleId: number;
  proposedTitle?: string;
  proposedContent?: string;
  proposedSeoTitle?: string;
  proposedSeoDescription?: string;
  insightId?: number;
}

export interface PushWordPressPayload {
  articleId: number;
  confirm: boolean;
  publishStatus?: string;
  insightId?: number;
}

export interface PushWordPressResult {
  post_id?: number;
  url?: string;
  edit_url?: string;
}

interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  required_credits?: number;
  available_credits?: number;
}

/**
 * Hook fetch bản xem trước Diff Preview (không trừ credit).
 */
export function useDiffPreview(articleId: number, enabled: boolean = true) {
  return useQuery<ContentDiffData, Error>({
    queryKey: ['article-diff-preview', articleId],
    queryFn: async () => {
      const response = await apiClient.get<ApiResponse<ContentDiffData>>(
        `/articles/${articleId}/diff-preview`
      );
      return response.data.data;
    },
    enabled: enabled && articleId > 0,
    staleTime: 60 * 1000,
  });
}

/**
 * Hook sinh kế hoạch tối ưu nội dung AI Content Refresh (trừ 2 credits).
 */
export function useGenerateOptimizationPlan() {
  const queryClient = useQueryClient();

  return useMutation<ContentDiffData, Error, GeneratePlanPayload>({
    mutationFn: async (payload: GeneratePlanPayload) => {
      const response = await apiClient.post<ApiResponse<ContentDiffData>>(
        `/articles/${payload.articleId}/optimization-plan`,
        {
          insight_id: payload.insightId,
          target_queries: payload.targetQueries,
          idempotency_key: payload.idempotencyKey,
        }
      );
      return response.data.data;
    },
    onSuccess: (data, variables) => {
      queryClient.setQueryData(['article-diff-preview', variables.articleId], data);
      queryClient.invalidateQueries({ queryKey: ['article', variables.articleId] });
      queryClient.invalidateQueries({ queryKey: ['billing', 'balance'] });
      queryClient.invalidateQueries({ queryKey: ['seo-insights'] });
    },
  });
}

/**
 * Hook áp dụng bản tối ưu vào bài viết, lưu phiên bản cũ (HasVersions).
 * Không tự publish lên WordPress.
 */
export function useApplyOptimization() {
  const queryClient = useQueryClient();

  return useMutation<{ article: unknown }, Error, ApplyOptimizationPayload>({
    mutationFn: async (payload: ApplyOptimizationPayload) => {
      const response = await apiClient.post<ApiResponse<{ article: unknown }>>(
        `/articles/${payload.articleId}/apply-optimization`,
        {
          proposed_title: payload.proposedTitle,
          proposed_content: payload.proposedContent,
          proposed_seo_title: payload.proposedSeoTitle,
          proposed_seo_description: payload.proposedSeoDescription,
          insight_id: payload.insightId,
        }
      );
      return response.data.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['article', variables.articleId] });
      queryClient.invalidateQueries({ queryKey: ['articles'] });
      queryClient.invalidateQueries({ queryKey: ['article-versions', variables.articleId] });
      queryClient.invalidateQueries({ queryKey: ['seo-insights'] });
    },
  });
}

/**
 * Hook Cổng Human-in-the-Loop: cập nhật bài viết lên WordPress sau khi xác nhận.
 */
export function usePushUpdateWordPress() {
  const queryClient = useQueryClient();

  return useMutation<PushWordPressResult, Error, PushWordPressPayload>({
    mutationFn: async (payload: PushWordPressPayload) => {
      const response = await apiClient.post<ApiResponse<PushWordPressResult>>(
        `/articles/${payload.articleId}/push-update-wordpress`,
        {
          confirm: payload.confirm,
          publish_status: payload.publishStatus,
          insight_id: payload.insightId,
        }
      );
      return response.data.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['article', variables.articleId] });
      queryClient.invalidateQueries({ queryKey: ['articles'] });
      queryClient.invalidateQueries({ queryKey: ['seo-insights'] });
    },
  });
}
