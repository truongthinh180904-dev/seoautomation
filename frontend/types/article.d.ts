interface ArticlePipelineStep {
  key: string;
  label: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  message?: string | null;
  error?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  updated_at?: string | null;
}

interface ArticlePipelineStatus {
  current?: string | null;
  last_error?: string | null;
  updated_at?: string | null;
  steps?: Record<string, ArticlePipelineStep>;
}

interface Article {
  id: number;
  keyword_id: number;
  title: string;
  content?: string | null;
  seo_description?: string | null;
  review_token?: string | null;
  status: 'draft' | 'review' | 'approved' | 'rejected' | 'publishing' | 'published' | 'failed';
  word_count: number;
  seo_score: number | null;
  internal_links?: unknown[] | null;
  media_plan?: Record<string, unknown> | null;
  featured_image_url?: string | null;
  pipeline_status?: ArticlePipelineStatus | null;
  quality_report?: {
    seo_score?: number;
    readability_score?: number;
    media_score?: number;
    wordpress_readiness_score?: number;
    total_score?: number;
    warnings?: string[];
    blocking_errors?: string[];
    auto_fixable?: string[];
  } | null;
  ai_cost_usd: number;
  wordpress_site: {
    id: number;
    name: string;
    url: string;
  } | null;
  keyword: {
    id: number;
    keyword: string;
    search_intent?: string | null;
    target_word_count?: number | null;
    target_url?: string | null;
    canonical_url?: string | null;
    brief_notes?: string | null;
    must_include_points?: string[] | null;
    avoid_topics?: string[] | null;
    reference_urls?: string[] | null;
    competitor_urls_override?: string[] | null;
    raw_import_row?: Record<string, unknown> | null;
    meta?: Record<string, unknown> | null;
  } | null;
  user: {
    id: number;
    name: string;
  } | null;
  created_at: string;
  published_at: string | null;
}

interface ArticleUpdatePayload {
  title?: string;
  content?: string;
  seo_title?: string;
  seo_description?: string;
  status?: Article['status'];
  media_plan?: Record<string, unknown> | null;
  internal_links?: unknown[] | null;
}
