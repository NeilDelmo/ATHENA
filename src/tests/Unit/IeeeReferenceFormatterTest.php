<?php

use App\Models\LiteratureSource;
use App\Models\ProposalDraftLiteratureSource;

test('IEEE references preserve author boundaries from imported and manual sources', function (string $authors, string $formattedAuthors) {
    $attributes = [
        'title' => 'Library management research',
        'authors' => $authors,
        'venue' => 'Journal of Library Research',
        'publication_year' => 2024,
    ];
    $reference = $formattedAuthors.', "Library management research," Journal of Library Research, 2024.';

    expect((new LiteratureSource($attributes))->referenceDraft())->toBe($reference)
        ->and((new ProposalDraftLiteratureSource($attributes))->referenceDraft(2))->toBe('[2] '.$reference);
})->with([
    'comma-separated names' => ['Maria Santos, Luis Cruz', 'M. Santos and L. Cruz'],
    'semicolon-separated names' => ['José Reyes; Ana Cruz', 'J. Reyes and A. Cruz'],
    'harvested surname-first names' => ['Reyes, José; Cruz, Ana', 'J. Reyes and A. Cruz'],
    'compound harvested surnames' => ['de la Cruz, Ana María; Reyes, José', 'A. M. de la Cruz and J. Reyes'],
    'unlisted authors' => ['Authors not listed', 'Author not listed'],
    'empty authors' => ['', 'Author not listed'],
]);
