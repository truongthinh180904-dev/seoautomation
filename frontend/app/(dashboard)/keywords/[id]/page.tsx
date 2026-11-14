"use client";

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { ArrowLeft, ImagePlus, Loader2, Save } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useKeyword, useKeywordActions } from '@/hooks/useKeywords';
import { useArticleActions } from '@/hooks/useArticles';
import { useMemo, useState } from 'react';

type ImageRow = {
  url: string;
  alt: string;
  caption: string;
};

function parseImageRows(value: unknown): ImageRow[] {
  const text = typeof value === 'string' ? value : '';
  const rows = text
    .split(';')
    .map((chunk) => chunk.trim())
    .filter(Boolean)
    .map((chunk) => {
      const [url = '', alt = '', caption = ''] = chunk.split('|').map((part) => part.trim());
      return { url, alt, caption };
    });

  return rows.length ? rows : [{ url: '', alt: '', caption: '' }];
}

function stringifyImageRows(rows: ImageRow[]): string {
  return rows
    .filter((row) => row.url.trim())
    .map((row) => [row.url.trim(), row.alt.trim(), row.caption.trim()].join('|'))
    .join(';');
}

function linesToArray(value: string): string[] {
  return value.split('\n').map((item) => item.trim()).filter(Boolean);
}

