<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentImageService
{
    private const MAX_EDGE = 1600;

    // shortcut: daily preview cleanup clears these derivatives; extend retention if rebuilding becomes costly.
    private const CACHE_DIRECTORY = 'image-previews/documents-v1/';

    public function contents(string $path): string
    {
        $dimensions = @getimagesize($path);
        if ($dimensions === false) {
            throw new RuntimeException('A report image is unavailable or invalid.');
        }
        [$width, $height, $type] = $dimensions;
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || max($width, $height) <= self::MAX_EDGE) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('A report image could not be read.');
            }

            return $contents;
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('A report image could not be read.');
        }
        $cachePath = Storage::disk('local')->path(self::CACHE_DIRECTORY.hash('sha256', $path.'|'.$hash).($type === IMAGETYPE_PNG ? '.png' : '.jpg'));
        $cached = is_file($cachePath) ? @file_get_contents($cachePath) : false;
        if ($cached !== false) {
            return $cached;
        }
        if (! function_exists('imagescale')) {
            throw new RuntimeException('The GD image extension is required to prepare large report images.');
        }
        $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
        $memoryLimit = $memoryLimit > 0 ? $memoryLimit : 512 * 1024 * 1024;
        $estimatedMemory = $width * $height * 8 + self::MAX_EDGE ** 2 * 8 + 32 * 1024 * 1024;
        if ($estimatedMemory > $memoryLimit - memory_get_usage(true)) {
            throw new RuntimeException('A report image is too large to process safely. Resize it before exporting.');
        }

        $source = $type === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if (! $source instanceof GdImage) {
            throw new RuntimeException('A report image could not be decoded.');
        }
        try {
            $scale = self::MAX_EDGE / max($width, $height);
            $image = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC_FIXED);
            unset($source);
            if (! $image instanceof GdImage) {
                throw new RuntimeException('A report image could not be resized.');
            }
            if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $orientation = (int) (@exif_read_data($path, 'IFD0')['Orientation'] ?? 1);
                $image = $this->orient($image, $orientation);
            }
            imagesavealpha($image, true);
            ob_start();
            try {
                $written = $type === IMAGETYPE_PNG ? imagepng($image, null, 6) : imagejpeg($image, null, 85);
                $contents = ob_get_contents();
                if (! $written || $contents === false || $contents === '') {
                    throw new RuntimeException('A report image could not be encoded.');
                }
                $this->cache($cachePath, $contents);

                return $contents;
            } finally {
                ob_end_clean();
            }
        } finally {
            unset($source, $image);
        }
    }

    private function cache(string $path, string $contents): void
    {
        $temporary = false;
        try {
            File::ensureDirectoryExists(dirname($path), 0700);
            $directory = realpath(dirname($path));
            if ($directory === false) {
                return;
            }
            $temporary = @tempnam($directory, 'image-');
            if ($temporary !== false && realpath(dirname($temporary)) === $directory
                && file_put_contents($temporary, $contents) === strlen($contents)) {
                @rename($temporary, $path);
            }
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3 => 180,
            6, 7 => -90,
            5, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('A report image could not be oriented.');
        }

        return $rotated;
    }
}
