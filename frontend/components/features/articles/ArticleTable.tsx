"use client";

import React from 'react';
import ArticleStatusBadge from './ArticleStatusBadge';
import { Eye, Edit, RotateCcw, Trash2 } from 'lucide-react';
import { useArticleActions } from '@/hooks/useArticles';
import Link from 'next/link';

interface ArticleTableProps {
  articles: any[];
  isLoading: boolean;
}

export default function ArticleTable({ articles, isLoading }: ArticleTableProps) {
  const { deleteArticle, retryArticle } = useArticleActions();
  
  if (isLoading) {
    return <div className="text-center py-12 text-slate-500 animate-pulse">Đang tải dữ liệu bài viết...</div>;
  }

  if (!articles?.length) {
    return <div className="text-center py-12 text-slate-500 bg-slate-50 rounded-xl border border-slate-200 border-dashed">Không tìm thấy bài viết nào phù hợp.</div>;
  }

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50/80 backdrop-blur-sm sticky top-0">
            <tr>
              <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Tiêu đề</th>
              <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Từ khoá</th>
              <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Trạng thái</th>
              <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Điểm SEO</th>
              <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Chi phí AI</th>
              <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Thao tác</th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-slate-100">
            {articles.map((article) => (
              <tr key={article.id} className="hover:bg-slate-50/80 transition-colors group">
                <td className="px-6 py-4">
                  <div className="text-sm font-semibold text-slate-900 line-clamp-1">{article.title || 'Đang tạo nội dung...'}</div>
                  <div className="text-xs text-slate-500 mt-1.5 flex gap-2">
                    {article.word_count ? <span>{article.word_count} từ</span> : null}
                    {article.wordpress_site?.name && <span className="bg-blue-100 text-blue-700 px-1.5 rounded">{article.wordpress_site.name}</span>}
                  </div>
                </td>
                <td className="px-6 py-4 text-sm font-medium text-slate-600">
                  {article.keyword?.keyword || article.keyword || 'N/A'}
                </td>
                <td className="px-6 py-4">
                  <ArticleStatusBadge status={article.status} />
                </td>
                <td className="px-6 py-4">
                  {article.seo_score ? (
                    <span className={`inline-flex items-center justify-center w-9 h-9 rounded-full font-bold text-sm ${article.seo_score >= 80 ? 'bg-green-100 text-green-700' : article.seo_score >= 60 ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'}`}>
                      {article.seo_score}
                    </span>
                  ) : (
                    <span className="text-slate-300 font-bold">--</span>
                  )}
                </td>
                <td className="px-6 py-4 text-sm font-mono text-slate-600">
                  ${Number(article.ai_cost_usd || 0).toFixed(4)}
                </td>
                <td className="px-6 py-4 text-right text-sm font-medium">
                  <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    {article.review_token && (
                      <Link href={`/review/${article.review_token}`} target="_blank" className="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Xem public review">
                        <Eye className="w-4 h-4" />
                      </Link>
                    )}
                    <button className="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Chỉnh sửa">
                      <Edit className="w-4 h-4" />
                    </button>
                    {article.status === 'failed' && (
                      <button 
                        onClick={() => {
                          if (confirm('Thử lại quá trình đăng bài?')) retryArticle.mutate(article.id);
                        }}
                        className="p-2 text-slate-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-all" 
                        title="Thử lại (Retry)"
                        disabled={retryArticle.isPending}
                      >
                        <RotateCcw className="w-4 h-4" />
                      </button>
                    )}
                    <button 
                      onClick={() => {
                        if (confirm('Bạn có chắc chắn muốn xóa bài viết này?')) deleteArticle.mutate(article.id);
                      }}
                      className="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" 
                      title="Xóa bài viết"
                      disabled={deleteArticle.isPending}
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
