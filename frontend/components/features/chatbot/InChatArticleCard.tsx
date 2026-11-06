'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  FileText,
  Copy,
  Check,
  ExternalLink,
  Sparkles,
  Image as ImageIcon,
  ChevronDown,
  ChevronUp,
  RefreshCw,
  Layers,
  ShieldCheck,
  Maximize2,
  Wand2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { InChatInlineToolbar } from './InChatInlineToolbar';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

export interface InChatArticleSection {
  level: string;
  heading: string;
  preview_text?: string;
  has_image?: boolean;
}

export interface InChatArticleMedia {
  id: number;
  url: string;
  alt_text: string;
  caption?: string;
  heading?: string;
  source_type: string;
  format?: string;
  size_bytes?: number;
  is_under_150kb?: boolean;
  provider?: string;
}

export interface InChatArticleCardData {
  id: number;
  title: string;
  slug?: string;
  focus_keyword?: string;
  excerpt?: string;
  content?: string;
  word_count?: number;
  seo_score?: number;
  readability_score?: number;
  status?: string;
  website?: {
    id: number;
    name: string;
    domain?: string;
  } | null;
  headings?: InChatArticleSection[];
  media_assets?: InChatArticleMedia[];
  mediaAssets?: InChatArticleMedia[];
  last_modified_at?: string;
}

export interface InChatArticleCardProps {
  article: InChatArticleCardData;
  onRefresh?: () => void;
  className?: string;
}

