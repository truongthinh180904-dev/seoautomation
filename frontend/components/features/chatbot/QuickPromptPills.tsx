'use client';

import React from 'react';
import {
  Search,
  TrendingUp,
  PenTool,
  CheckCircle2,
  Layers,
  Link as LinkIcon,
  Sparkles,
  Camera,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export interface QuickPromptItem {
  id: string;
  title: string;
  prompt: string;
  icon: React.ElementType;
  badge?: string;
}

const DEFAULT_PROMPTS: QuickPromptItem[] = [
  {
    id: 'keyword_research',
    title: 'Nghiên cứu từ khóa',
    prompt: 'Nghiên cứu 15 từ khóa tiềm năng có search intent cao về chủ đề: ',
    icon: Search,
    badge: 'Phase 1',
  },
  {
    id: 'competitor_analysis',
    title: 'Phân tích đối thủ',
    prompt: 'Phân tích Top 3 đối thủ trên Google SERP (lấy đối thủ #1 làm chuẩn) cho từ khóa: ',
    icon: TrendingUp,
    badge: 'Top 3 SERP',
  },
  {
    id: 'seo_writing',
    title: 'Viết bài SEO',
    prompt: 'Viết một bài viết chuẩn SEO chuyên sâu 2.000 từ theo tiêu chuẩn EEAT về từ khóa: ',
    icon: PenTool,
    badge: 'Full Article',
  },
  {
    id: 'seo_score',
    title: 'Chấm điểm bài',
    prompt: 'Chấm điểm SEO 100 điểm, đánh giá mật độ từ khóa và cấu trúc heading bài viết này: ',
    icon: CheckCircle2,
    badge: 'Audit 100đ',
  },
  {
    id: 'topic_cluster',
    title: 'Lập Topic Cluster',
    prompt: 'Lập sơ đồ Topic Cluster hoàn chỉnh gồm bài viết trụ cột (Pillar) và các bài cụm phụ (Cluster posts) cho chủ đề: ',
    icon: Layers,
    badge: 'Pillar',
  },
  {
    id: 'internal_linking',
    title: 'Gợi ý Internal Links',
    prompt: 'Quét và đề xuất liên kết nội bộ (Internal Links) tự nhiên theo ngữ cảnh bài viết cho website của tôi.',
    icon: LinkIcon,
    badge: 'SILO Graph',
  },
  {
    id: 'image_vision',
    title: 'Ghim ảnh Vision vào bài',
    prompt: 'Đọc hiểu hình ảnh đính kèm và tự động gợi ý vị trí heading H2/H3 phù hợp nhất để chèn vào bài viết kèm thẻ ALT chuẩn SEO.',
    icon: Camera,
    badge: 'Vision AI',
  },
  {
    id: 'rewrite_section',
    title: 'Viết lại cuốn hút hơn',
    prompt: 'Viết lại đoạn văn này bằng giọng văn chuyên gia, hấp dẫn và giữ trọn vẹn thông điệp thương hiệu: ',
    icon: Sparkles,
    badge: 'Copilot',
  },
];

interface QuickPromptPillsProps {
  onSelectPrompt: (promptText: string) => void;
  className?: string;
  compact?: boolean;
}

export function QuickPromptPills({
  onSelectPrompt,
  className,
  compact = false,
}: QuickPromptPillsProps) {
  return (
    <div className={cn('w-full', className)}>
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1.5 no-scrollbar scroll-smooth">
        {DEFAULT_PROMPTS.map((item) => {
          const Icon = item.icon;
          return (
            <button
              key={item.id}
              type="button"
              onClick={() => onSelectPrompt(item.prompt)}
              className={cn(
                'group inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border/80 bg-background/80 px-2.5 py-1 text-xs font-medium text-foreground transition-all duration-150 shadow-2xs hover:border-primary/60 hover:bg-primary/5 hover:text-primary active:scale-95',
                compact && 'px-2 py-0.5 text-[11px]'
              )}
            >
              <Icon className="h-3.5 w-3.5 text-muted-foreground transition-colors group-hover:text-primary" />
              <span>{item.title}</span>
              {item.badge && !compact && (
                <span className="rounded-full bg-muted px-1.5 py-0.2 text-[10px] text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary">
                  {item.badge}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
