<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class);

test('the ATHENA browser icon points to a circular PNG logo with transparent corners', function () {
    $this->blade('<x-favicon />')
        ->assertSee('rel="icon"', false)
        ->assertSee('type="image/png"', false)
        ->assertSee(asset('images/athena-favicon-circle.png'), false);

    expect(getimagesize(public_path('images/athena-favicon-circle.png'))['mime'])->toBe('image/png');

    $logo = imagecreatefrompng(public_path('images/athena-favicon-circle.png'));

    foreach ([[0, 0], [127, 0], [0, 127], [127, 127]] as [$x, $y]) {
        expect(imagecolorsforindex($logo, imagecolorat($logo, $x, $y))['alpha'])->toBe(127);
    }

    $background = imagecolorsforindex($logo, imagecolorat($logo, 10, 64));

    expect($background['alpha'])->toBe(0)
        ->and($background['red'])->toBeGreaterThanOrEqual(250)
        ->and($background['green'])->toBeGreaterThanOrEqual(250)
        ->and($background['blue'])->toBeGreaterThanOrEqual(250);
});

test('the fallback browser icon contains the circular ATHENA logo with transparent corners', function () {
    $icon = File::get(public_path('favicon.ico'));

    expect(substr($icon, 0, 6))->toBe(pack('vvv', 0, 1, 1));

    $logo = imagecreatefromstring(substr($icon, 22));

    expect(imagesx($logo))->toBe(32)
        ->and(imagesy($logo))->toBe(32)
        ->and(imagecolorsforindex($logo, imagecolorat($logo, 0, 0))['alpha'])
        ->toBe(127);
});

test('every HTML page head renders the ATHENA icon with the application base path', function () {
    $this->withoutVite();
    URL::forceRootUrl('https://athena.example.test/athena-app');
    URL::forceScheme('https');

    $heads = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (preg_match('/<head(?:\s[^>]*)?>.*?<\/head>/is', $file->getContents(), $matches)) {
            $heads[$file->getRelativePathname()] = $matches[0];
        }
    }

    expect($heads)->not->toBeEmpty();

    foreach ($heads as $view => $head) {
        $html = Blade::render($head, ['commentResponseForm' => ['form_label' => 'Form', 'project_title' => 'Project']]);
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $icons = (new DOMXPath($document))->query('//head/link[@rel="icon"]');

        $this->assertCount(1, $icons, $view);
        $this->assertSame('image/png', $icons[0]->getAttribute('type'), $view);
        $this->assertSame('https://athena.example.test/athena-app/images/athena-favicon-circle.png', $icons[0]->getAttribute('href'), $view);
    }
});
