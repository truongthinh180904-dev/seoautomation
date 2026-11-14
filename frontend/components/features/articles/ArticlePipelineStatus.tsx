import React from 'react';
import { CheckCircle2, Circle, Clock3, Loader2, XCircle } from 'lucide-react';

interface ArticlePipelineStatusProps {
  pipeline?: ArticlePipelineStatus | null;
  compact?: boolean;
}

const STATUS_CLASS: Record<ArticlePipelineStep['status'], string> = {
  pending: 'border-slate-200 bg-slate-50 text-slate-500',
  running: 'border-blue-200 bg-blue-50 text-blue-700',
  completed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  failed: 'border-red-200 bg-red-50 text-red-700',
};

function StepIcon({ status }: { status: ArticlePipelineStep['status'] }) {
  if (status === 'running') {
    return <Loader2 className="h-4 w-4 animate-spin" />;
  }

  if (status === 'completed') {
    return <CheckCircle2 className="h-4 w-4" />;
  }

  if (status === 'failed') {
    return <XCircle className="h-4 w-4" />;
  }

  return <Circle className="h-4 w-4" />;
}

export default function ArticlePipelineStatus({ pipeline, compact = false }: ArticlePipelineStatusProps) {
  const steps = Object.values(pipeline?.steps ?? {});
  const activeStep = steps.find((step) => step.key === pipeline?.current) || steps.find((step) => step.status === 'running');

  if (!steps.length) {
    return (
      <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm font-medium text-slate-500">
        Chưa có dữ liệu tiến trình cho bài viết này.
      </div>
    );
  }

  if (compact) {
    const step = activeStep || steps.find((item) => item.status === 'failed') || steps[0];
    return (
      <div className={`mt-2 inline-flex max-w-[260px] items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs font-bold ${STATUS_CLASS[step.status]}`}>
        <StepIcon status={step.status} />
        <span className="truncate">{step.status === 'failed' ? `Lỗi: ${step.label}` : step.label}</span>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-black text-slate-900">Tiến trình AI</h2>
        {pipeline?.updated_at && (
          <span className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400">
            <Clock3 className="h-3.5 w-3.5" />
            {new Date(pipeline.updated_at).toLocaleTimeString('vi-VN')}
          </span>
        )}
      </div>

      <div className="mt-4 space-y-2">
        {steps.map((step) => (
          <div key={step.key} className={`rounded-lg border p-3 ${STATUS_CLASS[step.status]}`}>
            <div className="flex items-start gap-3">
              <div className="mt-0.5">
                <StepIcon status={step.status} />
              </div>
              <div className="min-w-0 flex-1">
                <div className="font-extrabold">{step.label}</div>
                {step.message && <div className="mt-1 text-xs font-semibold opacity-80">{step.message}</div>}
                {step.error && <div className="mt-1 text-xs font-semibold text-red-700">{step.error}</div>}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
