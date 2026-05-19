import React from 'react';

interface ArticlePreviewProps {
  article: Article;
}

export default function ArticlePreview({ article }: ArticlePreviewProps) {
  const quality = article.quality_report;

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
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

      <aside className="space-y-4">
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-black text-slate-900">SEO QA</h2>
          {quality ? (
            <div className="mt-4 space-y-3">
              {[
                ['SEO', quality.seo_score ?? 0],
                ['Readability', quality.readability_score ?? 0],
                ['Media', quality.media_score ?? 0],
                ['WordPress', quality.wordpress_readiness_score ?? 0],
              ].map(([label, value]) => (
                <div key={label}>
                  <div className="mb-1 flex justify-between text-sm font-bold text-slate-600">
                    <span>{label}</span>
                    <span>{value}</span>
                  </div>
                  <div className="h-2 rounded-full bg-slate-100">
                    <div className="h-2 rounded-full bg-blue-600" style={{ width: `${Number(value)}%` }} />
                  </div>
                </div>
              ))}
              <div className="rounded-xl bg-slate-50 p-4 text-center">
                <div className="text-xs font-bold uppercase tracking-widest text-slate-400">Total score</div>
                <div className="mt-1 text-3xl font-black text-slate-900">{quality.total_score ?? article.seo_score ?? 0}</div>
              </div>
            </div>
          ) : (
            <p className="mt-3 text-sm text-slate-500">Chưa có quality report.</p>
          )}
        </div>

        {quality?.warnings && quality.warnings.length > 0 && (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h3 className="font-black text-amber-900">Warnings</h3>
            <div className="mt-3 space-y-2 text-sm font-medium text-amber-800">
              {quality.warnings.slice(0, 6).map((warning, index) => (
                <div key={index}>{warning}</div>
              ))}
            </div>
          </div>
        )}
      </aside>
    </div>
  );
}
