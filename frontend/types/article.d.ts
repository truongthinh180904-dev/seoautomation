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
}
