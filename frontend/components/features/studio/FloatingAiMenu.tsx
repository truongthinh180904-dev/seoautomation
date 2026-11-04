'use client';

import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Sparkles,
  Zap,
  TrendingUp,
  Scissors,
  Smile,
  Lightbulb,
  CheckCheck,
  Send,
  Loader2,
  X,
  RotateCcw,
  Coins,
} from 'lucide-react';
import {
  AiAssistAction,
  useAiCopilotAssist,
  AiAssistResult,
} from '@/hooks/useContentStudio';

interface FloatingAiMenuProps {
  articleId: number;
  selectedText: string;
  position?: { top: number; left: number } | null;
  isOpen: boolean;
  onApplyResult: (newText: string, mode: 'replace' | 'insert_after') => void;
  onClose: () => void;
}

const TONE_OPTIONS = [
  { value: 'chuyên nghiệp, đáng tin cậy', label: 'Chuyên nghiệp' },
  { value: 'thân thiện, gần gũi', label: 'Thân thiện' },
  { value: 'thuyết phục, định hướng hành động', label: 'Thuyết phục' },
  { value: 'hài hước, dí dỏm', label: 'Hài hước' },
  { value: 'học thuật, chuẩn xác', label: 'Học thuật' },
];

export function FloatingAiMenu({
  articleId,
  selectedText,
  position,
  isOpen,
  onApplyResult,
  onClose,
}: FloatingAiMenuProps) {
  const [activeTab, setActiveTab] = useState<'actions' | 'custom' | 'tone'>('actions');
  const [customPrompt, setCustomPrompt] = useState('');
  const [selectedTone, setSelectedTone] = useState(TONE_OPTIONS[0].value);
  const [result, setResult] = useState<AiAssistResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const assistMutation = useAiCopilotAssist();

  if (!isOpen || !selectedText.trim()) {
    return null;
  }

  const handleAction = async (action: AiAssistAction, tone?: string, instruction?: string) => {
    setErrorMessage(null);
    try {
      const res = await assistMutation.mutateAsync({
        article_id: articleId,
        action,
        selected_text: selectedText,
        tone: tone || selectedTone,
        instruction: instruction || customPrompt,
      });
      setResult(res);
    } catch (err: unknown) {
      const errorObj = err as { message?: string; response?: { data?: { message?: string } } };
      const msg = errorObj.response?.data?.message || errorObj.message || 'Không thể gọi AI Copilot. Vui lòng thử lại.';
      setErrorMessage(msg);
    }
  };

  const handleApply = (mode: 'replace' | 'insert_after') => {
    if (!result) return;
    onApplyResult(result.result_text, mode);
    handleReset();
    onClose();
  };

  const handleReset = () => {
    setResult(null);
    setErrorMessage(null);
    setCustomPrompt('');
    setActiveTab('actions');
  };

  const menuStyle: React.CSSProperties = position
    ? {
        position: 'absolute',
        top: `${Math.max(10, position.top)}px`,
        left: `${Math.max(10, Math.min(position.left, window.innerWidth - 420))}px`,
        zIndex: 50,
      }
    : {};

  return (
    <div
      style={menuStyle}
      className="w-[380px] bg-popover text-popover-foreground border border-border shadow-2xl rounded-xl p-3 select-none backdrop-blur-md bg-opacity-95 animate-in fade-in zoom-in-95 duration-150"
    >
      {/* Top Header */}
      <div className="flex items-center justify-between pb-2 border-b border-border/60 mb-2.5">
        <div className="flex items-center gap-1.5">
          <Sparkles className="w-4 h-4 text-amber-500 fill-amber-500" />
          <span className="font-semibold text-xs tracking-tight">AI Content Copilot</span>
          <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4 border-amber-500/40 text-amber-600 gap-0.5">
            <Coins className="w-2.5 h-2.5" /> 1 credit
          </Badge>
        </div>
        <button
          type="button"
          onClick={() => {
            handleReset();
            onClose();
          }}
          className="text-muted-foreground hover:text-foreground rounded p-0.5"
        >
          <X className="w-3.5 h-3.5" />
        </button>
      </div>

      {/* Snippet info */}
      <div className="text-[11px] text-muted-foreground line-clamp-1 mb-2 px-1 italic bg-muted/40 py-1 rounded">
        &ldquo;{selectedText}&rdquo;
      </div>

      {/* Error state */}
      {errorMessage && (
        <div className="mb-2 p-2 rounded bg-destructive/10 text-destructive text-xs">
          {errorMessage}
        </div>
      )}

      {/* Loading state */}
      {assistMutation.isPending ? (
        <div className="py-6 flex flex-col items-center justify-center gap-2 text-xs text-muted-foreground">
          <Loader2 className="w-6 h-6 animate-spin text-primary" />
          <span>AI đang phân tích và xử lý đoạn văn...</span>
        </div>
      ) : result ? (
        /* Result Preview state */
        <div className="space-y-2.5">
          <div className="text-xs font-medium text-foreground flex items-center justify-between">
            <span>Kết quả ({result.word_count} từ):</span>
            <Button variant="ghost" size="xs" onClick={handleReset} className="h-6 text-[11px] gap-1">
              <RotateCcw className="w-3 h-3" /> Thử lại
            </Button>
          </div>

          <div className="max-h-[180px] overflow-y-auto p-2.5 rounded-md bg-muted/50 border border-border text-xs leading-relaxed text-foreground select-text whitespace-pre-wrap">
            {result.result_text}
          </div>

          <div className="flex items-center justify-end gap-1.5 pt-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => handleApply('insert_after')}
              className="text-xs h-7"
            >
              Chèn tiếp theo
            </Button>
            <Button
              size="sm"
              onClick={() => handleApply('replace')}
              className="text-xs h-7 gap-1 bg-primary text-primary-foreground hover:bg-primary/90"
            >
              <CheckCheck className="w-3.5 h-3.5" /> Thay thế đoạn chọn
            </Button>
          </div>
        </div>
      ) : (
        /* Action selection state */
        <div className="space-y-2">
          {activeTab === 'actions' && (
            <div className="grid grid-cols-2 gap-1.5">
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleAction('rewrite')}
                className="justify-start text-xs h-8 gap-1.5"
              >
                <Zap className="w-3.5 h-3.5 text-blue-500" />
                Viết lại mượt mà
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleAction('expand')}
                className="justify-start text-xs h-8 gap-1.5"
              >
                <TrendingUp className="w-3.5 h-3.5 text-emerald-500" />
                Mở rộng ý
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleAction('shorten')}
                className="justify-start text-xs h-8 gap-1.5"
              >
                <Scissors className="w-3.5 h-3.5 text-rose-500" />
                Rút gọn súc tích
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => setActiveTab('tone')}
                className="justify-start text-xs h-8 gap-1.5"
              >
                <Smile className="w-3.5 h-3.5 text-purple-500" />
                Đổi giọng văn...
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleAction('add_example')}
                className="justify-start text-xs h-8 gap-1.5"
              >
                <Lightbulb className="w-3.5 h-3.5 text-amber-500" />
                Thêm ví dụ thực tế
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleAction('fix_grammar')}
                className="justify-start text-xs h-8 gap-1.5"
              >
                <CheckCheck className="w-3.5 h-3.5 text-cyan-500" />
                Sửa ngữ pháp & dấu
              </Button>
            </div>
          )}

          {activeTab === 'tone' && (
            <div className="space-y-2">
              <div className="text-[11px] font-medium text-muted-foreground">Chọn phong cách giọng văn:</div>
              <div className="grid grid-cols-2 gap-1">
                {TONE_OPTIONS.map((t) => (
                  <Button
                    key={t.value}
                    variant={selectedTone === t.value ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => {
                      setSelectedTone(t.value);
                      handleAction('change_tone', t.value);
                    }}
                    className="text-xs h-7 justify-start"
                  >
                    {t.label}
                  </Button>
                ))}
              </div>
              <div className="flex justify-start pt-1">
                <Button variant="ghost" size="xs" onClick={() => setActiveTab('actions')} className="text-[11px] h-6">
                  Quay lại
                </Button>
              </div>
            </div>
          )}

          {/* Custom Prompt Toggle / Input */}
          <div className="pt-1.5 border-t border-border/60">
            {activeTab === 'custom' ? (
              <div className="space-y-1.5">
                <div className="flex items-center gap-1.5">
                  <Input
                    placeholder="Nhập yêu cầu tùy chỉnh cho AI..."
                    value={customPrompt}
                    onChange={(e) => setCustomPrompt(e.target.value)}
                    className="h-7 text-xs flex-1"
                    onKeyDown={(e) => e.key === 'Enter' && customPrompt.trim() && handleAction('custom', undefined, customPrompt)}
                    autoFocus
                  />
                  <Button
                    size="xs"
                    disabled={!customPrompt.trim() || assistMutation.isPending}
                    onClick={() => handleAction('custom', undefined, customPrompt)}
                    className="h-7 px-2.5"
                  >
                    <Send className="w-3 h-3" />
                  </Button>
                </div>
                <Button variant="ghost" size="xs" onClick={() => setActiveTab('actions')} className="text-[11px] h-5 px-1 text-muted-foreground">
                  Hủy yêu cầu tùy chỉnh
                </Button>
              </div>
            ) : (
              <button
                type="button"
                onClick={() => setActiveTab('custom')}
                className="w-full text-left text-[11px] text-muted-foreground hover:text-foreground flex items-center justify-between py-1 px-1 rounded hover:bg-muted/50 transition-colors"
              >
                <span>Yêu cầu tùy chỉnh khác...</span>
                <Sparkles className="w-3 h-3 text-primary" />
              </button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
