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
  scheduled_at: string | null;
  processed_at: string | null;
  batch_id: string | null;
  wordpress_site: {
    id: number;
    name: string;
  } | null;
  created_at: string;
}

interface KeywordImportResult {
  batch_id: string;
  total: number;
  imported: number;
  skipped: number;
  errors: string[];
}
