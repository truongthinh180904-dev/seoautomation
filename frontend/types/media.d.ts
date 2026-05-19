interface MediaAsset {
  id: number;
  campaign_id: number | null;
  article_id: number | null;
  source_type: 'uploaded' | 'external_url' | 'ai_generated' | 'wordpress_existing';
  source_url: string | null;
  local_path: string | null;
  wordpress_media_id: number | null;
  wordpress_media_url: string | null;
  alt_text: string | null;
  caption: string | null;
  description: string | null;
  credit: string | null;
  status: 'pending' | 'downloaded' | 'uploaded' | 'failed';
  error_message: string | null;
  metadata: Record<string, unknown> | null;
  article?: {
    id: number;
    title: string;
  } | null;
  campaign?: {
    id: number;
    name: string;
  } | null;
  created_at: string;
  updated_at: string;
}
