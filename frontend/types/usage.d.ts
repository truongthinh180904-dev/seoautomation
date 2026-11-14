interface UsageAnalytics {
  subscription: {
    id: number;
    status: string;
    articles_used_this_month: number;
    ai_cost_used_this_month_usd: number;
    billing_cycle_start: string | null;
    billing_cycle_end: string | null;
    plan: {
      id: number;
      name: string;
      articles_per_month: number | null;
      ai_cost_budget_usd: number | null;
      campaigns_limit: number | null;
      wordpress_sites_limit: number | null;
      team_members_limit: number | null;
    };
  };
  quota: {
    canProceed: boolean;
    articlesExceeded: boolean;
    costExceeded: boolean;
    warningThreshold: boolean;
    articlesLimit: number | null;
    articlesUsed: number;
    costLimitUsd: number | null;
    costUsedUsd: number;
    message: string | null;
  };
  cost_by_job_type: Record<string, number>;
}

interface AnalyticsSummaryResponse {
  by_status: Record<string, number>;
  total: number;
  total_keywords?: number;
  total_campaigns?: number;
  avg_seo: number;
  avg_words: number;
  total_cost: number;
  credit_history_daily?: Array<{
    date: string;
    credits_used: number;
    tx_count: number;
  }>;
  ai_costs_by_website?: Array<{
    website_id: number;
    name: string;
    domain: string;
    cost_usd: number;
    calls: number;
    credits: number;
  }>;
}

interface AdminPlatformOverview {
  workspaces: {
    total: number;
    active: number;
    suspended: number;
  };
  users: {
    total: number;
  };
  websites: {
    total: number;
  };
  articles: {
    total: number;
  };
  keywords: {
    total: number;
  };
  orders: {
    total: number;
    pending: number;
    approved: number;
  };
  ai: {
    total_cost_usd: number;
    total_tokens: number;
    providers: Array<{
      provider: string;
      cost: number;
      calls: number;
    }>;
  };
  queue: {
    failed_tasks_count: number;
    recent_failed_tasks: Array<{
      id: number;
      workspace_id: number;
      task_type: string;
      error_message: string | null;
      created_at: string;
    }>;
  };
}
