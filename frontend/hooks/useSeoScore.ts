'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export type SeoScoreRating = 'excellent' | 'good' | 'needs_improvement' | 'poor';

export interface SeoCriterion {
  name: string;
  score: number;
  max_score: number;
  passed: boolean;
  status: 'passed' | 'warning' | 'failed';
  value?: string | number | null;
  details?: Record<string, unknown>;
  message: string;
  warning_message?: string | null;
  recommendations: string[];
  auto_fixable?: boolean;
}

export interface SeoHeadingsSummary {
  h1: number;
  h2: number;
  h3: number;
  h4?: number;
}

export interface SeoCriteriaCounts {
  passed: number;
  warning: number;
  failed: number;
  total: number;
}

export interface SeoSummary {
  word_count: number;
  character_count: number;
  reading_time_minutes: number;
  keyword: string;
  keyword_count: number;
  keyword_density: number;
  headings: SeoHeadingsSummary;
  images_count: number;
  images_with_alt_count: number;
  links_count: number;
  internal_links_count: number;
  external_links_count: number;
  faqs_count: number;
  has_schema: boolean;
  criteria_summary?: SeoCriteriaCounts;
}

export interface SeoAuditResult {
  total_score: number;
  seo_score: number;
  readability_score: number;
  status: SeoScoreRating;
  execution_time_ms: number;
  criteria: {
    h1_title: SeoCriterion;
    meta_description: SeoCriterion;
    heading_structure: SeoCriterion;
    keyword_density: SeoCriterion;
    image_alts: SeoCriterion;
    readability: SeoCriterion;
    links: SeoCriterion;
    faqs: SeoCriterion;
    schema_markup: SeoCriterion;
    [key: string]: SeoCriterion;
  };
  summary: SeoSummary;
  recommendations: string[];
  warnings: string[];
}

export interface AuditScorePayload {
  article_id?: number;
  content?: string;
  title?: string;
  seo_title?: string;
  seo_description?: string;
  focus_keyword?: string;
  faqs?: Array<{ question: string; answer: string }>;
  internal_links?: Array<{ url: string; anchor: string }>;
  persist?: boolean;
}

/**
 * Hook mutation gọi API Backend kiểm toán 9 tiêu chí SEO 100 điểm.
 * Endpoint: POST /api/v1/articles/{id}/score-audit hoặc POST /api/v1/articles/score-audit.
 * Thời gian thực thi < 50ms, 0 credit cost.
 */
export function useAuditArticleScore() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: AuditScorePayload): Promise<SeoAuditResult> => {
      const endpoint = payload.article_id
        ? `/articles/${payload.article_id}/score-audit`
        : '/articles/score-audit';

      const response = await apiClient.post<{
        success: boolean;
        message: string;
        data: SeoAuditResult;
      }>(endpoint, payload);

      return response.data.data;
    },
    onSuccess: (data, variables) => {
      if (variables.article_id && variables.persist !== false) {
        // Cập nhật lại cache bài viết trên Content Studio
        queryClient.invalidateQueries({
          queryKey: ['content-studio', variables.article_id],
        });
        queryClient.invalidateQueries({
          queryKey: ['articles', variables.article_id],
        });
      }
    },
  });
}

/**
 * Thuật toán tính nhanh điểm SEO ước lượng trên Client (0ms latency),
 * hỗ trợ phản hồi mượt mà cho Lexical Editor khi người dùng đang gõ phím.
 */
export function calculateClientQuickScore(params: {
  content: string;
  title?: string;
  seoTitle?: string;
  seoDescription?: string;
  keyword?: string;
}): {
  estimatedScore: number;
  wordCount: number;
  keywordDensity: number;
  keywordCount: number;
  h2Count: number;
  hasImages: boolean;
} {
  const cleanContent = params.content || '';
  const plainText = cleanContent.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

  const words = plainText ? plainText.split(/\s+/).filter(Boolean) : [];
  const wordCount = words.length;

  const kw = (params.keyword || '').trim().toLowerCase();
  let keywordCount = 0;
  let keywordDensity = 0;

  if (kw && wordCount > 0) {
    const textLower = plainText.toLowerCase();
    const parts = textLower.split(kw);
    keywordCount = Math.max(0, parts.length - 1);
    keywordDensity = Number(((keywordCount / wordCount) * 100).toFixed(2));
  }

  // Heading H2 count
  const h2Matches = cleanContent.match(/<h2[^>]*>/gi);
  const h2Count = h2Matches ? h2Matches.length : 0;

  // Has images
  const hasImages = /<img\s+[^>]*>/i.test(cleanContent);

  // Quick checks
  let checksPassed = 0;
  const totalChecks = 6;

  // 1. Title has keyword
  const effectiveTitle = (params.seoTitle || params.title || '').toLowerCase();
  if (kw && effectiveTitle.includes(kw)) checksPassed++;

  // 2. Title length ok (40-65)
  if (effectiveTitle.length >= 40 && effectiveTitle.length <= 65) checksPassed++;

  // 3. Meta description ok (120-160)
  const metaDesc = (params.seoDescription || '').trim();
  if (metaDesc.length >= 120 && metaDesc.length <= 165) checksPassed++;

  // 4. Word count >= 800
  if (wordCount >= 800) checksPassed++;
  else if (wordCount >= 400) checksPassed += 0.5;

  // 5. Keyword density in safe range (0.8% - 2.2%)
  if (keywordDensity >= 0.8 && keywordDensity <= 2.2) checksPassed++;
  else if (keywordDensity > 0.3 && keywordDensity <= 3.0) checksPassed += 0.5;

  // 6. Has H2 and images
  if (h2Count >= 2 && hasImages) checksPassed++;
  else if (h2Count >= 1 || hasImages) checksPassed += 0.5;

  const estimatedScore = Math.min(100, Math.max(0, Math.round((checksPassed / totalChecks) * 100)));

  return {
    estimatedScore,
    wordCount,
    keywordDensity,
    keywordCount,
    h2Count,
    hasImages,
  };
}
