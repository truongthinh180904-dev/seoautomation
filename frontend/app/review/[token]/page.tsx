"use client";

import { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import ArticlePreview from '@/components/features/articles/ArticlePreview';
import ApprovalButtons from '@/components/features/zalo/ApprovalButtons';
import apiClient from '@/lib/api/client';

export default function ReviewPage() {
  const params = useParams();
  const token = params.token as string;

  const [article, setArticle] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionSuccess, setActionSuccess] = useState('');

  useEffect(() => {
    if (!token) return;
    
    // Fetch article by review token (no auth required)
    apiClient.get(`/articles/review/${token}`)
      .then(res => setArticle(res.data.data || res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to load article. The token might be invalid or expired.'))
      .finally(() => setLoading(false));
  }, [token]);

  if (loading) return <div className="min-h-screen flex items-center justify-center text-slate-500">Loading article...</div>;
  if (error) return <div className="min-h-screen flex items-center justify-center text-red-500">{error}</div>;
  if (!article) return <div className="min-h-screen flex items-center justify-center text-slate-500">Article not found</div>;

  return (
    <div className="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-4xl mx-auto space-y-6">
        {actionSuccess ? (
          <div className="bg-white border border-green-200 text-green-700 px-6 py-12 rounded-xl shadow-sm text-center">
            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </div>
            <h2 className="text-2xl font-bold mb-2">Review Submitted</h2>
            <p className="text-slate-600">{actionSuccess}</p>
          </div>
        ) : (
          <>
            <ArticlePreview article={article} />
            <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 sticky bottom-4">
              <ApprovalButtons 
                token={token} 
                articleId={article.id} 
                onSuccess={(msg) => setActionSuccess(msg)} 
              />
            </div>
          </>
        )}
      </div>
    </div>
  );
}
