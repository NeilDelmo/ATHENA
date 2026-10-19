<?php

use App\Services\ImagePreviewService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::set('local', Storage::fake('image-preview-tests'));
});

function storeLargePreviewImage(string $path, int $seed = 1): void
{
    $image = imagecreatetruecolor(2400, 1800);
    for ($index = 0; $index < 400; $index++) {
        $color = imagecolorallocate($image, ($index * 29 + $seed) % 256, ($index * 71) % 256, ($index * 113) % 256);
        imagefilledellipse($image, ($index * 127) % 2400, ($index * 197) % 1800, 240, 160, $color);
    }
    Storage::disk('local')->makeDirectory(dirname($path));
    imagejpeg($image, Storage::disk('local')->path($path), 95);
    imagedestroy($image);
}

test('private previews are smaller bounded derivatives and never alter the original', function () {
    $path = 'reports/figure.jpg';
    storeLargePreviewImage($path);
    $disk = Storage::disk('local');
    $original = $disk->get($path);
    $service = app(ImagePreviewService::class);

    $preview = $service->previewPath($path);
    [$width, $height] = getimagesize($disk->path($preview));

    expect($preview)->toStartWith('image-previews/v1/')
        ->and(max($width, $height))->toBe(1600)
        ->and($disk->size($preview))->toBeLessThan($disk->size($path))
        ->and($disk->get($path))->toBe($original)
        ->and($service->previewPath($path))->toBe($preview);

    $response = $service->response($path, 'figure.jpg');
    expect($response->headers->get('Content-Type'))->toBe('image/webp')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('replacing image contents changes its cached preview and prunes the older derivative', function () {
    $path = 'reports/figure.jpg';
    storeLargePreviewImage($path);
    $service = app(ImagePreviewService::class);
    $previous = $service->previewPath($path);
    storeLargePreviewImage($path, 29);

    $current = $service->previewPath($path);

    expect($current)->not->toBe($previous);
    Storage::disk('local')->assertExists([$path, $current]);
    Storage::disk('local')->assertMissing($previous);
});

test('small and invalid images remain unchanged without attempting an unsafe decode', function () {
    $disk = Storage::disk('local');
    $disk->put('reports/invalid.jpg', 'not an image');
    $image = imagecreatetruecolor(80, 60);
    $disk->makeDirectory('reports');
    imagepng($image, $disk->path('reports/small.png'));
    imagedestroy($image);
    $oversized = $disk->get('reports/small.png');
    $disk->put('reports/oversized.png', substr_replace($oversized, pack('N', 20001), 16, 4));

    $service = app(ImagePreviewService::class);
    expect($service->previewPath('reports/invalid.jpg'))->toBeNull()
        ->and($service->previewPath('reports/small.png'))->toBeNull()
        ->and($service->previewPath('reports/oversized.png'))->toBeNull()
        ->and($disk->allFiles('image-previews'))->toBe([]);
});

test('preview cleanup removes deleted originals and expired derivatives', function () {
    $disk = Storage::disk('local');
    storeLargePreviewImage('reports/deleted.jpg');
    storeLargePreviewImage('reports/expired.jpg');
    $service = app(ImagePreviewService::class);
    $deleted = $service->previewPath('reports/deleted.jpg');
    $expired = $service->previewPath('reports/expired.jpg');
    $disk->delete('reports/deleted.jpg');
    touch($disk->path($expired), now()->subDays(8)->timestamp);

    expect($service->prune())->toBe(2);
    $disk->assertMissing([$deleted, $expired]);
    $disk->assertExists('reports/expired.jpg');
});

test('a preview cache failure still serves the original image privately', function () {
    Exceptions::fake();
    Storage::disk('local')->put('reports/original.jpg', 'original image bytes');
    $service = Mockery::mock(ImagePreviewService::class)->makePartial();
    $service->shouldReceive('previewPath')->once()->andThrow(new RuntimeException('Cache is unavailable'));

    $response = $service->response('reports/original.jpg', 'original.jpg');
    ob_start();
    $response->sendContent();
    $contents = ob_get_clean();

    expect($contents)->toBe('original image bytes')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
    Exceptions::assertReported(RuntimeException::class);
});

test('JPEG previews preserve all EXIF orientations including mirrored photos', function (int $orientation, array $expected) {
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
    imagedestroy($image);
    $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('V', 0);
    $path = 'reports/oriented.jpg';
    Storage::disk('local')->put($path, substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

    $previewPath = app(ImagePreviewService::class)->previewPath($path);
    $preview = imagecreatefromwebp(Storage::disk('local')->path($previewPath));
    $width = imagesx($preview);
    $height = imagesy($preview);
    expect($width > $height)->toBe($orientation < 5);
    foreach ($expected as $index => $colorIndex) {
        $pixel = imagecolorat($preview, (int) ($width * (($index % 2) ? 0.75 : 0.25)), (int) ($height * (intdiv($index, 2) ? 0.75 : 0.25)));
        $actual = imagecolorsforindex($preview, $pixel);
        foreach (['red', 'green', 'blue'] as $channel => $name) {
            expect(abs($actual[$name] - $colors[$colorIndex][$channel]))->toBeLessThan(30);
        }
    }
    imagedestroy($preview);
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
