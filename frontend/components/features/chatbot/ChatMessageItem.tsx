'use client';

import React, { useState } from 'react';
import {
  Bot,
  User,
  Copy,
  Check,
  Sparkles,
  FileText,
  CheckCircle2,
  Clock,
  Wrench,
  ChevronDown,
  ChevronUp,
  Maximize2,
  AlertCircle,
  ExternalLink,
  TrendingUp,
  BarChart3,
  Search,
  Lightbulb,
  RefreshCw,
} from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

export interface ToolCallInfo {
  id: string;
  tool: string;
  parameters?: Record<string, unknown>;
  status: 'pending' | 'running' | 'completed' | 'failed';
  result?: Record<string, unknown>;
}

export interface UploadedImageInfo {
  id: string;
  previewUrl: string;
  name: string;
  size?: number;
  mimeType?: string;
}

export interface ChatMessage {
  id: string;
  uuid: string;
  role: 'user' | 'assistant' | 'system' | 'tool';
  content: string;
  createdAt: string;
  images?: UploadedImageInfo[];
  toolCalls?: ToolCallInfo[];
  metadata?: {
    actionType?: 'confirmation_needed' | 'task_progress' | 'article_preview';
    taskId?: string;
    taskProgress?: number;
    taskStep?: string;
    articleId?: number;
    articleTitle?: string;
    seoScore?: number;
    wordCount?: number;
    creditCost?: number;
    targetWebsite?: string;
    gscData?: {
      clicks?: number;
      impressions?: number;
      ctr?: number;
      position?: number;
      dateRange?: string;
    };
    rankData?: {
      keyword?: string;
      position?: number | null;
      changeLabel?: string;
      bestPosition?: number | null;
    };
    insightData?: {
      title?: string;
      severity?: 'info' | 'warning' | 'critical';
      opportunityScore?: number;
      suggestedAction?: string;
    };
    diffData?: {
      scoreBefore?: number;
      scoreAfter?: number;
      scoreDelta?: number;
      wordCountDelta?: number;
    };
  };
}

interface ChatMessageItemProps {
  message: ChatMessage;
  userName?: string;
  onApproveAction?: (actionId?: string) => void;
  onRejectAction?: (actionId?: string) => void;
}

// Helper to render inline markdown styles (**bold**, *italic*, `code`, [link](url))
function renderInlineMarkdown(text: string): React.ReactNode {
  // Regex to split by markdown tokens
  const parts = text.split(/(\*\*.*?\*\*|\*.*?\*|`.*?`|\[.*?\]\(.*?\))/g);

  return parts.map((part, index) => {
    if (!part) return null;

    if (part.startsWith('**') && part.endsWith('**') && part.length >= 4) {
      return (
        <strong key={index} className="font-semibold text-foreground">
          {part.slice(2, -2)}
        </strong>
      );
    }

    if (part.startsWith('*') && part.endsWith('*') && part.length >= 2) {
      return (
        <em key={index} className="italic">
          {part.slice(1, -1)}
        </em>
      );
    }

    if (part.startsWith('`') && part.endsWith('`') && part.length >= 2) {
      return (
        <code
          key={index}
          className="rounded bg-muted px-1.5 py-0.5 font-mono text-[12px] text-primary"
        >
          {part.slice(1, -1)}
        </code>
      );
    }

    const linkMatch = part.match(/^\[(.*?)\]\((.*?)\)$/);
    if (linkMatch) {
      return (
        <a
          key={index}
          href={linkMatch[2]}
          target="_blank"
          rel="noopener noreferrer"
          className="font-medium text-primary underline underline-offset-2 hover:text-primary/80"
        >
          {linkMatch[1]}
        </a>
      );
    }

    return <span key={index}>{part}</span>;
  });
}

