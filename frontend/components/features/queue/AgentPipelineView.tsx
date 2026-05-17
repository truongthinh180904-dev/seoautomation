import React from 'react';
import { CheckCircle2, Loader2, Clock, AlertCircle } from 'lucide-react';

const PIPELINE_STEPS = [
  { key: 'serp_research',        label: 'SERP Research',        icon: '🔍', queue: 'ai-research' },
  { key: 'competitor_analysis',  label: 'Đối thủ',              icon: '🕵️', queue: 'ai-research' },
  { key: 'content_analysis',     label: 'Phân tích nội dung',   icon: '📊', queue: 'ai-research' },
  { key: 'outline',              label: 'Dàn ý AI',             icon: '📝', queue: 'ai-writing' },
  { key: 'writing',              label: 'Viết bài AI',          icon: '✍️', queue: 'ai-writing' },
  { key: 'seo_optimization',     label: 'Tối ưu SEO',           icon: '🎯', queue: 'ai-writing' },
  { key: 'internal_linking',     label: 'Link nội bộ',          icon: '🔗', queue: 'ai-writing' },
  { key: 'qa_validation',        label: 'QA Kiểm tra',          icon: '✅', queue: 'ai-writing' },
  { key: 'publishing',           label: 'Đăng WordPress',       icon: '🚀', queue: 'publishing' },
];

const QUEUE_COLORS: Record<string, string> = {
  'ai-research': 'from-violet-500 to-purple-600',
  'ai-writing':  'from-blue-500 to-cyan-600',
  'publishing':  'from-emerald-500 to-green-600',
};

export default function AgentPipelineView() {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <h2 className="text-lg font-extrabold text-slate-900 mb-6">AI Pipeline — Luồng xử lý Agent</h2>

      <div className="flex items-center gap-0 overflow-x-auto pb-2">
        {PIPELINE_STEPS.map((step, idx) => {
          const color = QUEUE_COLORS[step.queue] || 'from-slate-400 to-slate-600';
          const isLast = idx === PIPELINE_STEPS.length - 1;

          return (
            <React.Fragment key={step.key}>
              <div className="flex flex-col items-center gap-2 min-w-[100px]">
                <div className={`w-12 h-12 rounded-full bg-gradient-to-br ${color} flex items-center justify-center text-xl shadow-md`}>
                  {step.icon}
                </div>
                <span className="text-xs font-bold text-slate-600 text-center leading-tight max-w-[90px]">{step.label}</span>
                <span className="text-[10px] font-mono bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{step.queue}</span>
              </div>
              {!isLast && (
                <div className="flex-shrink-0 w-8 flex items-start pt-4">
                  <div className="w-full h-0.5 bg-gradient-to-r from-slate-300 to-slate-200"></div>
                  <div className="text-slate-300 -ml-1">▶</div>
                </div>
              )}
            </React.Fragment>
          );
        })}
      </div>

      <div className="mt-6 flex items-center gap-6 text-xs font-bold text-slate-500 border-t border-slate-100 pt-4">
        <span className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-full bg-gradient-to-br from-violet-500 to-purple-600 inline-block shadow-sm"></span>
          Queue: ai-research
        </span>
        <span className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 inline-block shadow-sm"></span>
          Queue: ai-writing
        </span>
        <span className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-full bg-gradient-to-br from-emerald-500 to-green-600 inline-block shadow-sm"></span>
          Queue: publishing
        </span>
      </div>
    </div>
  );
}
