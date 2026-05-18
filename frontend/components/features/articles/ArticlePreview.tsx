import React from 'react';

interface ArticlePreviewProps {
  article: Article;
}

export default function ArticlePreview({ article }: ArticlePreviewProps) {
  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div className="p-6 border-b border-slate-200 bg-slate-50/50">
        <div className="flex flex-wrap gap-4 items-center justify-between mb-4">
          <h1 className="text-2xl font-bold text-slate-900">{article.title || 'Untitled Article'}</h1>
          <div className="flex gap-2">
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              SEO Score: {article.seo_score ?? 'N/A'}
            </span>
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
              Word Count: {article.word_count ?? 0}
            </span>
          </div>
        </div>
        
        <div className="text-sm text-slate-600 mb-2">
          <strong className="text-slate-800">Keyword:</strong> {article.keyword?.keyword || 'N/A'}
        </div>
        
        {article.seo_description && (
          <div className="text-sm text-slate-600">
            <strong className="text-slate-800">Meta Description:</strong> {article.seo_description}
          </div>
        )}
      </div>
      
      <div 
        className="p-8 prose prose-slate max-w-none prose-headings:font-bold prose-a:text-blue-600" 
        dangerouslySetInnerHTML={{ __html: article.content || '<p>No content generated yet.</p>' }} 
      />
    </div>
  );
}
