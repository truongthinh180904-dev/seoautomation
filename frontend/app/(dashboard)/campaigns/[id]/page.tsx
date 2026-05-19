"use client";

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, FileText, Image as ImageIcon, Loader2, Pause, Play, RotateCcw, Search, Send, Target } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCampaign, useCampaignActions, useCampaignStats } from '@/hooks/useCampaigns';

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
  const { data: campaignResponse, isLoading } = useCampaign(campaignId);
  const { data: stats } = useCampaignStats(campaignId);
  const actions = useCampaignActions(campaignId);
  const campaign = campaignResponse?.data;

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
        <div className="text-xl font-bold text-rose-500">Không tải được campaign</div>
        <Link href="/campaigns">
          <Button variant="outline">Quay lại danh sách</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Link href="/campaigns">
            <Button variant="ghost" size="icon" className="rounded-full">
              <ArrowLeft className="h-5 w-5" />
            </Button>
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{campaign.status}</span>
              {campaign.wordpress_site && <span className="text-sm font-medium text-slate-500">{campaign.wordpress_site.name}</span>}
            </div>
            <h1 className="mt-2 text-3xl font-black tracking-tight text-slate-900">{campaign.name}</h1>
            <p className="mt-1 text-sm text-slate-500">{campaign.description || campaign.content_goal || 'Campaign SEO đang được theo dõi trong dashboard.'}</p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={() => actions.start.mutate()} disabled={actions.start.isPending}>
            <Play className="mr-2 h-4 w-4" />
            Start
          </Button>
          <Button variant="outline" onClick={() => actions.pause.mutate()} disabled={actions.pause.isPending}>
            <Pause className="mr-2 h-4 w-4" />
            Pause
          </Button>
          <Button variant="outline" onClick={() => actions.resume.mutate()} disabled={actions.resume.isPending}>
            <RotateCcw className="mr-2 h-4 w-4" />
            Resume
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
          <h2 className="text-lg font-black text-slate-900">Tiến độ bài viết</h2>
          <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            {[
              ['Review', stats?.articles.in_review ?? 0],
              ['Approved', stats?.articles.approved ?? 0],
              ['Published', stats?.articles.published ?? 0],
              ['Failed', stats?.articles.failed ?? 0],
            ].map(([label, value]) => (
              <div key={label} className="rounded-xl bg-slate-50 p-4">
                <div className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</div>
                <div className="mt-1 text-2xl font-black text-slate-900">{value}</div>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-black text-slate-900">Coverage</h2>
          <div className="mt-4 space-y-4">
            <div>
              <div className="mb-1 flex justify-between text-sm font-bold text-slate-600">
                <span className="flex items-center gap-2"><ImageIcon className="h-4 w-4" /> Media</span>
                <span>{stats?.coverage.media_percent ?? 0}%</span>
              </div>
              <div className="h-2 rounded-full bg-slate-100">
                <div className="h-2 rounded-full bg-blue-600" style={{ width: `${stats?.coverage.media_percent ?? 0}%` }} />
              </div>
            </div>
            <div>
              <div className="mb-1 flex justify-between text-sm font-bold text-slate-600">
                <span>Internal links</span>
                <span>{stats?.coverage.internal_links_percent ?? 0}%</span>
              </div>
              <div className="h-2 rounded-full bg-slate-100">
                <div className="h-2 rounded-full bg-emerald-500" style={{ width: `${stats?.coverage.internal_links_percent ?? 0}%` }} />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-wrap gap-3">
        <Link href={`/keywords?campaign_id=${campaign.id}`}>
          <Button variant="outline">Xem keyword</Button>
        </Link>
        <Link href={`/articles?campaign_id=${campaign.id}`}>
          <Button variant="outline">Xem bài viết</Button>
        </Link>
        <Link href="/keywords/import">
          <Button className="bg-blue-600 hover:bg-blue-700">Import Excel vào campaign</Button>
        </Link>
        <Link href={`/campaigns/${campaign.id}/media`}>
          <Button variant="outline">Media manager</Button>
        </Link>
        <Link href={`/campaigns/${campaign.id}/internal-links`}>
          <Button variant="outline">Internal links</Button>
        </Link>
      </div>
    </div>
  );
}
