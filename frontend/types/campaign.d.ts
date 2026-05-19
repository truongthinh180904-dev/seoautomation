interface Campaign {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  language: string;
  brand_voice: string | null;
  target_audience: string | null;
  content_goal: string | null;
  default_word_count: number;
  default_category_ids: number[] | null;
  default_tag_names: string[] | null;
  approval_required: boolean;
  status:
    | 'draft'
    | 'imported'
    | 'planning'
    | 'ready'
    | 'processing'
    | 'reviewing'
    | 'publishing'
    | 'completed'
    | 'paused'
    | 'failed';
  keywords_count?: number;
  articles_count?: number;
  wordpress_site?: {
    id: number;
    name: string;
    url?: string | null;
  } | null;
  creator?: {
    id: number;
    name: string;
  } | null;
  created_at: string;
  updated_at: string;
}

interface CampaignStats {
  keywords: {
    total: number;
    by_status: Record<string, number>;
  };
  articles: {
    total: number;
    by_status: Record<string, number>;
    generated: number;
    in_review: number;
    approved: number;
    published: number;
    failed: number;
  };
  cost: {
    ai_cost_usd: number;
    average_seo_score: number;
  };
  coverage: {
    media_percent: number;
    internal_links_percent: number;
  };
  calendar: Array<{
    id: number;
    title: string;
    status: string;
    scheduled_publish_at: string;
  }>;
}
