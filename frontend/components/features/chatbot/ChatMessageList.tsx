'use client';

import React, { useEffect, useRef, useState } from 'react';
import { ChatMessage, ChatMessageItem } from './ChatMessageItem';
import { QuickPromptPills } from './QuickPromptPills';
import { Sparkles, ArrowDown, Bot, ShieldCheck, Zap, Search, PenTool, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ChatMessageListProps {
  messages: ChatMessage[];
  isLoading?: boolean;
  userName?: string;
  websiteName?: string;
  onSelectPrompt?: (prompt: string) => void;
  onApproveAction?: (actionId?: string) => void;
  onRejectAction?: (actionId?: string) => void;
  className?: string;
}

export function ChatMessageList({
  messages,
  isLoading = false,
  userName = 'Bạn',
  websiteName,
  onSelectPrompt,
  onApproveAction,
  onRejectAction,
  className,
}: ChatMessageListProps) {
  const bottomRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [showScrollBottom, setShowScrollBottom] = useState(false);

  // Auto-scroll on new messages or loading state change
  useEffect(() => {
    if (!showScrollBottom) {
      bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages, isLoading, showScrollBottom]);

  // Handle user scroll to toggle the "Scroll to Bottom" floating button
  const handleScroll = () => {
    if (!containerRef.current) return;
    const { scrollTop, scrollHeight, clientHeight } = containerRef.current;
    const isScrolledUp = scrollHeight - scrollTop - clientHeight > 150;
    setShowScrollBottom(isScrolledUp);
  };

  const scrollToBottom = () => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    setShowScrollBottom(false);
  };

  return (
    <div className={cn('relative flex-1 overflow-hidden', className)}>
      <div
        ref={containerRef}
        onScroll={handleScroll}
        className="h-full overflow-y-auto px-4 py-4 md:px-6 md:py-6 space-y-4 scroll-smooth"
      >
        {/* Empty State / Welcome Screen */}
        {messages.length === 0 ? (
          <div className="flex min-h-[70vh] flex-col items-center justify-center text-center max-w-2xl mx-auto px-4 py-8">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-tr from-purple-600 via-indigo-600 to-blue-500 text-white shadow-lg ring-4 ring-purple-100 dark:ring-purple-950/50">
              <Sparkles className="h-8 w-8 animate-pulse" />
            </div>

            <h2 className="text-xl md:text-2xl font-bold tracking-tight text-foreground">
              Aura SEO Assistant
            </h2>
            <p className="mt-2 text-sm text-muted-foreground leading-relaxed">
              Trung tâm điều hành AI SEO & Tự động hóa nội dung đa phương thức.
              {websiteName && (
                <span className="block mt-1 font-medium text-primary">
                  Website đang kết nối: <u>{websiteName}</u>
                </span>
              )}
            </p>

            {/* Capability Feature Cards */}
            <div className="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-3 w-full text-left">
              <div className="rounded-xl border border-border/80 bg-card/60 p-3.5 shadow-2xs hover:border-primary/50 transition-colors">
                <div className="flex items-center gap-2 font-semibold text-xs text-foreground mb-1">
                  <div className="p-1 rounded-md bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                    <Search className="h-3.5 w-3.5" />
                  </div>
                  Nghiên cứu & Đối thủ Top 3
                </div>
                <p className="text-xs text-muted-foreground">
                  Phân tích Top 3 SERP đối thủ, lấy #1 làm chuẩn mật độ LSI và cấu trúc heading.
                </p>
              </div>

              <div className="rounded-xl border border-border/80 bg-card/60 p-3.5 shadow-2xs hover:border-primary/50 transition-colors">
                <div className="flex items-center gap-2 font-semibold text-xs text-foreground mb-1">
                  <div className="p-1 rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    <PenTool className="h-3.5 w-3.5" />
                  </div>
                  Viết bài & Xử lý ảnh Vision
                </div>
                <p className="text-xs text-muted-foreground">
                  Đọc hiểu ảnh đính kèm bằng Vision AI, tự động ghim vào H2/H3 kèm nén WebP &lt;150KB.
                </p>
              </div>

              <div className="rounded-xl border border-border/80 bg-card/60 p-3.5 shadow-2xs hover:border-primary/50 transition-colors">
                <div className="flex items-center gap-2 font-semibold text-xs text-foreground mb-1">
                  <div className="p-1 rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                  </div>
                  Chấm điểm SEO 100 Điểm
                </div>
                <p className="text-xs text-muted-foreground">
                  Đánh giá toàn diện 100 tiêu chí SEO on-page không tốn credit, gợi ý internal link SILO.
                </p>
              </div>

              <div className="rounded-xl border border-border/80 bg-card/60 p-3.5 shadow-2xs hover:border-primary/50 transition-colors">
                <div className="flex items-center gap-2 font-semibold text-xs text-foreground mb-1">
                  <div className="p-1 rounded-md bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                    <ShieldCheck className="h-3.5 w-3.5" />
                  </div>
                  Human-in-the-Loop Gate
                </div>
                <p className="text-xs text-muted-foreground">
                  Bắt buộc xác nhận trước khi đăng WordPress hoặc trừ lượng lớn Credit.
                </p>
              </div>
            </div>

            {/* Quick Prompt Starters */}
            {onSelectPrompt && (
              <div className="mt-8 w-full">
                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2.5">
                  Gợi ý câu lệnh nhanh
                </p>
                <QuickPromptPills onSelectPrompt={onSelectPrompt} />
              </div>
            )}
          </div>
        ) : (
          <div className="flex flex-col space-y-2">
            {messages.map((message) => (
              <ChatMessageItem
                key={message.uuid || message.id}
                message={message}
                userName={userName}
                onApproveAction={onApproveAction}
                onRejectAction={onRejectAction}
              />
            ))}

            {/* Typing / Loading indicator */}
            {isLoading && (
              <div className="flex w-full gap-3 py-3 items-start animate-in fade-in-50 duration-300">
                <div className="shrink-0 pt-0.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-full border border-purple-200 bg-gradient-to-tr from-purple-600 via-indigo-600 to-blue-500 text-white shadow-xs">
                    <Sparkles className="h-4 w-4 animate-spin" />
                  </div>
                </div>
                <div className="rounded-2xl rounded-tl-xs border border-border bg-card px-4 py-3 shadow-2xs">
                  <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span className="font-medium text-foreground">
                      Aura đang xử lý
                    </span>
                    <span className="flex gap-1">
                      <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-purple-600 [animation-delay:-0.3s]" />
                      <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-600 [animation-delay:-0.15s]" />
                      <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-blue-600" />
                    </span>
                  </div>
                </div>
              </div>
            )}

            <div ref={bottomRef} className="h-2" />
          </div>
        )}
      </div>

      {/* Floating Scroll to Bottom Button */}
      {showScrollBottom && (
        <Button
          type="button"
          variant="secondary"
          size="icon-sm"
          onClick={scrollToBottom}
          className="absolute bottom-4 right-6 shadow-md rounded-full bg-background/90 backdrop-blur-xs border border-border hover:bg-background z-20"
          title="Cuộn xuống tin nhắn mới nhất"
        >
          <ArrowDown className="h-4 w-4" />
          <span className="sr-only">Scroll to bottom</span>
        </Button>
      )}
    </div>
  );
}
