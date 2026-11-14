"use client";

import React, { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import { useArticle, useArticleActions } from '@/hooks/useArticles';
import ArticleEditor from '@/components/features/articles/ArticleEditor';
import SEOScorePanel from '@/components/features/articles/ArticleEditor/SEOScorePanel';
import ArticleImportSettings from '@/components/features/articles/ArticleImportSettings';
import ArticleTimeline from '@/components/features/articles/ArticleTimeline';
import {
  ArrowLeft,
  Loader2,
  PanelRight,
  PanelRightClose,
  SlidersHorizontal,
  Sparkles,
  BarChart3,
  FileText,
  Clock,
  ExternalLink,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import Link from 'next/link';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

export default function ArticleEditPage() {
  const { id } = useParams();
  const { data: articleResponse, isLoading, error } = useArticle(Number(id));
  const { updateArticle, publishArticle } = useArticleActions();

  // MẶC ĐỊNH ĐÓNG TAB BÊN PHẢI (TRẠNG THÁI BÀI VIẾT & SEO) THEO YÊU CẦU NGƯỜI DÙNG
  const [isRightSidebarOpen, setIsRightSidebarOpen] = useState(false);
  const [activeRightTab, setActiveRightTab] = useState<'settings' | 'seo' | 'timeline'>('settings');

  const article = articleResponse?.data;

  // Track live typing values from Editor to sync with SEO Score panel
  const [editorValues, setEditorValues] = useState<{
    title: string;
    content: string;
    metaDescription: string;
  }>({
    title: '',
    content: '',
    metaDescription: '',
  });

  useEffect(() => {
    if (article) {
      setEditorValues({
        title: article.title || '',
        content: article.content || '',
        metaDescription: article.seo_description || '',
      });
    }
  }, [article]);

  const handleSave = async (updatedData: { title: string; content: string; metaDescription: string }) => {
    try {
      await updateArticle.mutateAsync({
        id: Number(id),
        data: {
          title: updatedData.title,
          content: updatedData.content,
          seo_description: updatedData.metaDescription,
        }
      });
      toast.success("Đã lưu bài viết thành công!");
    } catch {
      toast.error("Không thể lưu bài viết");
    }
  };

  const handleStatusChange = async (newStatus: Article['status']) => {
    try {
      await updateArticle.mutateAsync({
        id: Number(id),
        data: { status: newStatus }
      });
      toast.success(`Đã cập nhật trạng thái: ${newStatus}`);
    } catch {
      toast.error("Lỗi cập nhật trạng thái");
    }
  };

  const handleSettingsSave = async (payload: ArticleUpdatePayload) => {
    try {
      await updateArticle.mutateAsync({
        id: Number(id),
        data: payload,
      });
      toast.success("Đã lưu cài đặt bài viết thành công!");
    } catch {
      toast.error("Không thể lưu cài đặt bài viết");
    }
  };

  const handlePublish = async () => {
    if (!window.confirm('Duyệt bài và gửi lên WordPress site đã kết nối?')) {
      return;
    }

    try {
      await publishArticle.mutateAsync(Number(id));
      toast.success('Đã đưa bài vào queue đăng WordPress.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Không thể gửi bài lên WordPress.');
    }
  };

  if (isLoading) {
    return (
      <div className="h-[60vh] flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-blue-600 animate-spin" />
      </div>
    );
  }

  if (error || !article) {
    return (
      <div className="h-[60vh] flex flex-col items-center justify-center space-y-4">
        <div className="text-rose-500 font-bold text-xl">Không tìm thấy bài viết</div>
        <Button asChild variant="outline">
          <Link href="/articles">Quay lại danh sách</Link>
        </Button>
      </div>
    );
  }

  // Xây dựng Timeline sự kiện thực tế từ dữ liệu bài viết
  const timelineEvents: Array<{ id: string; status: string; user: string; timestamp: string; note?: string }> = [];

  if (article.created_at) {
    timelineEvents.push({
      id: 'init',
      status: 'draft',
      user: 'Khởi tạo từ khóa',
      timestamp: new Date(article.created_at).toLocaleString('vi-VN'),
      note: `Từ khóa: ${article.keyword?.keyword || 'N/A'}`
    });
  }

  if (article.pipeline_status?.steps) {
    const steps = article.pipeline_status.steps;
    if (steps.writing?.status === 'completed') {
      timelineEvents.push({
        id: 'writing',
        status: 'review',
        user: 'AI Writing Agent',
        timestamp: steps.writing.finished_at ? new Date(steps.writing.finished_at).toLocaleString('vi-VN') : 'Đã hoàn thành',
        note: `Độ dài: ${article.word_count || 0} từ`
      });
    }
    if (steps.seo_metadata?.status === 'completed') {
      timelineEvents.push({
        id: 'seo',
        status: 'review',
        user: 'SEO Optimization Agent',
        timestamp: steps.seo_metadata.finished_at ? new Date(steps.seo_metadata.finished_at).toLocaleString('vi-VN') : 'Đã tối ưu',
        note: `SEO Score: ${article.seo_score ?? 'N/A'}/100`
      });
    }
  }

  if (article.status === 'approved' || article.status === 'published') {
    timelineEvents.push({
      id: 'approved',
      status: 'approved',
      user: 'Quản trị viên',
      timestamp: article.updated_at ? new Date(article.updated_at).toLocaleString('vi-VN') : 'Đã duyệt',
      note: 'Bài viết đã được duyệt chất lượng.'
    });
  }

  if (article.published_at || article.status === 'published') {
    timelineEvents.push({
      id: 'published',
      status: 'published',
      user: `WordPress (${article.wordpress_site?.name || 'Site'})`,
      timestamp: article.published_at ? new Date(article.published_at).toLocaleString('vi-VN') : 'Đã xuất bản',
      note: (article.wordpress_post_url || article.wordpress_url) ? `URL: ${article.wordpress_post_url || article.wordpress_url}` : 'Đã đẩy lên WordPress'
    });
  }

  if (article.status === 'failed') {
    timelineEvents.push({
      id: 'failed',
      status: 'rejected',
      user: 'System Error Handler',
      timestamp: article.updated_at ? new Date(article.updated_at).toLocaleString('vi-VN') : 'Lỗi xử lý',
      note: article.review_notes || 'Tác vụ gặp sự cố trong pipeline.'
    });
  }

  return (
    <div className="space-y-6 max-w-[1600px] mx-auto pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 md:p-5 rounded-2xl border border-sky-100 shadow-2xs">
        <div className="flex items-center gap-3.5">
          <Button asChild variant="ghost" size="icon" className="rounded-full hover:bg-sky-50 text-slate-600 hover:text-sky-700">
            <Link href="/articles">
              <ArrowLeft className="w-5 h-5" />
            </Link>
          </Button>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Chỉnh sửa bài viết</h1>
              <Badge variant="outline" className="bg-sky-50 text-sky-700 border-sky-200 text-xs font-semibold">
                ID #{article.id}
              </Badge>
            </div>
            <p className="text-xs md:text-sm font-medium text-slate-500 mt-0.5">
              Từ khóa trọng tâm: <strong className="text-slate-700">{article.keyword?.keyword || article.focus_keyword || 'N/A'}</strong>
              {article.word_count ? ` • ${article.word_count} từ` : ''}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2.5 flex-wrap">
          {/* Nút Toggle mở/đóng tab Trạng thái & SEO Score (Mặc định Đóng) */}
          <Button
            type="button"
            variant="outline"
            onClick={() => setIsRightSidebarOpen(!isRightSidebarOpen)}
            className={cn(
              "gap-2 font-semibold transition-all duration-300 border-sky-200 shadow-2xs text-xs md:text-sm h-9 md:h-10",
              isRightSidebarOpen
                ? "bg-gradient-to-r from-sky-500 to-blue-600 text-white hover:from-sky-600 hover:to-blue-700 border-transparent shadow-sky-200"
                : "bg-white text-slate-700 hover:bg-sky-50/70 hover:text-sky-700 hover:border-sky-300"
            )}
          >
            {isRightSidebarOpen ? (
              <>
                <PanelRightClose className="w-4 h-4" />
                <span>Thu gọn bảng điều khiển</span>
              </>
            ) : (
              <>
                <SlidersHorizontal className="w-4 h-4 text-sky-600" />
                <span>Trạng thái bài viết & SEO</span>
                <span className="flex items-center gap-1 ml-0.5">
                  <Badge variant="outline" className="text-[10px] py-0 px-1.5 bg-emerald-50 text-emerald-700 border-emerald-300 font-bold uppercase">
                    {article.status}
                  </Badge>
                  {article.seo_score ? (
                    <Badge variant="outline" className="text-[10px] py-0 px-1.5 bg-sky-50 text-sky-700 border-sky-300 font-bold">
                      SEO {article.seo_score}đ
                    </Badge>
                  ) : null}
                </span>
              </>
            )}
          </Button>

          {/* Quick link to Content Studio */}
          <Button asChild variant="outline" size="sm" className="border-sky-200 text-sky-700 hover:bg-sky-50 text-xs md:text-sm h-9 md:h-10 gap-1.5">
            <Link href={`/content-studio/${article.id}`}>
              <Sparkles className="w-4 h-4 text-sky-600" />
              <span className="hidden sm:inline">Content Studio</span>
            </Link>
          </Button>

          {/* Publish Button */}
          {article.status !== 'published' && (
            <Button 
              onClick={handlePublish} 
              disabled={publishArticle.isPending}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs md:text-sm h-9 md:h-10 shadow-sm"
            >
              {publishArticle.isPending && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
              Duyệt & Đăng WordPress
            </Button>
          )}
        </div>
      </div>

      {/* Main Workspace Layout with Smooth Toggle */}
      <div className="relative flex flex-col xl:flex-row gap-6 items-start w-full transition-all duration-300 ease-in-out">
        {/* Editor Area (Chiếm toàn bộ không gian thoáng đãng khi Tab Phải đóng) */}
        <div
          className={cn(
            "w-full transition-all duration-300 ease-in-out",
            isRightSidebarOpen ? "xl:w-[calc(100%-380px)]" : "w-full"
          )}
        >
          <ArticleEditor 
            initialTitle={article.title || ''}
            initialContent={article.content || ''}
            initialMetaDescription={article.seo_description || ''}
            keyword={article.keyword?.keyword || article.focus_keyword || ''}
            onSave={handleSave}
            isSaving={updateArticle.isPending}
            hideSeoPanel={true}
            onValuesChange={(vals) => setEditorValues(vals)}
          />
        </div>

        {/* Right Collapsible Panel (Mặc định đóng, mở ra trượt mượt mà) */}
        <div
          className={cn(
            "transition-all duration-300 ease-in-out shrink-0",
            isRightSidebarOpen
              ? "w-full xl:w-[360px] opacity-100 translate-x-0 visible"
              : "w-0 xl:w-0 opacity-0 translate-x-8 overflow-hidden pointer-events-none hidden xl:block"
          )}
        >
          {isRightSidebarOpen && (
            <div className="space-y-4 w-full xl:w-[360px] sticky top-20">
              {/* Tab Selector */}
              <div className="flex items-center p-1 bg-slate-100/90 rounded-xl border border-slate-200/80 shadow-2xs">
                <button
                  type="button"
                  onClick={() => setActiveRightTab('settings')}
                  className={cn(
                    "flex-1 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5",
                    activeRightTab === 'settings'
                      ? "bg-white text-slate-900 shadow-2xs"
                      : "text-slate-500 hover:text-slate-900"
                  )}
                >
                  <FileText className="w-3.5 h-3.5 text-sky-600" />
                  <span>Trạng Thái</span>
                </button>
                <button
                  type="button"
                  onClick={() => setActiveRightTab('seo')}
                  className={cn(
                    "flex-1 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5",
                    activeRightTab === 'seo'
                      ? "bg-white text-slate-900 shadow-2xs"
                      : "text-slate-500 hover:text-slate-900"
                  )}
                >
                  <BarChart3 className="w-3.5 h-3.5 text-emerald-600" />
                  <span>SEO Score</span>
                </button>
                <button
                  type="button"
                  onClick={() => setActiveRightTab('timeline')}
                  className={cn(
                    "flex-1 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5",
                    activeRightTab === 'timeline'
                      ? "bg-white text-slate-900 shadow-2xs"
                      : "text-slate-500 hover:text-slate-900"
                  )}
                >
                  <Clock className="w-3.5 h-3.5 text-purple-600" />
                  <span>Lịch Sử</span>
                </button>
              </div>

              {/* Tab 1: Cài đặt & Trạng thái bài viết */}
              {activeRightTab === 'settings' && (
                <div className="animate-in fade-in-50 duration-200 space-y-4">
                  <ArticleImportSettings 
                    article={article}
                    onSave={handleSettingsSave}
                    onStatusChange={handleStatusChange}
                    isSaving={updateArticle.isPending}
                  />
                </div>
              )}

              {/* Tab 2: Chấm điểm SEO On-Page cập nhật trực tiếp theo thời gian thực */}
              {activeRightTab === 'seo' && (
                <div className="animate-in fade-in-50 duration-200">
                  <SEOScorePanel 
                    content={editorValues.content || article.content || ''} 
                    title={editorValues.title || article.title || ''} 
                    metaDescription={editorValues.metaDescription || article.seo_description || ''} 
                    keyword={article.keyword?.keyword || article.focus_keyword || ''} 
                  />
                </div>
              )}

              {/* Tab 3: Timeline lịch sử xử lý */}
              {activeRightTab === 'timeline' && (
                <div className="animate-in fade-in-50 duration-200">
                  <ArticleTimeline events={timelineEvents} />
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
