'use client';

import React, { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  SeoAuditResult,
  SeoCriterion,
  useAuditArticleScore,
  calculateClientQuickScore,
} from '@/hooks/useSeoScore';
import {
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Loader2,
  Sparkles,
  Zap,
  Heading1,
  AlignLeft,
  ListTree,
  Hash,
  Image as ImageIcon,
  BookOpen,
  Link2,
  HelpCircle,
  Code2,
  ChevronDown,
  ChevronUp,
  RotateCw,
  Lightbulb,
} from 'lucide-react';

export interface SeoScoreSidebarProps {
  articleId?: number;
  initialScore?: number;
  content?: string;
  title?: string;
  seoTitle?: string;
  seoDescription?: string;
  focusKeyword?: string;
  className?: string;
  onApplyFix?: (field: string, value: string) => void;
}

export function SeoScoreSidebar({
  articleId,
  initialScore = 0,
  content = '',
  title = '',
  seoTitle = '',
  seoDescription = '',
  focusKeyword = '',
  className = '',
  onApplyFix,
}: SeoScoreSidebarProps) {
  const [activeTab, setActiveTab] = useState<'criteria' | 'recommendations'>('criteria');
  const [expandedCriteria, setExpandedCriteria] = useState<Record<string, boolean>>({});
  const [auditResult, setAuditResult] = useState<SeoAuditResult | null>(null);

  const auditMutation = useAuditArticleScore();

  // Instant client-side quick estimate for 0ms typing feedback
  const clientQuick = calculateClientQuickScore({
    content,
    title,
    seoTitle,
    seoDescription,
    keyword: focusKeyword,
  });

  const displayScore = auditResult ? auditResult.total_score : (initialScore || clientQuick.estimatedScore);

  // Trigger audit from Backend
  const handleTriggerAudit = async (persist = false) => {
    try {
      const res = await auditMutation.mutateAsync({
        article_id: articleId,
        content,
        title,
        seo_title: seoTitle,
        seo_description: seoDescription,
        focus_keyword: focusKeyword,
        persist,
      });
      setAuditResult(res);
    } catch {
      // Silently keep previous state or fallback
    }
  };

  // Initial load audit
  useEffect(() => {
    if (articleId) {
      handleTriggerAudit(false);
    }
  }, [articleId]);

  const toggleCriterionExpand = (key: string) => {
    setExpandedCriteria((prev) => ({
      ...prev,
      [key]: !prev[key],
    }));
  };

  const getCriterionIcon = (key: string) => {
    switch (key) {
      case 'h1_title':
        return <Heading1 className="w-4 h-4 text-primary" />;
      case 'meta_description':
        return <AlignLeft className="w-4 h-4 text-primary" />;
      case 'heading_structure':
        return <ListTree className="w-4 h-4 text-primary" />;
      case 'keyword_density':
        return <Hash className="w-4 h-4 text-primary" />;
      case 'image_alts':
        return <ImageIcon className="w-4 h-4 text-primary" />;
      case 'readability':
        return <BookOpen className="w-4 h-4 text-primary" />;
      case 'links':
        return <Link2 className="w-4 h-4 text-primary" />;
      case 'faqs':
        return <HelpCircle className="w-4 h-4 text-primary" />;
      case 'schema_markup':
        return <Code2 className="w-4 h-4 text-primary" />;
      default:
        return <Sparkles className="w-4 h-4 text-primary" />;
    }
  };

  const getStatusIcon = (status: 'passed' | 'warning' | 'failed') => {
    switch (status) {
      case 'passed':
        return <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />;
      case 'warning':
        return <AlertTriangle className="w-4 h-4 text-amber-500 shrink-0" />;
      case 'failed':
        return <XCircle className="w-4 h-4 text-rose-500 shrink-0" />;
    }
  };

  // Score color formatting
  const getScoreColorClass = (score: number) => {
    if (score >= 80) return 'text-emerald-500';
    if (score >= 60) return 'text-amber-500';
    return 'text-rose-500';
  };

  const getScoreBgClass = (score: number) => {
    if (score >= 80) return 'bg-emerald-500';
    if (score >= 60) return 'bg-amber-500';
    return 'bg-rose-500';
  };

  const criteriaList: Array<{ key: string; data: SeoCriterion }> = auditResult?.criteria
    ? [
        { key: 'h1_title', data: auditResult.criteria.h1_title },
        { key: 'meta_description', data: auditResult.criteria.meta_description },
        { key: 'heading_structure', data: auditResult.criteria.heading_structure },
        { key: 'keyword_density', data: auditResult.criteria.keyword_density },
        { key: 'image_alts', data: auditResult.criteria.image_alts },
        { key: 'readability', data: auditResult.criteria.readability },
        { key: 'links', data: auditResult.criteria.links },
        { key: 'faqs', data: auditResult.criteria.faqs },
        { key: 'schema_markup', data: auditResult.criteria.schema_markup },
      ].filter((item) => Boolean(item.data))
    : [];

  return (
    <div className={`flex flex-col h-full bg-card border-l border-border select-none ${className}`}>
      {/* Header Bar */}
      <div className="p-3.5 border-b border-border space-y-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1.5 font-semibold text-xs text-foreground">
            <Zap className="w-4 h-4 text-primary" />
            <span>SEO Scoring Engine</span>
          </div>

          <div className="flex items-center gap-1">
            <Badge variant="outline" className="text-[10px] text-muted-foreground font-mono">
              0 Credits
            </Badge>
            {auditResult && (
              <Badge variant="secondary" className="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">
                {auditResult.execution_time_ms}ms
              </Badge>
            )}
          </div>
        </div>

        {/* Score Circular Gauge & Progress */}
        <div className="p-3 rounded-xl border border-border bg-muted/20 space-y-2">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[11px] text-muted-foreground block font-medium">Điểm SEO tổng thể</span>
              <div className="flex items-baseline gap-1 mt-0.5">
                <span className={`text-2xl font-black tracking-tight ${getScoreColorClass(displayScore)}`}>
                  {displayScore}
                </span>
                <span className="text-xs text-muted-foreground font-semibold">/100</span>
              </div>
            </div>

            {/* Re-audit Button (Double-Submit Protected) */}
            <Button
              size="xs"
              variant="outline"
              disabled={auditMutation.isPending}
              onClick={() => handleTriggerAudit(true)}
              className="h-7 text-xs gap-1 border-border hover:bg-muted"
            >
              <RotateCw className={`w-3 h-3 ${auditMutation.isPending ? 'animate-spin' : ''}`} />
              <span>{auditMutation.isPending ? 'Đang chấm...' : 'Chấm lại'}</span>
            </Button>
          </div>

          {/* Simple Linear Progress Bar */}
          <div className="w-full bg-muted rounded-full h-1.5 overflow-hidden">
            <div
              className={`h-full transition-all duration-500 rounded-full ${getScoreBgClass(displayScore)}`}
              style={{ width: `${Math.min(100, Math.max(0, displayScore))}%` }}
            />
          </div>

          <div className="flex items-center justify-between text-[10px] text-muted-foreground pt-0.5">
            <span>
              {displayScore >= 80 ? '🟢 Đạt chuẩn Top 3 Google' : displayScore >= 60 ? '🟡 Cần tối ưu thêm' : '🔴 Điểm SEO thấp'}
            </span>
            <span>{auditResult?.summary?.word_count ?? clientQuick.wordCount} từ</span>
          </div>
        </div>

        {/* Tab Switcher */}
        <Tabs value={activeTab} onValueChange={(val) => setActiveTab(val as 'criteria' | 'recommendations')}>
          <TabsList className="w-full grid grid-cols-2 h-7 p-0.5 bg-muted/50">
            <TabsTrigger value="criteria" className="text-xs py-1">
              9 Tiêu chí ({criteriaList.length || 9})
            </TabsTrigger>
            <TabsTrigger value="recommendations" className="text-xs py-1">
              Khuyến nghị ({auditResult?.recommendations.length || 0})
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      {/* Main Content Area */}
      <div className="flex-1 overflow-y-auto p-3 space-y-2.5">
        {activeTab === 'criteria' && (
          <div className="space-y-2">
            {criteriaList.length === 0 ? (
              <div className="p-4 text-center text-xs text-muted-foreground space-y-2">
                <Loader2 className="w-5 h-5 mx-auto animate-spin text-primary" />
                <p>Đang tải dữ liệu kiểm toán SEO 100 điểm...</p>
              </div>
            ) : (
              criteriaList.map(({ key, data }) => {
                const isExpanded = expandedCriteria[key] ?? false;

                return (
                  <div
                    key={key}
                    className="rounded-lg border border-border bg-card p-2.5 transition-all hover:border-primary/40 space-y-1.5 text-xs"
                  >
                    <div
                      className="flex items-center justify-between cursor-pointer"
                      onClick={() => toggleCriterionExpand(key)}
                    >
                      <div className="flex items-center gap-2 min-w-0">
                        {getStatusIcon(data.status)}
                        <span className="font-semibold text-foreground truncate">{data.name}</span>
                      </div>

                      <div className="flex items-center gap-1.5 shrink-0">
                        <span className="font-mono text-[11px] font-bold">
                          {data.score}/{data.max_score}
                        </span>
                        {isExpanded ? (
                          <ChevronUp className="w-3.5 h-3.5 text-muted-foreground" />
                        ) : (
                          <ChevronDown className="w-3.5 h-3.5 text-muted-foreground" />
                        )}
                      </div>
                    </div>

                    {/* Value Badge or Short Message */}
                    <p className="text-[11px] text-muted-foreground leading-snug line-clamp-2">
                      {data.message}
                    </p>

                    {/* Expandable Details & Recommendations */}
                    {isExpanded && (
                      <div className="pt-2 border-t border-border/60 space-y-2 text-[11px]">
                        {data.value && (
                          <div className="flex items-center justify-between text-muted-foreground bg-muted/30 p-1.5 rounded">
                            <span className="font-medium">Chỉ số phân tích:</span>
                            <span className="font-mono font-semibold text-foreground">{String(data.value)}</span>
                          </div>
                        )}

                        {data.recommendations && data.recommendations.length > 0 && (
                          <div className="space-y-1 bg-amber-500/5 border border-amber-500/20 p-2 rounded text-amber-900 dark:text-amber-200">
                            <span className="font-semibold block text-[10px] uppercase tracking-wider text-amber-700 dark:text-amber-400">
                              Cách khắc phục:
                            </span>
                            <ul className="list-disc pl-3.5 space-y-0.5">
                              {data.recommendations.map((rec, i) => (
                                <li key={i}>{rec}</li>
                              ))}
                            </ul>
                          </div>
                        )}

                        {data.auto_fixable && onApplyFix && (
                          <Button
                            size="xs"
                            variant="secondary"
                            onClick={() => onApplyFix(key, '')}
                            className="w-full text-[10px] h-6 gap-1"
                          >
                            <Sparkles className="w-3 h-3 text-primary" />
                            Tự động sửa lỗi này
                          </Button>
                        )}
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
        )}

        {/* Tab 2: Recommendations List */}
        {activeTab === 'recommendations' && (
          <div className="space-y-2">
            {!auditResult || auditResult.recommendations.length === 0 ? (
              <div className="p-6 text-center text-xs text-muted-foreground space-y-2">
                <CheckCircle2 className="w-8 h-8 mx-auto text-emerald-500 opacity-80" />
                <p className="font-semibold text-foreground">Không còn cảnh báo quan trọng!</p>
                <p>Bài viết của bạn đã đáp ứng xuất sắc các tiêu chuẩn SEO cốt lõi.</p>
              </div>
            ) : (
              auditResult.recommendations.map((rec, index) => (
                <div
                  key={index}
                  className="p-2.5 rounded-lg border border-border bg-card space-y-1 text-xs"
                >
                  <div className="flex items-start gap-2">
                    <Lightbulb className="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" />
                    <p className="text-[11px] text-foreground leading-relaxed flex-1">{rec}</p>
                  </div>
                </div>
              ))
            )}
          </div>
        )}
      </div>

      {/* Footer Metrics Summary */}
      <div className="p-3 border-t border-border bg-muted/20 text-[10px] text-muted-foreground space-y-1.5">
        <div className="grid grid-cols-3 gap-1.5 text-center">
          <div className="p-1 rounded bg-background border border-border">
            <span className="block text-[9px] text-muted-foreground">Mật độ từ khóa</span>
            <span className="font-bold font-mono text-foreground">
              {auditResult?.summary?.keyword_density ?? clientQuick.keywordDensity}%
            </span>
          </div>
          <div className="p-1 rounded bg-background border border-border">
            <span className="block text-[9px] text-muted-foreground">Thẻ H2 / H3</span>
            <span className="font-bold font-mono text-foreground">
              {auditResult?.summary?.headings?.h2 ?? clientQuick.h2Count} H2
            </span>
          </div>
          <div className="p-1 rounded bg-background border border-border">
            <span className="block text-[9px] text-muted-foreground">Ảnh có ALT</span>
            <span className="font-bold font-mono text-foreground">
              {auditResult?.summary?.images_with_alt_count ?? (clientQuick.hasImages ? 1 : 0)} ảnh
            </span>
          </div>
        </div>

        <div className="flex items-center justify-between text-[9px] pt-1 text-muted-foreground">
          <span>Động cơ: Pure Deterministic PHP</span>
          <span>Độ trễ: &lt; 50ms</span>
        </div>
      </div>
    </div>
  );
}
