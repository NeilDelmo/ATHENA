<?php

use Illuminate\Support\Facades\Blade;

test('kpi strips preserve supplied labels values and optional tooltip hints', function (int $count) {
    $items = [
        ['label' => 'Open now', 'value' => 7, 'icon' => 'megaphone', 'hint' => 'Currently accepting proposals'],
        ['label' => 'Upcoming', 'value' => 0, 'icon' => 'calendar'],
        ['label' => 'Previous', 'value' => 23, 'icon' => 'archive'],
        ['label' => 'Budget ceiling', 'value' => 'PHP 987,654.32', 'icon' => 'wallet', 'hint' => 'Fixed per proposal'],
        ['label' => 'Revisions received', 'value' => 41, 'icon' => 'refresh'],
    ];
    $items = array_slice($items, 0, $count);
    $html = Blade::render('<x-kpi-strip data-submission-summary :items="$items" />', ['items' => $items]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $metrics = $xpath->query('//dl[@data-kpi-strip]/div');

    expect($metrics->length)->toBe($count)
        ->and($xpath->query('//dl[@data-submission-summary]')->length)->toBe(1)
        ->and($xpath->query('//dl')->item(0)->getAttribute('style'))->toBe('--cols: '.$count)
        ->and($xpath->query('//svg[@fill="none"]')->length)->toBe($count)
        ->and($xpath->query('//p | //a | //button')->length)->toBe(0);

    foreach ($items as $index => $item) {
        $metric = $metrics->item($index);

        expect(trim($xpath->query('dt', $metric)->item(0)->textContent))->toBe($item['label'])
            ->and(trim($xpath->query('dd', $metric)->item(0)->textContent))->toBe((string) $item['value'])
            ->and($metric->getAttribute('title'))->toBe($item['hint'] ?? '')
            ->and($metric->textContent)->not->toContain($item['hint'] ?? 'Missing hint');
    }
})->with([4, 5]);

test('kpi strips escape supplied labels values and hints', function () {
    $html = Blade::render('<x-kpi-strip :items="$items" />', ['items' => [
        ['label' => '<script>label</script>', 'value' => '<script>value</script>', 'icon' => 'folder', 'hint' => '" onmouseover="alert(1)'],
    ]]);

    expect($html)->not->toContain('<script>', 'title="" onmouseover=')
        ->toContain('&lt;script&gt;label&lt;/script&gt;', '&lt;script&gt;value&lt;/script&gt;', '&quot; onmouseover=&quot;');
});
