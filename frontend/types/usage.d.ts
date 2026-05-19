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
