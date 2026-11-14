'use client';

import React, { useCallback } from 'react';
import { useDropzone, FileRejection } from 'react-dropzone';
import { UploadCloud, X, Image as ImageIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

export interface UploadedImage {
  id: string;
  file?: File;
  previewUrl: string;
  name: string;
  size: number;
  mimeType?: string;
}

interface ChatImageUploadDropzoneProps {
  images: UploadedImage[];
  onImagesChange: (images: UploadedImage[]) => void;
  maxFiles?: number;
  maxSizeMB?: number;
  disabled?: boolean;
  className?: string;
  compact?: boolean;
}

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

export function ChatImageUploadDropzone({
  images,
  onImagesChange,
  maxFiles = 5,
  maxSizeMB = 10,
  disabled = false,
  className,
  compact = false,
}: ChatImageUploadDropzoneProps) {
  const onDrop = useCallback(
    (acceptedFiles: File[], fileRejections: FileRejection[]) => {
      if (fileRejections.length > 0) {
        const errorMsg =
          fileRejections[0]?.errors[0]?.message ||
          `File không hợp lệ hoặc vượt quá ${maxSizeMB}MB`;
        toast.error(`Lỗi tải ảnh: ${errorMsg}`);
      }

      if (acceptedFiles.length === 0) return;

      const remainingSlots = maxFiles - images.length;
      if (remainingSlots <= 0) {
        toast.error(`Đã đạt giới hạn tối đa ${maxFiles} hình ảnh đính kèm.`);
        return;
      }

      const filesToAdd = acceptedFiles.slice(0, remainingSlots);
      const newImages: UploadedImage[] = filesToAdd.map((file) => ({
        id: `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`,
        file,
        previewUrl: URL.createObjectURL(file),
        name: file.name,
        size: file.size,
        mimeType: file.type,
      }));

      onImagesChange([...images, ...newImages]);
      toast.success(`Đã thêm ${newImages.length} hình ảnh.`);
    },
    [images, maxFiles, maxSizeMB, onImagesChange]
  );

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'image/*': ['.png', '.jpg', '.jpeg', '.webp', '.gif'],
    },
    maxSize: maxSizeMB * 1024 * 1024,
    disabled: disabled || images.length >= maxFiles,
  });

  const removeImage = (id: string) => {
    const target = images.find((img) => img.id === id);
    if (target && target.previewUrl.startsWith('blob:')) {
      URL.revokeObjectURL(target.previewUrl);
    }
    onImagesChange(images.filter((img) => img.id !== id));
  };

  return (
    <div className={cn('space-y-3', className)}>
      {/* Uploaded image previews */}
      {images.length > 0 && (
        <div className="flex flex-wrap gap-2.5 p-2.5 rounded-lg bg-muted/40 border border-border/60">
          {images.map((img) => (
            <div
              key={img.id}
              className="group relative flex items-center gap-2 rounded-lg border bg-background p-1.5 pr-2.5 shadow-2xs transition-all hover:border-primary/50"
            >
              <div className="relative h-12 w-12 shrink-0 overflow-hidden rounded-md border bg-muted">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={img.previewUrl}
                  alt={img.name}
                  className="h-full w-full object-cover"
                />
              </div>
              <div className="min-w-0 max-w-[130px] flex-1 text-xs">
                <p className="truncate font-medium text-foreground">{img.name}</p>
                <p className="text-[11px] text-muted-foreground">
                  {formatFileSize(img.size)}
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="size-5 rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                onClick={() => removeImage(img.id)}
                disabled={disabled}
                title="Xóa ảnh"
              >
                <X className="h-3 w-3" />
                <span className="sr-only">Xóa ảnh</span>
              </Button>
            </div>
          ))}
        </div>
      )}

      {/* Dropzone container */}
      {images.length < maxFiles && (
        <div
          {...getRootProps()}
          className={cn(
            'group relative flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed transition-all duration-200 outline-none',
            isDragActive
              ? 'border-primary bg-primary/5 text-primary scale-[0.99]'
              : 'border-border/80 bg-background/50 hover:border-primary/60 hover:bg-muted/30 text-muted-foreground',
            disabled && 'cursor-not-allowed opacity-50',
            compact ? 'p-3 text-xs' : 'p-4 md:p-6 text-sm'
          )}
        >
          <input {...getInputProps()} />
          <div className="flex flex-col items-center justify-center gap-1.5 text-center">
            <div
              className={cn(
                'flex items-center justify-center rounded-full bg-muted transition-colors group-hover:bg-primary/10 group-hover:text-primary',
                compact ? 'h-8 w-8' : 'h-10 w-10'
              )}
            >
              {isDragActive ? (
                <UploadCloud className="h-5 w-5 animate-bounce text-primary" />
              ) : (
                <ImageIcon className="h-5 w-5 text-muted-foreground group-hover:text-primary" />
              )}
            </div>
            <div>
              <p className="font-medium text-foreground">
                {isDragActive
                  ? 'Thả ảnh vào đây ngay...'
                  : 'Kéo & thả ảnh vào đây, hoặc nhấn để chọn'}
              </p>
              <p className="text-xs text-muted-foreground">
                Hỗ trợ PNG, JPG, WebP (Tối đa {maxSizeMB}MB • {images.length}/{maxFiles} ảnh)
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
