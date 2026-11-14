'use client';

import React, { useEffect, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { ImageUp, Link2, Loader2 } from 'lucide-react';
import { postForm } from '@/lib/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { cn } from '@/lib/utils';

const imageDetailsSchema = z.object({
  src: z
    .string()
    .trim()
    .min(1, 'Vui lòng tải ảnh lên hoặc dán đường dẫn ảnh.')
    .refine((v) => /^(https?:\/\/|\/)/i.test(v), 'Đường dẫn ảnh phải bắt đầu bằng http(s):// hoặc /'),
  altText: z.string().trim().max(255, 'ALT tối đa 255 ký tự.'),
  caption: z.string().trim().max(300, 'Chú thích tối đa 300 ký tự.'),
  width: z.number().min(20).max(100),
  align: z.enum(['left', 'center', 'right']),
});

export type ImageDetails = z.infer<typeof imageDetailsSchema>;

interface UploadResponse {
  success: boolean;
  data: { url: string };
}

/** Tải ảnh từ máy lên backend (nén WebP/resize) và trả về URL công khai. */
export async function uploadEditorImage(file: File, alt?: string): Promise<string> {
  const formData = new FormData();
  formData.append('image', file);
  if (alt) formData.append('alt', alt);
  try {
    const res = await postForm<UploadResponse>('/editor/images', formData);
    toast.success('Đã tải ảnh lên thành công');
    return res.data.url;
  } catch (error) {
    const message =
      (error as { response?: { data?: { message?: string } } })?.response?.data?.message ||
      'Không thể tải ảnh lên. Vui lòng thử lại.';
    toast.error(message);
    throw error;
  }
}

interface ImageDetailsDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  submitLabel: string;
  initial?: Partial<ImageDetails>;
  onSubmit: (details: ImageDetails) => void;
}

const DEFAULTS: ImageDetails = { src: '', altText: '', caption: '', width: 100, align: 'center' };

export function ImageDetailsDialog({ open, onOpenChange, title, submitLabel, initial, onSubmit }: ImageDetailsDialogProps) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [isUploading, setUploading] = useState(false);
  const form = useForm<ImageDetails>({
    resolver: zodResolver(imageDetailsSchema),
    defaultValues: { ...DEFAULTS, ...initial },
  });

  useEffect(() => {
    if (open) form.reset({ ...DEFAULTS, ...initial });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const src = form.watch('src');

  const handleFile = async (file?: File) => {
    if (!file) return;
    setUploading(true);
    try {
      const url = await uploadEditorImage(file, form.getValues('altText'));
      form.setValue('src', url, { shouldValidate: true });
    } catch {
      // toast đã hiển thị lỗi
    } finally {
      setUploading(false);
    }
  };

  const submit = form.handleSubmit((values) => {
    onSubmit(values);
    onOpenChange(false);
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl" onClick={(e) => e.stopPropagation()}>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>
            Tải ảnh từ máy tính (tự động nén WebP) hoặc dán đường dẫn ảnh. ALT giúp Google hiểu nội dung ảnh.
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={submit} className="space-y-4">
            <div
              className={cn(
                'flex min-h-[150px] items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-slate-50',
                !src && 'cursor-pointer hover:border-sky-400 hover:bg-sky-50/50'
              )}
              onClick={() => !src && fileRef.current?.click()}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => {
                e.preventDefault();
                void handleFile(e.dataTransfer.files?.[0]);
              }}
            >
              {isUploading ? (
                <Loader2 className="h-6 w-6 animate-spin text-sky-600" />
              ) : src ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={src} alt="Xem trước" className="max-h-56 w-full object-contain" />
              ) : (
                <div className="text-center text-sm text-slate-500">
                  <ImageUp className="mx-auto mb-2 h-8 w-8 text-sky-500" />
                  Kéo thả ảnh vào đây hoặc <span className="font-semibold text-sky-600">bấm để chọn ảnh</span>
                </div>
              )}
            </div>

            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" disabled={isUploading} onClick={() => fileRef.current?.click()}>
                <ImageUp className="mr-1.5 h-4 w-4" /> {src ? 'Tải ảnh khác' : 'Tải ảnh từ máy'}
              </Button>
              <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="hidden"
                onChange={(e) => {
                  void handleFile(e.target.files?.[0]);
                  e.target.value = '';
                }}
              />
            </div>

            <FormField
              control={form.control}
              name="src"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="flex items-center gap-1">
                    <Link2 className="h-3.5 w-3.5" /> Đường dẫn ảnh (URL)
                  </FormLabel>
                  <FormControl>
                    <Input placeholder="https://..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="altText"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Văn bản thay thế (ALT)</FormLabel>
                  <FormControl>
                    <Input placeholder="VD: Bác sĩ tư vấn niềng răng trong suốt" {...field} />
                  </FormControl>
                  <FormDescription>Nên chứa từ khóa một cách tự nhiên.</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="caption"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Chú thích (hiển thị dưới ảnh)</FormLabel>
                  <FormControl>
                    <Textarea rows={2} className="resize-none" placeholder="Nguồn ảnh / mô tả ngắn..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="width"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Chiều rộng: {field.value}%</FormLabel>
                    <FormControl>
                      <input
                        type="range"
                        min={20}
                        max={100}
                        step={5}
                        value={field.value}
                        onChange={(e) => field.onChange(Number(e.target.value))}
                        className="w-full accent-sky-600"
                      />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="align"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Căn lề</FormLabel>
                    <div className="flex gap-1">
                      {(['left', 'center', 'right'] as const).map((a) => (
                        <Button
                          key={a}
                          type="button"
                          size="sm"
                          variant={field.value === a ? 'default' : 'outline'}
                          onClick={() => field.onChange(a)}
                        >
                          {a === 'left' ? 'Trái' : a === 'right' ? 'Phải' : 'Giữa'}
                        </Button>
                      ))}
                    </div>
                  </FormItem>
                )}
              />
            </div>

            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                Hủy
              </Button>
              <Button type="submit" disabled={isUploading}>
                {submitLabel}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
