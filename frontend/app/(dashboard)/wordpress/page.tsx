"use client";

import { useState } from 'react';
import { 
  CheckCircle2, 
  Globe, 
  Loader2, 
  PlugZap, 
  Trash2, 
  XCircle, 
  Edit3, 
  ExternalLink,
  RefreshCw, 
  AlertTriangle
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useWordPressSiteActions, useWordPressSites } from '@/hooks/useWordPressSites';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { toast } from 'sonner';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';

const urlRegex = /^https?:\/\/.+/i;

const createWordPressSiteSchema = z.object({
  name: z.string().min(1, 'Tên website không được để trống'),
  url: z.string().min(1, 'Website URL không được để trống').regex(urlRegex, 'URL phải bắt đầu bằng http:// hoặc https://'),
  api_url: z.string().optional().refine((val) => !val || urlRegex.test(val), {
    message: 'API URL phải bắt đầu bằng http:// hoặc https://',
  }),
  username: z.string().min(1, 'Username không được để trống'),
  app_password: z.string().min(1, 'Application Password không được để trống'),
  default_author_id: z.string().optional().refine((val) => !val || (!isNaN(Number(val)) && Number(val) >= 1), {
    message: 'Author ID phải là số nguyên dương',
  }),
  default_category_id: z.string().optional().refine((val) => !val || (!isNaN(Number(val)) && Number(val) >= 1), {
    message: 'Category ID phải là số nguyên dương',
  }),
  default_status: z.enum(['draft', 'publish']),
  is_active: z.boolean(),
});

type CreateWordPressSiteValues = z.infer<typeof createWordPressSiteSchema>;

const editWordPressSiteSchema = z.object({
  name: z.string().min(1, 'Tên website không được để trống'),
  url: z.string().min(1, 'Website URL không được để trống').regex(urlRegex, 'URL phải bắt đầu bằng http:// hoặc https://'),
  api_url: z.string().optional().refine((val) => !val || urlRegex.test(val), {
    message: 'API URL phải bắt đầu bằng http:// hoặc https://',
  }),
  username: z.string().min(1, 'Username không được để trống'),
  app_password: z.string().optional(),
  default_author_id: z.string().optional().refine((val) => !val || (!isNaN(Number(val)) && Number(val) >= 1), {
    message: 'Author ID phải là số nguyên dương',
  }),
  default_category_id: z.string().optional().refine((val) => !val || (!isNaN(Number(val)) && Number(val) >= 1), {
    message: 'Category ID phải là số nguyên dương',
  }),
  default_status: z.enum(['draft', 'publish']),
  is_active: z.boolean(),
});

type EditWordPressSiteValues = z.infer<typeof editWordPressSiteSchema>;

