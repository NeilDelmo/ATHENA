<?php

use App\Services\ResearchManualComparison;

function comparisonIndex(array $passages, array $unindexed = []): array
{
    return ['unindexed_pages' => $unindexed, 'chunks' => array_map(fn (array $passage): array => [
        'section' => $passage[0], 'content' => $passage[1], 'page_start' => $passage[2] ?? 2,
        'page_end' => $passage[3] ?? ($passage[2] ?? 2), 'source_kind' => $passage[4] ?? 'policy',
    ], $passages)];
}

test('manual comparison preserves unchanged passages and highlights changed words with original page ranges', function () {
    $before = comparisonIndex([
        ['1.1 Ethics', "Signed consent is required.\n\nSubmit two copies before recruitment.", 2, 3],
        ['1.2 Funding', 'Old funding text.', 4],
        ['1.3 Outputs', ' Submit the report. ', 5],
    ]);
    $after = comparisonIndex([
        ['1.1 Ethics', "Signed consent is required.\n\nSubmit three copies before recruitment.", 7, 8],
        ['1.3 Outputs', "Submit\n the report.", 9],
        ['1.4 Forms', 'Use the updated form.', 10, 10, 'appendix'],
    ]);
    $comparison = (new ResearchManualComparison)->compare($before, $after);
    expect($comparison['unchanged_sections'])->toBe(1)
        ->and(array_column($comparison['changes'], 'type'))->toBe(['changed', 'added', 'removed'])
        ->and($comparison['changes'][0]['before']['page_start'])->toBe(2)
        ->and($comparison['changes'][0]['before']['page_end'])->toBe(3)
        ->and($comparison['changes'][0]['after']['page_start'])->toBe(7)
        ->and($comparison['changes'][0]['before']['segments'][1]['text'])->toBe('two')
        ->and($comparison['changes'][0]['after']['segments'][1]['text'])->toBe('three')
        ->and($comparison['changes'][1]['source_kind'])->toBe('appendix');
});

test('unreadable counterpart pages leave unmatched passages for PDF review instead of asserting policy removal', function () {
    $comparison = (new ResearchManualComparison)->compare(
        comparisonIndex([['1.1 Ethics', 'Consent is required.']], [6]),
        comparisonIndex([['1.2 Funding', 'Funding text.']], [3]),
    );
    expect(array_column($comparison['changes'], 'type'))->toBe(['unreadable', 'unreadable']);
});

test('manual comparison ignores changed pagination and chunk boundaries but detects reordered and duplicate passages', function () {
    $service = new ResearchManualComparison;
    expect($service->compare(
        comparisonIndex([['1.1 Ethics', 'First rule.', 1], ['1.1 Ethics', 'Second rule.', 2]]),
        comparisonIndex([['1.1 Ethics', 'First rule. Second rule.', 7]]),
    ))->toBe(['changes' => [], 'unchanged_sections' => 1]);

    $before = comparisonIndex([['1.1 Ethics', "First rule.\n\nSecond rule.\n\nFirst rule."]]);
    $after = comparisonIndex([['1.1 Ethics', "Second rule.\n\nFirst rule.\n\nFirst rule."]]);
    $comparison = $service->compare($before, $after);
    expect($comparison['changes'])->toHaveCount(2)
        ->and(array_column($comparison['changes'], 'type'))->toBe(['removed', 'added'])
        ->and($service->compare($before, $after))->toBe($comparison);
});

test('manual comparison keeps appendix and policy passages distinct and preserves Unicode', function () {
    $comparison = (new ResearchManualComparison)->compare(
        comparisonIndex([['Ética', 'Submit café form.', 1, 1, 'policy']]),
        comparisonIndex([['Ética', 'Submit café form.', 2, 2, 'appendix']]),
    );
    expect(array_column($comparison['changes'], 'type'))->toBe(['added', 'removed'])
        ->and($comparison['changes'][0]['after']['text'])->toBe('Submit café form.');
});
