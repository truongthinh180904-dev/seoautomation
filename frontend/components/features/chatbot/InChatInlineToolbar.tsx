'use client';

import React, { useState } from 'react';
import {
  Sparkles,
  RefreshCw,
  Check,
  X,
  FileEdit,
  Maximize2,
  Minimize2,
  CheckCheck,
  Lightbulb,
  Coins,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

export interface InChatInlineToolbarProps {
  selectedText: string;
  articleId?: number;
  targetHeading?: string;
  onApplyEdit: (originalText: string, replacementText: string, action: string) => Promise<void> | void;
  onClose?: () => void;
  className?: string;
}

const COPILOT_ACTIONS = [
  { id: 'rewrite', label: 'Viết lại', icon: RefreshCw, desc: 'Viết lại cuốn hút, giữ nguyên ý chính' },
  { id: 'expand', label: 'Mở rộng', icon: Maximize2, desc: 'Bổ sung luận điểm và chiều sâu' },
  { id: 'shorten', label: 'Rút gọn', icon: Minimize2, desc: 'Súc tích, cô đọng nội dung' },
  { id: 'change_tone', label: 'Đổi giọng văn', icon: FileEdit, desc: 'Chuyển sang phong cách chuyên gia' },
  { id: 'add_example', label: 'Thêm ví dụ', icon: Lightbulb, desc: 'Bổ sung dẫn chứng thực tế minh họa' },
  { id: 'fix_grammar', label: 'Sửa ngữ pháp', icon: CheckCheck, desc: 'Chuẩn hóa câu từ và chính tả' },
] as const;

const TONE_OPTIONS = [
  'Chuyên gia y khoa / Kỹ thuật',
  'Thân thiện, gần gũi',
  'Thuyết phục, chuyển đổi cao',
  'Khách quan, trung lập',
] as const;

export function InChatInlineToolbar({
  selectedText,
  articleId,
  targetHeading,
  onApplyEdit,
  onClose,
  className,
}: InChatInlineToolbarProps) {
  const [activeAction, setActiveAction] = useState<string | null>(null);
  const [selectedTone, setSelectedTone] = useState<string>(TONE_OPTIONS[0]);
  const [showTonePicker, setShowTonePicker] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [isApplying, setIsApplying] = useState<boolean>(false);
  const [suggestion, setSuggestion] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const cleanSelectedText = selectedText.trim();

  const handleExecuteAction = async (actionId: string, toneOverride?: string) => {
    if (!cleanSelectedText) {
      toast.error('Vui lòng chọn đoạn văn bản cần chỉnh sửa');
      return;
    }

    if (actionId === 'change_tone' && !toneOverride && !showTonePicker) {
      setShowTonePicker(true);
      setActiveAction('change_tone');
      return;
    }

    setActiveAction(actionId);
    setIsLoading(true);
    setErrorMessage(null);

    try {
      // Gọi API AI Copilot biên tập trực tiếp
      const response = await fetch('/api/v1/chatbot/content/assist', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          article_id: articleId ?? 1,
          action: actionId,
          selected_text: cleanSelectedText,
          tone: toneOverride || selectedTone,
        }),
      });

      const json = await response.json() as {
        success?: boolean;
        message?: string;
        data?: { result_text?: string };
      };

      if (!response.ok || !json.success || !json.data?.result_text) {
        throw new Error(json.message || 'Không thể xử lý yêu cầu AI Copilot.');
      }

      setSuggestion(json.data.result_text);
      setShowTonePicker(false);
      toast.success('AI Copilot đã hoàn tất gợi ý mới (-1 Credit)');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi kết nối AI Copilot.';
      setErrorMessage(msg);
      toast.error(msg);
    } finally {
      setIsLoading(false);
    }
  };

  const handleApply = async () => {
    if (!suggestion) return;

    setIsApplying(true);
    try {
      await onApplyEdit(cleanSelectedText, suggestion, activeAction || 'rewrite');
      toast.success('Đã cập nhật thay đổi vào bài viết');
      setSuggestion(null);
      onClose?.();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Lỗi khi áp dụng vào bài viết.';
      toast.error(msg);
    } finally {
      setIsApplying(false);
    }
  };

  return (
    <div
      className={cn(
        'w-full overflow-hidden rounded-xl border border-purple-200/80 bg-white/95 p-3 shadow-lg backdrop-blur-md dark:border-purple-900/60 dark:bg-slate-900/95 transition-all',
        className
      )}
    >
      {/* Header Bar */}
      <div className="flex items-center justify-between pb-2 mb-2 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="flex h-6 w-6 items-center justify-center rounded-md bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
            <Sparkles className="h-3.5 w-3.5" />
          </div>
          <span className="text-xs font-semibold text-foreground">
            Aura AI Copilot Toolbar
          </span>
          <Badge
            variant="outline"
            className="flex items-center gap-1 border-amber-300 bg-amber-50/80 text-[10px] font-medium text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
          >
            <Coins className="h-2.5 w-2.5" />
            1 Credit / lượt
          </Badge>
          {targetHeading && (
            <span className="hidden sm:inline text-[11px] text-muted-foreground truncate max-w-[160px]">
              • {targetHeading}
            </span>
          )}
        </div>

        {onClose && (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            onClick={onClose}
            disabled={isLoading || isApplying}
            className="h-6 w-6 text-muted-foreground hover:text-foreground"
            title="Đóng thanh công cụ"
          >
            <X className="h-3.5 w-3.5" />
          </Button>
        )}
      </div>

      {/* Selected Quote Preview */}
      <div className="mb-2.5 max-h-16 overflow-y-auto rounded-lg bg-muted/40 p-2 text-xs italic text-muted-foreground border-l-2 border-purple-400">
        &ldquo;{cleanSelectedText || 'Chưa chọn đoạn văn bản...'}&rdquo;
      </div>

      {/* Action Buttons Row */}
      <div className="flex flex-wrap items-center gap-1.5">
        {COPILOT_ACTIONS.map((action) => {
          const Icon = action.icon;
          const isActive = activeAction === action.id;

          return (
            <Button
              key={action.id}
              type="button"
              variant={isActive ? 'default' : 'outline'}
              size="sm"
              disabled={isLoading || isApplying || !cleanSelectedText}
              onClick={() => handleExecuteAction(action.id)}
              className={cn(
                'h-7 gap-1.5 text-xs transition-all',
                isActive
                  ? 'bg-purple-600 text-white hover:bg-purple-700'
                  : 'hover:border-purple-300 hover:bg-purple-50/50 dark:hover:bg-purple-950/30'
              )}
              title={action.desc}
            >
              <Icon className={cn('h-3.5 w-3.5', isLoading && isActive && 'animate-spin')} />
              <span>{action.label}</span>
            </Button>
          );
        })}
      </div>

      {/* Tone Picker Sub-Menu */}
      {showTonePicker && (
        <div className="mt-2.5 rounded-lg border border-purple-200 bg-purple-50/50 p-2 text-xs dark:border-purple-900 dark:bg-purple-950/30">
          <div className="mb-1.5 font-medium text-foreground">
            Chọn giọng văn chuyên biệt:
          </div>
          <div className="flex flex-wrap gap-1.5">
            {TONE_OPTIONS.map((tone) => (
              <Button
                key={tone}
                type="button"
                variant={selectedTone === tone ? 'secondary' : 'ghost'}
                size="sm"
                className="h-6 text-[11px] px-2"
                onClick={() => {
                  setSelectedTone(tone);
                  handleExecuteAction('change_tone', tone);
                }}
              >
                {tone}
              </Button>
            ))}
          </div>
        </div>
      )}

      {/* Error Feedback */}
      {errorMessage && (
        <div className="mt-2 rounded-lg bg-destructive/10 p-2 text-xs text-destructive">
          {errorMessage}
        </div>
      )}

      {/* AI Suggestion Output Diff/Preview Box */}
      {suggestion && (
        <div className="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
          <div className="flex items-center justify-between text-xs">
            <span className="font-semibold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
              <Check className="h-3.5 w-3.5" />
              Đề xuất từ AI Copilot
            </span>
            <span className="text-[11px] text-muted-foreground">
              {suggestion.split(/\s+/).length} từ
            </span>
          </div>

          <div className="text-xs md:text-sm leading-relaxed text-foreground bg-white/80 dark:bg-slate-900/80 p-2.5 rounded border border-emerald-100 dark:border-emerald-900">
            {suggestion}
          </div>

          <div className="flex items-center justify-end gap-2 pt-1">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setSuggestion(null)}
              disabled={isApplying}
              className="h-7 text-xs"
            >
              Hủy bỏ
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={handleApply}
              disabled={isApplying}
              className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
            >
              <Check className={cn('h-3.5 w-3.5', isApplying && 'animate-spin')} />
              <span>{isApplying ? 'Đang lưu...' : 'Áp dụng vào bài viết'}</span>
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
