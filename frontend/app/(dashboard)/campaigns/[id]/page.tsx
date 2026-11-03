"use client";

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, FileText, Image as ImageIcon, Loader2, Pause, Play, RotateCcw, Search, Send, Target, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCampaign, useCampaignActions, useCampaignStats } from '@/hooks/useCampaigns';
import { toast } from 'sonner';

function StatBlock({ label, value, icon: Icon }: { label: string; value: string | number; icon: typeof Target }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-black text-slate-900">{value}</p>
        </div>
        <div className="rounded-xl bg-blue-50 p-3 text-blue-600">
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

export default function CampaignDetailPage() {
  const { id } = useParams();
  const campaignId = Number(id);
  const { data: campaignResponse, isLoading, refetch } = useCampaign(campaignId);
  const { data: stats } = useCampaignStats(campaignId);
  const actions = useCampaignActions(campaignId);
  const campaign = campaignResponse?.data;

  const handleStart = () => {
    actions.start.mutate(undefined, {
      onSuccess: () => toast.success('Đã khởi chạy chiến dịch SEO! Các tác vụ AI đang được đẩy vào hàng đợi.'),
      onError: (err) => toast.error(err instanceof Error ? err.message : 'Không thể khởi chạy chiến dịch'),
    });
  };

  const handlePause = () => {
    actions.pause.mutate(undefined, {
      onSuccess: () => toast.info('Đã tạm dừng chiến dịch SEO.'),
      onError: (err) => toast.error(err instanceof Error ? err.message : 'Không thể tạm dừng chiến dịch'),
    });
  };

  const handleResume = () => {
    actions.resume.mutate(undefined, {
      onSuccess: () => toast.success('Đã tiếp tục chiến dịch SEO.'),
      onError: (err) => toast.error(err instanceof Error ? err.message : 'Không thể tiếp tục chiến dịch'),
    });
  };

  if (isLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  if (!campaign) {
    return (
      <div className="flex h-[60vh] flex-col items-center justify-center gap-4">
        <div className="text-xl font-bold text-rose-500">Không tìm thấy chiến dịch SEO</div>
        <Link href="/campaigns">
          <Button variant="outline">Quay lại danh sách</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Link href="/campaigns">
            <Button variant="ghost" size="icon" className="rounded-full">
              <ArrowLeft className="h-5 w-5" />
            </Button>
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 capitalize">
                {campaign.status}
              </span>
              {campaign.wordpress_site && (
                <span className="text-xs font-medium text-slate-500">{campaign.wordpress_site.name}</span>
              )}
            </div>
            <h1 className="mt-2 text-3xl font-black tracking-tight text-slate-900">{campaign.name}</h1>
            <p className="mt-1 text-sm text-slate-500">
              {campaign.description || campaign.content_goal || 'Campaign SEO đang được theo dõi và tự động hóa.'}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2 items-center">
          <Button 
            variant="outline" 
            onClick={handleStart} 
            disabled={actions.start.isPending || campaign.status === 'processing'}
            className="rounded-xl font-bold text-xs"
          >
            {actions.start.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Play className="mr-2 h-4 w-4" />}
            Bắt đầu
          </Button>
          <Button 
            variant="outline" 
            onClick={handlePause} 
            disabled={actions.pause.isPending || campaign.status === 'paused'}
            className="rounded-xl font-bold text-xs"
          >
            {actions.pause.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Pause className="mr-2 h-4 w-4" />}
            Tạm dừng
          </Button>
          <Button 
            variant="outline" 
            onClick={handleResume} 
            disabled={actions.resume.isPending || campaign.status !== 'paused'}
            className="rounded-xl font-bold text-xs"
          >
            {actions.resume.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RotateCcw className="mr-2 h-4 w-4" />}
            Tiếp tục
          </Button>
          <Button 
            variant="ghost" 
            size="icon" 
            onClick={() => refetch()}
            className="rounded-xl border border-slate-200 text-slate-500 hover:text-slate-900"
            title="Làm mới"
          >
            <RefreshCw className="h-4 w-4" />
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatBlock label="Keywords" value={stats?.keywords.total ?? campaign.keywords_count ?? 0} icon={Search} />
        <StatBlock label="Articles" value={stats?.articles.total ?? campaign.articles_count ?? 0} icon={FileText} />
        <StatBlock label="Published" value={stats?.articles.published ?? 0} icon={Send} />
        <StatBlock label="AI Cost" value={`$${(stats?.cost.ai_cost_usd ?? 0).toFixed(4)}`} icon={Target} />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
          <h2 className="text-lg font-black text-slate-900">Tiến độ bài viết theo trạng thái</h2>
          <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            {[
              ['Review', stats?.articles.in_review ?? 0, 'text-blue-600'],
              ['Approved', stats?.articles.approved ?? 0, 'text-emerald-600'],
              ['Published', stats?.articles.published ?? 0, 'text-teal-600'],
              ['Failed', stats?.articles.failed ?? 0, 'text-rose-600'],
            ].map(([label, value, color]) => (
              <div key={label as string} className="rounded-xl bg-slate-50 p-4 border border-slate-100">
                <div className="text-xs font-bold uppercase tracking-wide text-slate-400">{label as string}</div>
                <div className={`mt-1 text-2xl font-black ${color as string}`}>{value as number}</div>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-black text-slate-900">Độ phủ SEO</h2>
          <div className="mt-4 space-y-4">
            <div>
              <div className="mb-1 flex justify-between text-sm font-bold text-slate-600">
                <span className="flex items-center gap-2"><ImageIcon className="h-4 w-4" /> Media Images</span>
                <span>{stats?.coverage.media_percent ?? 0}%</span>
              </div>
              <div className="h-2 rounded-full bg-slate-100">
                <div className="h-2 rounded-full bg-blue-600 transition-all duration-500" style={{ width: `${stats?.coverage.media_percent ?? 0}%` }} />
              </div>
            </div>
            <div>
              <div className="mb-1 flex justify-between text-sm font-bold text-slate-600">
                <span>Internal links</span>
                <span>{stats?.coverage.internal_links_percent ?? 0}%</span>
              </div>
              <div className="h-2 rounded-full bg-slate-100">
                <div className="h-2 rounded-full bg-emerald-500 transition-all duration-500" style={{ width: `${stats?.coverage.internal_links_percent ?? 0}%` }} />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-wrap gap-3">
        <Link href={`/keywords?campaign_id=${campaign.id}`}>
          <Button variant="outline" className="rounded-xl font-bold text-xs">Xem từ khóa</Button>
        </Link>
        <Link href={`/articles?campaign_id=${campaign.id}`}>
          <Button variant="outline" className="rounded-xl font-bold text-xs">Xem bài viết</Button>
        </Link>
        <Link href="/keywords/import">
          <Button className="rounded-xl bg-blue-600 hover:bg-blue-700 font-bold text-xs">Import Excel vào campaign</Button>
        </Link>
        <Link href={`/campaigns/${campaign.id}/media`}>
          <Button variant="outline" className="rounded-xl font-bold text-xs">Quản lý Media</Button>
        </Link>
        <Link href={`/campaigns/${campaign.id}/internal-links`}>
          <Button variant="outline" className="rounded-xl font-bold text-xs">Internal links</Button>
        </Link>
      </div>
    </div>
  );
}
