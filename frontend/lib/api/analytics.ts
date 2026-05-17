import apiClient from './client';

export const analyticsApi = {
  summary: (params?: { days?: number }) =>
    apiClient.get('/analytics/summary', { params }).then(r => r.data),

  aiCosts: (params?: { days?: number }) =>
    apiClient.get('/analytics/ai-costs', { params }).then(r => r.data),

  keywordsDaily: (params?: { days?: number }) =>
    apiClient.get('/analytics/keywords-daily', { params }).then(r => r.data),

  failingAgents: (params?: { days?: number }) =>
    apiClient.get('/analytics/failing-agents', { params }).then(r => r.data),
};
