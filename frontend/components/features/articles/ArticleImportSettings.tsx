"use client";

import React, { useMemo, useState } from 'react';
import { ImagePlus, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useMediaAssetActions, useMediaAssets } from '@/hooks/useMediaAssets';
import { useArticleActions } from '@/hooks/useArticles';
import { toast } from 'sonner';

interface ArticleImportSettingsProps {
  article: Article;
  onSave: (payload: ArticleUpdatePayload) => Promise<void> | void;
  isSaving?: boolean;
}

function valueToText(value: unknown): string {
  if (Array.isArray(value)) return value.join('\n');
  if (value && typeof value === 'object') return JSON.stringify(value, null, 2);
  return value ? String(value) : '';
}

export default function ArticleImportSettings({ article, onSave, isSaving }: ArticleImportSettingsProps) {
  const mediaPlan = article.media_plan ?? {};
  const keywordMeta = article.keyword?.meta ?? {};
  const [inlineImageCount, setInlineImageCount] = useState(
    Number(mediaPlan.inline_image_count ?? keywordMeta.inline_image_count ?? 3)
  );
  const [featuredImageUrl, setFeaturedImageUrl] = useState(String(mediaPlan.featured_image_url ?? keywordMeta.featured_image_url ?? ''));
  const [imageUrls, setImageUrls] = useState(String(mediaPlan.image_urls ?? keywordMeta.image_urls ?? ''));
  const [internalLinks, setInternalLinks] = useState(String(mediaPlan.internal_links ?? keywordMeta.internal_links ?? ''));
  const [imagePrompt, setImagePrompt] = useState(String(mediaPlan.image_generation_prompt ?? keywordMeta.image_generation_prompt ?? ''));
  const [imageSearchQuery, setImageSearchQuery] = useState(String(mediaPlan.image_search_query ?? keywordMeta.image_search_query ?? ''));
  const [imageSource, setImageSource] = useState(String(mediaPlan.image_source ?? keywordMeta.image_source ?? 'hybrid'));
  const [newImageUrl, setNewImageUrl] = useState('');
  const [newImageAlt, setNewImageAlt] = useState('');
  const [newImageCaption, setNewImageCaption] = useState('');

  const { data: mediaResponse } = useMediaAssets({ article_id: article.id, per_page: 100 });
  const mediaAssets = mediaResponse?.data ?? [];
  const mediaActions = useMediaAssetActions();
  const { generateImages } = useArticleActions();

  const importRows = useMemo(() => {
    const keyword = article.keyword;
    return [
      ['Search intent', keyword?.search_intent],
      ['Target word count', keyword?.target_word_count],
      ['Target URL', keyword?.target_url],
      ['Canonical URL', keyword?.canonical_url],
      ['Brief notes', keyword?.brief_notes],
      ['Must include', keyword?.must_include_points],
      ['Avoid topics', keyword?.avoid_topics],
      ['Reference URLs', keyword?.reference_urls],
      ['Raw Excel row', keyword?.raw_import_row],
    ];
  }, [article.keyword]);

  const handleSave = async () => {
    const count = Math.min(10, Math.max(0, inlineImageCount || 0));
    await onSave({
      media_plan: {
        ...mediaPlan,
        inline_image_count: count,
        featured_image_url: featuredImageUrl || null,
        image_urls: imageUrls || null,
        internal_links: internalLinks || null,
        image_generation_prompt: imagePrompt || null,
        image_search_query: imageSearchQuery || null,
        image_source: imageSource,
      },
    });
  };

  const handleAddImage = async () => {
    if (!newImageUrl.trim()) return;

    await mediaActions.createAsset.mutateAsync({
      article_id: article.id,
      source_type: 'external_url',
      source_url: newImageUrl.trim(),
      alt_text: newImageAlt || article.keyword?.keyword || article.title,
      caption: newImageCaption || null,
      metadata: { role: 'inline', keyword: article.keyword?.keyword },
    });

    setNewImageUrl('');
    setNewImageAlt('');
    setNewImageCaption('');
  };

  const handleGenerateImages = async () => {
    try {
      await handleSave();
      await generateImages.mutateAsync(article.id);
      toast.success('Đã đưa job tạo ảnh AI vào queue.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Không thể tạo ảnh AI.');
    }
  };

  return (
    <div className="space-y-4">
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-base font-black text-slate-900">Brief từ Excel</h2>
        <div className="mt-4 space-y-3">
          {importRows.map(([label, value]) => (
            <div key={String(label)}>
              <div className="text-[11px] font-black uppercase tracking-widest text-slate-400">{String(label)}</div>
              <pre className="mt-1 max-h-32 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-2 text-xs font-semibold text-slate-600">
                {valueToText(value) || '-'}
              </pre>
            </div>
          ))}
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-base font-black text-slate-900">Cài đặt ảnh & link</h2>
        <div className="mt-4 space-y-4">
          <label className="block">
            <span className="text-xs font-bold text-slate-500">Số ảnh trong bài</span>
            <input
              type="number"
              min={0}
              max={10}
              value={inlineImageCount}
              onChange={(event) => setInlineImageCount(Number(event.target.value))}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold"
            />
            <span className="mt-1 block text-xs text-slate-400">Mặc định 3, tối đa 10.</span>
          </label>

          <label className="block">
            <span className="text-xs font-bold text-slate-500">Ảnh đại diện URL</span>
            <input value={featuredImageUrl} onChange={(e) => setFeaturedImageUrl(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </label>

          <label className="block">
            <span className="text-xs font-bold text-slate-500">Ảnh trong bài từ Excel</span>
            <textarea value={imageUrls} onChange={(e) => setImageUrls(e.target.value)} rows={3} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </label>

          <label className="block">
            <span className="text-xs font-bold text-slate-500">Internal links</span>
            <textarea value={internalLinks} onChange={(e) => setInternalLinks(e.target.value)} rows={3} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </label>

          <label className="block">
            <span className="text-xs font-bold text-slate-500">Nguồn ảnh tự động</span>
            <select
              value={imageSource}
              onChange={(event) => setImageSource(event.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold"
            >
              <option value="hybrid">Cân bằng: tìm ảnh trước, thiếu mới AI</option>
              <option value="stock">Tiết kiệm: chỉ Pexels/Unsplash</option>
              <option value="ai">Chất lượng/style: chỉ AI tạo ảnh</option>
            </select>
          </label>

          <label className="block">
            <span className="text-xs font-bold text-slate-500">Từ khóa tìm ảnh stock</span>
            <textarea value={imageSearchQuery} onChange={(e) => setImageSearchQuery(e.target.value)} rows={2} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </label>

          <label className="block">
            <span className="text-xs font-bold text-slate-500">Prompt tạo ảnh AI nếu thiếu ảnh</span>
            <textarea value={imagePrompt} onChange={(e) => setImagePrompt(e.target.value)} rows={3} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </label>

          <Button onClick={handleSave} disabled={isSaving} className="w-full rounded-xl bg-blue-600 hover:bg-blue-700">
            <Save className="mr-2 h-4 w-4" />
            Lưu biến bài viết
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={handleGenerateImages}
            disabled={generateImages.isPending}
            className="w-full rounded-xl"
          >
            <ImagePlus className="mr-2 h-4 w-4" />
            Tạo ảnh AI còn thiếu
          </Button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-base font-black text-slate-900">Thêm ảnh trong bài</h2>
        <div className="mt-4 space-y-3">
          <input placeholder="URL ảnh" value={newImageUrl} onChange={(e) => setNewImageUrl(e.target.value)} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input placeholder="Alt text" value={newImageAlt} onChange={(e) => setNewImageAlt(e.target.value)} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input placeholder="Caption/mô tả" value={newImageCaption} onChange={(e) => setNewImageCaption(e.target.value)} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <Button variant="outline" onClick={handleAddImage} disabled={mediaActions.createAsset.isPending} className="w-full rounded-xl">
            <ImagePlus className="mr-2 h-4 w-4" />
            Thêm ảnh
          </Button>
        </div>

        <div className="mt-4 space-y-2">
          {mediaAssets.map((asset) => (
            <div key={asset.id} className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs">
              <div className="font-bold text-slate-700">{asset.alt_text || asset.caption || asset.source_url}</div>
              <div className="mt-1 text-slate-400">{asset.status} · {String(asset.metadata?.role ?? 'inline')}</div>
              {asset.error_message && <div className="mt-1 text-red-600">{asset.error_message}</div>}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
