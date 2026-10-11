<?php

use App\Support\ProposalRichText;

test('managed reference paragraphs retain only valid source identifiers', function (string $identifier, string $attribute) {
    $richText = new ProposalRichText;
    $html = '<p data-proposal-managed-reference="'.$identifier.'" onclick="alert(1)" class="unsafe">[1] A. Reader, "Reading evidence," 2025.</p>';

    expect($richText->sanitize($html))->toBe('<p'.$attribute.'>[1] A. Reader, &quot;Reading evidence,&quot; 2025.</p>');
})->with([
    'positive identifier' => ['42', ' data-proposal-managed-reference="42"'],
    'trimmed identifier' => [' 42 ', ' data-proposal-managed-reference="42"'],
    'empty identifier' => ['', ''],
    'zero identifier' => ['0', ''],
    'negative identifier' => ['-42', ''],
    'decimal identifier' => ['4.2', ''],
    'unsafe identifier' => ['42&quot; onmouseover=&quot;alert(1)', ''],
]);
