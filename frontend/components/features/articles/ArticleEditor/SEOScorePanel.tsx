"use client";

import React from 'react';
import { Progress } from "@/components/ui/progress";
import { CheckCircle2, AlertCircle, Info } from "lucide-react";

interface SEOScorePanelProps {
  content: string;
  title: string;
  metaDescription: string;
  keyword: string;
}

export default function SEOScorePanel({ content, title, metaDescription, keyword }: SEOScorePanelProps) {
  // Simple SEO scoring logic
  const plainText = content.replace(/<[^>]*>/g, '');
  const wordCount = plainText.split(/\s+/).filter(Boolean).length;
  
  const keywordLower = keyword.toLowerCase();
  const keywordDensity = wordCount > 0 
    ? (plainText.toLowerCase().split(keywordLower).length - 1) / wordCount * 100 
    : 0;

  const checks = [
    {
      label: "Keyword in Title",
      passed: title.toLowerCase().includes(keywordLower),
      info: "Keyword should appear in the H1 title."
    },
    {
      label: "Title Length",
      passed: title.length >= 40 && title.length <= 60,
      info: "Ideal length is 40-60 characters."
    },
    {
      label: "Meta Description Length",
      passed: metaDescription.length >= 120 && metaDescription.length <= 160,
      info: "Ideal length is 120-160 characters."
    },
    {
      label: "Keyword Density",
      passed: keywordDensity >= 0.5 && keywordDensity <= 2.5,
      info: `Target density: 0.5% - 2.5%. Current: ${keywordDensity.toFixed(2)}%`
    },
    {
      label: "Word Count",
      passed: wordCount >= 1000,
      info: `Minimum 1000 words recommended. Current: ${wordCount}`
    }
  ];

  const score = Math.round((checks.filter(c => c.passed).length / checks.length) * 100);

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sticky top-24">
      <div className="flex items-center justify-between mb-6">
        <h3 className="font-extrabold text-slate-900 tracking-tight">SEO Score</h3>
        <div className={`text-2xl font-black ${score > 80 ? 'text-emerald-500' : score > 50 ? 'text-amber-500' : 'text-rose-500'}`}>
          {score}%
        </div>
      </div>

      <Progress value={score} className="h-3 mb-8" />

      <div className="space-y-4">
        {checks.map((check, i) => (
          <div key={i} className="flex items-start gap-3 group">
            {check.passed ? (
              <CheckCircle2 className="w-5 h-5 text-emerald-500 mt-0.5 shrink-0" />
            ) : (
              <AlertCircle className="w-5 h-5 text-rose-500 mt-0.5 shrink-0" />
            )}
            <div className="flex-1">
              <div className="flex items-center gap-1.5">
                <span className={`text-sm font-bold ${check.passed ? 'text-slate-700' : 'text-slate-900'}`}>
                  {check.label}
                </span>
                <div className="relative group/info">
                  <Info className="w-3.5 h-3.5 text-slate-400 cursor-help" />
                  <div className="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 w-48 p-2 bg-slate-900 text-white text-[10px] rounded-lg opacity-0 invisible group-hover/info:opacity-100 group-hover/info:visible transition-all z-50">
                    {check.info}
                  </div>
                </div>
              </div>
              <div className="text-[11px] text-slate-500 font-medium">
                {check.passed ? "Optimized" : "Needs attention"}
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-8 pt-6 border-t border-slate-100">
        <div className="flex justify-between text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">
          <span>Stats</span>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div className="bg-slate-50 rounded-xl p-3">
            <div className="text-[10px] font-bold text-slate-500 uppercase">Words</div>
            <div className="text-lg font-black text-slate-900">{wordCount}</div>
          </div>
          <div className="bg-slate-50 rounded-xl p-3">
            <div className="text-[10px] font-bold text-slate-500 uppercase">Density</div>
            <div className="text-lg font-black text-slate-900">{keywordDensity.toFixed(1)}%</div>
          </div>
        </div>
      </div>
    </div>
  );
}
