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

test('all fixed page actions share the audited components and retain their interaction hooks', function () {
    $pages = [];
    $fixedBottomViews = [];
    foreach (File::allFiles(resource_path('views')) as $file) {
        $view = str_replace('\\', '/', $file->getRelativePathname());
        $contents = $file->getContents();
        if (preg_match('/fixed[^"\r\n]*bottom-/', $contents)) {
            $fixedBottomViews[] = $view;
        }
        preg_match_all('/<x-back-link\s([^>]*?)>(.*?)<\/x-back-link>/s', $contents, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (! preg_match('/(?:^|\s)fixed(?:\s|$)/', $match[1])) {
                continue;
            }
            $saveHook = str_contains($match[1], 'data-paper-cancel-exit') ? ' data-paper-cancel-exit' : '';
            $label = trim(strip_tags(preg_replace('/{{.*?}}/s', 'submitted proposal', $match[2])));
            $html = Blade::render('<x-back-link fixed'.$saveHook.' href="/proposal#attachments">'.e($label).'</x-back-link>');
            expect($html)->toContain('data-fixed-back-link', 'href="/proposal#attachments"', 'print:hidden');
            $pages[] = ['view' => $view, 'html' => $html];
        }
    }
    sort($fixedBottomViews);
    expect($fixedBottomViews)->toBe([
        'components/back-link.blade.php',
        'components/monitoring-action-dock.blade.php',
        'components/research-assistant-drawer.blade.php',
        'topics/partials/project-monitoring.blade.php',
    ])->and(count($pages))->toBeGreaterThanOrEqual(20);

    $dock = Blade::render('<x-monitoring-action-dock fixed><x-back-link data-paper-cancel-exit href="/project#monitoring">Exit monitoring</x-back-link><button type="button" class="min-h-12 rounded-xl bg-white px-5 py-3">Save draft</button><button type="button" class="min-h-12 rounded-xl bg-white px-5 py-3">Preview report</button><form action="/prepare" method="POST"><button type="submit" name="prepare" value="1" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 text-white">Prepare official PDF</button></form><button type="button" disabled class="min-h-12 rounded-xl bg-white px-5 py-3">Submit to Research Head</button></x-monitoring-action-dock>');
    expect($dock)->toContain('data-monitoring-action-dock-fixed', 'data-paper-cancel-exit', 'method="POST"', 'name="prepare"', 'disabled');
    $inline = Blade::render('<x-monitoring-action-dock><button type="button">Preview report</button></x-monitoring-action-dock>');
    expect($inline)->not->toContain('data-monitoring-action-dock-fixed', 'fixed inset-x-4');

    if (getenv('FLOATING_ACTIONS_QA_PATH')) {
        $drawer = File::get(resource_path('views/components/research-assistant-drawer.blade.php'));
        File::put(getenv('FLOATING_ACTIONS_QA_PATH'), json_encode([
            'pages' => $pages,
            'dock' => $dock,
            'inline' => $inline,
            'launcher' => Blade::render(substr($drawer, 0, strpos($drawer, '</button>') + strlen('</button>'))),
        ], JSON_THROW_ON_ERROR));
    }
});
