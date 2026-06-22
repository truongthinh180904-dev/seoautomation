"use client";

import { useState } from 'react';
import { CheckCircle2, Globe, Loader2, PlugZap, Trash2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useWordPressSiteActions, useWordPressSites } from '@/hooks/useWordPressSites';

const emptyForm: WordPressSitePayload = {
  name: '',
  url: '',
  api_url: '',
  username: '',
  app_password: '',
  default_author_id: null,
  default_category_id: null,
  default_status: 'draft',
  is_active: true,
};

export default function WordPressPage() {
  const { data, isLoading } = useWordPressSites();
  const actions = useWordPressSiteActions();
  const [form, setForm] = useState<WordPressSitePayload>(emptyForm);
  const [message, setMessage] = useState<string | null>(null);
  const sites = data?.data ?? [];

  function update<K extends keyof WordPressSitePayload>(key: K, value: WordPressSitePayload[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage(null);
    actions.create.mutate(form, {
      onSuccess: () => {
        setForm(emptyForm);
        setMessage('Đã lưu WordPress site.');
      },
      onError: (error) => {
        setMessage(error instanceof Error ? error.message : 'Không lưu được WordPress site.');
      },
    });
  }

  function testConnection(id: number) {
    setMessage(null);
    actions.test.mutate(id, {
      onSuccess: (result) => {
        setMessage(result.success ? 'Kết nối WordPress thành công.' : result.message || result.error || 'Kết nối thất bại.');
      },
      onError: (error) => {
        setMessage(error instanceof Error ? error.message : 'Kết nối thất bại.');
      },
    });
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">WordPress Sites</h1>
        <p className="mt-1 text-slate-500">Kết nối WordPress REST API bằng Application Password để publish bài và upload media.</p>
      </div>

      {message && (
        <div className="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm font-bold text-blue-800">
          {message}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[420px_1fr]">
        <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-blue-50 p-3 text-blue-600">
              <PlugZap className="h-5 w-5" />
            </div>
            <div>
              <h2 className="font-black text-slate-900">Thêm WordPress site</h2>
              <p className="text-sm text-slate-500">`api_url` thường là `/wp-json`.</p>
            </div>
          </div>

          <div>
            <label className="text-sm font-bold text-slate-700">Tên site</label>
            <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" value={form.name} onChange={(e) => update('name', e.target.value)} required />
          </div>

          <div>
            <label className="text-sm font-bold text-slate-700">Website URL</label>
            <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" placeholder="https://domain.com" value={form.url} onChange={(e) => update('url', e.target.value)} required />
          </div>

          <div>
            <label className="text-sm font-bold text-slate-700">API URL</label>
            <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" placeholder="https://domain.com/wp-json" value={form.api_url} onChange={(e) => update('api_url', e.target.value)} />
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="text-sm font-bold text-slate-700">Username</label>
              <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" value={form.username} onChange={(e) => update('username', e.target.value)} required />
            </div>
            <div>
              <label className="text-sm font-bold text-slate-700">App Password</label>
              <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" type="password" value={form.app_password} onChange={(e) => update('app_password', e.target.value)} required />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="text-sm font-bold text-slate-700">Author ID</label>
              <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" type="number" min={1} value={form.default_author_id ?? ''} onChange={(e) => update('default_author_id', e.target.value ? Number(e.target.value) : null)} />
            </div>
            <div>
              <label className="text-sm font-bold text-slate-700">Category ID</label>
              <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" type="number" min={1} value={form.default_category_id ?? ''} onChange={(e) => update('default_category_id', e.target.value ? Number(e.target.value) : null)} />
            </div>
          </div>

          <div>
            <label className="text-sm font-bold text-slate-700">Default status</label>
            <select className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500" value={form.default_status} onChange={(e) => update('default_status', e.target.value as 'draft' | 'publish')}>
              <option value="draft">Draft</option>
              <option value="publish">Publish</option>
            </select>
          </div>

          <Button type="submit" disabled={actions.create.isPending} className="w-full rounded-xl bg-blue-600 hover:bg-blue-700">
            {actions.create.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Lưu site
          </Button>
        </form>

        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="font-black text-slate-900">Site đã kết nối</h2>

          {isLoading ? (
            <div className="flex h-40 items-center justify-center">
              <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
            </div>
          ) : sites.length === 0 ? (
            <div className="py-10 text-center">
              <Globe className="mx-auto h-10 w-10 text-slate-300" />
              <p className="mt-3 text-sm font-medium text-slate-500">Chưa có WordPress site.</p>
            </div>
          ) : (
            <div className="mt-4 divide-y divide-slate-100">
              {sites.map((site) => (
                <div key={site.id} className="flex flex-col gap-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <div className="flex items-center gap-2">
                      {site.connection_status === 'connected' ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <XCircle className="h-4 w-4 text-slate-400" />}
                      <h3 className="font-black text-slate-900">{site.name}</h3>
                    </div>
                    <p className="mt-1 break-all text-sm text-slate-500">{site.url}</p>
                    <p className="mt-1 text-xs font-medium text-slate-400">API: {site.api_url}</p>
                  </div>
                  <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={() => testConnection(site.id)} disabled={actions.test.isPending}>
                      Test
                    </Button>
                    <Button variant="outline" size="icon" onClick={() => actions.remove.mutate(site.id)} disabled={actions.remove.isPending}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
