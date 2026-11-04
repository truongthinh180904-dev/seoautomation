'use client';

import React, { useEffect, useState, useTransition } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
  Link2,
  Sparkles,
  Image as ImageIcon,
  Check,
  CheckCircle2,
  Loader2,
  ArrowRight,
  ExternalLink,
  Coins,
  AlertCircle,
  Plus,
  RefreshCw,
} from 'lucide-react';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';

export interface InternalLinkSuggestionsModalProps {
  isOpen: boolean;
  onClose: () => void;
  articleId: number;
  websiteId?: number;
  headings?: string[];
  onInsertLink?: (anchorText: string, targetUrl: string) => void;
  onInsertImage?: (imageUrl: string, altText: string, caption?: string) => void;
}

interface ArticleRef {
  id: number;
  title: string;
  slug: string;
  focus_keyword?: string;
  wordpress_post_url?: string;
}

interface LinkSuggestion {
  id: number;
  source_article_id: number;
  target_article_id: number;
  suggested_anchor_text: string;
  context_sentence?: string;
  relevance_score: number;
  status: 'pending' | 'accepted' | 'rejected' | 'applied';
  target_article?: ArticleRef;
}

interface GeneratedMediaAsset {
  id: number;
  local_path: string;
  alt_text: string;
  caption?: string;
  metadata?: {
    mime_type?: string;
    size_bytes?: number;
    width?: number;
    height?: number;
  };
}

