<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu ảnh người dùng tải lên từ trình soạn thảo bài viết (giống Media Library của WordPress):
 * nén/resize qua ImageOptimizationService rồi lưu vào disk public theo workspace.
 */
class EditorImageUploadService
{
    public function __construct(
        protected ImageOptimizationService $optimizer
    ) {}

    /**
     * @return array{url: string, path: string, width: int|null, height: int|null, size: int, mime_type: string}
     */
    public function store(UploadedFile $file, int $workspaceId, ?string $altText = null): array
    {
        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $optimized = $this->optimizer->optimize((string) $file->get(), $mimeType);

        $contents = $optimized['contents'] ?? (string) $file->get();
        $extension = $optimized['extension'] ?? ($file->guessExtension() ?: 'jpg');
        $baseName = Str::slug($altText ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';

        $path = sprintf(
            'articles_media/uploads/ws-%d/%s-%s.%s',
            $workspaceId,
            Str::limit($baseName, 60, ''),
            Str::lower(Str::random(8)),
            $extension
        );

        Storage::disk('public')->put($path, $contents);

        return [
            'url' => asset('storage/'.$path),
            'path' => $path,
            'width' => $optimized['width'] ?? null,
            'height' => $optimized['height'] ?? null,
            'size' => strlen($contents),
            'mime_type' => $optimized['mime_type'] ?? $mimeType,
        ];
    }
}