export default function KeywordDetailPage() {
  const { id } = useParams();
  const keywordId = Number(id);
  const { data, isLoading, error } = useKeyword(keywordId);
  const keyword = data?.data;

  if (isLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  if (error || !keyword) {
    return (
      <div className="flex h-[60vh] flex-col items-center justify-center gap-4">
        <div className="text-xl font-bold text-rose-500">Không tải được keyword</div>
        <Link href="/keywords">
          <Button variant="outline">Quay lại danh sách</Button>
        </Link>
      </div>
    );
  }

  return <KeywordDetailForm key={keyword.id} keyword={keyword} />;
}

function KeywordDetailForm({ keyword }: { keyword: Keyword }) {
  const { updateKeyword } = useKeywordActions();
  const { generateArticle } = useArticleActions();
  const meta = keyword.meta ?? {};
  const [keywordText, setKeywordText] = useState(keyword.keyword ?? '');
  const [searchIntent, setSearchIntent] = useState(keyword.search_intent ?? '');
  const [targetWordCount, setTargetWordCount] = useState(keyword.target_word_count ?? 1200);
  const [briefNotes, setBriefNotes] = useState(keyword.brief_notes ?? '');
  const [mustInclude, setMustInclude] = useState((keyword.must_include_points ?? []).join('\n'));
  const [avoidTopics, setAvoidTopics] = useState((keyword.avoid_topics ?? []).join('\n'));
  const [referenceUrls, setReferenceUrls] = useState((keyword.reference_urls ?? []).join('\n'));
  const [internalLinks, setInternalLinks] = useState(String(meta.internal_links ?? ''));
  const [featuredImageUrl, setFeaturedImageUrl] = useState(String(meta.featured_image_url ?? ''));
  const [imagePrompt, setImagePrompt] = useState(String(meta.image_generation_prompt ?? ''));
  const [imageSearchQuery, setImageSearchQuery] = useState(String(meta.image_search_query ?? ''));
  const [imageSource, setImageSource] = useState(String(meta.image_source ?? 'hybrid'));
  const [inlineImageCount, setInlineImageCount] = useState(Number(meta.inline_image_count ?? 3));
  const [imageRows, setImageRows] = useState<ImageRow[]>(parseImageRows(meta.image_urls));
  const rawImportRows = useMemo(() => Object.entries(keyword.raw_import_row ?? {}), [keyword.raw_import_row]);

  const updateImageRow = (index: number, patch: Partial<ImageRow>) => {
    setImageRows((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...patch } : row));
  };

  const removeImageRow = (index: number) => {
    setImageRows((rows) => rows.filter((_, rowIndex) => rowIndex !== index));
  };

  const handleSave = async () => {
    const count = Math.min(10, Math.max(0, inlineImageCount || 0));

    try {
      await updateKeyword.mutateAsync({
        id: keyword.id,
        data: {
          keyword: keywordText,
          search_intent: searchIntent || null,
          target_word_count: targetWordCount,
          brief_notes: briefNotes || null,
          must_include_points: linesToArray(mustInclude),
          avoid_topics: linesToArray(avoidTopics),
          reference_urls: linesToArray(referenceUrls),
          meta: {
            ...(keyword.meta ?? {}),
            featured_image_url: featuredImageUrl || null,
            image_urls: stringifyImageRows(imageRows) || null,
            image_generation_prompt: imagePrompt || null,
            image_search_query: imageSearchQuery || null,
            image_source: imageSource,
            internal_links: internalLinks || null,
            inline_image_count: count,
          },
        },
      });
      toast.success('Đã lưu brief keyword.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Không thể lưu keyword.');
    }
  };

  const handleGenerate = async () => {
    await handleSave();
    generateArticle.mutate(keyword.id);
  };

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Link href="/keywords">
            <Button variant="ghost" size="icon" className="rounded-full">
              <ArrowLeft className="h-5 w-5" />
            </Button>
          </Link>
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-900">Chi tiết keyword</h1>
            <p className="text-sm font-medium text-slate-500">Chỉnh brief, hình ảnh và dữ liệu Excel trước khi viết bài</p>
          </div>
        </div>

        <div className="flex gap-2">
          <Button variant="outline" className="rounded-xl" onClick={handleSave} disabled={updateKeyword.isPending}>
            <Save className="mr-2 h-4 w-4" />
            Lưu brief
          </Button>
          <Button className="rounded-xl bg-blue-600 hover:bg-blue-700" onClick={handleGenerate} disabled={generateArticle.isPending || updateKeyword.isPending}>
            AI viết bài
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="font-black text-slate-900">Brief nội dung</h2>
            <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
              <label className="md:col-span-2">
                <span className="text-xs font-bold text-slate-500">Keyword</span>
                <input value={keywordText} onChange={(e) => setKeywordText(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Search intent</span>
                <input value={searchIntent} onChange={(e) => setSearchIntent(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Target word count</span>
                <input type="number" min={300} max={10000} value={targetWordCount} onChange={(e) => setTargetWordCount(Number(e.target.value))} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label className="md:col-span-2">
                <span className="text-xs font-bold text-slate-500">Brief notes</span>
                <textarea value={briefNotes} onChange={(e) => setBriefNotes(e.target.value)} rows={5} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Must include, mỗi dòng 1 ý</span>
                <textarea value={mustInclude} onChange={(e) => setMustInclude(e.target.value)} rows={5} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Avoid topics, mỗi dòng 1 ý</span>
                <textarea value={avoidTopics} onChange={(e) => setAvoidTopics(e.target.value)} rows={5} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Reference URLs</span>
                <textarea value={referenceUrls} onChange={(e) => setReferenceUrls(e.target.value)} rows={5} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Internal links: URL|anchor, mỗi dòng hoặc dấu ;</span>
                <textarea value={internalLinks} onChange={(e) => setInternalLinks(e.target.value)} rows={5} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
              <h2 className="font-black text-slate-900">Dashboard hình ảnh</h2>
              <Button variant="outline" size="sm" className="rounded-lg" onClick={() => setImageRows((rows) => [...rows, { url: '', alt: '', caption: '' }])}>
                <ImagePlus className="mr-2 h-4 w-4" />
                Thêm dòng ảnh
              </Button>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4">
              <label>
                <span className="text-xs font-bold text-slate-500">Ảnh đại diện URL</span>
                <input value={featuredImageUrl} onChange={(e) => setFeaturedImageUrl(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Số ảnh trong bài, mặc định 3 tối đa 10</span>
                <input type="number" min={0} max={10} value={inlineImageCount} onChange={(e) => setInlineImageCount(Number(e.target.value))} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Nguồn ảnh tự động</span>
                <select value={imageSource} onChange={(e) => setImageSource(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                  <option value="hybrid">Cân bằng: tìm ảnh trước, thiếu mới AI</option>
                  <option value="stock">Tiết kiệm: chỉ Pexels/Unsplash</option>
                  <option value="ai">Chất lượng/style: chỉ AI tạo ảnh</option>
                </select>
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Từ khóa tìm ảnh stock</span>
                <textarea value={imageSearchQuery} onChange={(e) => setImageSearchQuery(e.target.value)} rows={2} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
              <label>
                <span className="text-xs font-bold text-slate-500">Prompt tạo ảnh AI nếu thiếu ảnh</span>
                <textarea value={imagePrompt} onChange={(e) => setImagePrompt(e.target.value)} rows={3} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
              </label>
            </div>

            <div className="mt-5 space-y-3">
              {imageRows.map((row, index) => (
                <div key={index} className="grid grid-cols-1 gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 md:grid-cols-[120px_1fr]">
                  <div className="overflow-hidden rounded-lg border border-slate-200 bg-white aspect-[4/3]">
                    {row.url ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={row.url} alt={row.alt || 'Ảnh trong bài'} className="h-full w-full object-cover" />
                    ) : (
                      <div className="flex h-full items-center justify-center text-xs font-bold text-slate-300">Ảnh</div>
                    )}
                  </div>
                  <div className="space-y-2">
                    <input placeholder="URL ảnh" value={row.url} onChange={(e) => updateImageRow(index, { url: e.target.value })} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <input placeholder="Alt text / nội dung ảnh" value={row.alt} onChange={(e) => updateImageRow(index, { alt: e.target.value })} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <textarea placeholder="Mô tả/caption cho ảnh" value={row.caption} onChange={(e) => updateImageRow(index, { caption: e.target.value })} rows={2} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <button type="button" onClick={() => removeImageRow(index)} className="text-xs font-bold text-red-500 hover:text-red-600">Xóa dòng ảnh</button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <aside className="space-y-4">
          <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="font-black text-slate-900">Dữ liệu import gốc</h2>
            <div className="mt-4 max-h-[720px] space-y-2 overflow-auto">
              {rawImportRows.map(([key, value]) => (
                <div key={key} className="rounded-lg bg-slate-50 p-3">
                  <div className="text-[11px] font-black uppercase tracking-widest text-slate-400">{key}</div>
                  <pre className="mt-1 whitespace-pre-wrap text-xs font-semibold text-slate-600">{typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value ?? '-')}</pre>
                </div>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}
