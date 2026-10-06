<?php

use Tests\TestCase;

uses(TestCase::class);

test('every editable proposal paper has the same accessible close and reopen tools', function (string $paper) {
    $html = view('components.'.$paper.'-writing-toolbar')->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-proposal-workspace-toolbar]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-writing-toolbar-close][@aria-label="Hide tools"]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-writing-toolbar-open]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="'.$paper.'-tools-controls"][@data-writing-toolbar-controls]')->length)->toBe(1)
        ->and($html)->toContain('closeWritingToolbar()', 'showWritingToolbar()', 'Show tools');
})->with(['detailed-proposal', 'work-plan', 'line-item-budget', 'expense-breakdown', 'curriculum-vitae']);
