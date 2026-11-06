<?php

use App\Services\DocumentImageService;
use App\Services\ImagePreviewService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::set('local', Storage::fake('document-image-tests'));
});

function documentImageFixture(string $filename, int $width, int $height, bool $transparent = false): string
{
    $path = Storage::disk('local')->path($filename);
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 220, 30, 60, $transparent ? 96 : 0));
    if (str_ends_with($filename, '.png')) {
        imagepng($image, $path);
    } else {
        imagejpeg($image, $path, 95);
    }
    unset($image);

    return $path;
}

test('document photos are resized proportionally without changing the stored original', function () {
    $path = documentImageFixture('large.jpg', 3000, 2000);
    $original = file_get_contents($path);

    $contents = app(DocumentImageService::class)->contents($path);
    [$width, $height, $type] = getimagesizefromstring($contents);
    $image = imagecreatefromstring($contents);
    $color = imagecolorsforindex($image, imagecolorat($image, 800, 500));

    expect([$width, $height, $type])->toBe([1600, 1067, IMAGETYPE_JPEG])
        ->and(strlen($contents))->toBeLessThan(strlen($original))
        ->and(file_get_contents($path))->toBe($original)
        ->and(abs($color['red'] - 220))->toBeLessThan(10)
        ->and(abs($color['green'] - 30))->toBeLessThan(10)
        ->and(abs($color['blue'] - 60))->toBeLessThan(10);
});

test('document PNG resizing preserves transparency and its image type', function () {
    $path = documentImageFixture('transparent.png', 2400, 1200, true);
    $original = file_get_contents($path);
    $contents = app(DocumentImageService::class)->contents($path);
    $image = imagecreatefromstring($contents);
    $color = imagecolorsforindex($image, imagecolorat($image, 800, 400));

    expect(array_slice(getimagesizefromstring($contents), 0, 3))->toBe([1600, 800, IMAGETYPE_PNG])
        ->and(abs($color['alpha'] - 96))->toBeLessThanOrEqual(1)
        ->and(file_get_contents($path))->toBe($original);
});

test('small document images keep their original bytes without upscaling', function (string $filename) {
    $path = documentImageFixture($filename, 120, 80);
    expect(app(DocumentImageService::class)->contents($path))->toBe(file_get_contents($path));
})->with(['small.jpg', 'small.png']);

test('unavailable or corrupt report images fail instead of producing an incomplete report', function (bool $exists) {
    $path = Storage::disk('local')->path('unavailable.jpg');
    if ($exists) {
        file_put_contents($path, 'corrupt image');
    }
    expect(fn () => app(DocumentImageService::class)->contents($path))->toThrow(RuntimeException::class);
})->with(['missing' => false, 'corrupt' => true]);

test('document images with excessive decoded dimensions are rejected before decoding', function () {
    $path = documentImageFixture('huge.png', 10, 10);
    $contents = file_get_contents($path);
    file_put_contents($path, substr_replace($contents, pack('NN', 20000, 20000), 16, 8));

    expect(fn () => app(DocumentImageService::class)->contents($path))
        ->toThrow(RuntimeException::class, 'too large');
});

test('document image processing respects the remaining PHP memory budget', function () {
    $path = documentImageFixture('limited.jpg', 2400, 1800);
    $previous = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 1024 * 1024));
    try {
        expect(fn () => app(DocumentImageService::class)->contents($path))
            ->toThrow(RuntimeException::class, 'too large');
    } finally {
        ini_set('memory_limit', $previous);
    }
});

test('unchanged report photos reuse a private reduced copy without decoding again', function () {
    $path = documentImageFixture('cached.jpg', 2400, 1800);
    $service = app(DocumentImageService::class);
    $first = $service->contents($path);
    $previous = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 1024 * 1024));
    try {
        expect($service->contents($path))->toBe($first)
            ->and(Storage::disk('local')->allFiles('image-previews/documents-v1'))->toHaveCount(1);
    } finally {
        ini_set('memory_limit', $previous);
    }
});

test('changing the original photo invalidates its reduced copy', function () {
    $path = documentImageFixture('replaced.jpg', 2400, 1800);
    $service = app(DocumentImageService::class);
    $first = $service->contents($path);
    $image = imagecreatetruecolor(2400, 1800);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 180, 240));
    imagejpeg($image, $path, 95);
    unset($image);
    $second = $service->contents($path);

    expect($second)->not->toBe($first)
        ->and(Storage::disk('local')->allFiles('image-previews/documents-v1'))->toHaveCount(2);
});

test('daily preview cleanup removes document derivatives while preserving originals', function () {
    $path = documentImageFixture('pruned.jpg', 2400, 1800);
    app(DocumentImageService::class)->contents($path);
    expect(app(ImagePreviewService::class)->prune())->toBe(1)
        ->and(Storage::disk('local')->allFiles('image-previews/documents-v1'))->toBe([])
        ->and(is_file($path))->toBeTrue();
});

test('an unavailable derivative cache does not prevent a valid photo export', function () {
    Exceptions::fake();
    $path = documentImageFixture('cache-failure.jpg', 2400, 1800);
    Storage::disk('local')->put('image-previews', 'not a directory');
    $contents = app(DocumentImageService::class)->contents($path);
    expect(array_slice(getimagesizefromstring($contents), 0, 2))->toBe([1600, 1200]);
});

test('resized document photos preserve EXIF rotations and reflections', function (int $orientation, array $expected) {
    $colors = [[255, 0, 0], [0, 255, 0], [0, 0, 255], [255, 255, 0]];
    $image = imagecreatetruecolor(2400, 1800);
    foreach ($colors as $index => $color) {
        $left = ($index % 2) * 1200;
        $top = intdiv($index, 2) * 900;
        imagefilledrectangle($image, $left, $top, $left + 1199, $top + 899, imagecolorallocate($image, ...$color));
    }
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = ob_get_clean();
    unset($image);
    $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('V', 0);
    $path = Storage::disk('local')->path('oriented.jpg');
    file_put_contents($path, substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

    $contents = app(DocumentImageService::class)->contents($path);
    $result = imagecreatefromstring($contents);
    $width = imagesx($result);
    $height = imagesy($result);
    expect($width > $height)->toBe($orientation < 5);
    foreach ($expected as $index => $colorIndex) {
        $pixel = imagecolorat($result, (int) ($width * (($index % 2) ? 0.75 : 0.25)), (int) ($height * (intdiv($index, 2) ? 0.75 : 0.25)));
        $actual = imagecolorsforindex($result, $pixel);
        foreach (['red', 'green', 'blue'] as $channel => $name) {
            expect(abs($actual[$name] - $colors[$colorIndex][$channel]))->toBeLessThan(30);
        }
    }
})->with([
    'normal' => [1, [0, 1, 2, 3]],
    'mirror horizontal' => [2, [1, 0, 3, 2]],
    'rotate 180' => [3, [3, 2, 1, 0]],
    'mirror vertical' => [4, [2, 3, 0, 1]],
    'transpose' => [5, [0, 2, 1, 3]],
    'rotate clockwise' => [6, [2, 0, 3, 1]],
    'transverse' => [7, [3, 1, 2, 0]],
    'rotate counterclockwise' => [8, [1, 3, 0, 2]],
]);
