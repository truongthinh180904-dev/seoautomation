'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import {
  CheckCircle2,
  AlertCircle,
  Clock,
  RotateCcw,
  XCircle,
  ExternalLink,
  Loader2,
  ChevronDown,
  ChevronUp,
  FileText,
  Sparkles,
  Zap,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import apiClient from '@/lib/api/client';
import { cn } from '@/lib/utils';
import { useAuthStore } from '@/stores/authStore';
import { toast } from 'sonner';

export type TaskState = 'pending' | 'running' | 'success' | 'failed' | 'cancelled';

export interface TaskArticleMeta {
  id: number;
  title: string;
  status?: string;
  slug?: string;
}

export interface TaskKeywordMeta {
  id: number;
  keyword: string;
}

export interface TaskCampaignMeta {
  id: number;
  name: string;
}

export interface TaskStreamData {
  id?: number;
  uuid: string;
  type: string;
  status: TaskState;
  status_label: string;
  progress: number;
  current_step: string;
  credits_reserved?: number;
  credits_settled?: number;
  attempts?: number;
  max_attempts?: number;
  error_code?: string | null;
  error_message?: string | null;
  payload?: Record<string, unknown>;
  can_resume?: boolean;
  can_retry?: boolean;
  is_finished?: boolean;
  started_at?: string | null;
  finished_at?: string | null;
  cancelled_at?: string | null;
  article?: TaskArticleMeta | null;
  keyword?: TaskKeywordMeta | null;
  campaign?: TaskCampaignMeta | null;
}

export interface ChatTaskProgressWidgetProps {
  taskUuid: string;
  initialData?: Partial<TaskStreamData>;
  mode?: 'sse' | 'polling';
  pollIntervalMs?: number;
  onCompleted?: (task: TaskStreamData) => void;
  onError?: (error: string) => void;
  onCancelled?: (task: TaskStreamData) => void;
  className?: string;
  compact?: boolean;
}

const DEFAULT_STEPS = [
  { minProgress: 0, label: 'Khởi tạo tác vụ & nạp dữ liệu' },
  { minProgress: 20, label: 'Nghiên cứu SERP Top 3 & Lập dàn ý' },
  { minProgress: 45, label: 'AI viết nội dung chuẩn Brand Voice' },
  { minProgress: 75, label: 'Xử lý hình ảnh 3 tầng & nén WebP' },
  { minProgress: 90, label: 'Chấm điểm SEO 100 điểm & hoàn tất' },
];

