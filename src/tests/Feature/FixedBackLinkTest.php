<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('fixed back links preserve navigation and the save-before-exit hook', function () {
    $html = Blade::render('<x-back-link fixed data-paper-cancel-exit href="/proposal#attachments">Exit editor</x-back-link>');
    expect($html)->toContain('data-fixed-back-link', 'fixed bottom-4 right-4 z-40', 'sm:bottom-6 sm:right-6', 'data-paper-cancel-exit', 'href="/proposal#attachments"', 'print:hidden');
    $inline = Blade::render('<x-back-link href="/review">Back to review</x-back-link>');
    expect($inline)->not->toContain('data-fixed-back-link', 'fixed bottom-4');
    if (getenv('BACK_LINK_QA_PATH')) {
        file_put_contents(getenv('BACK_LINK_QA_PATH'), Blade::render('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">@vite(["resources/css/app.css"])</head><body class="bg-white dark:bg-slate-950"><header class="p-6"><h1>Proposal editor</h1>'.$html.'</header><main><div style="height: 2200px">Proposal contents</div><button id="last-control">Save changes</button></main></body></html>'));
    }
});

test('faculty proposal pages consistently opt into the fixed back link', function () {
    $links = 0;
    foreach (File::allFiles(resource_path('views/faculty/proposal-drafts')) as $file) {
        preg_match_all('/<x-back-link\s[^>]*>/', $file->getContents(), $matches);
        foreach ($matches[0] as $link) {
            expect($link)->toContain(' fixed ')->not->toContain('w-full', 'bottom-', 'right-');
            $links++;
        }
    }
    expect($links)->toBeGreaterThan(10);
});
