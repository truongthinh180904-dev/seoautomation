"use client";

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Edit, ExternalLink, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import ArticlePreview from '@/components/features/articles/ArticlePreview';
import { useArticle } from '@/hooks/useArticles';

export default function ArticleDetailPage() {
  const { id } = useParams();
  const articleId = Number(id);
  const { data: articleResponse, isLoading, error } = useArticle(articleId);
  const article = articleResponse?.data;

  if (isLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  if (error || !article) {
    return (
      <div className="flex h-[60vh] flex-col items-center justify-center gap-4">
        <div className="text-xl font-bold text-rose-500">Không tải được bài viết</div>
        <Link href="/articles">
          <Button variant="outline">Quay lại danh sách</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Link href="/articles">
            <Button variant="ghost" size="icon" className="rounded-full">
              <ArrowLeft className="h-5 w-5" />
            </Button>
          </Link>
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-900">Xem bài viết</h1>
            <p className="text-sm font-medium text-slate-500">Bản xem nội bộ trên dashboard</p>
          </div>
        </div>

        <div className="flex gap-2">
          {article.review_token && (
            <Link href={`/review/${article.review_token}`} target="_blank">
              <Button variant="outline" className="rounded-xl">
                <ExternalLink className="mr-2 h-4 w-4" />
                Link duyệt public
              </Button>
            </Link>
          )}
          <Link href={`/articles/${article.id}/edit`}>
            <Button className="rounded-xl bg-blue-600 hover:bg-blue-700">
              <Edit className="mr-2 h-4 w-4" />
              Sửa bài
            </Button>
          </Link>
        </div>
      </div>

      <ArticlePreview article={article} />
    </div>
  );
}
