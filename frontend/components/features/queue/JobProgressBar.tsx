import React from 'react';

interface JobProgressBarProps {
  label: string;
  pending: number;
  processing: number;
  failed: number;
  total: number;
}

export default function JobProgressBar({ label, pending, processing, failed, total }: JobProgressBarProps) {
  const done = total - pending - processing - failed;
  const pct = (n: number) => total > 0 ? Math.round((n / total) * 100) : 0;

  return (
    <div className="space-y-2">
      <div className="flex justify-between items-center text-sm">
        <span className="font-semibold text-slate-700">{label}</span>
        <span className="text-slate-400 font-mono">{total} jobs</span>
      </div>
      <div className="h-3 bg-slate-100 rounded-full overflow-hidden flex gap-px">
        {done > 0 && (
          <div className="bg-emerald-400 h-full rounded-l-full transition-all duration-500" style={{ width: `${pct(done)}%` }} title={`Done: ${done}`} />
        )}
        {processing > 0 && (
          <div className="bg-blue-500 h-full animate-pulse transition-all duration-500" style={{ width: `${pct(processing)}%` }} title={`Processing: ${processing}`} />
        )}
        {pending > 0 && (
          <div className="bg-amber-300 h-full transition-all duration-500" style={{ width: `${pct(pending)}%` }} title={`Pending: ${pending}`} />
        )}
        {failed > 0 && (
          <div className="bg-red-500 h-full rounded-r-full transition-all duration-500" style={{ width: `${pct(failed)}%` }} title={`Failed: ${failed}`} />
        )}
      </div>
      <div className="flex gap-4 text-xs font-medium text-slate-500">
        <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>Xong: {done}</span>
        <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>Đang: {processing}</span>
        <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-amber-300 inline-block"></span>Chờ: {pending}</span>
        <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-red-500 inline-block"></span>Lỗi: {failed}</span>
      </div>
    </div>
  );
}
