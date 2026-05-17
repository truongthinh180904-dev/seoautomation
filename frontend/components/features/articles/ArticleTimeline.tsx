"use client";

import React from 'react';
import { Clock, CheckCircle2, XCircle, Send } from 'lucide-react';

interface TimelineEvent {
  id: string;
  status: string;
  user: string;
  timestamp: string;
  note?: string;
}

interface ArticleTimelineProps {
  events: TimelineEvent[];
}

const STATUS_ICONS: Record<string, any> = {
  draft: Clock,
  review: Clock,
  approved: CheckCircle2,
  rejected: XCircle,
  publishing: Send,
  published: CheckCircle2,
};

const STATUS_COLORS: Record<string, string> = {
  draft: 'text-slate-400 bg-slate-100',
  review: 'text-blue-500 bg-blue-50',
  approved: 'text-emerald-500 bg-emerald-50',
  rejected: 'text-rose-500 bg-rose-50',
  publishing: 'text-purple-500 bg-purple-50',
  published: 'text-emerald-500 bg-emerald-50',
};

export default function ArticleTimeline({ events }: ArticleTimelineProps) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <h3 className="font-extrabold text-slate-900 mb-6">Activity Timeline</h3>
      
      <div className="space-y-6 relative before:absolute before:left-4 before:top-2 before:bottom-2 before:w-px before:bg-slate-100">
        {events.map((event, i) => {
          const Icon = STATUS_ICONS[event.status] || Clock;
          const colorClass = STATUS_COLORS[event.status] || 'text-slate-400 bg-slate-100';
          
          return (
            <div key={event.id} className="relative pl-10">
              <div className={`absolute left-0 top-0.5 w-8 h-8 rounded-full flex items-center justify-center z-10 ${colorClass}`}>
                <Icon className="w-4 h-4" />
              </div>
              
              <div>
                <div className="flex items-center justify-between gap-2 mb-1">
                  <span className="text-sm font-bold text-slate-900 uppercase tracking-tight">
                    {event.status.replace('_', ' ')}
                  </span>
                  <span className="text-[10px] font-bold text-slate-400">
                    {event.timestamp}
                  </span>
                </div>
                
                <div className="text-xs font-medium text-slate-500">
                  By <span className="text-slate-700">{event.user}</span>
                </div>
                
                {event.note && (
                  <div className="mt-2 text-xs text-slate-600 bg-slate-50 rounded-lg p-2 border border-slate-100 italic">
                    "{event.note}"
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
