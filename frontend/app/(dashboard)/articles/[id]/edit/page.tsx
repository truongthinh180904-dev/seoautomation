"use client";

import React from 'react';
import { useParams } from 'next/navigation';
import { useArticle, useArticleActions } from '@/hooks/useArticles';
import ArticleEditor from '@/components/features/articles/ArticleEditor';
import ArticleImportSettings from '@/components/features/articles/ArticleImportSettings';
import ArticleTimeline from '@/components/features/articles/ArticleTimeline';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
import { toast } from 'sonner';

export default function ArticleEditPage() {
  const { id } = useParams();
  const { data: articleResponse, isLoading, error } = useArticle(Number(id));
  const { updateArticle, publishArticle } = useArticleActions();

  const article = articleResponse?.data;

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
        <Link href="/articles">
          <Button variant="outline">Quay lại danh sách</Button>
        </Link>
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
    <div className="space-y-8 max-w-[1400px] mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div className="flex items-center gap-4">
          <Link href="/articles">
            <Button variant="ghost" size="icon" className="rounded-full">
              <ArrowLeft className="w-5 h-5" />
            </Button>
          </Link>
          <div>
            <h1 className="text-2xl font-black text-slate-900 tracking-tight">Chỉnh sửa bài viết</h1>
            <p className="text-sm font-medium text-slate-500">ID #{article.id} • {article.keyword?.keyword}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          {article.status !== 'published' && (
            <Button 
              onClick={handlePublish} 
              disabled={publishArticle.isPending}
              className="bg-emerald-600 hover:bg-emerald-700 font-bold"
            >
              {publishArticle.isPending && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
              Duyệt & Đăng WordPress
            </Button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-8">
        {/* Editor Area */}
        <div className="space-y-8">
          <ArticleEditor 
            initialTitle={article.title || ''}
            initialContent={article.content || ''}
            initialMetaDescription={article.seo_description || ''}
            keyword={article.keyword?.keyword || ''}
            onSave={handleSave}
            isSaving={updateArticle.isPending}
          />
        </div>

        {/* Sidebar Controls */}
        <div className="space-y-6">
          <ArticleImportSettings 
            article={article}
            onSave={handleSettingsSave}
            onStatusChange={handleStatusChange}
            isSaving={updateArticle.isPending}
          />
          <ArticleTimeline events={timelineEvents} />
        </div>
      </div>
    </div>
  );
}
