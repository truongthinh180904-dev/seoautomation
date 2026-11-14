interface WordPressSite {
  id: number;
  name: string;
  url: string;
  api_url: string;
  username: string;
  default_author_id: number | null;
  default_category_id: number | null;
  default_status: 'draft' | 'publish';
  connection_status: string | null;
  last_connected_at: string | null;
  is_active: boolean;
  settings: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}

interface WordPressSitePayload {
  name: string;
  url: string;
  api_url?: string;
  username: string;
  app_password: string;
  default_author_id?: number | null;
  default_category_id?: number | null;
  default_status?: 'draft' | 'publish';
  is_active?: boolean;
}

interface WordPressConnectionResult {
  success: boolean;
  message?: string;
  user?: unknown;
  error?: string;
}
