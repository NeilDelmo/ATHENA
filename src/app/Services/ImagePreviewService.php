<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImagePreviewService
{
    private const DIRECTORY = 'image-previews/v1/';

    public function response(string $path, string $filename): StreamedResponse
    {
        $preview = null;
        try {
            $preview = $this->previewPath($path);
        } catch (Throwable $exception) {
            report($exception);
        }
        $disk = Storage::disk('local');

        return $disk->response($preview ?? $path, $preview ? pathinfo($filename, PATHINFO_FILENAME).'.webp' : $filename, [
            'Content-Type' => $preview ? 'image/webp' : $disk->mimeType($path),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function previewPath(string $path): ?string
    {
        $disk = Storage::disk('local');
        if (! function_exists('imagewebp') || str_contains($path, '..') || ! $disk->exists($path)
            || $disk->size($path) > 20 * 1024 * 1024) {
            return null;
        }
        $absolutePath = $disk->path($path);
        $dimensions = @getimagesize($absolutePath);
        if (! $dimensions || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        [$width, $height] = $dimensions;
        // shortcut: images above 20 megapixels keep their originals; use queued processing if they need lighter previews.
        if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || $width * $height > 20000000
            || (max($width, $height) <= 1600 && $disk->size($path) <= 256 * 1024)) {
            return null;
        }
        $cachePath = self::DIRECTORY.$path.'.'.hash_file('sha256', $absolutePath).'.webp';
        if ($disk->exists($cachePath)) {
            return $cachePath;
        }
        $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
        if ($memoryLimit > 0 && $width * $height * 8 + 1600 * 1600 * 8 + 32 * 1024 * 1024 > $memoryLimit - memory_get_usage(true)) {
            return null;
        }

        $source = match ($dimensions[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolutePath),
        };
        if (! $source instanceof GdImage) {
            return null;
        }
        $scale = min(1, 1600 / max($width, $height));
        $preview = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC_FIXED);
        imagedestroy($source);
        if (! $preview instanceof GdImage) {
            return null;
        }
        if ($dimensions[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($absolutePath, 'IFD0')['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($preview, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) {
                3 => 180,
                6, 7 => -90,
                5, 8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $rotated = imagerotate($preview, $angle, 0);
                if ($rotated instanceof GdImage) {
                    imagedestroy($preview);
                    $preview = $rotated;
                }
            }
        }
        imagesavealpha($preview, true);
        $disk->makeDirectory(dirname($cachePath));
        $temporary = tempnam(dirname($disk->path($cachePath)), 'preview-');
        if ($temporary === false) {
            imagedestroy($preview);

            return null;
        }
        try {
            if (! imagewebp($preview, $temporary, 78) || filesize($temporary) >= $disk->size($path)) {
                return null;
            }
            if (! @rename($temporary, $disk->path($cachePath)) && ! $disk->exists($cachePath)) {
                return null;
            }
            foreach ($disk->files(dirname($cachePath)) as $cached) {
                if ($cached !== $cachePath && preg_replace('/\.[a-f0-9]{64}\.webp$/', '', $cached) === self::DIRECTORY.$path) {
                    $disk->delete($cached);
                }
            }

            return $cachePath;
        } finally {
            imagedestroy($preview);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function prune(): int
    {
        $disk = Storage::disk('local');
        $deleted = 0;
        foreach ($disk->allFiles('image-previews') as $path) {
            $original = preg_replace('/\.[a-f0-9]{64}\.webp$/', '', substr($path, strlen(self::DIRECTORY)));
            if (! str_starts_with($path, self::DIRECTORY) || $disk->lastModified($path) < now()->subDays(7)->timestamp || ! $disk->exists($original)) {
                $deleted += (int) $disk->delete($path);
            }
        }

        return $deleted;
    }
}