export default function WordPressPage() {
  const { data, isLoading, refetch } = useWordPressSites();
  const actions = useWordPressSiteActions();
  
  const [editingSite, setEditingSite] = useState<WordPressSite | null>(null);
  const [siteToDelete, setSiteToDelete] = useState<WordPressSite | null>(null);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);

  const createForm = useForm<CreateWordPressSiteValues>({
    resolver: zodResolver(createWordPressSiteSchema),
    defaultValues: {
      name: '',
      url: '',
      api_url: '',
      username: '',
      app_password: '',
      default_author_id: '',
      default_category_id: '',
      default_status: 'draft',
      is_active: true,
    },
  });

  const editForm = useForm<EditWordPressSiteValues>({
    resolver: zodResolver(editWordPressSiteSchema),
    defaultValues: {
      name: '',
      url: '',
      api_url: '',
      username: '',
      app_password: '',
      default_author_id: '',
      default_category_id: '',
      default_status: 'draft',
      is_active: true,
    },
  });

  const sites = data?.data ?? [];

  const submitCreate = (values: CreateWordPressSiteValues) => {
    const payload: WordPressSitePayload = {
      name: values.name.trim(),
      url: values.url.trim(),
      api_url: values.api_url?.trim() || undefined,
      username: values.username.trim(),
      app_password: values.app_password.trim(),
      default_author_id: values.default_author_id ? Number(values.default_author_id) : null,
      default_category_id: values.default_category_id ? Number(values.default_category_id) : null,
      default_status: values.default_status,
      is_active: values.is_active,
    };

    actions.create.mutate(payload, {
      onSuccess: () => {
        createForm.reset({
          name: '',
          url: '',
          api_url: '',
          username: '',
          app_password: '',
          default_author_id: '',
          default_category_id: '',
          default_status: 'draft',
          is_active: true,
        });
        toast.success('Đã lưu WordPress site thành công.');
      },
      onError: (error) => {
        toast.error(error instanceof Error ? error.message : 'Không lưu được WordPress site.');
      },
    });
  };

  const openEdit = (site: WordPressSite) => {
    setEditingSite(site);
    editForm.reset({
      name: site.name,
      url: site.url,
      api_url: site.api_url || '',
      username: site.username,
      app_password: '',
      default_author_id: site.default_author_id !== null ? String(site.default_author_id) : '',
      default_category_id: site.default_category_id !== null ? String(site.default_category_id) : '',
      default_status: site.default_status || 'draft',
      is_active: site.is_active,
    });
    setIsEditDialogOpen(true);
  };

  const submitEdit = (values: EditWordPressSiteValues) => {
    if (!editingSite) return;

    const payload: Partial<WordPressSitePayload> = {
      name: values.name.trim(),
      url: values.url.trim(),
      api_url: values.api_url?.trim() || undefined,
      username: values.username.trim(),
      default_author_id: values.default_author_id ? Number(values.default_author_id) : null,
      default_category_id: values.default_category_id ? Number(values.default_category_id) : null,
      default_status: values.default_status,
      is_active: values.is_active,
    };

    if (values.app_password && values.app_password.trim()) {
      payload.app_password = values.app_password.trim();
    }

    actions.update.mutate(
      { id: editingSite.id, payload },
      {
        onSuccess: () => {
          setIsEditDialogOpen(false);
          setEditingSite(null);
          toast.success('Cập nhật WordPress site thành công.');
        },
        onError: (err) => {
          toast.error(err instanceof Error ? err.message : 'Lỗi khi cập nhật site.');
        },
      }
    );
  };

  function confirmDelete() {
    if (!siteToDelete) return;

    actions.remove.mutate(siteToDelete.id, {
      onSuccess: () => {
        setIsDeleteDialogOpen(false);
        setSiteToDelete(null);
        toast.success('Đã xóa WordPress site.');
      },
      onError: (err) => {
        toast.error(err instanceof Error ? err.message : 'Lỗi khi xóa site.');
      },
    });
  }

  function testConnection(id: number) {
    toast.loading('Đang kiểm tra kết nối REST API WordPress...', { id: `test-${id}` });
    actions.test.mutate(id, {
      onSuccess: (result) => {
        if (result.success) {
          toast.success('Kết nối WordPress REST API thành công!', { id: `test-${id}` });
        } else {
          toast.error(result.message || result.error || 'Kết nối thất bại.', { id: `test-${id}` });
        }
      },
      onError: (error) => {
        toast.error(error instanceof Error ? error.message : 'Kết nối thất bại.', { id: `test-${id}` });
      },
    });
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">WordPress Sites</h1>
          <p className="mt-1 text-slate-500">Kết nối WordPress REST API bằng Application Password để publish bài viết và tải media tự động.</p>
        </div>
        <Button 
          variant="outline" 
          size="sm" 
          onClick={() => refetch()} 
          disabled={isLoading}
          className="rounded-xl border-slate-200"
        >
          <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
          Làm mới
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[440px_1fr]">
        {/* Form thêm mới */}
        <form onSubmit={createForm.handleSubmit(submitCreate)} className="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="rounded-2xl bg-blue-50 p-3 text-blue-600">
              <PlugZap className="h-6 w-6" />
            </div>
            <div>
              <h2 className="font-black text-slate-900">Thêm WordPress site</h2>
              <p className="text-xs text-slate-500">REST API URL mặc định: /wp-json</p>
            </div>
          </div>

          <div>
            <label className="text-xs font-bold text-slate-700">Tên website <span className="text-red-500">*</span></label>
            <Input 
              className="mt-1.5 rounded-xl text-sm" 
              placeholder="VD: Blog Công Nghệ" 
              {...createForm.register('name')}
            />
            {createForm.formState.errors.name && (
              <p className="mt-1 text-xs text-red-500 font-medium">
                {createForm.formState.errors.name.message}
              </p>
            )}
          </div>

          <div>
            <label className="text-xs font-bold text-slate-700">Website URL <span className="text-red-500">*</span></label>
            <Input 
              className="mt-1.5 rounded-xl text-sm" 
              placeholder="https://example.com" 
              {...createForm.register('url')}
            />
            {createForm.formState.errors.url && (
              <p className="mt-1 text-xs text-red-500 font-medium">
                {createForm.formState.errors.url.message}
              </p>
            )}
          </div>

          <div>
            <label className="text-xs font-bold text-slate-700">API URL (Tùy chọn)</label>
            <Input 
              className="mt-1.5 rounded-xl text-sm" 
              placeholder="https://example.com/wp-json" 
              {...createForm.register('api_url')}
            />
            {createForm.formState.errors.api_url && (
              <p className="mt-1 text-xs text-red-500 font-medium">
                {createForm.formState.errors.api_url.message}
              </p>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="text-xs font-bold text-slate-700">Username WP <span className="text-red-500">*</span></label>
              <Input 
                className="mt-1.5 rounded-xl text-sm" 
                placeholder="admin" 
                {...createForm.register('username')}
              />
              {createForm.formState.errors.username && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {createForm.formState.errors.username.message}
                </p>
              )}
            </div>
            <div>
              <label className="text-xs font-bold text-slate-700">Application Password <span className="text-red-500">*</span></label>
              <Input 
                className="mt-1.5 rounded-xl text-sm font-mono" 
                type="password" 
                placeholder="xxxx xxxx xxxx xxxx" 
                {...createForm.register('app_password')}
              />
              {createForm.formState.errors.app_password && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {createForm.formState.errors.app_password.message}
                </p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="text-xs font-bold text-slate-700">Author ID mặc định</label>
              <Input 
                className="mt-1.5 rounded-xl text-sm" 
                type="number" 
                min={1} 
                placeholder="1" 
                {...createForm.register('default_author_id')}
              />
              {createForm.formState.errors.default_author_id && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {createForm.formState.errors.default_author_id.message}
                </p>
              )}
            </div>
            <div>
              <label className="text-xs font-bold text-slate-700">Category ID mặc định</label>
              <Input 
                className="mt-1.5 rounded-xl text-sm" 
                type="number" 
                min={1} 
                placeholder="1" 
                {...createForm.register('default_category_id')}
              />
              {createForm.formState.errors.default_category_id && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {createForm.formState.errors.default_category_id.message}
                </p>
              )}
            </div>
          </div>

          <div>
            <label className="text-xs font-bold text-slate-700">Trạng thái bài đăng mặc định</label>
            <select 
              className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium outline-none focus:border-blue-500" 
              {...createForm.register('default_status')}
            >
              <option value="draft">Bản nháp (Draft)</option>
              <option value="publish">Xuất bản ngay (Publish)</option>
            </select>
            {createForm.formState.errors.default_status && (
              <p className="mt-1 text-xs text-red-500 font-medium">
                {createForm.formState.errors.default_status.message}
              </p>
            )}
          </div>

          <Button 
            type="submit" 
            disabled={actions.create.isPending} 
            className="w-full rounded-xl bg-blue-600 hover:bg-blue-700 font-bold py-5 cursor-pointer"
          >
            {actions.create.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Lưu WordPress Site
          </Button>
        </form>

        {/* Danh sách sites */}
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100">
            <h2 className="font-black text-slate-900 text-lg">Danh sách Website đã kết nối</h2>
            <span className="text-xs font-bold text-slate-400">{sites.length} sites</span>
          </div>

          {isLoading ? (
            <div className="flex h-48 items-center justify-center">
              <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
            </div>
          ) : sites.length === 0 ? (
            <div className="py-16 text-center">
              <Globe className="mx-auto h-12 w-12 text-slate-300" />
              <p className="mt-3 text-base font-bold text-slate-700">Chưa có WordPress site nào.</p>
              <p className="mt-1 text-xs text-slate-400">Hãy kết nối website đầu tiên ở biểu mẫu bên cạnh.</p>
            </div>
          ) : (
            <div className="mt-4 divide-y divide-slate-100">
              {sites.map((site) => (
                <div key={site.id} className="flex flex-col gap-4 py-5 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <div className="flex items-center gap-2.5">
                      {site.connection_status === 'connected' ? (
                        <span className="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                          <CheckCircle2 className="h-3 w-3" /> Đã kết nối
                        </span>
                      ) : (
                        <span className="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                          <XCircle className="h-3 w-3" /> Chưa xác thực
                        </span>
                      )}
                      <h3 className="font-black text-slate-900 text-base">{site.name}</h3>
                    </div>
                    <div className="mt-2 flex items-center gap-2 text-sm text-slate-500">
                      <a href={site.url} target="_blank" rel="noopener noreferrer" className="hover:text-blue-600 flex items-center gap-1 font-medium">
                        {site.url} <ExternalLink className="w-3 h-3" />
                      </a>
                    </div>
                    <div className="mt-1 flex flex-wrap gap-3 text-xs text-slate-400">
                      <span>Username: <strong className="text-slate-600">{site.username}</strong></span>
                      <span>•</span>
                      <span>Default status: <strong className="text-slate-600 uppercase">{site.default_status}</strong></span>
                      {site.last_connected_at && (
                        <>
                          <span>•</span>
                          <span>Kết nối lần cuối: {new Date(site.last_connected_at).toLocaleString('vi-VN')}</span>
                        </>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <Button 
                      variant="outline" 
                      size="sm" 
                      onClick={() => testConnection(site.id)} 
                      disabled={actions.test.isPending}
                      className="rounded-xl text-xs font-bold border-slate-200"
                    >
                      {actions.test.isPending ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : null}
                      Kiểm tra
                    </Button>
                    <Button 
                      variant="outline" 
                      size="sm" 
                      onClick={() => openEdit(site)}
                      className="rounded-xl text-xs font-bold border-slate-200"
                    >
                      <Edit3 className="mr-1.5 h-3.5 w-3.5" />
                      Sửa
                    </Button>
                    <Button 
                      variant="outline" 
                      size="icon" 
                      onClick={() => {
                        setSiteToDelete(site);
                        setIsDeleteDialogOpen(true);
                      }} 
                      className="rounded-xl border-rose-200 text-rose-600 hover:bg-rose-50"
                      title="Xóa website"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Dialog Sửa Site với React Hook Form & Zod */}
      <Dialog 
        open={isEditDialogOpen} 
        onOpenChange={(open) => {
          setIsEditDialogOpen(open);
          if (!open) {
            setEditingSite(null);
            editForm.reset();
          }
        }}
      >
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Chỉnh sửa WordPress Site</DialogTitle>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit(submitEdit)} className="space-y-4">
            <div>
              <label className="text-xs font-bold text-slate-700">Tên website <span className="text-red-500">*</span></label>
              <Input
                className="mt-1.5 text-sm"
                {...editForm.register('name')}
              />
              {editForm.formState.errors.name && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {editForm.formState.errors.name.message}
                </p>
              )}
            </div>
            <div>
              <label className="text-xs font-bold text-slate-700">Website URL <span className="text-red-500">*</span></label>
              <Input
                className="mt-1.5 text-sm"
                {...editForm.register('url')}
              />
              {editForm.formState.errors.url && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {editForm.formState.errors.url.message}
                </p>
              )}
            </div>
            <div>
              <label className="text-xs font-bold text-slate-700">API URL (Tùy chọn)</label>
              <Input
                className="mt-1.5 text-sm"
                {...editForm.register('api_url')}
              />
              {editForm.formState.errors.api_url && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {editForm.formState.errors.api_url.message}
                </p>
              )}
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-xs font-bold text-slate-700">Username <span className="text-red-500">*</span></label>
                <Input
                  className="mt-1.5 text-sm"
                  {...editForm.register('username')}
                />
                {editForm.formState.errors.username && (
                  <p className="mt-1 text-xs text-red-500 font-medium">
                    {editForm.formState.errors.username.message}
                  </p>
                )}
              </div>
              <div>
                <label className="text-xs font-bold text-slate-700">App Password (đổi mới)</label>
                <Input
                  className="mt-1.5 text-sm font-mono"
                  type="password"
                  placeholder="Để trống nếu giữ nguyên"
                  {...editForm.register('app_password')}
                />
                {editForm.formState.errors.app_password && (
                  <p className="mt-1 text-xs text-red-500 font-medium">
                    {editForm.formState.errors.app_password.message}
                  </p>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-xs font-bold text-slate-700">Author ID mặc định</label>
                <Input
                  className="mt-1.5 text-sm"
                  type="number"
                  min={1}
                  {...editForm.register('default_author_id')}
                />
                {editForm.formState.errors.default_author_id && (
                  <p className="mt-1 text-xs text-red-500 font-medium">
                    {editForm.formState.errors.default_author_id.message}
                  </p>
                )}
              </div>
              <div>
                <label className="text-xs font-bold text-slate-700">Category ID mặc định</label>
                <Input
                  className="mt-1.5 text-sm"
                  type="number"
                  min={1}
                  {...editForm.register('default_category_id')}
                />
                {editForm.formState.errors.default_category_id && (
                  <p className="mt-1 text-xs text-red-500 font-medium">
                    {editForm.formState.errors.default_category_id.message}
                  </p>
                )}
              </div>
            </div>
            <div>
              <label className="text-xs font-bold text-slate-700">Trạng thái bài đăng mặc định</label>
              <select
                className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium outline-none focus:border-blue-500"
                {...editForm.register('default_status')}
              >
                <option value="draft">Bản nháp (Draft)</option>
                <option value="publish">Xuất bản ngay (Publish)</option>
              </select>
              {editForm.formState.errors.default_status && (
                <p className="mt-1 text-xs text-red-500 font-medium">
                  {editForm.formState.errors.default_status.message}
                </p>
              )}
            </div>
            <DialogFooter className="gap-2 sm:gap-0">
              <Button 
                type="button" 
                variant="outline" 
                onClick={() => {
                  setIsEditDialogOpen(false);
                  setEditingSite(null);
                  editForm.reset();
                }}
              >
                Hủy
              </Button>
              <Button type="submit" disabled={actions.update.isPending} className="bg-blue-600 hover:bg-blue-700">
                {actions.update.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Lưu thay đổi
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Dialog Xác nhận Xóa Site */}
      <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 mb-2">
              <AlertTriangle className="h-6 w-6" />
            </div>
            <DialogTitle className="text-center">Xác nhận xóa website?</DialogTitle>
            <DialogDescription className="text-center text-xs text-slate-500">
              Bạn có chắc chắn muốn ngắt kết nối website <strong className="text-slate-800">{siteToDelete?.name}</strong>? Thao tác này sẽ không xóa bài viết trên WordPress nhưng sẽ ngừng tự động đăng bài lên site này.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
              Hủy
            </Button>
            <Button 
              type="button" 
              variant="destructive" 
              onClick={confirmDelete} 
              disabled={actions.remove.isPending}
            >
              {actions.remove.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Xác nhận xóa
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