function CodeBlock({ code, language }: { code: string; language: string }) {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(code);
    setCopied(true);
    toast.success('Đã sao chép đoạn mã vào clipboard');
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="relative my-3 overflow-hidden rounded-lg border border-slate-800 bg-slate-950 text-slate-100 shadow-md">
      <div className="flex items-center justify-between border-b border-slate-800 bg-slate-900/90 px-3 py-1.5 text-xs text-slate-400">
        <span className="font-mono uppercase font-semibold text-[11px] tracking-wider text-slate-300">
          {language || 'code'}
        </span>
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          onClick={handleCopy}
          className="size-6 text-slate-400 hover:bg-slate-800 hover:text-slate-100"
          title="Sao chép mã"
        >
          {copied ? <Check className="h-3 w-3 text-emerald-400" /> : <Copy className="h-3 w-3" />}
          <span className="sr-only">Copy</span>
        </Button>
      </div>
      <div className="overflow-x-auto p-3 text-xs font-mono leading-relaxed">
        <pre>{code}</pre>
      </div>
    </div>
  );
}

function MarkdownTable({ lines }: { lines: string[] }) {
  if (lines.length < 2) return null;

  const headerLine = lines[0];
  const parseRow = (line: string) =>
    line
      .trim()
      .replace(/^\|/, '')
      .replace(/\|$/, '')
      .split('|')
      .map((c) => c.trim());

  const headers = parseRow(headerLine);
  // Row 1 is divider (|---|---|)
  const dataRows = lines.slice(2).map(parseRow);

  return (
    <div className="my-3 overflow-x-auto rounded-lg border border-border shadow-2xs">
      <table className="w-full text-left text-xs border-collapse">
        <thead className="bg-muted/70 text-foreground font-semibold border-b border-border">
          <tr>
            {headers.map((header, idx) => (
              <th key={idx} className="p-2.5 whitespace-nowrap font-medium text-foreground">
                {renderInlineMarkdown(header)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {dataRows.map((row, rowIdx) => (
            <tr key={rowIdx} className="hover:bg-muted/30 transition-colors">
              {row.map((cell, cellIdx) => (
                <td key={cellIdx} className="p-2.5 text-muted-foreground whitespace-normal">
                  {renderInlineMarkdown(cell)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function RichContentRenderer({ content }: { content: string }) {
  const lines = content.split('\n');
  const elements: React.ReactNode[] = [];

  let inCodeBlock = false;
  let codeBuffer: string[] = [];
  let codeLanguage = '';

  let inTable = false;
  let tableBuffer: string[] = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    // Check for fenced code block toggle
    if (line.trim().startsWith('```')) {
      if (inCodeBlock) {
        // End code block
        elements.push(
          <CodeBlock
            key={`code-${i}`}
            code={codeBuffer.join('\n')}
            language={codeLanguage}
          />
        );
        inCodeBlock = false;
        codeBuffer = [];
        codeLanguage = '';
      } else {
        // Start code block
        inCodeBlock = true;
        codeLanguage = line.trim().slice(3).trim();
        codeBuffer = [];
      }
      continue;
    }

    if (inCodeBlock) {
      codeBuffer.push(line);
      continue;
    }

    // Check for markdown table line
    const isTableLine = line.trim().startsWith('|') && line.trim().endsWith('|');
    if (isTableLine) {
      inTable = true;
      tableBuffer.push(line);
      continue;
    } else if (inTable) {
      // End table block
      elements.push(<MarkdownTable key={`table-${i}`} lines={tableBuffer} />);
      inTable = false;
      tableBuffer = [];
    }

    // Empty lines
    if (line.trim() === '') {
      elements.push(<div key={`blank-${i}`} className="h-2" />);
      continue;
    }

    // Headers
    if (line.startsWith('#### ')) {
      elements.push(
        <h4 key={`h4-${i}`} className="mt-3 mb-1 text-xs font-bold tracking-tight text-foreground">
          {renderInlineMarkdown(line.slice(5))}
        </h4>
      );
      continue;
    }
    if (line.startsWith('### ')) {
      elements.push(
        <h3 key={`h3-${i}`} className="mt-3.5 mb-1.5 text-sm font-bold tracking-tight text-foreground">
          {renderInlineMarkdown(line.slice(4))}
        </h3>
      );
      continue;
    }
    if (line.startsWith('## ')) {
      elements.push(
        <h2 key={`h2-${i}`} className="mt-4 mb-2 text-base font-bold tracking-tight text-foreground border-b pb-1">
          {renderInlineMarkdown(line.slice(3))}
        </h2>
      );
      continue;
    }
    if (line.startsWith('# ')) {
      elements.push(
        <h1 key={`h1-${i}`} className="mt-4 mb-2 text-lg font-extrabold tracking-tight text-foreground border-b pb-1">
          {renderInlineMarkdown(line.slice(2))}
        </h1>
      );
      continue;
    }

    // Blockquotes
    if (line.startsWith('> ')) {
      elements.push(
        <blockquote
          key={`quote-${i}`}
          className="my-2 border-l-3 border-primary/60 bg-muted/30 py-1.5 pl-3 pr-2 text-xs italic text-muted-foreground rounded-r"
        >
          {renderInlineMarkdown(line.slice(2))}
        </blockquote>
      );
      continue;
    }

    // Unordered lists
    if (line.trim().startsWith('- ') || line.trim().startsWith('* ')) {
      const bulletText = line.trim().slice(2);
      elements.push(
        <div key={`li-${i}`} className="flex items-start gap-2 my-1 text-xs leading-relaxed text-foreground">
          <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary/70" />
          <div className="flex-1">{renderInlineMarkdown(bulletText)}</div>
        </div>
      );
      continue;
    }

    // Numbered lists
    const numMatch = line.trim().match(/^(\d+)\.\s+(.*)$/);
    if (numMatch) {
      elements.push(
        <div key={`numli-${i}`} className="flex items-start gap-2 my-1 text-xs leading-relaxed text-foreground">
          <span className="font-semibold text-primary shrink-0 min-w-[16px] text-right">
            {numMatch[1]}.
          </span>
          <div className="flex-1">{renderInlineMarkdown(numMatch[2])}</div>
        </div>
      );
      continue;
    }

    // Default paragraph
    elements.push(
      <p key={`p-${i}`} className="my-1 text-xs md:text-sm leading-relaxed text-foreground">
        {renderInlineMarkdown(line)}
      </p>
    );
  }

  // Close open table if exists at the end
  if (inTable && tableBuffer.length > 0) {
    elements.push(<MarkdownTable key="table-end" lines={tableBuffer} />);
  }

  // Close open code block if exists at the end
  if (inCodeBlock && codeBuffer.length > 0) {
    elements.push(
      <CodeBlock
        key="code-end"
        code={codeBuffer.join('\n')}
        language={codeLanguage}
      />
    );
  }

  return <div className="space-y-1">{elements}</div>;
}

export function ChatMessageItem({
  message,
  userName = 'Bạn',
  onApproveAction,
  onRejectAction,
}: ChatMessageItemProps) {
  const [copied, setCopied] = useState(false);
  const [showToolCalls, setShowToolCalls] = useState(false);
  const [previewImage, setPreviewImage] = useState<string | null>(null);

  const isUser = message.role === 'user';
  const isAssistant = message.role === 'assistant';
  const isTool = message.role === 'tool';

  const handleCopyMessage = () => {
    navigator.clipboard.writeText(message.content);
    setCopied(true);
    toast.success('Đã sao chép toàn bộ nội dung tin nhắn');
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div
      className={cn(
        'group relative flex w-full gap-3 py-3 transition-colors',
        isUser ? 'flex-row-reverse' : 'flex-row'
      )}
    >
      {/* Avatar */}
      <div className="shrink-0 pt-0.5">
        {isUser ? (
          <Avatar className="h-8 w-8 border border-primary/20 bg-primary text-primary-foreground shadow-2xs">
            <AvatarFallback className="bg-primary text-primary-foreground text-xs font-semibold">
              <User className="h-4 w-4" />
            </AvatarFallback>
          </Avatar>
        ) : isAssistant ? (
          <Avatar className="h-8 w-8 border border-purple-200 bg-gradient-to-tr from-purple-600 via-indigo-600 to-blue-500 text-white shadow-xs">
            <AvatarFallback className="bg-transparent text-white">
              <Sparkles className="h-4 w-4 animate-pulse" />
            </AvatarFallback>
          </Avatar>
        ) : (
          <Avatar className="h-8 w-8 border bg-muted text-muted-foreground">
            <AvatarFallback className="text-xs">
              <Wrench className="h-4 w-4" />
            </AvatarFallback>
          </Avatar>
        )}
      </div>

      {/* Message Bubble Body */}
      <div
        className={cn(
          'flex flex-col space-y-1.5 max-w-[85%] md:max-w-[80%]',
          isUser ? 'items-end' : 'items-start'
        )}
      >
        {/* Author & Timestamp Bar */}
        <div className="flex items-center gap-2 text-[11px] text-muted-foreground px-1">
          <span className="font-semibold text-foreground">
            {isUser ? userName : isAssistant ? 'Aura SEO Specialist' : 'System Tool'}
          </span>
          <span>•</span>
          <span>{message.createdAt}</span>
          {isAssistant && (
            <Badge variant="outline" className="text-[10px] py-0 px-1.5 h-4 border-purple-200 text-purple-700 bg-purple-50/50">
              AI Copilot
            </Badge>
          )}
        </div>

        {/* Message Card Container */}
        <div
          className={cn(
            'relative rounded-2xl px-4 py-3 shadow-2xs text-sm transition-all',
            isUser
              ? 'bg-primary text-primary-foreground rounded-tr-xs selection:bg-white/20'
              : 'bg-card border border-border text-card-foreground rounded-tl-xs'
          )}
        >
          {/* Attached Images Grid */}
          {message.images && message.images.length > 0 && (
            <div className="mb-3 flex flex-wrap gap-2">
              {message.images.map((img) => (
                <div
                  key={img.id}
                  className="group/img relative overflow-hidden rounded-lg border border-border/70 bg-black/5 cursor-pointer"
                  onClick={() => setPreviewImage(img.previewUrl)}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={img.previewUrl}
                    alt={img.name}
                    className="h-28 w-28 md:h-36 md:md:w-36 object-cover transition-transform duration-200 group-hover/img:scale-105"
                  />
                  <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/img:opacity-100">
                    <Maximize2 className="h-5 w-5 text-white" />
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Main Text Content */}
          {isUser ? (
            <p className="whitespace-pre-wrap leading-relaxed text-xs md:text-sm">
              {message.content}
            </p>
          ) : (
            <RichContentRenderer content={message.content} />
          )}

          {/* Tool Calls Summary (Collapsible) */}
          {message.toolCalls && message.toolCalls.length > 0 && (
            <div className="mt-3 border-t border-border/80 pt-2 text-xs">
              <button
                type="button"
                onClick={() => setShowToolCalls(!showToolCalls)}
                className="flex items-center gap-1.5 text-muted-foreground hover:text-foreground font-medium"
              >
                <Wrench className="h-3.5 w-3.5 text-purple-600" />
                <span>
                  {message.toolCalls.length} công cụ SEO đã thực thi
                </span>
                {showToolCalls ? (
                  <ChevronUp className="h-3 w-3" />
                ) : (
                  <ChevronDown className="h-3 w-3" />
                )}
              </button>

              {showToolCalls && (
                <div className="mt-2 space-y-1.5 rounded-lg bg-muted/50 p-2.5">
                  {message.toolCalls.map((tc) => (
                    <div
                      key={tc.id}
                      className="flex items-center justify-between text-[11px]"
                    >
                      <span className="font-mono font-medium text-foreground">
                        {tc.tool}
                      </span>
                      <Badge
                        variant={tc.status === 'completed' ? 'default' : 'secondary'}
                        className="text-[10px] h-4 py-0"
                      >
                        {tc.status === 'completed' ? 'Hoàn thành' : tc.status}
                      </Badge>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Rich Metadata Card: Article Preview */}
          {message.metadata?.articleTitle && (
            <div className="mt-3 rounded-xl border border-blue-200 bg-blue-50/50 p-3 text-slate-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
              <div className="flex items-start justify-between gap-2">
                <div className="space-y-1">
                  <div className="flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-300">
                    <FileText className="h-3.5 w-3.5" />
                    <span>Bài viết đã khởi tạo</span>
                  </div>
                  <h4 className="text-sm font-bold text-foreground">
                    {message.metadata.articleTitle}
                  </h4>
                </div>
                <div className="flex gap-1.5">
                  {message.metadata.seoScore !== undefined && (
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                      SEO {message.metadata.seoScore}/100
                    </Badge>
                  )}
                  {message.metadata.wordCount !== undefined && (
                    <Badge variant="outline" className="bg-purple-50 text-purple-700 border-purple-200 text-xs">
                      {message.metadata.wordCount} từ
                    </Badge>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Rich Metadata Card: Task Progress */}
          {message.metadata?.taskProgress !== undefined && (
            <div className="mt-3 space-y-2 rounded-xl border border-border bg-muted/40 p-3">
              <div className="flex items-center justify-between text-xs">
                <span className="font-medium text-foreground flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5 text-purple-600 animate-spin" />
                  {message.metadata.taskStep || 'Đang xử lý tác vụ ngầm...'}
                </span>
                <span className="font-bold text-primary">
                  {message.metadata.taskProgress}%
                </span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full bg-primary transition-all duration-300 rounded-full"
                  style={{ width: `${message.metadata.taskProgress}%` }}
                />
              </div>
            </div>
          )}

          {/* Rich Metadata Card: GSC Performance Widget */}
          {message.metadata?.gscData && (
            <div className="mt-3 space-y-2 rounded-xl border border-sky-200 bg-sky-50/50 p-3 text-slate-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300">
                  <BarChart3 className="h-4 w-4" />
                  <span>Google Search Console Data</span>
                </div>
                {message.metadata.gscData.dateRange && (
                  <span className="text-[10px] text-muted-foreground">
                    {message.metadata.gscData.dateRange}
                  </span>
                )}
              </div>
              <div className="grid grid-cols-2 gap-2 pt-1 sm:grid-cols-4">
                <div className="rounded-lg bg-background/80 p-2 border border-sky-100 dark:border-sky-900/60">
                  <div className="text-[10px] text-muted-foreground">Clicks</div>
                  <div className="text-sm font-bold text-foreground">
                    {message.metadata.gscData.clicks?.toLocaleString() ?? 0}
                  </div>
                </div>
                <div className="rounded-lg bg-background/80 p-2 border border-sky-100 dark:border-sky-900/60">
                  <div className="text-[10px] text-muted-foreground">Impressions</div>
                  <div className="text-sm font-bold text-foreground">
                    {message.metadata.gscData.impressions?.toLocaleString() ?? 0}
                  </div>
                </div>
                <div className="rounded-lg bg-background/80 p-2 border border-sky-100 dark:border-sky-900/60">
                  <div className="text-[10px] text-muted-foreground">CTR TB</div>
                  <div className="text-sm font-bold text-foreground">
                    {typeof message.metadata.gscData.ctr === 'number'
                      ? `${(message.metadata.gscData.ctr * 100).toFixed(1)}%`
                      : '0%'}
                  </div>
                </div>
                <div className="rounded-lg bg-background/80 p-2 border border-sky-100 dark:border-sky-900/60">
                  <div className="text-[10px] text-muted-foreground">Vị trí TB</div>
                  <div className="text-sm font-bold text-foreground">
                    {typeof message.metadata.gscData.position === 'number'
                      ? message.metadata.gscData.position.toFixed(1)
                      : 'N/A'}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Rich Metadata Card: Rank Tracker Widget */}
          {message.metadata?.rankData && (
            <div className="mt-3 space-y-2 rounded-xl border border-indigo-200 bg-indigo-50/50 p-3 text-slate-800 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                  <TrendingUp className="h-4 w-4" />
                  <span>Xếp hạng từ khóa</span>
                </div>
                {message.metadata.rankData.changeLabel && (
                  <Badge variant="outline" className="text-[10px] border-indigo-200 bg-indigo-100/60 text-indigo-800 dark:text-indigo-200">
                    {message.metadata.rankData.changeLabel}
                  </Badge>
                )}
              </div>
              <div className="flex items-center justify-between rounded-lg bg-background/80 p-2.5 border border-indigo-100 dark:border-indigo-900/60">
                <div className="space-y-0.5">
                  <span className="text-[11px] text-muted-foreground">Từ khóa theo dõi</span>
                  <div className="text-xs font-bold text-foreground">
                    {message.metadata.rankData.keyword || 'Chưa xác định'}
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="text-right">
                    <span className="text-[10px] text-muted-foreground">Hiện tại</span>
                    <div className="text-sm font-extrabold text-indigo-600 dark:text-indigo-400">
                      {message.metadata.rankData.position ? `#${message.metadata.rankData.position}` : 'N/A'}
                    </div>
                  </div>
                  {message.metadata.rankData.bestPosition && (
                    <div className="text-right border-l pl-3 border-border">
                      <span className="text-[10px] text-muted-foreground">Tốt nhất</span>
                      <div className="text-xs font-semibold text-muted-foreground">
                        #{message.metadata.rankData.bestPosition}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Rich Metadata Card: SEO Intelligence Widget */}
          {message.metadata?.insightData && (
            <div className="mt-3 space-y-2 rounded-xl border border-amber-200 bg-amber-50/40 p-3 text-slate-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-amber-800 dark:text-amber-300">
                  <Lightbulb className="h-4 w-4 text-amber-600" />
                  <span>Cơ hội SEO thông minh</span>
                </div>
                <div className="flex items-center gap-1.5">
                  {message.metadata.insightData.opportunityScore !== undefined && (
                    <Badge variant="outline" className="text-[10px] bg-amber-100/70 text-amber-800 border-amber-300">
                      Điểm: {message.metadata.insightData.opportunityScore}/100
                    </Badge>
                  )}
                  {message.metadata.insightData.severity && (
                    <Badge
                      variant="outline"
                      className={cn(
                        'text-[10px] uppercase font-bold',
                        message.metadata.insightData.severity === 'critical'
                          ? 'border-rose-300 bg-rose-100 text-rose-800'
                          : message.metadata.insightData.severity === 'warning'
                          ? 'border-amber-300 bg-amber-100 text-amber-800'
                          : 'border-blue-300 bg-blue-100 text-blue-800'
                      )}
                    >
                      {message.metadata.insightData.severity}
                    </Badge>
                  )}
                </div>
              </div>
              {message.metadata.insightData.title && (
                <div className="text-xs font-bold text-foreground">
                  {message.metadata.insightData.title}
                </div>
              )}
              {message.metadata.insightData.suggestedAction && (
                <p className="text-[11px] leading-relaxed text-muted-foreground bg-background/70 p-2 rounded-lg border border-amber-100 dark:border-amber-900/50">
                  <strong className="text-foreground">Đề xuất: </strong>
                  {message.metadata.insightData.suggestedAction}
                </p>
              )}
            </div>
          )}

          {/* Rich Metadata Card: Content Refresh Diff Widget */}
          {message.metadata?.diffData && (
            <div className="mt-3 space-y-2 rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 text-slate-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                  <RefreshCw className="h-4 w-4" />
                  <span>Kết quả làm mới nội dung (Content Refresh)</span>
                </div>
                {message.metadata.diffData.scoreDelta !== undefined && (
                  <Badge variant="outline" className="text-[10px] bg-emerald-100 text-emerald-800 border-emerald-300 font-bold">
                    +{message.metadata.diffData.scoreDelta} Điểm SEO
                  </Badge>
                )}
              </div>
              <div className="grid grid-cols-2 gap-2 pt-1">
                <div className="rounded-lg bg-background/80 p-2 border border-emerald-100 dark:border-emerald-900/60">
                  <div className="text-[10px] text-muted-foreground">Điểm SEO ước tính</div>
                  <div className="text-xs font-bold text-foreground flex items-center gap-1 mt-0.5">
                    <span className="text-muted-foreground">{message.metadata.diffData.scoreBefore ?? 0}</span>
                    <span>→</span>
                    <span className="text-emerald-600 dark:text-emerald-400 font-extrabold">{message.metadata.diffData.scoreAfter ?? 0}/100</span>
                  </div>
                </div>
                <div className="rounded-lg bg-background/80 p-2 border border-emerald-100 dark:border-emerald-900/60">
                  <div className="text-[10px] text-muted-foreground">Chênh lệch số từ</div>
                  <div className="text-xs font-bold text-foreground mt-0.5">
                    {message.metadata.diffData.wordCountDelta !== undefined
                      ? `${message.metadata.diffData.wordCountDelta >= 0 ? '+' : ''}${message.metadata.diffData.wordCountDelta} từ`
                      : '0 từ'}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Rich Metadata Card: Confirmation Needed (Human-in-the-Loop) */}
          {message.metadata?.actionType === 'confirmation_needed' && (
            <div className="mt-3 space-y-3 rounded-xl border border-amber-300 bg-amber-50/70 p-3 dark:border-amber-900/60 dark:bg-amber-950/40">
              <div className="flex items-center gap-2 text-xs font-semibold text-amber-800 dark:text-amber-300">
                <AlertCircle className="h-4 w-4 text-amber-600" />
                <span>Cần bạn xác nhận trước khi thực hiện</span>
              </div>
              <p className="text-xs text-muted-foreground">
                Hành động này sẽ tác động lên website đích{' '}
                <strong className="text-foreground">
                  {message.metadata.targetWebsite || 'Website mặc định'}
                </strong>{' '}
                và tiêu tốn{' '}
                <strong className="text-foreground">
                  {message.metadata.creditCost ?? 0} Credits
                </strong>
                .
              </p>
              <div className="flex items-center gap-2 pt-1">
                <Button
                  size="sm"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 h-7 text-xs"
                  onClick={() => onApproveAction?.(message.uuid)}
                >
                  <CheckCircle2 className="h-3.5 w-3.5" />
                  Xác nhận thực hiện
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  className="h-7 text-xs border-amber-300 text-amber-800 hover:bg-amber-100"
                  onClick={() => onRejectAction?.(message.uuid)}
                >
                  Hủy bỏ
                </Button>
              </div>
            </div>
          )}
        </div>

        {/* Action Toolbar on Hover */}
        <div
          className={cn(
            'flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 px-1',
            isUser ? 'justify-end' : 'justify-start'
          )}
        >
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="size-6 text-muted-foreground hover:text-foreground"
            onClick={handleCopyMessage}
            title="Sao chép nội dung"
          >
            {copied ? (
              <Check className="h-3 w-3 text-emerald-500" />
            ) : (
              <Copy className="h-3 w-3" />
            )}
            <span className="sr-only">Copy message</span>
          </Button>
        </div>
      </div>

      {/* Image Preview Modal */}
      {previewImage && (
        <Dialog open={Boolean(previewImage)} onOpenChange={() => setPreviewImage(null)}>
          <DialogContent className="max-w-3xl p-2 bg-background">
            <DialogTitle className="sr-only">Xem ảnh chi tiết</DialogTitle>
            <div className="relative flex items-center justify-center overflow-hidden rounded-lg">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={previewImage}
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
