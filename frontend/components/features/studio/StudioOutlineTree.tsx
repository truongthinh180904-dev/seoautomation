'use client';

import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { OutlineItem } from '@/hooks/useContentStudio';
import {
  Hash,
  Plus,
  Trash2,
  Sparkles,
  MoveUp,
  MoveDown,
  BookOpen,
} from 'lucide-react';

interface StudioOutlineTreeProps {
  outline: OutlineItem[];
  onSelectSection?: (title: string) => void;
  onUpdateOutline: (newOutline: OutlineItem[]) => void;
  onAddAiSection?: () => void;
  isGenerating?: boolean;
}

export function StudioOutlineTree({
  outline,
  onSelectSection,
  onUpdateOutline,
  onAddAiSection,
  isGenerating = false,
}: StudioOutlineTreeProps) {
  const [newTitle, setNewTitle] = useState('');
  const [newLevel, setNewLevel] = useState<1 | 2 | 3>(2);
  const [isAdding, setIsAdding] = useState(false);

  const handleAddSection = () => {
    if (!newTitle.trim()) return;

    const newItem: OutlineItem = {
      id: `sec-${Date.now()}`,
      level: newLevel,
      title: newTitle.trim(),
    };

    onUpdateOutline([...outline, newItem]);
    setNewTitle('');
    setIsAdding(false);
  };

  const handleRemove = (id: string) => {
    onUpdateOutline(outline.filter((item) => item.id !== id));
  };

  const handleMove = (index: number, direction: 'up' | 'down') => {
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= outline.length) return;

    const updated = [...outline];
    const temp = updated[index];
    updated[index] = updated[targetIndex];
    updated[targetIndex] = temp;
    onUpdateOutline(updated);
  };

  return (
    <aside className="w-full flex flex-col h-full bg-card border-r border-border select-none">
      {/* Header */}
      <div className="p-3.5 border-b border-border flex items-center justify-between">
        <div className="flex items-center gap-2">
          <BookOpen className="w-4 h-4 text-primary" />
          <h3 className="font-semibold text-sm tracking-tight text-foreground">
            Dàn ý bài viết
          </h3>
          <Badge variant="secondary" className="text-xs px-1.5 py-0 h-5">
            {outline.length} mục
          </Badge>
        </div>
        <Button
          variant="ghost"
          size="icon-xs"
          onClick={() => setIsAdding(!isAdding)}
          title="Thêm mục mới"
        >
          <Plus className="w-4 h-4" />
        </Button>
      </div>

      {/* Quick Add Form */}
      {isAdding && (
        <div className="p-3 bg-muted/40 border-b border-border space-y-2">
          <Input
            placeholder="Tiêu đề mục mới..."
            value={newTitle}
            onChange={(e) => setNewTitle(e.target.value)}
            className="h-8 text-xs"
            onKeyDown={(e) => e.key === 'Enter' && handleAddSection()}
          />
          <div className="flex items-center justify-between gap-1.5">
            <div className="flex items-center gap-1">
              {(['H1', 'H2', 'H3'] as const).map((tag, idx) => {
                const lvl = (idx + 1) as 1 | 2 | 3;
                return (
                  <Button
                    key={tag}
                    variant={newLevel === lvl ? 'default' : 'outline'}
                    size="xs"
                    onClick={() => setNewLevel(lvl)}
                    className="text-[11px] h-6 px-2"
                  >
                    {tag}
                  </Button>
                );
              })}
            </div>
            <div className="flex items-center gap-1">
              <Button
                variant="ghost"
                size="xs"
                onClick={() => setIsAdding(false)}
                className="text-[11px] h-6"
              >
                Hủy
              </Button>
              <Button
                size="xs"
                onClick={handleAddSection}
                disabled={!newTitle.trim()}
                className="text-[11px] h-6"
              >
                Thêm
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Outline Items List */}
      <div className="flex-1 overflow-y-auto p-2 space-y-1">
        {outline.length === 0 ? (
          <div className="p-6 text-center text-xs text-muted-foreground">
            <Hash className="w-8 h-8 mx-auto mb-2 opacity-30" />
            <p>Chưa có cấu trúc dàn ý.</p>
            <p className="mt-1">Thêm mục mới hoặc nhờ AI tạo tự động.</p>
          </div>
        ) : (
          outline.map((item, idx) => {
            const isSub = item.level === 3;
            const isH1 = item.level === 1;

            return (
              <div
                key={item.id || `outline-item-${idx}`}
                className={`group flex items-center justify-between gap-1 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-accent hover:text-accent-foreground ${
                  isH1 ? 'font-semibold text-foreground' : isSub ? 'pl-6 text-muted-foreground' : 'pl-3'
                }`}
              >
                <button
                  type="button"
                  onClick={() => onSelectSection?.(item.title)}
                  className="flex items-center gap-1.5 flex-1 min-w-0 text-left cursor-pointer"
                >
                  <span className="font-mono text-[10px] text-muted-foreground px-1 py-0.5 rounded bg-muted/60">
                    {`H${item.level}`}
                  </span>
                  <span className="truncate">{item.title}</span>
                </button>

                {/* Actions on hover */}
                <div className="opacity-0 group-hover:opacity-100 flex items-center gap-0.5 transition-opacity">
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    disabled={idx === 0}
                    onClick={() => handleMove(idx, 'up')}
                    title="Lên trên"
                  >
                    <MoveUp className="w-3 h-3" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    disabled={idx === outline.length - 1}
                    onClick={() => handleMove(idx, 'down')}
                    title="Xuống dưới"
                  >
                    <MoveDown className="w-3 h-3" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={() => handleRemove(item.id)}
                    className="text-destructive hover:text-destructive"
                    title="Xóa mục"
                  >
                    <Trash2 className="w-3 h-3" />
                  </Button>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* AI Next Section Suggestion Footer */}
      {onAddAiSection && (
        <div className="p-3 border-t border-border bg-card">
          <Button
            variant="outline"
            size="sm"
            onClick={onAddAiSection}
            disabled={isGenerating}
            className="w-full text-xs gap-1.5 border-dashed hover:border-primary hover:text-primary"
          >
            <Sparkles className="w-3.5 h-3.5 text-amber-500 animate-pulse" />
            {isGenerating ? 'Đang tạo mục...' : 'Gợi ý mục tiếp theo bằng AI'}
          </Button>
        </div>
      )}
    </aside>
  );
}
