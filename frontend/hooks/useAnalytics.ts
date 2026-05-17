import { useQuery } from '@tanstack/react-query';
import { analyticsApi } from '@/lib/api/analytics';

export function useAnalyticsSummary(days = 30) {
  return useQuery({
    queryKey: ['analytics', 'summary', days],
    queryFn: () => analyticsApi.summary({ days }),
  });
}

export function useAICosts(days = 30) {
  return useQuery({
    queryKey: ['analytics', 'ai-costs', days],
    queryFn: () => analyticsApi.aiCosts({ days }),
  });
}

export function useKeywordsDaily(days = 30) {
  return useQuery({
    queryKey: ['analytics', 'keywords-daily', days],
    queryFn: () => analyticsApi.keywordsDaily({ days }),
  });
}

export function useFailingAgents(days = 30) {
  return useQuery({
    queryKey: ['analytics', 'failing-agents', days],
    queryFn: () => analyticsApi.failingAgents({ days }),
  });
}
