interface Keyword {
  id: number;
  keyword: string;
  language: string;
  search_volume: number | null;
  difficulty: number | null;
  cpc: number | null;
  search_intent: string | null;
  priority: number;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'skipped';
  campaign?: {
    id: number;
    name: string;
    status: Campaign['status'];
  } | null;
  pillar_topic?: string | null;
  content_cluster?: string | null;
  funnel_stage?: string | null;
  target_word_count?: number | null;
  target_url?: string | null;
  canonical_url?: string | null;
  brief_notes?: string | null;
  must_include_points?: string[] | null;
  avoid_topics?: string[] | null;
  reference_urls?: string[] | null;
  competitor_urls_override?: string[] | null;
  raw_import_row?: Record<string, unknown> | null;
  template_version?: string | null;
  meta?: Record<string, unknown> | null;
  scheduled_at: string | null;
  processed_at: string | null;
  batch_id: string | null;
  wordpress_site: {
    id: number;
    name: string;
  } | null;
  article_id?: number | null;
  article?: {
    id: number;
    status: Article['status'];
    review_notes?: string | null;
    pipeline_status?: ArticlePipelineStatus | null;
  } | null;
  created_at: string;
}

interface KeywordImportResult {
  batch_id: string;
  total: number;
  imported: number;
  skipped: number;
  media_assets_created?: number;
  errors: string[];
}

interface KeywordImportPreviewRow {
  keyword: string;
  search_intent?: string | null;
  target_word_count?: number | null;
  campaign_id?: number | null;
}

interface KeywordImportPreviewResult {
  template_version: string;
  total_rows: number;
  valid_rows: number;
  invalid_rows: number;
  sample_rows: KeywordImportPreviewRow[];
  errors: Array<{
    row: number;
    column?: string;
    message: string;
  }>;
  warnings: Array<{
    row: number;
    message: string;
  }>;
  estimated_cost: {
    serper_calls: number;
    serper_cost_usd: number;
    gemini_tokens_estimated: number;
    gemini_cost_estimated_usd: number;
    total_estimated_usd: number;
  };
  quota_check: {
    articles_remaining: number | null;
    budget_remaining_usd: number | null;
    can_proceed: boolean;
    warning: string | null;
  };
}
