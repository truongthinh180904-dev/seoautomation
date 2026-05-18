"use client";

import React from 'react';
import { useParams } from 'next/navigation';
import { useArticle, useArticleActions } from '@/hooks/useArticles';
import ArticleEditor from '@/components/features/articles/ArticleEditor';
import ArticleTimeline from '@/components/features/articles/ArticleTimeline';
import { ArrowLeft, Loader2, CheckCircle, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
import { toast } from 'sonner';

export default function ArticleEditPage() {
  const { id } = useParams();
  const { data: articleResponse, isLoading, error } = useArticle(Number(id));
  const { updateArticle } = useArticleActions();

  const article = articleResponse?.data;

  const handleSave = async (updatedData: { title: string, content: string, metaDescription: string }) => {
    try {
      await updateArticle.mutateAsync({
        id: Number(id),
        data: {
          title: updatedData.title,
          content: updatedData.content,
          seo_description: updatedData.metaDescription,
        }
      });
      toast.success("Article saved successfully");
    } catch {
      toast.error("Failed to save article");
    }
  };

  const handleStatusChange = async (newStatus: Article['status']) => {
    try {
      await updateArticle.mutateAsync({
        id: Number(id),
        data: { status: newStatus }
      });
      toast.success(`Article ${newStatus}`);
    } catch {
      toast.error("Failed to update status");
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
        <div className="text-rose-500 font-bold text-xl">Article not found</div>
        <Link href="/articles">
          <Button variant="outline">Back to Articles</Button>
        </Link>
      </div>
    );
  }

  // Mock timeline events for now
  const timelineEvents = [
    { id: '1', status: 'published', user: 'System (WordPress)', timestamp: '2 hours ago' },
    { id: '2', status: 'approved', user: 'Admin', timestamp: '3 hours ago', note: 'Content looks great.' },
    { id: '3', status: 'review', user: 'AI Writing Agent', timestamp: '4 hours ago' },
    { id: '4', status: 'draft', user: 'System', timestamp: '5 hours ago' },
  ];

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
            <h1 className="text-2xl font-black text-slate-900 tracking-tight">Edit Article</h1>
            <div className="flex items-center gap-2 mt-1">
              <span className="text-xs font-bold text-slate-500 uppercase tracking-widest">Keyword:</span>
              <span className="text-xs font-black text-blue-600 px-2 py-0.5 bg-blue-50 rounded-full">
                {article.keyword?.keyword}
              </span>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Button 
            variant="outline" 
            className="rounded-xl border-slate-200"
            onClick={() => handleStatusChange('rejected')}
          >
            <XCircle className="w-4 h-4 mr-2 text-rose-500" />
            Reject
          </Button>
          <Button 
            variant="default" 
            className="bg-emerald-600 hover:bg-emerald-700 rounded-xl"
            onClick={() => handleStatusChange('approved')}
          >
            <CheckCircle className="w-4 h-4 mr-2" />
            Approve
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
        <div className="xl:col-span-9">
          <ArticleEditor 
            initialTitle={article.title}
            initialContent={article.content ?? ''}
            initialMetaDescription={article.seo_description || ''}
            keyword={article.keyword?.keyword || ''}
            onSave={handleSave}
          />
        </div>

        <div className="xl:col-span-3">
          <ArticleTimeline events={timelineEvents} />
        </div>
      </div>
    </div>
  );
}
