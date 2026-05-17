import { useQuery } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface QueueStat {
  name: string;
  label: string;
  pending: number;
  processing: number;
  failed: number;
}

export interface FailedJob {
  id: number;
  queue: string;
  payload: { displayName?: string };
  exception: string;
  failed_at: string;
}

export interface PipelineStep {
  name: string;
  label: string;
  icon: string;
  count: number;
  status: 'done' | 'active' | 'pending' | 'failed';
}

export function useQueueStatus() {
  return useQuery({
    queryKey: ['queue-status'],
    queryFn: async () => {
      const { data } = await apiClient.get('/queue/status');
      return data as { stats: QueueStat[]; failed_jobs: FailedJob[] };
    },
    refetchInterval: 15000,
    // Return safe defaults so UI never breaks if endpoint isn't ready
    placeholderData: {
      stats: [
        { name: 'ai-research', label: 'AI Research', pending: 0, processing: 0, failed: 0 },
        { name: 'ai-writing', label: 'AI Writing', pending: 0, processing: 0, failed: 0 },
        { name: 'publishing', label: 'Publishing', pending: 0, processing: 0, failed: 0 },
        { name: 'notifications', label: 'Notifications', pending: 0, processing: 0, failed: 0 },
      ],
      failed_jobs: [],
    },
  });
}

export function useRetryJob() {
  return {
    retry: async (jobId: number) => {
      await apiClient.post(`/queue/retry/${jobId}`);
    },
  };
}
