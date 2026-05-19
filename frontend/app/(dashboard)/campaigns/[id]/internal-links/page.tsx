"use client";

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Link2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useArticles } from '@/hooks/useArticles';

export default function CampaignInternalLinksPage() {
  const { id } = useParams();
  const campaignId = Number(id);
  const { data, isLoading } = useArticles({ page: 1, campaign_id: campaignId, per_page: 100 });
  const articles = data?.data ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link href={`/campaigns/${campaignId}`}>
          <Button variant="ghost" size="icon" className="rounded-full">
            <ArrowLeft className="h-5 w-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-black tracking-tight text-slate-900">Internal Link Map</h1>
          <p className="mt-1 text-sm text-slate-500">Theo dõi internal links đã được gắn cho bài viết trong campaign.</p>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="p-8 text-sm font-medium text-slate-500">Đang tải...</div>
        ) : articles.length === 0 ? (
          <div className="p-10 text-center">
            <Link2 className="mx-auto h-10 w-10 text-slate-300" />
            <h2 className="mt-4 text-lg font-bold text-slate-900">Chưa có bài viết</h2>
          </div>
        ) : (
          <div className="divide-y divide-slate-100">
            {articles.map((article: Article) => {
              const links = Array.isArray(article.internal_links) ? article.internal_links : [];
              return (
                <div key={article.id} className="p-5">
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <h2 className="font-black text-slate-900">{article.title}</h2>
                      <p className="mt-1 text-sm text-slate-500">{links.length} internal links</p>
                    </div>
                    <Link href={`/articles/${article.id}`}>
                      <Button variant="outline" size="sm">Xem bài</Button>
                    </Link>
                  </div>
                  {links.length > 0 && (
                    <div className="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                      {links.slice(0, 8).map((link: unknown, index: number) => {
                        const item = link as { url?: string; anchor?: string; text?: string };
                        return (
                          <div key={index} className="rounded-xl bg-slate-50 p-3 text-sm">
                            <div className="font-bold text-slate-800">{item.anchor || item.text || 'Internal link'}</div>
                            <div className="mt-1 break-all text-slate-500">{item.url || JSON.stringify(item)}</div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