export function InChatArticleCard({
  article,
  onRefresh,
  className,
}: InChatArticleCardProps) {
  const [copied, setCopied] = useState(false);
  const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>({});
  const [selectedText, setSelectedText] = useState<string>('');
  const [activeHeadingForEdit, setActiveHeadingForEdit] = useState<string | undefined>(undefined);
  const [showInlineToolbar, setShowInlineToolbar] = useState(false);
  const [isRunningPipeline, setIsRunningPipeline] = useState(false);
  const [previewImageUrl, setPreviewImageUrl] = useState<string | null>(null);

  const seoScore = article.seo_score ?? 75;
  const wordCount = article.word_count ?? 0;
  const headings = article.headings ?? [];
  const mediaAssets = article.mediaAssets ?? article.media_assets ?? [];

  const handleCopyFullArticle = () => {
    const textToCopy = article.content || `${article.title}\n\n${article.excerpt || ''}`;
    navigator.clipboard.writeText(textToCopy);
    setCopied(true);
    toast.success('Đã sao chép toàn bộ bài viết vào clipboard');
    setTimeout(() => setCopied(false), 2000);
  };

  const toggleSection = (heading: string) => {
    setExpandedSections((prev) => ({
      ...prev,
      [heading]: !prev[heading],
    }));
  };

  const handleTextSelection = (headingTitle?: string) => {
    const selection = window.getSelection();
    const text = selection ? selection.toString().trim() : '';
    if (text.length >= 5) {
      setSelectedText(text);
      setActiveHeadingForEdit(headingTitle);
      setShowInlineToolbar(true);
    }
  };

  const handleRunImagePipeline = async () => {
    setIsRunningPipeline(true);
    try {
      const response = await fetch('/api/v1/chatbot/content/run-image-pipeline', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          article_id: article.id,
        }),
      });

      const json = await response.json() as {
        success?: boolean;
        message?: string;
        data?: { total_processed?: number };
      };

      if (!response.ok || !json.success) {
        throw new Error(json.message || 'Lỗi khi kích hoạt xử lý ảnh 3 tầng.');
      }

      toast.success(
        `Đã tự động tối ưu và nén WebP cho ${json.data?.total_processed ?? 'các'} mục H2`
      );
      onRefresh?.();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi xử lý ảnh.';
      toast.error(msg);
    } finally {
      setIsRunningPipeline(false);
    }
  };

  const handleApplyCopilotEdit = async (
    originalText: string,
    replacementText: string
  ) => {
    const response = await fetch('/api/v1/chatbot/content/apply-edit', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        article_id: article.id,
        original_text: originalText,
        replacement_text: replacementText,
        target_heading: activeHeadingForEdit,
      }),
    });

    const json = await response.json() as { success?: boolean; message?: string };
    if (!response.ok || !json.success) {
      throw new Error(json.message || 'Không thể lưu bản sửa đổi vào bài viết.');
    }

    setShowInlineToolbar(false);
    setSelectedText('');
    onRefresh?.();
  };

  return (
    <div
      className={cn(
        'w-full max-w-3xl overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-md transition-all',
        className
      )}
    >
      {/* Top Banner / Header */}
      <div className="border-b border-border/80 bg-gradient-to-r from-blue-50/80 via-indigo-50/50 to-purple-50/80 p-4 dark:from-slate-900 dark:via-indigo-950/30 dark:to-purple-950/30">
        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
          <div className="space-y-1.5 flex-1 min-w-0">
            <div className="flex items-center gap-2">
              <Badge
                variant="outline"
                className="gap-1 border-blue-200 bg-blue-100/60 text-blue-700 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300 text-[11px] font-semibold"
              >
                <FileText className="h-3 w-3" />
                Bản nháp bài viết SEO
              </Badge>
              {article.status && (
                <Badge variant="secondary" className="text-[10px] uppercase font-mono tracking-wider">
                  {article.status}
                </Badge>
              )}
            </div>

            <h3 className="text-base font-bold text-foreground leading-snug line-clamp-2">
              {article.title}
            </h3>

            {article.focus_keyword && (
              <p className="text-xs text-muted-foreground flex items-center gap-1">
                <span>Từ khóa chính:</span>
                <strong className="text-foreground font-semibold">
                  {article.focus_keyword}
                </strong>
              </p>
            )}
          </div>

          {/* Quick Metrics */}
          <div className="flex sm:flex-col items-center sm:items-end justify-between gap-2 shrink-0">
            <div
              className={cn(
                'flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold border shadow-2xs',
                seoScore >= 80
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                  : seoScore >= 60
                  ? 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800'
                  : 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800'
              )}
            >
              <Sparkles className="h-3.5 w-3.5" />
              <span>SEO {seoScore}/100</span>
            </div>

            <span className="text-[11px] text-muted-foreground font-mono">
              {wordCount.toLocaleString()} từ
            </span>
          </div>
        </div>
      </div>

      {/* Action Command Bar */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 bg-muted/30 px-4 py-2.5 text-xs">
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handleCopyFullArticle}
            className="h-7 text-xs gap-1.5"
            title="Sao chép toàn bộ nội dung"
          >
            {copied ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
            <span>{copied ? 'Đã sao chép' : 'Sao chép'}</span>
          </Button>

          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={isRunningPipeline}
            onClick={handleRunImagePipeline}
            className="h-7 text-xs gap-1.5 text-purple-700 border-purple-200 hover:bg-purple-50 dark:border-purple-900 dark:text-purple-300"
            title="Tự động xử lý ảnh 3 tầng: Ảnh người dùng -> Stock miễn phí -> AI Imagen"
          >
            <RefreshCw className={cn('h-3 w-3', isRunningPipeline && 'animate-spin')} />
            <span>Tối ưu ảnh 3 tầng</span>
          </Button>
        </div>

        <Link
          href={`/content-studio/${article.id}`}
          className="inline-flex items-center gap-1.5 text-primary hover:text-primary/80 font-semibold hover:underline text-xs"
        >
          <span>Mở trong Content Studio</span>
          <ExternalLink className="h-3 w-3" />
        </Link>
      </div>

      {/* Floating or Inline AI Copilot Toolbar if text is selected */}
      {showInlineToolbar && (
        <div className="p-3 bg-muted/40 border-b border-border/80">
          <InChatInlineToolbar
            selectedText={selectedText}
            articleId={article.id}
            targetHeading={activeHeadingForEdit}
            onApplyEdit={handleApplyCopilotEdit}
            onClose={() => setShowInlineToolbar(false)}
          />
        </div>
      )}

      {/* Sections & Media Content Accordion List */}
      <div className="p-4 space-y-3">
        <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground pb-1">
          <span className="flex items-center gap-1.5">
            <Layers className="h-3.5 w-3.5 text-primary" />
            Cấu trúc phân đoạn (Headings & Media)
          </span>
          <span className="text-[11px] font-normal">
            Bôi đen văn bản để gọi AI Copilot
          </span>
        </div>

        {headings.length === 0 ? (
          <div
            className="rounded-xl border border-dashed border-border p-4 text-xs text-muted-foreground leading-relaxed cursor-text selection:bg-purple-200 selection:text-purple-900"
            onMouseUp={() => handleTextSelection()}
          >
            {article.content ? (
              <div
                dangerouslySetInnerHTML={{ __html: article.content }}
                className="prose prose-xs max-w-none text-foreground"
              />
            ) : (
              <p>{article.excerpt || 'Nội dung bài viết đang được tạo...'}</p>
            )}
          </div>
        ) : (
          <div className="space-y-2.5">
            {headings.map((sec, idx) => {
              const isExpanded = expandedSections[sec.heading] ?? true;
              const matchingMedia = mediaAssets.filter(
                (m) =>
                  m.heading &&
                  m.heading.toLowerCase().includes(sec.heading.toLowerCase())
              );

              return (
                <div
                  key={idx}
                  className="overflow-hidden rounded-xl border border-border/80 bg-background transition-colors hover:border-border"
                >
                  {/* Section Title Bar */}
                  <div
                    className="flex items-center justify-between p-3 cursor-pointer select-none bg-muted/20 hover:bg-muted/40"
                    onClick={() => toggleSection(sec.heading)}
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <Badge
                        variant="secondary"
                        className="h-5 px-1.5 text-[10px] font-mono uppercase shrink-0"
                      >
                        {sec.level || 'H2'}
                      </Badge>
                      <h4 className="text-xs md:text-sm font-semibold text-foreground truncate">
                        {sec.heading}
                      </h4>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      {matchingMedia.length > 0 && (
                        <Badge
                          variant="outline"
                          className="gap-1 border-emerald-300 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                        >
                          <ImageIcon className="h-3 w-3" />
                          <span>{matchingMedia.length} ảnh WebP</span>
                        </Badge>
                      )}
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        className="h-6 w-6 text-muted-foreground"
                      >
                        {isExpanded ? (
                          <ChevronUp className="h-3.5 w-3.5" />
                        ) : (
                          <ChevronDown className="h-3.5 w-3.5" />
                        )}
                      </Button>
                    </div>
                  </div>

                  {/* Section Expanded Content & Attached Images */}
                  {isExpanded && (
                    <div className="p-3 border-t border-border/60 space-y-3">
                      {/* Section Text Preview with Selection Hook */}
                      <div
                        className="text-xs leading-relaxed text-muted-foreground p-2 rounded-lg bg-muted/20 cursor-text selection:bg-purple-200 selection:text-purple-950"
                        onMouseUp={() => handleTextSelection(sec.heading)}
                      >
                        {sec.preview_text || 'Đoạn phân tích nội dung chuẩn SEO cho tiêu đề này.'}
                      </div>

                      {/* Attached SEO Images for this Section */}
                      {matchingMedia.map((media) => (
                        <div
                          key={media.id}
                          className="overflow-hidden rounded-lg border border-border bg-muted/10 p-2 text-xs space-y-2"
                        >
                          <div className="relative group overflow-hidden rounded-md bg-black/5 aspect-video max-h-48 flex items-center justify-center">
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img
                              src={media.url}
                              alt={media.alt_text}
                              className="h-full w-full object-cover rounded-md"
                            />
                            <button
                              type="button"
                              onClick={() => setPreviewImageUrl(media.url)}
                              className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                              <Maximize2 className="h-5 w-5 text-white" />
                            </button>
                          </div>

                          {/* SEO & Compression Badges */}
                          <div className="flex flex-wrap items-center justify-between gap-1.5 pt-1">
                            <div className="flex flex-wrap items-center gap-1.5">
                              <Badge className="bg-emerald-600 text-white text-[10px] h-4 py-0 gap-1">
                                <ShieldCheck className="h-2.5 w-2.5" />
                                <span>WebP &lt; 150KB</span>
                              </Badge>
                              <Badge variant="outline" className="text-[10px] h-4 py-0 text-muted-foreground">
                                {media.source_type === 'uploaded'
                                  ? 'Ảnh người dùng (Vision)'
                                  : media.source_type === 'ai_generated'
                                  ? 'AI Imagen 3'
                                  : 'Stock miễn phí bản quyền'}
                              </Badge>
                            </div>

                            {media.size_bytes && (
                              <span className="text-[10px] font-mono text-muted-foreground">
                                {(media.size_bytes / 1024).toFixed(1)} KB
                              </span>
                            )}
                          </div>

                          <div className="text-[11px] text-muted-foreground">
                            <span className="font-semibold text-foreground">ALT:</span>{' '}
                            {media.alt_text}
                          </div>

                          {media.caption && (
                            <div className="text-[11px] italic text-muted-foreground">
                              {media.caption}
                            </div>
                          )}
                        </div>
                      ))}

                      {/* Quick Trigger to edit section */}
                      <div className="flex justify-end pt-1">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => {
                            setSelectedText(sec.preview_text || sec.heading);
                            setActiveHeadingForEdit(sec.heading);
                            setShowInlineToolbar(true);
                          }}
                          className="h-6 text-[11px] text-purple-700 hover:text-purple-800 hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-purple-950/40 gap-1"
                        >
                          <Wand2 className="h-3 w-3" />
                          <span>Biên tập đoạn này</span>
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Enlarged Image Preview Modal */}
      {previewImageUrl && (
        <Dialog open={Boolean(previewImageUrl)} onOpenChange={() => setPreviewImageUrl(null)}>
          <DialogContent className="max-w-3xl p-2 bg-background">
            <DialogTitle className="sr-only">Xem ảnh minh họa chi tiết</DialogTitle>
            <div className="relative flex items-center justify-center overflow-hidden rounded-lg">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={previewImageUrl}
                alt="Enlarged Preview"
                className="max-h-[80vh] w-auto object-contain rounded-lg"
              />
            </div>
          </DialogContent>
        </Dialog>
      )}
    </div>
  );
}
