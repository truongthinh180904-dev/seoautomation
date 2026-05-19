"use client";

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Image as ImageIcon, Loader2, RefreshCw, UploadCloud } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCampaign } from '@/hooks/useCampaigns';
import { useMediaAssetActions, useMediaAssets } from '@/hooks/useMediaAssets';

const statusClasses: Record<MediaAsset['status'], string> = {
  pending: 'bg-slate-100 text-slate-700',
  downloaded: 'bg-blue-50 text-blue-700',
  uploaded: 'bg-emerald-50 text-emerald-700',
  failed: 'bg-rose-50 text-rose-700',
};

export default function CampaignMediaPage() {
  const { id } = useParams();
  const campaignId = Number(id);
  const { data, isLoading } = useMediaAssets({ campaign_id: campaignId });
  const { data: campaignResponse } = useCampaign(campaignId);
  const actions = useMediaAssetActions();
  const mediaAssets = data?.data ?? [];
  const wordpressSiteId = campaignResponse?.data.wordpress_site?.id;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link href={`/campaigns/${campaignId}`}>
          <Button variant="ghost" size="icon" className="rounded-full">
            <ArrowLeft className="h-5 w-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-black tracking-tight text-slate-900">Campaign Media</h1>
          <p className="mt-1 text-sm text-slate-500">Ảnh được import từ Excel và trạng thái download/upload WordPress.</p>
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-60 items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      ) : mediaAssets.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
          <ImageIcon className="mx-auto h-10 w-10 text-slate-300" />
          <h2 className="mt-4 text-lg font-bold text-slate-900">Chưa có media asset</h2>
          <p className="mt-1 text-sm text-slate-500">Import Excel có `featured_image_url` hoặc `image_urls` để tạo asset.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          {mediaAssets.map((asset) => (
            <div key={asset.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <span className={`rounded-full px-3 py-1 text-xs font-bold ${statusClasses[asset.status]}`}>
                    {asset.status}
                  </span>
                  <h2 className="mt-3 truncate text-sm font-black text-slate-900">
                    {asset.alt_text || asset.caption || `Media #${asset.id}`}
                  </h2>
                  <p className="mt-1 line-clamp-2 break-all text-xs font-medium text-slate-500">
                    {asset.wordpress_media_url || asset.source_url || asset.local_path}
                  </p>
                </div>
                <ImageIcon className="h-6 w-6 shrink-0 text-slate-300" />
              </div>

              {asset.error_message && (
                <div className="mt-4 rounded-xl bg-rose-50 p-3 text-xs font-medium text-rose-700">
                  {asset.error_message}
                </div>
              )}

              <div className="mt-5 flex gap-2">
                {asset.status === 'failed' && (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => actions.retryDownload.mutate(asset.id)}
                    disabled={actions.retryDownload.isPending}
                  >
                    <RefreshCw className="mr-2 h-4 w-4" />
                    Retry
                  </Button>
                )}
                {asset.status === 'downloaded' && (
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={!wordpressSiteId || actions.uploadToWordPress.isPending}
                    onClick={() => wordpressSiteId && actions.uploadToWordPress.mutate({ id: asset.id, wordpressSiteId })}
                    title={wordpressSiteId ? 'Upload ảnh lên WordPress media library' : 'Campaign chưa có WordPress site'}
                  >
                    <UploadCloud className="mr-2 h-4 w-4" />
                    Upload WP
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
