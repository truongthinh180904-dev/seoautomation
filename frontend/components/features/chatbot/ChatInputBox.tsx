'use client';

import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
  SendHorizontal,
  Paperclip,
  Image as ImageIcon,
  Loader2,
  X,
  Sparkles,
} from 'lucide-react';
import {
  ChatImageUploadDropzone,
  UploadedImage,
} from './ChatImageUploadDropzone';
import { QuickPromptPills } from './QuickPromptPills';
import { cn } from '@/lib/utils';

interface ChatInputBoxProps {
  onSendMessage: (content: string, images: UploadedImage[]) => void;
  isLoading?: boolean;
  disabled?: boolean;
  placeholder?: string;
  className?: string;
  showQuickPrompts?: boolean;
}

export function ChatInputBox({
  onSendMessage,
  isLoading = false,
  disabled = false,
  placeholder = 'Nhập câu lệnh SEO hoặc câu hỏi cho Aura Assistant... (Shift + Enter để xuống dòng)',
  className,
  showQuickPrompts = true,
}: ChatInputBoxProps) {
  const [content, setContent] = useState('');
  const [images, setImages] = useState<UploadedImage[]>([]);
  const [showDropzone, setShowDropzone] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Auto-resize textarea height as content changes
  const handleInputChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setContent(e.target.value);
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.height = `${Math.min(
        textareaRef.current.scrollHeight,
        200
      )}px`;
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    }
  };

  const handleSelectPrompt = (promptText: string) => {
    setContent(promptText);
    if (textareaRef.current) {
      textareaRef.current.focus();
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.height = `${Math.min(
        textareaRef.current.scrollHeight,
        200
      )}px`;
    }
  };

  const handleSubmit = (e?: React.FormEvent) => {
    e?.preventDefault();
    if (isLoading || disabled) return;

    const trimmed = content.trim();
    if (!trimmed && images.length === 0) return;

    onSendMessage(trimmed, images);
    setContent('');
    setImages([]);
    setShowDropzone(false);

    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
    }
  };

  const isSubmitDisabled =
    isLoading || disabled || (!content.trim() && images.length === 0);

  return (
    <div className={cn('relative w-full space-y-2', className)}>
      {/* Quick Prompts Pill Row */}
      {showQuickPrompts && (
        <div className="px-1">
          <QuickPromptPills onSelectPrompt={handleSelectPrompt} compact />
        </div>
      )}

      {/* Main Input Box Card */}
      <div className="relative rounded-2xl border border-border bg-background shadow-xs focus-within:border-primary/70 focus-within:ring-2 focus-within:ring-primary/20 transition-all overflow-hidden">
        {/* Dropzone drawer if open */}
        {showDropzone && (
          <div className="border-b border-border/80 p-3 bg-muted/20">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                <ImageIcon className="h-3.5 w-3.5 text-primary" />
                Đính kèm hình ảnh minh họa cho Vision AI
              </span>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                onClick={() => setShowDropzone(false)}
                className="h-5 w-5 rounded-full text-muted-foreground hover:text-foreground"
              >
                <X className="h-3 w-3" />
                <span className="sr-only">Đóng</span>
              </Button>
            </div>
            <ChatImageUploadDropzone
              images={images}
              onImagesChange={setImages}
              disabled={isLoading || disabled}
              compact
            />
          </div>
        )}

        {/* Uploaded images quick preview strip if dropzone is closed */}
        {!showDropzone && images.length > 0 && (
          <div className="flex flex-wrap gap-2 border-b border-border/60 p-2 bg-muted/20">
            {images.map((img) => (
              <div
                key={img.id}
                className="group relative flex items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs shadow-2xs"
              >
                <div className="relative h-6 w-6 shrink-0 overflow-hidden rounded">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={img.previewUrl}
                    alt={img.name}
                    className="h-full w-full object-cover"
                  />
                </div>
                <span className="max-w-[100px] truncate text-[11px] text-foreground">
                  {img.name}
                </span>
                <button
                  type="button"
                  onClick={() =>
                    setImages(images.filter((i) => i.id !== img.id))
                  }
                  className="rounded-full text-muted-foreground hover:text-destructive"
                  title="Xóa ảnh"
                >
                  <X className="h-3 w-3" />
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Text Area */}
        <div className="flex items-end gap-2 p-3">
          <textarea
            ref={textareaRef}
            value={content}
            onChange={handleInputChange}
            onKeyDown={handleKeyDown}
            disabled={disabled || isLoading}
            placeholder={placeholder}
            rows={1}
            className="flex-1 max-h-48 resize-none border-0 bg-transparent p-0 text-sm leading-relaxed text-foreground placeholder:text-muted-foreground outline-none focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50"
          />

          {/* Action Buttons: Attach & Submit */}
          <div className="flex items-center gap-1 shrink-0 pb-0.5">
            {/* Attachment Button */}
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              onClick={() => setShowDropzone(!showDropzone)}
              disabled={disabled || isLoading}
              className={cn(
                'size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors',
                showDropzone && 'bg-primary/10 text-primary'
              )}
              title="Đính kèm hình ảnh (Hỗ trợ Vision AI ghim bài)"
            >
              <Paperclip className="h-4 w-4" />
              <span className="sr-only">Attach files</span>
            </Button>

            {/* Submit Button with Loading Spinner (Double-Submit Protection) */}
            <Button
              type="button"
              size="icon-sm"
              onClick={() => handleSubmit()}
              disabled={isSubmitDisabled}
              className={cn(
                'size-8 rounded-lg transition-all',
                isSubmitDisabled
                  ? 'bg-muted text-muted-foreground opacity-50 cursor-not-allowed'
                  : 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90'
              )}
              title={isLoading ? 'Aura đang phản hồi...' : 'Gửi tin nhắn (Enter)'}
            >
              {isLoading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <SendHorizontal className="h-4 w-4" />
              )}
              <span className="sr-only">Gửi</span>
            </Button>
          </div>
        </div>

        {/* Hint footer */}
        <div className="flex items-center justify-between border-t border-border/40 bg-muted/15 px-3 py-1 text-[11px] text-muted-foreground">
          <div className="flex items-center gap-1.5">
            <Sparkles className="h-3 w-3 text-purple-600" />
            <span>Aura AI SEO Copilot • Tích hợp 10 Tools SEO Phase 1 & 2</span>
          </div>
          <span className="hidden sm:inline">Nhấn Enter để gửi, Shift + Enter để xuống dòng</span>
        </div>
      </div>
    </div>
  );
}