export function InternalLinkSuggestionsModal({
  isOpen,
  onClose,
  articleId,
  websiteId,
  headings = [],
  onInsertLink,
  onInsertImage,
}: InternalLinkSuggestionsModalProps) {
  const [activeTab, setActiveTab] = useState<'links' | 'images'>('links');

  // Link suggestions state
  const [suggestions, setSuggestions] = useState<LinkSuggestion[]>([]);
  const [isLoadingLinks, setIsLoadingLinks] = useState<boolean>(false);
  const [isScanningLinks, setIsScanningLinks] = useState<boolean>(false);
  const [applyingLinkId, setApplyingLinkId] = useState<number | null>(null);

  // AI Image generation state
  const [selectedHeading, setSelectedHeading] = useState<string>(headings[0] || '');
  const [customPrompt, setCustomPrompt] = useState<string>('');
  const [isGeneratingImage, setIsGeneratingImage] = useState<boolean>(false);
  const [generatedAsset, setGeneratedAsset] = useState<GeneratedMediaAsset | null>(null);

  useEffect(() => {
    if (isOpen && articleId) {
      loadArticleSuggestions();
      if (headings.length > 0 && !selectedHeading) {
        setSelectedHeading(headings[0]);
      }
    }
  }, [isOpen, articleId]);

  async function loadArticleSuggestions() {
    try {
      setIsLoadingLinks(true);
      const res = await apiClient.get<{ data: { data: LinkSuggestion[] } }>(
        `/internal-links?article_id=${articleId}`
      );
      setSuggestions(res.data?.data?.data || []);
    } catch {
      toast.error('Không thể tải gợi ý liên kết');
    } finally {
      setIsLoadingLinks(false);
    }
  }

  async function handleScanArticleLinks() {
    try {
      setIsScanningLinks(true);
      const res = await apiClient.post<{ message: string; data: { suggestions_count: number } }>(
        '/internal-links/scan',
        { article_id: articleId }
      );
      toast.success(res.data?.message || 'Quét liên kết thành công');
      loadArticleSuggestions();
    } catch {
      toast.error('Lỗi khi quét liên kết');
    } finally {
      setIsScanningLinks(false);
    }
  }

  async function handleApplyLink(suggestion: LinkSuggestion) {
    try {
      setApplyingLinkId(suggestion.id);
      const res = await apiClient.post<{ message: string }>(
        `/internal-links/${suggestion.id}/apply`
      );
      toast.success(res.data?.message || 'Đã áp dụng liên kết vào bài viết');

      setSuggestions((prev) =>
        prev.map((s) => (s.id === suggestion.id ? { ...s, status: 'applied' } : s))
      );

      const targetUrl =
        suggestion.target_article?.wordpress_post_url ||
        `/articles/${suggestion.target_article?.slug || suggestion.target_article_id}`;

      if (onInsertLink) {
        onInsertLink(suggestion.suggested_anchor_text, targetUrl);
      }
    } catch {
      toast.error('Lỗi khi áp dụng liên kết');
    } finally {
      setApplyingLinkId(null);
    }
  }

  async function handleGenerateImage() {
    if (!selectedHeading.trim()) {
      toast.error('Vui lòng chọn hoặc nhập tiêu đề mục H2');
      return;
    }

    try {
      setIsGeneratingImage(true);
      const res = await apiClient.post<{ message: string; data: GeneratedMediaAsset }>(
        '/internal-links/generate-image',
        {
          article_id: articleId,
          heading: selectedHeading,
          prompt: customPrompt.trim() ? customPrompt : undefined,
        }
      );
      toast.success(res.data?.message || 'Tạo ảnh AI Imagen 3 thành công');
      setGeneratedAsset(res.data?.data || null);
    } catch (err: unknown) {
      toast.error('Không thể tạo ảnh AI');
    } finally {
      setIsGeneratingImage(false);
    }
  }

  function handleInsertGeneratedImage() {
    if (!generatedAsset) return;
    const imageUrl = `/storage/${generatedAsset.local_path}`;
    if (onInsertImage) {
      onInsertImage(imageUrl, generatedAsset.alt_text, generatedAsset.caption);
    }
    toast.success('Đã chèn ảnh vào trình soạn thảo');
    onClose();
  }

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl sm:max-w-4xl p-0 overflow-hidden rounded-2xl">
        <DialogHeader className="p-6 pb-2 border-b border-slate-100 bg-slate-50/50">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="rounded-xl bg-blue-600 p-2 text-white">
                <Sparkles className="h-5 w-5" />
              </div>
              <div>
                <DialogTitle className="text-xl font-black text-slate-900">
                  SEO Smart Assistant: Internal Links & AI Images
                </DialogTitle>
                <DialogDescription className="text-xs text-slate-500">
                  Gợi ý liên kết nội bộ tự nhiên và sinh ảnh minh họa AI Imagen 3 chuẩn SEO.
                </DialogDescription>
              </div>
            </div>
          </div>
        </DialogHeader>

        <div className="p-6">
          <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'links' | 'images')}>
            <TabsList className="grid w-full grid-cols-2 mb-6">
              <TabsTrigger value="links" className="gap-2 font-bold">
                <Link2 className="h-4 w-4" />
                Đề xuất liên kết nội bộ ({suggestions.length})
              </TabsTrigger>
              <TabsTrigger value="images" className="gap-2 font-bold">
                <ImageIcon className="h-4 w-4" />
                Sinh ảnh AI theo mục H2 (Imagen 3)
              </TabsTrigger>
            </TabsList>

            {/* TAB 1: INTERNAL LINKS */}
            <TabsContent value="links" className="space-y-4 m-0">
              <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-slate-500">
                  Cơ hội chèn link liên quan tới các bài viết khác trong cùng website.
                </p>

                <Button
                  size="sm"
                  variant="outline"
                  onClick={handleScanArticleLinks}
                  disabled={isScanningLinks}
                  className="gap-1.5 text-xs font-bold"
                >
                  {isScanningLinks ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Đang quét...
                    </>
                  ) : (
                    <>
                      <RefreshCw className="h-3.5 w-3.5" />
                      Quét cơ hội liên kết
                    </>
                  )}
                </Button>
              </div>

              <div className="max-h-[380px] overflow-y-auto space-y-3 pr-1">
                {isLoadingLinks ? (
                  <div className="py-12 text-center text-slate-400">
                    <Loader2 className="mx-auto h-6 w-6 animate-spin text-blue-500" />
                    <p className="mt-2 text-xs">Đang tải đề xuất...</p>
                  </div>
                ) : suggestions.length === 0 ? (
                  <div className="py-12 text-center text-slate-400 border border-dashed rounded-xl">
                    <Link2 className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 font-bold text-slate-700">Chưa có đề xuất liên kết nào</p>
                    <p className="text-xs text-slate-400 mt-0.5">
                      Bấm "Quét cơ hội liên kết" để AI phân tích nội dung bài viết.
                    </p>
                  </div>
                ) : (
                  suggestions.map((sugg) => (
                    <div
                      key={sugg.id}
                      className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-300 transition-all flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                    >
                      <div className="space-y-1.5 flex-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-sm text-slate-900">
                            {sugg.target_article?.title || `Bài viết #${sugg.target_article_id}`}
                          </span>
                          <Badge variant="secondary" className="text-[11px] font-bold text-blue-700 bg-blue-50">
                            {sugg.suggested_anchor_text}
                          </Badge>
                          <span className="text-[11px] font-mono font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                            {Number(sugg.relevance_score).toFixed(0)}% Match
                          </span>
                        </div>

                        {sugg.context_sentence && (
                          <p className="text-xs text-slate-500 italic bg-slate-50 p-2 rounded-lg border border-slate-100">
                            "{sugg.context_sentence}"
                          </p>
                        )}
                      </div>

                      <div className="flex items-center gap-2 shrink-0">
                        {sugg.status === 'applied' ? (
                          <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 font-bold text-xs gap-1">
                            <Check className="h-3 w-3" /> Đã chèn
                          </Badge>
                        ) : (
                          <Button
                            size="sm"
                            disabled={applyingLinkId === sugg.id}
                            onClick={() => handleApplyLink(sugg)}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs gap-1.5"
                          >
                            {applyingLinkId === sugg.id ? (
                              <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                              <CheckCircle2 className="h-3.5 w-3.5" />
                            )}
                            Chèn vào bài
                          </Button>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </TabsContent>

            {/* TAB 2: AI IMAGES (IMAGEN 3) */}
            <TabsContent value="images" className="space-y-4 m-0">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Left: Input parameters */}
                <div className="space-y-4">
                  <div className="space-y-2">
                    <Label className="text-xs font-bold text-slate-700">
                      Chọn tiêu đề mục H2 để sinh ảnh minh họa
                    </Label>
                    {headings.length > 0 ? (
                      <select
                        value={selectedHeading}
                        onChange={(e) => setSelectedHeading(e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        {headings.map((h, i) => (
                          <option key={i} value={h}>
                            {h}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <Input
                        placeholder="Nhập tiêu đề mục H2..."
                        value={selectedHeading}
                        onChange={(e) => setSelectedHeading(e.target.value)}
                        className="text-xs"
                      />
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label className="text-xs font-bold text-slate-700 flex items-center justify-between">
                      <span>Mô tả ảnh AI bổ sung (Tùy chọn)</span>
                      <span className="text-[10px] text-slate-400">Tự động kết hợp ALT SEO</span>
                    </Label>
                    <Textarea
                      placeholder="VD: Không gian làm việc hiện đại, phong cách editorial, ánh sáng tự nhiên..."
                      value={customPrompt}
                      onChange={(e) => setCustomPrompt(e.target.value)}
                      rows={3}
                      className="text-xs"
                    />
                  </div>

                  <div className="rounded-xl bg-blue-50 p-3 text-xs text-blue-800 space-y-1">
                    <div className="flex items-center gap-1.5 font-bold">
                      <Coins className="h-4 w-4 text-blue-600" />
                      Chi phí: 5 Credits / Ảnh AI Imagen 3
                    </div>
                    <p className="text-[11px] text-blue-600">
                      Tự động nén định dạng WebP (&lt; 100KB), kích thước chuẩn 16:9 và gắn thẻ ALT chuẩn SEO.
                    </p>
                  </div>

                  <Button
                    onClick={handleGenerateImage}
                    disabled={isGeneratingImage || !selectedHeading.trim()}
                    className="w-full bg-blue-600 hover:bg-blue-700 font-bold text-xs gap-2"
                  >
                    {isGeneratingImage ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Đang sinh ảnh Imagen 3...
                      </>
                    ) : (
                      <>
                        <Sparkles className="h-4 w-4" />
                        Tạo ảnh AI theo H2
                      </>
                    )}
                  </Button>
                </div>

                {/* Right: Preview & Insert */}
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 flex flex-col items-center justify-center min-h-[280px]">
                  {isGeneratingImage ? (
                    <div className="text-center text-slate-400 space-y-3">
                      <Loader2 className="h-10 w-10 animate-spin text-blue-600 mx-auto" />
                      <p className="text-xs font-semibold text-slate-600">
                        Đang khởi tạo prompt và render ảnh Imagen 3...
                      </p>
                      <p className="text-[11px] text-slate-400">
                        Tự động nén WebP và tối ưu dung lượng...
                      </p>
                    </div>
                  ) : generatedAsset ? (
                    <div className="w-full space-y-3">
                      <div className="relative aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-200 shadow-sm">
                        <img
                          src={`/storage/${generatedAsset.local_path}`}
                          alt={generatedAsset.alt_text}
                          className="h-full w-full object-cover"
                        />
                      </div>

                      <div className="space-y-1 text-xs">
                        <div className="flex items-center justify-between text-slate-500 text-[11px]">
                          <span>ALT: <strong className="text-slate-800">{generatedAsset.alt_text}</strong></span>
                          <Badge variant="outline" className="font-mono text-[10px]">
                            {generatedAsset.metadata?.mime_type || 'image/webp'}
                          </Badge>
                        </div>
                      </div>

                      <Button
                        onClick={handleInsertGeneratedImage}
                        className="w-full bg-emerald-600 hover:bg-emerald-700 font-bold text-xs gap-2"
                      >
                        <Check className="h-4 w-4" />
                        Chèn ảnh vào bài viết
                      </Button>
                    </div>
                  ) : (
                    <div className="text-center text-slate-400 space-y-2">
                      <ImageIcon className="h-12 w-12 text-slate-300 mx-auto" />
                      <p className="text-xs font-bold text-slate-600">Chưa có ảnh được tạo</p>
                      <p className="text-[11px] text-slate-400 max-w-xs">
                        Chọn một tiêu đề H2 ở bên trái và bấm "Tạo ảnh AI theo H2" để xem trước.
                      </p>
                    </div>
                  )}
                </div>
              </div>
            </TabsContent>
          </Tabs>
        </div>

        <DialogFooter className="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
          <span className="text-[11px] text-slate-400">
            Internal Linking & AI Images v2.8 • QLPK Safe Autonomous
          </span>
          <Button variant="ghost" size="sm" onClick={onClose} className="font-bold text-xs">
            Đóng
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
