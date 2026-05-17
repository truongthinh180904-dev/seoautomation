"use client";

import { useState } from 'react';
import QueueStats from '@/components/features/queue/QueueStats';
import JobProgressBar from '@/components/features/queue/JobProgressBar';
import AgentPipelineView from '@/components/features/queue/AgentPipelineView';
import { useQueueStatus, useRetryJob } from '@/hooks/useQueueStatus';
import { RefreshCcw, AlertTriangle, RotateCcw } from 'lucide-react';
import { toast } from 'sonner';

export default function QueueMonitorPage() {
  const { data, isLoading, refetch, isFetching, dataUpdatedAt } = useQueueStatus();
  const { retry } = useRetryJob();
  const [retryingId, setRetryingId] = useState<number | null>(null);

  const stats = data?.stats ?? [];
  const failedJobs = data?.failed_jobs ?? [];

  const totalPending    = stats.reduce((s, q) => s + q.pending, 0);
  const totalProcessing = stats.reduce((s, q) => s + q.processing, 0);
  const totalFailed     = stats.reduce((s, q) => s + q.failed, 0);
  const grandTotal      = totalPending + totalProcessing + totalFailed;

  const handleRetry = async (jobId: number) => {
    setRetryingId(jobId);
    try {
      await retry(jobId);
      toast.success('Job đã được đẩy vào queue để thử lại!');
      refetch();
    } catch {
      toast.error('Không thể retry job này.');
    } finally {
      setRetryingId(null);
    }
  };

  const lastUpdated = dataUpdatedAt
    ? new Date(dataUpdatedAt).toLocaleTimeString('vi-VN')
    : '—';

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">Queue Monitor</h1>
          <p className="text-slate-500 mt-1 font-medium">
            Theo dõi luồng xử lý AI — tự làm mới mỗi 15 giây
            <span className="ml-2 text-slate-400">· Cập nhật lần cuối: {lastUpdated}</span>
          </p>
        </div>
        <button
          onClick={() => refetch()}
          disabled={isFetching}
          className="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl font-bold hover:bg-slate-50 transition-all active:scale-95 shadow-sm disabled:opacity-50"
        >
          <RefreshCcw className={`w-4 h-4 ${isFetching ? 'animate-spin' : ''}`} />
          {isFetching ? 'Đang làm mới...' : 'Làm mới ngay'}
        </button>
      </div>

      {/* Summary bar */}
      {grandTotal > 0 && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
          <h2 className="text-sm font-bold text-slate-500 uppercase tracking-widest mb-4">Tổng quan tất cả queues</h2>
          <JobProgressBar
            label="Toàn hệ thống"
            pending={totalPending}
            processing={totalProcessing}
            failed={totalFailed}
            total={grandTotal}
          />
        </div>
      )}

      {/* Per-queue stats */}
      <QueueStats stats={stats} isLoading={isLoading} />

      {/* Pipeline view */}
      <AgentPipelineView />

      {/* Failed Jobs */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/70">
          <h2 className="font-extrabold text-slate-900 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-red-500" />
            Failed Jobs
            {failedJobs.length > 0 && (
              <span className="ml-2 bg-red-100 text-red-600 text-xs font-bold px-2.5 py-0.5 rounded-full">
                {failedJobs.length}
              </span>
            )}
          </h2>
        </div>

        {failedJobs.length === 0 ? (
          <div className="text-center py-12 text-slate-400 font-medium">
            <div className="text-4xl mb-3">🎉</div>
            Không có job lỗi nào. Hệ thống đang chạy tốt!
          </div>
        ) : (
          <div className="divide-y divide-slate-100">
            {failedJobs.map((job) => (
              <div key={job.id} className="px-6 py-4 hover:bg-red-50/30 transition-colors group">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1.5">
                      <span className="font-bold text-slate-800 truncate">
                        {job.payload?.displayName || `Job #${job.id}`}
                      </span>
                      <span className="flex-shrink-0 text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold">
                        {job.queue}
                      </span>
                    </div>
                    <p className="text-xs font-mono text-red-600 bg-red-50 p-2 rounded-lg border border-red-100 line-clamp-2">
                      {job.exception}
                    </p>
                    <p className="text-xs text-slate-400 mt-2 font-medium">
                      Thất bại lúc: {new Date(job.failed_at).toLocaleString('vi-VN')}
                    </p>
                  </div>
                  <button
                    onClick={() => handleRetry(job.id)}
                    disabled={retryingId === job.id}
                    className="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 border border-blue-200 bg-blue-50 text-blue-700 rounded-xl text-sm font-bold hover:bg-blue-100 transition-all active:scale-95 disabled:opacity-50"
                  >
                    <RotateCcw className={`w-3.5 h-3.5 ${retryingId === job.id ? 'animate-spin' : ''}`} />
                    Retry
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
