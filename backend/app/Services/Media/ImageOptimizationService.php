<?php

namespace App\Services\Media;

class ImageOptimizationService
{
    public function optimize(string $contents, string $mimeType): array
    {
        $originalSize = strlen($contents);

        if (!config('media.optimization.enabled', true) || !function_exists('imagecreatefromstring')) {
            return $this->result($contents, $mimeType, false, $originalSize);
        }

        $image = @imagecreatefromstring($contents);
        if (!$image) {
            return $this->result($contents, $mimeType, false, $originalSize);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = max(320, (int) config('media.optimization.max_width', 1200));

        if ($width > $maxWidth) {
            $targetWidth = $maxWidth;
            $targetHeight = (int) round($height * ($targetWidth / $width));
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);

            if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $targetWidth;
            $height = $targetHeight;
        }

        $outputMimeType = $this->outputMimeType($image, $mimeType);
        
        $maxSizeBytes = 100 * 1024; // 100KB
        $optimized = null;

        // Try converting oversized PNGs to WebP/JPEG to save size
        if ($outputMimeType === 'image/png') {
            $optimized = $this->encode($image, $outputMimeType, 9);
            if ($optimized && strlen($optimized) > $maxSizeBytes) {
                if (!$this->hasTransparency($image)) {
                    $outputMimeType = 'image/jpeg';
                } else {
                    $outputMimeType = 'image/webp';
                }
            }
        }

        if ($outputMimeType !== 'image/png') {
            $initialQuality = ($outputMimeType === 'image/webp') ? $this->webpQuality() : $this->jpegQuality();
            $quality = $initialQuality;
            do {
                $optimized = $this->encode($image, $outputMimeType, $quality);
                if (!$optimized || strlen($optimized) <= $maxSizeBytes) {
                    break;
                }
                $quality -= 10;
            } while ($quality >= 40);
        } else {
            $optimized = $this->encode($image, $outputMimeType, 9);
        }

        imagedestroy($image);

        if (!$optimized || strlen($optimized) >= $originalSize) {
            return $this->result($contents, $mimeType, false, $originalSize, $width, $height);
        }

        return $this->result($optimized, $outputMimeType, true, $originalSize, $width, $height);
    }

    protected function encode(\GdImage $image, string $mimeType, ?int $qualityOverride = null): ?string
    {
        ob_start();

        $success = match ($mimeType) {
            'image/png' => imagepng($image, null, $qualityOverride ?? $this->pngCompression()),
            'image/webp' => function_exists('imagewebp') && imagewebp($image, null, $qualityOverride ?? $this->webpQuality()),
            default => imagejpeg($image, null, $qualityOverride ?? $this->jpegQuality()),
        };

        $contents = ob_get_clean();

        return $success ? $contents : null;
    }

    protected function result(
        string $contents,
        string $mimeType,
        bool $optimized,
        int $originalSize,
        ?int $width = null,
        ?int $height = null
    ): array {
        $size = getimagesizefromstring($contents) ?: [];

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'extension' => $this->extension($mimeType),
            'size_bytes' => strlen($contents),
            'original_size_bytes' => $originalSize,
            'width' => $width ?: ($size[0] ?? null),
            'height' => $height ?: ($size[1] ?? null),
            'optimized' => $optimized,
        ];
    }

    protected function extension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    protected function outputMimeType(\GdImage $image, string $mimeType): string
    {
        if (
            $mimeType !== 'image/jpeg'
            && config('media.optimization.convert_to_jpeg', true)
            && !$this->hasTransparency($image)
        ) {
            return 'image/jpeg';
        }

        return $mimeType;
    }

    protected function hasTransparency(\GdImage $image): bool
    {
        $transparentIndex = imagecolortransparent($image);
        if ($transparentIndex >= 0) {
            return true;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y += max(1, (int) floor($height / 80))) {
            for ($x = 0; $x < $width; $x += max(1, (int) floor($width / 80))) {
                $rgba = imagecolorat($image, $x, $y);
                if ((($rgba >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function jpegQuality(): int
    {
        return max(60, min(95, (int) config('media.optimization.jpeg_quality', 82)));
    }

    protected function webpQuality(): int
    {
        return max(60, min(95, (int) config('media.optimization.webp_quality', 82)));
    }

    protected function pngCompression(): int
    {
        return max(0, min(9, (int) config('media.optimization.png_compression', 6)));
    }
}
