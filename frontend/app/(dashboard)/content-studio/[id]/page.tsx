'use client';

import React, { useEffect, useRef, useState, useTransition } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { StudioOutlineTree } from '@/components/features/studio/StudioOutlineTree';
import { LexicalStudioEditor } from '@/components/features/studio/LexicalStudioEditor';
import {
  OutlineItem,
  useAutosaveArticle,
  useContentStudioArticle,
  useContentStudioVersions,
} from '@/hooks/useContentStudio';
import {
  ArrowLeft,
  Check,
  Clock,
  History,
  Loader2,
  Save,
  Sparkles,
  Globe,
  Tag,
  AlertCircle,
  PanelLeftClose,
  PanelLeftOpen,
  PanelRightClose,
  PanelRightOpen,
  ExternalLink,
} from 'lucide-react';

export default function ContentStudioPage() {
  const params = useParams();
  const router = useRouter();
  const articleId = Number(params?.id);

  const {
    data: article,
    isLoading,
    isError,
    error,
    refetch,
  } = useContentStudioArticle(articleId);

  const {
    data: versions = [],
    refetch: refetchVersions,
  } = useContentStudioVersions(articleId);

  const autosaveMutation = useAutosaveArticle();

  // Local state for article data
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [outline, setOutline] = useState<OutlineItem[]>([]);
  const [seoTitle, setSeoTitle] = useState('');
  const [seoDescription, setSeoDescription] = useState('');
  const [wordCount, setWordCount] = useState(0);

  // Autosave tracking state
  const [saveStatus, setSaveStatus] = useState<'saved' | 'saving' | 'unsaved'>('saved');
  const [lastSavedTime, setLastSavedTime] = useState<string | null>(null);
  const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);

  // Layout sidebar visibility toggles
  const [showLeftSidebar, setShowLeftSidebar] = useState(true);
  const [showRightSidebar, setShowRightSidebar] = useState(true);

  // Version snapshot modal state
  const [isVersionModalOpen, setIsVersionModalOpen] = useState(false);
  const [versionReason, setVersionReason] = useState('');
  const [isCreatingVersion, startCreateVersionTransition] = useTransition();

  // Initialize state when article data loads
  useEffect(() => {
    if (article) {
      setTitle(article.title || '');
      setContent(article.content || '');
      setOutline((article.outline as OutlineItem[]) || []);
      setSeoTitle(article.seo_title || '');
      setSeoDescription(article.seo_description || '');
      setWordCount(article.word_count || 0);
      setLastSavedTime(article.last_saved_at || null);
    }
  }, [article]);

  // Trigger Debounced Autosave (2.5 giây sau khi dừng gõ)
  const triggerDebouncedAutosave = (newContent: string, newTitle?: string) => {
    setSaveStatus('unsaved');

    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    debounceTimerRef.current = setTimeout(async () => {
      setSaveStatus('saving');
      try {
        const res = await autosaveMutation.mutateAsync({
          article_id: articleId,
          content: newContent,
          title: newTitle ?? title,
          outline,
          seo_title: seoTitle,
          seo_description: seoDescription,
        });

        setSaveStatus('saved');
        setLastSavedTime(res.saved_at);
        setWordCount(res.word_count);
      } catch {
        setSaveStatus('unsaved');
      }
    }, 2500);
  };

  // Manual save trigger (Double-Submit Protected)
  const handleManualSave = async (createVersion = false, reason = '') => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    setSaveStatus('saving');
    try {
      const res = await autosaveMutation.mutateAsync({
        article_id: articleId,
        content,
        title,
        outline,
        seo_title: seoTitle,
        seo_description: seoDescription,
        create_version: createVersion,
        version_reason: reason || undefined,
      });

      setSaveStatus('saved');
      setLastSavedTime(res.saved_at);
      setWordCount(res.word_count);

      if (createVersion) {
        setIsVersionModalOpen(false);
        setVersionReason('');
        refetchVersions();
      }
    } catch {
      setSaveStatus('unsaved');
    }
  };

  // Content change callback from Lexical Editor
  const handleEditorContentChange = (newHtml: string, words: number) => {
    setContent(newHtml);
    setWordCount(words);
    triggerDebouncedAutosave(newHtml);
  };

  // Outline change callback from StudioOutlineTree
  const handleUpdateOutline = (newOutline: OutlineItem[]) => {
    setOutline(newOutline);
    triggerDebouncedAutosave(content);
  };

  // Restore previous version
  const handleRestoreVersion = (ver: (typeof versions)[0]) => {
    if (confirm(`Bạn có chắc muốn khôi phục lại nội dung phiên bản #${ver.version_number}?`)) {
      setContent(ver.content);
      setTitle(ver.title || title);
      setSeoTitle(ver.seo_title || seoTitle);
      setSeoDescription(ver.seo_description || seoDescription);
      setWordCount(ver.word_count || 0);
      handleManualSave(true, `Khôi phục từ phiên bản #${ver.version_number}`);
    }
  };

  if (isLoading) {
    return (
      <div className="flex flex-col h-[calc(100vh-4rem)] p-4 space-y-4">
        <div className="flex items-center justify-between border-b pb-3">
          <Skeleton className="h-8 w-64" />
          <div className="flex gap-2">
            <Skeleton className="h-8 w-24" />
            <Skeleton className="h-8 w-24" />
          </div>
        </div>
        <div className="flex-1 grid grid-cols-12 gap-4">
          <Skeleton className="col-span-3 h-full rounded-xl" />
          <Skeleton className="col-span-6 h-full rounded-xl" />
          <Skeleton className="col-span-3 h-full rounded-xl" />
        </div>
      </div>
    );
  }

  if (isError || !article) {
    return (
      <div className="flex flex-col items-center justify-center h-[calc(100vh-6rem)] p-6 text-center">
        <AlertCircle className="w-12 h-12 text-destructive mb-3" />
        <h2 className="text-xl font-bold mb-2">Không thể tải bài viết</h2>
        <p className="text-sm text-muted-foreground max-w-md mb-4">
          {error instanceof Error ? error.message : 'Bài viết không tồn tại hoặc bạn không có quyền truy cập.'}
        </p>
        <Button onClick={() => router.push('/articles')} variant="outline">
          <ArrowLeft className="w-4 h-4 mr-2" /> Quay lại danh sách bài viết
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-[calc(100vh-4.2rem)] bg-background overflow-hidden">
      {/* Top Studio Header Bar */}
      <header className="flex items-center justify-between px-4 py-2.5 border-b border-border bg-card/80 backdrop-blur-md z-10 shrink-0">
        <div className="flex items-center gap-3 min-w-0">
          <Button
            variant="ghost"
            size="icon-xs"
            onClick={() => router.push('/articles')}
            className="rounded-full shrink-0"
            title="Quay lại danh sách bài viết"
          >
            <ArrowLeft className="w-4 h-4" />
          </Button>

          {/* Editable Title */}
          <div className="flex items-center gap-2 min-w-0 max-w-xl">
            <Input
              value={title}
              onChange={(e) => {
                setTitle(e.target.value);
                triggerDebouncedAutosave(content, e.target.value);
              }}
              placeholder="Nhập tiêu đề bài viết..."
              className="h-8 text-sm font-semibold bg-transparent border-transparent hover:border-border focus:border-primary truncate px-2"
            />
          </div>

          {/* Focus Keyword & Status */}
          {article.focus_keyword && (
            <Badge variant="secondary" className="hidden sm:inline-flex items-center gap-1 text-xs shrink-0">
              <Tag className="w-3 h-3 text-muted-foreground" />
              {article.focus_keyword}
            </Badge>
          )}

          <Badge variant="outline" className="hidden md:inline-flex text-[11px] shrink-0 uppercase">
            {article.status}
          </Badge>
        </div>

        {/* Right Action Controls */}
        <div className="flex items-center gap-2 shrink-0">
          {/* Word count & Autosave Status */}
          <div className="hidden lg:flex items-center gap-2 text-xs text-muted-foreground mr-1">
            <span className="font-mono bg-muted/60 px-2 py-0.5 rounded text-[11px]">
              {wordCount} từ
            </span>

            <div className="flex items-center gap-1">
              {saveStatus === 'saving' ? (
                <>
                  <Loader2 className="w-3 h-3 animate-spin text-primary" />
                  <span>Đang lưu nháp...</span>
                </>
              ) : saveStatus === 'saved' ? (
                <>
                  <Check className="w-3 h-3 text-emerald-500" />
                  <span>Đã lưu {lastSavedTime ? new Date(lastSavedTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}</span>
                </>
              ) : (
                <>
                  <Clock className="w-3 h-3 text-amber-500" />
                  <span className="text-amber-600 dark:text-amber-400">Có thay đổi chưa lưu</span>
                </>
              )}
            </div>
          </div>

          {/* Sidebar toggles on desktop */}
          <Button
            variant="ghost"
            size="icon-xs"
            onClick={() => setShowLeftSidebar(!showLeftSidebar)}
            className="hidden md:flex"
            title={showLeftSidebar ? 'Ẩn dàn ý' : 'Hiện dàn ý'}
          >
            {showLeftSidebar ? <PanelLeftClose className="w-4 h-4" /> : <PanelLeftOpen className="w-4 h-4" />}
          </Button>

          <Button
            variant="ghost"
            size="icon-xs"
            onClick={() => setShowRightSidebar(!showRightSidebar)}
            className="hidden md:flex"
            title={showRightSidebar ? 'Ẩn bảng SEO' : 'Hiện bảng SEO'}
          >
            {showRightSidebar ? <PanelRightClose className="w-4 h-4" /> : <PanelRightOpen className="w-4 h-4" />}
          </Button>

          {/* Create Version Button */}
          <Button
            variant="outline"
            size="xs"
            onClick={() => setIsVersionModalOpen(true)}
            className="text-xs h-7 gap-1"
          >
            <History className="w-3.5 h-3.5" />
            <span className="hidden sm:inline">Tạo phiên bản</span>
          </Button>

          {/* Manual Save Button (Double-Submit Protected) */}
          <Button
            size="xs"
            disabled={saveStatus === 'saving'}
            onClick={() => handleManualSave(false)}
            className="text-xs h-7 gap-1 bg-primary text-primary-foreground hover:bg-primary/90"
          >
            {saveStatus === 'saving' ? (
              <Loader2 className="w-3.5 h-3.5 animate-spin" />
            ) : (
              <Save className="w-3.5 h-3.5" />
            )}
            <span>Lưu nháp</span>
          </Button>
        </div>
      </header>

      {/* Main 3-Column Content Studio Layout */}
      <div className="flex-1 flex overflow-hidden">
        {/* CỘT 1: Outline Tree (260px - 280px) */}
        {showLeftSidebar && (
          <div className="w-[270px] shrink-0 h-full border-r border-border hidden md:block">
            <StudioOutlineTree
              outline={outline}
              onUpdateOutline={handleUpdateOutline}
              onSelectSection={(secTitle) => {
                // Focus heading in editor if needed
              }}
              onAddAiSection={() => {
                // Thêm mục tiếp theo bằng AI
                const nextItem: OutlineItem = {
                  id: `sec-${Date.now()}`,
                  level: 2,
                  title: `Mục bổ sung: Tối ưu chuyên sâu ${outline.length + 1}`,
                };
                handleUpdateOutline([...outline, nextItem]);
              }}
            />
          </div>
        )}

        {/* CỘT 2: Lexical Rich-Text Editor (Không gian làm việc chính) */}
        <main className="flex-1 flex flex-col h-full overflow-hidden p-3 bg-muted/10">
          <LexicalStudioEditor
            articleId={articleId}
            initialHtml={content}
            onChange={handleEditorContentChange}
            placeholder="Bắt đầu viết hoặc biên tập bài viết chuẩn SEO tại đây..."
          />
        </main>

        {/* CỘT 3: SEO Inspector & Version History Panel (320px) */}
        {showRightSidebar && (
          <aside className="w-[320px] shrink-0 h-full border-l border-border bg-card flex flex-col hidden lg:flex">
            <Tabs defaultValue="seo" className="flex-1 flex flex-col">
              <div className="p-2 border-b border-border">
                <TabsList className="w-full grid grid-cols-2 h-8">
                  <TabsTrigger value="seo" className="text-xs">
                    SEO & Meta
                  </TabsTrigger>
                  <TabsTrigger value="history" className="text-xs gap-1">
                    <History className="w-3 h-3" />
                    Lịch sử ({versions.length})
                  </TabsTrigger>
                </TabsList>
              </div>

              {/* Tab 1: SEO & Metadata Inspector */}
              <TabsContent value="seo" className="flex-1 overflow-y-auto p-4 space-y-4 m-0">
                {/* Website Info */}
                {article.website && (
                  <div className="p-3 rounded-lg border border-border bg-muted/30 space-y-1">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                      <span className="flex items-center gap-1 font-medium text-foreground">
                        <Globe className="w-3.5 h-3.5 text-primary" />
                        {article.website.name}
                      </span>
                      <a
                        href={article.website.url || `https://${article.website.domain}`}
                        target="_blank"
                        rel="noreferrer"
                        className="hover:underline flex items-center gap-0.5 text-[11px]"
                      >
                        {article.website.domain}
                        <ExternalLink className="w-2.5 h-2.5" />
                      </a>
                    </div>
                  </div>
                )}

                {/* SEO Title Input */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs">
                    <label className="font-medium text-foreground">SEO Title</label>
                    <span className={`text-[10px] ${seoTitle.length > 60 ? 'text-destructive' : 'text-muted-foreground'}`}>
                      {seoTitle.length}/60 ký tự
                    </span>
                  </div>
                  <Input
                    value={seoTitle}
                    onChange={(e) => {
                      setSeoTitle(e.target.value);
                      triggerDebouncedAutosave(content);
                    }}
                    placeholder="Tiêu đề chuẩn SEO hiển thị Google..."
                    className="text-xs h-8"
                  />
                </div>

                {/* SEO Description Input */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs">
                    <label className="font-medium text-foreground">Meta Description</label>
                    <span className={`text-[10px] ${seoDescription.length > 160 ? 'text-destructive' : 'text-muted-foreground'}`}>
                      {seoDescription.length}/160 ký tự
                    </span>
                  </div>
                  <Textarea
                    value={seoDescription}
                    onChange={(e) => {
                      setSeoDescription(e.target.value);
                      triggerDebouncedAutosave(content);
                    }}
                    placeholder="Đoạn trích tóm tắt bài viết trên kết quả tìm kiếm..."
                    className="text-xs min-h-[80px] resize-none"
                  />
                </div>

                {/* Metrics Summary */}
                <div className="p-3 rounded-lg border border-border bg-muted/20 space-y-2">
                  <h4 className="text-xs font-semibold text-foreground">Chỉ số bài viết</h4>
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div className="p-2 rounded bg-background border border-border">
                      <span className="text-[10px] text-muted-foreground block">Tổng số từ</span>
                      <span className="font-bold text-sm text-foreground">{wordCount}</span>
                    </div>
                    <div className="p-2 rounded bg-background border border-border">
                      <span className="text-[10px] text-muted-foreground block">Điểm SEO</span>
                      <span className="font-bold text-sm text-primary">
                        {article.seo_score ? `${article.seo_score}/100` : 'Chưa chấm'}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Quick Copilot Tip */}
                <div className="p-3 rounded-lg bg-amber-500/5 border border-amber-500/20 text-xs text-amber-900 dark:text-amber-200 space-y-1">
                  <div className="flex items-center gap-1.5 font-semibold text-amber-700 dark:text-amber-400">
                    <Sparkles className="w-3.5 h-3.5" />
                    Mẹo AI Copilot
                  </div>
                  <p className="text-[11px] leading-relaxed">
                    Bôi đen bất kỳ đoạn văn nào trên trình soạn thảo để kích hoạt AI viết lại, mở rộng, hoặc đổi giọng văn tức thì.
                  </p>
                </div>
              </TabsContent>

              {/* Tab 2: Version History */}
              <TabsContent value="history" className="flex-1 overflow-y-auto p-3 space-y-2 m-0">
                {versions.length === 0 ? (
                  <div className="p-6 text-center text-xs text-muted-foreground">
                    <History className="w-8 h-8 mx-auto mb-2 opacity-30" />
                    <p>Chưa có phiên bản nào được lưu.</p>
                    <p className="mt-1">Nhấn &ldquo;Tạo phiên bản&rdquo; để tạo snapshot an toàn.</p>
                  </div>
                ) : (
                  versions.map((ver) => (
                    <div
                      key={ver.id}
                      className="p-2.5 rounded-lg border border-border bg-card hover:bg-muted/30 transition-colors space-y-1.5 text-xs"
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-primary">
                          Phiên bản #{ver.version_number}
                        </span>
                        <span className="text-[10px] text-muted-foreground">
                          {new Date(ver.created_at).toLocaleString([], {
                            month: 'numeric',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </span>
                      </div>

                      {ver.change_reason && (
                        <p className="text-[11px] text-foreground italic">
                          &ldquo;{ver.change_reason}&rdquo;
                        </p>
                      )}

                      <div className="flex items-center justify-between pt-1 border-t border-border/50 text-[10px] text-muted-foreground">
                        <span>{ver.word_count} từ</span>
                        <Button
                          variant="ghost"
                          size="xs"
                          onClick={() => handleRestoreVersion(ver)}
                          className="h-5 text-[10px] px-1.5 text-primary hover:text-primary"
                        >
                          Khôi phục
                        </Button>
                      </div>
                    </div>
                  ))
                )}
              </TabsContent>
            </Tabs>
          </aside>
        )}
      </div>

      {/* Modal tạo phiên bản mới (Create Version Snapshot) */}
      <Dialog open={isVersionModalOpen} onOpenChange={setIsVersionModalOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="text-sm font-semibold flex items-center gap-1.5">
              <History className="w-4 h-4 text-primary" />
              Tạo phiên bản lưu trữ (Snapshot)
            </DialogTitle>
          </DialogHeader>

          <div className="space-y-3 py-2">
            <p className="text-xs text-muted-foreground">
              Tạo một điểm mốc phiên bản hoàn chỉnh để có thể xem lại hoặc khôi phục bất cứ lúc nào.
            </p>

            <div className="space-y-1.5">
              <label className="text-xs font-medium">Lý do thay đổi / Ghi chú mốc:</label>
              <Input
                placeholder="VD: Đã hoàn tất chỉnh sửa dàn ý và mở rộng H2..."
                value={versionReason}
                onChange={(e) => setVersionReason(e.target.value)}
                className="text-xs"
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    startCreateVersionTransition(() => handleManualSave(true, versionReason));
                  }
                }}
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setIsVersionModalOpen(false)}
              className="text-xs"
            >
              Hủy
            </Button>
            <Button
              size="sm"
              disabled={isCreatingVersion || autosaveMutation.isPending}
              onClick={() => startCreateVersionTransition(() => handleManualSave(true, versionReason))}
              className="text-xs gap-1.5"
            >
              {isCreatingVersion || autosaveMutation.isPending ? (
                <Loader2 className="w-3.5 h-3.5 animate-spin" />
              ) : (
                <Save className="w-3.5 h-3.5" />
              )}
              Lưu phiên bản
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
