<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class);

test('the ATHENA browser icon points to a valid PNG logo', function () {
    $this->blade('<x-favicon />')
        ->assertSee('rel="icon"', false)
        ->assertSee('type="image/png"', false)
        ->assertSee(asset('images/athenalogo-transparent.png'), false);

    expect(getimagesize(public_path('images/athenalogo-transparent.png'))['mime'])->toBe('image/png');
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
        $this->assertSame('https://athena.example.test/athena-app/images/athenalogo-transparent.png', $icons[0]->getAttribute('href'), $view);
    }
});
