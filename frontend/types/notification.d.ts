interface AppNotification {
  id: number;
  user_id: number | null;
  type: string;
  channel: 'dashboard' | 'email' | 'telegram';
  subject: string | null;
  message: string | null;
  data: Record<string, unknown> | null;
  status: 'pending' | 'sent' | 'failed';
  error_message: string | null;
  read_at: string | null;
  sent_at: string | null;
  created_at: string;
}