export function ChatTaskProgressWidget({
  taskUuid,
  initialData,
  mode = 'sse',
  pollIntervalMs = 2000,
  onCompleted,
  onError,
  onCancelled,
  className,
  compact = false,
}: ChatTaskProgressWidgetProps) {
  const [task, setTask] = useState<TaskStreamData>({
    uuid: taskUuid,
    type: initialData?.type || 'full_article_pipeline',
    status: initialData?.status || 'running',
    status_label: initialData?.status_label || 'Đang xử lý',
    progress: initialData?.progress ?? 5,
    current_step: initialData?.current_step || 'Đang kết nối hệ thống hàng đợi...',
    credits_reserved: initialData?.credits_reserved ?? 0,
    credits_settled: initialData?.credits_settled ?? 0,
    attempts: initialData?.attempts ?? 1,
    max_attempts: initialData?.max_attempts ?? 3,
    error_code: initialData?.error_code || null,
    error_message: initialData?.error_message || null,
    payload: initialData?.payload || {},
    can_resume: initialData?.can_resume ?? false,
    can_retry: initialData?.can_retry ?? false,
    is_finished: initialData?.is_finished ?? false,
    started_at: initialData?.started_at || null,
    finished_at: initialData?.finished_at || null,
    cancelled_at: initialData?.cancelled_at || null,
    article: initialData?.article || null,
    keyword: initialData?.keyword || null,
    campaign: initialData?.campaign || null,
  });

  const [showDetails, setShowDetails] = useState<boolean>(!compact);
  const [isResuming, setIsResuming] = useState<boolean>(false);
  const [isCancelling, setIsCancelling] = useState<boolean>(false);
  const [elapsedSeconds, setElapsedSeconds] = useState<number>(0);

  const onCompletedRef = useRef(onCompleted);
  const onErrorRef = useRef(onError);
  const onCancelledRef = useRef(onCancelled);

  useEffect(() => {
    onCompletedRef.current = onCompleted;
    onErrorRef.current = onError;
    onCancelledRef.current = onCancelled;
  }, [onCompleted, onError, onCancelled]);

  // Elapsed timer
  useEffect(() => {
    if (task.is_finished || ['success', 'failed', 'cancelled'].includes(task.status)) {
      return;
    }

    const timer = setInterval(() => {
      setElapsedSeconds((prev) => prev + 1);
    }, 1000);

    return () => clearInterval(timer);
  }, [task.is_finished, task.status]);

  const formatElapsed = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  // Helper xử lý task update
  const handleTaskUpdate = useCallback((updated: TaskStreamData) => {
    setTask(updated);

    if (updated.status === 'success') {
      onCompletedRef.current?.(updated);
    } else if (updated.status === 'failed') {
      onErrorRef.current?.(updated.error_message || 'Tác vụ đã thất bại.');
    } else if (updated.status === 'cancelled') {
      onCancelledRef.current?.(updated);
    }
  }, []);

  // Polling fallback
  const fetchPollingStatus = useCallback(async () => {
    try {
      const response = await apiClient.get<{ success: boolean; data: TaskStreamData }>(
        `/chatbot/tasks/${taskUuid}/status`
      );
      if (response.data?.data) {
        handleTaskUpdate(response.data.data);
      }
    } catch {
      // Ignored non-fatal polling error
    }
  }, [taskUuid, handleTaskUpdate]);

  // Stream hoặc Polling lifecycle
  useEffect(() => {
    if (task.is_finished || ['success', 'failed', 'cancelled'].includes(task.status)) {
      return;
    }

    let abortController: AbortController | null = null;
    let pollInterval: NodeJS.Timeout | null = null;

    if (mode === 'sse' && typeof window !== 'undefined' && 'fetch' in window) {
      abortController = new AbortController();

      const startFetchStream = async () => {
        try {
          const authState = useAuthStore.getState();
          const token = authState.token;
          const workspaceId = authState.activeWorkspaceId;

          const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';
          const headers: Record<string, string> = {
            Accept: 'text/event-stream',
          };
          if (token) {
            headers.Authorization = `Bearer ${token}`;
          }
          if (workspaceId) {
            headers['X-Workspace-Id'] = workspaceId.toString();
          }

          const response = await fetch(`${apiUrl}/chatbot/tasks/${taskUuid}/stream`, {
            method: 'GET',
            headers,
            signal: abortController?.signal,
          });

          if (!response.ok || !response.body) {
            throw new Error('SSE stream connection refused');
          }

          const reader = response.body.getReader();
          const decoder = new TextDecoder('utf-8');
          let buffer = '';

          while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
              if (line.startsWith('data: ')) {
                try {
                  const parsed = JSON.parse(line.slice(6)) as TaskStreamData;
                  if (parsed && parsed.uuid) {
                    handleTaskUpdate(parsed);
                    if (
                      parsed.is_finished ||
                      ['success', 'failed', 'cancelled'].includes(parsed.status)
                    ) {
                      return;
                    }
                  }
                } catch {
                  // Non-json SSE frame
                }
              }
            }
          }
        } catch (err: unknown) {
          if ((err as Error)?.name === 'AbortError') {
            return;
          }
          // Fallback sang Polling định kỳ nếu SSE ngắt
          pollInterval = setInterval(fetchPollingStatus, pollIntervalMs);
        }
      };

      startFetchStream();
    } else {
      // Polling mode thuần túy
      fetchPollingStatus();
      pollInterval = setInterval(fetchPollingStatus, pollIntervalMs);
    }

    return () => {
      if (abortController) {
        abortController.abort();
      }
      if (pollInterval) {
        clearInterval(pollInterval);
      }
    };
  }, [taskUuid, mode, pollIntervalMs, task.status, task.is_finished, fetchPollingStatus, handleTaskUpdate]);

  // Hành động Resume / Retry
  const handleResume = async () => {
    if (isResuming) return;
    setIsResuming(true);
    try {
      const response = await apiClient.post<{ success: boolean; message: string; data: TaskStreamData }>(
        `/chatbot/tasks/${taskUuid}/resume`
      );

      if (response.data?.success && response.data.data) {
        toast.success('Đã tiếp tục tác vụ thành công');
        handleTaskUpdate(response.data.data);
      }
    } catch (err: unknown) {
      const errorMsg = (err as Error)?.message || 'Không thể khôi phục tác vụ.';
      toast.error(errorMsg);
    } finally {
      setIsResuming(false);
    }
  };

  // Hành động Cancel
  const handleCancel = async () => {
    if (isCancelling) return;
    setIsCancelling(true);
    try {
      const response = await apiClient.post<{ success: boolean; message: string; data: TaskStreamData }>(
        `/chatbot/tasks/${taskUuid}/cancel`,
        { reason: 'Người dùng yêu cầu dừng từ khung chat' }
      );

      if (response.data?.success && response.data.data) {
        toast.info('Đã hủy tác vụ an toàn');
        handleTaskUpdate(response.data.data);
      }
    } catch (err: unknown) {
      const errorMsg = (err as Error)?.message || 'Không thể hủy tác vụ.';
      toast.error(errorMsg);
    } finally {
      setIsCancelling(false);
    }
  };

  const isRunning = task.status === 'running' || task.status === 'pending';
  const isSuccess = task.status === 'success';
  const isFailed = task.status === 'failed';
  const isCancelled = task.status === 'cancelled';

  return (
    <div
      className={cn(
        'group relative my-2 overflow-hidden rounded-2xl border transition-all duration-200 shadow-2xs',
        isRunning && 'border-purple-200 bg-linear-to-b from-purple-50/40 via-card to-card dark:border-purple-900/40 dark:from-purple-950/20',
        isSuccess && 'border-emerald-200 bg-linear-to-b from-emerald-50/40 via-card to-card dark:border-emerald-900/40 dark:from-emerald-950/20',
        isFailed && 'border-rose-200 bg-linear-to-b from-rose-50/40 via-card to-card dark:border-rose-900/40 dark:from-rose-950/20',
        isCancelled && 'border-border bg-muted/30',
        className
      )}
    >
      {/* Top Header Card */}
      <div className="flex items-center justify-between p-3 md:p-3.5">
        <div className="flex items-center gap-2.5 min-w-0">
          <div
            className={cn(
              'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition-transform',
              isRunning && 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
              isSuccess && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
              isFailed && 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
              isCancelled && 'bg-muted text-muted-foreground'
            )}
          >
            {isRunning && <Loader2 className="h-5 w-5 animate-spin" />}
            {isSuccess && <CheckCircle2 className="h-5 w-5" />}
            {isFailed && <AlertCircle className="h-5 w-5" />}
            {isCancelled && <XCircle className="h-5 w-5" />}
          </div>

          <div className="min-w-0 space-y-0.5">
            <div className="flex items-center gap-2">
              <h4 className="truncate text-xs md:text-sm font-semibold text-foreground">
                {task.article?.title || task.keyword?.keyword || 'Tác vụ SEO tự động'}
              </h4>
              <Badge
                variant="outline"
                className={cn(
                  'h-4.5 px-1.5 text-[10px] font-medium border',
                  isRunning && 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-900 dark:bg-purple-950 dark:text-purple-300',
                  isSuccess && 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
                  isFailed && 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
                  isCancelled && 'border-border bg-muted text-muted-foreground'
                )}
              >
                {task.status_label || task.status}
              </Badge>
            </div>

            <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
              {isRunning && (
                <span className="flex items-center gap-1 text-purple-600 dark:text-purple-400">
                  <Clock className="h-3 w-3 animate-pulse" />
                  {formatElapsed(elapsedSeconds)}
                </span>
              )}
              {task.attempts && task.attempts > 1 && (
                <span>Lần thử #{task.attempts}</span>
              )}
              {task.credits_reserved ? (
                <span className="flex items-center gap-0.5 text-amber-600 dark:text-amber-400">
                  <Zap className="h-3 w-3" />
                  {task.credits_reserved} credits
                </span>
              ) : null}
            </div>
          </div>
        </div>

        {/* Right side: Progress percentage & Accordion toggle */}
        <div className="flex items-center gap-3 shrink-0">
          <div className="text-right">
            <span
              className={cn(
                'text-base md:text-lg font-bold tabular-nums',
                isRunning && 'text-purple-600 dark:text-purple-400',
                isSuccess && 'text-emerald-600 dark:text-emerald-400',
                isFailed && 'text-rose-600 dark:text-rose-400',
                isCancelled && 'text-muted-foreground'
              )}
            >
              {task.progress}%
            </span>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            onClick={() => setShowDetails(!showDetails)}
            className="size-7 text-muted-foreground hover:text-foreground"
            title={showDetails ? 'Thu gọn' : 'Xem các bước'}
          >
            {showDetails ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
            <span className="sr-only">Toggle steps</span>
          </Button>
        </div>
      </div>

      {/* Progress Track Bar */}
      <div className="px-3 md:px-3.5 pb-2">
        <div className="relative h-2 w-full overflow-hidden rounded-full bg-muted/60">
          <div
            className={cn(
              'h-full transition-all duration-500 ease-out rounded-full',
              isRunning && 'bg-linear-to-r from-purple-500 to-indigo-600 animate-pulse',
              isSuccess && 'bg-linear-to-r from-emerald-500 to-teal-600',
              isFailed && 'bg-rose-500',
              isCancelled && 'bg-slate-400 dark:bg-slate-600'
            )}
            style={{ width: `${Math.min(100, Math.max(2, task.progress))}%` }}
          />
        </div>

        {/* Current running step text */}
        <div className="mt-2 flex items-center justify-between text-xs">
          <div className="flex items-center gap-1.5 truncate text-foreground font-medium">
            {isRunning && <Sparkles className="h-3.5 w-3.5 text-purple-600 shrink-0" />}
            <span className="truncate">{task.current_step}</span>
          </div>
        </div>
      </div>

      {/* Error Callout (Nếu task thất bại) */}
      {isFailed && (
        <div className="mx-3 md:mx-3.5 mb-3 rounded-xl border border-rose-300 bg-rose-50/80 p-3 text-xs text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
          <div className="flex items-start gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
            <div className="space-y-1">
              <div className="font-semibold text-rose-800 dark:text-rose-300">
                Sự cố trong quá trình thực thi: {task.error_code || 'TASK_EXECUTION_ERROR'}
              </div>
              <p className="text-rose-700/90 dark:text-rose-300/80 leading-relaxed">
                {task.error_message || 'Hệ thống đã tự động dừng để bảo vệ credit và tránh gián đoạn dữ liệu.'}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Step by step timeline details (Collapsible) */}
      {showDetails && (
        <div className="border-t border-border/60 bg-muted/20 px-3 md:px-3.5 py-2.5">
          <div className="space-y-2">
            {DEFAULT_STEPS.map((step, idx) => {
              const isPast = task.progress >= step.minProgress + 15 || isSuccess;
              const isCurrent =
                isRunning &&
                task.progress >= step.minProgress &&
                (idx === DEFAULT_STEPS.length - 1 || task.progress < DEFAULT_STEPS[idx + 1].minProgress);

              return (
                <div key={idx} className="flex items-center gap-2.5 text-xs">
                  <div className="flex items-center justify-center shrink-0 w-4 h-4">
                    {isPast ? (
                      <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    ) : isCurrent ? (
                      <Loader2 className="h-3.5 w-3.5 text-purple-600 animate-spin" />
                    ) : (
                      <div className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
                    )}
                  </div>
                  <span
                    className={cn(
                      'leading-tight',
                      isCurrent && 'font-semibold text-foreground',
                      isPast && 'text-muted-foreground line-through decoration-muted-foreground/40',
                      !isCurrent && !isPast && 'text-muted-foreground/70'
                    )}
                  >
                    {step.label}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Action Footer Buttons */}
      <div className="flex items-center justify-between border-t border-border/60 bg-card/60 px-3 md:px-3.5 py-2 text-xs">
        <div className="text-[11px] text-muted-foreground truncate">
          Mã tác vụ: <code className="font-mono text-foreground text-[10px]">{task.uuid.slice(0, 8)}...</code>
        </div>

        <div className="flex items-center gap-2">
          {/* Nút Cancel khi đang chạy */}
          {isRunning && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={isCancelling}
              onClick={handleCancel}
              className="h-7 px-2.5 text-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950"
            >
              {isCancelling ? (
                <Loader2 className="h-3 w-3 animate-spin mr-1" />
              ) : (
                <XCircle className="h-3 w-3 mr-1" />
              )}
              Dừng tác vụ
            </Button>
          )}

          {/* Nút Resume / Retry khi Failed hoặc Cancelled */}
          {(isFailed || isCancelled) && (
            <Button
              type="button"
              size="sm"
              disabled={isResuming}
              onClick={handleResume}
              className="h-7 px-2.5 text-xs bg-purple-600 hover:bg-purple-700 text-white gap-1"
            >
              {isResuming ? (
                <Loader2 className="h-3 w-3 animate-spin" />
              ) : (
                <RotateCcw className="h-3 w-3" />
              )}
              Tiếp tục / Thử lại
            </Button>
          )}

          {/* Nút View Article khi Success */}
          {isSuccess && task.article?.id && (
            <Button
              asChild
              size="sm"
              className="h-7 px-2.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
            >
              <Link href={`/content-studio/${task.article.id}`}>
                <FileText className="h-3 w-3" />
                Mở bài viết
                <ExternalLink className="h-2.5 w-2.5 opacity-70" />
              </Link>
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
