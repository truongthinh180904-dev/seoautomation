import apiClient from './client';

export const analyticsApi = {
  summary: (params?: { days?: number; scope?: string }): Promise<AnalyticsSummaryResponse> =>
    apiClient.get('/analytics/summary', { params }).then(r => r.data),

  aiCosts: (params?: { days?: number }): Promise<{ data: Array<{ date: string; total_cost: number; calls: number; tokens: number }>; days: number }> =>
    apiClient.get('/analytics/ai-costs', { params }).then(r => r.data),

  keywordsDaily: (params?: { days?: number }) =>
    apiClient.get('/analytics/keywords-daily', { params }).then(r => r.data),

  failingAgents: (params?: { days?: number }) =>
    apiClient.get('/analytics/failing-agents', { params }).then(r => r.data),

  usage: (): Promise<UsageAnalytics> =>
    apiClient.get('/analytics/usage').then(r => r.data),

  platformOverview: (days = 30): Promise<AdminPlatformOverview> =>
    apiClient.get('/analytics/summary', { params: { scope: 'platform', days } }).then(r => r.data),
};
