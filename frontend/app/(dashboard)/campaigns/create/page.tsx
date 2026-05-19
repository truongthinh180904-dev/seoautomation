"use client";

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Loader2, Save } from 'lucide-react';
import Link from 'next/link';
import apiClient from '@/lib/api/client';
import { Button } from '@/components/ui/button';

export default function CreateCampaignPage() {
  const router = useRouter();
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [language, setLanguage] = useState('vi');
  const [defaultWordCount, setDefaultWordCount] = useState(1200);
  const [approvalRequired, setApprovalRequired] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSaving(true);
    setError(null);

    try {
      const response = await apiClient.post<ApiResponse<Campaign>>('/campaigns', {
        name,
        description: description || null,
        language,
        default_word_count: defaultWordCount,
        approval_required: approvalRequired,
      });

      router.push(`/campaigns/${response.data.data.id}`);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Không tạo được campaign.');
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="flex items-center gap-3">
        <Link href="/campaigns">
          <Button variant="ghost" size="icon" className="rounded-full">
            <ArrowLeft className="h-5 w-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-black tracking-tight text-slate-900">Tạo chiến dịch SEO</h1>
          <p className="mt-1 text-sm text-slate-500">Campaign sẽ gom keyword, bài viết, lịch đăng và chất lượng SEO vào một luồng.</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        {error && <div className="rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-700">{error}</div>}

        <div>
          <label className="text-sm font-bold text-slate-700">Tên campaign</label>
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            required
            className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
            placeholder="VD: SEO thiệp cưới điện tử tháng 5"
          />
        </div>

        <div>
          <label className="text-sm font-bold text-slate-700">Mục tiêu / ghi chú</label>
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            rows={4}
            className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
            placeholder="Nêu nhóm dịch vụ, thị trường, tone nội dung hoặc mục tiêu chuyển đổi..."
          />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label className="text-sm font-bold text-slate-700">Ngôn ngữ</label>
            <input
              value={language}
              onChange={(event) => setLanguage(event.target.value)}
              className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
            />
          </div>
          <div>
            <label className="text-sm font-bold text-slate-700">Số từ mặc định</label>
            <input
              type="number"
              min={300}
              max={10000}
              value={defaultWordCount}
              onChange={(event) => setDefaultWordCount(Number(event.target.value))}
              className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
            />
          </div>
        </div>

        <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
          <input
            type="checkbox"
            checked={approvalRequired}
            onChange={(event) => setApprovalRequired(event.target.checked)}
            className="h-4 w-4 rounded border-slate-300"
          />
          <span className="text-sm font-semibold text-slate-700">Bắt buộc duyệt bài trước khi đăng WordPress</span>
        </label>

        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving || name.trim().length < 2} className="rounded-xl bg-blue-600 hover:bg-blue-700">
            {isSaving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
            Lưu campaign
          </Button>
        </div>
      </form>
    </div>
  );
}
