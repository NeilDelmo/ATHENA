<?php

use App\Support\ProposalRichText;

test('table preservation is opt in and strips unsupported attributes and executable content', function () {
    $richText = new ProposalRichText;
    $html = '<table onclick="alert(1)"><tr><th colspan="2">Measure</th><th>Result</th></tr><tr><td style="color:red"><strong>Coast</strong><script>alert(1)</script></td><td><span data-proposal-citation="42" class="unsafe">Source</span></td></tr></table>';

    expect($richText->sanitize($html))->not->toContain('<table', '<tr', '<td', '<th')
        ->and($richText->sanitize($html, allowTables: true))
        ->toBe('<table><tbody><tr><th>Measure</th><th>Result</th></tr><tr><td><strong>Coast</strong></td><td><span data-proposal-citation="42">Source</span></td></tr></tbody></table>');
});

test('tables normalize uneven rows and flatten nested grids without duplicating their rows', function () {
    $richText = new ProposalRichText;
    $html = '<table><tr><td>First</td><td>Second</td></tr><tr><td><table><tr><td><em>Nested content</em></td></tr></table></td></tr></table>';

    expect($richText->sanitize($html, allowTables: true))
        ->toBe('<table><tbody><tr><td>First</td><td>Second</td></tr><tr><td><em>Nested content</em></td><td><p><br></p></td></tr></tbody></table>');
});

test('pasted tables are bounded to supported row and column counts', function () {
    $richText = new ProposalRichText;
    $html = '<table>'.str_repeat('<tr>'.str_repeat('<td>Cell</td>', 15).'</tr>', 105).'</table>';
    $sanitized = $richText->sanitize($html, allowTables: true);

    expect(substr_count($sanitized, '<tr>'))->toBe(100)
        ->and(substr_count($sanitized, '<td>'))->toBe(1200);
});

test('document blocks retain paragraph list and table order with cell formatting', function () {
    $richText = new ProposalRichText;
    $blocks = $richText->contentBlocks('<p>Before</p><table><tr><th>Heading</th></tr><tr><td><strong>Cell</strong></td></tr></table><ul><li>After</li></ul>');

    expect(array_column($blocks, 'type'))->toBe(['paragraph', 'table', 'unordered'])
        ->and($blocks[0]['runs'][0]['text'])->toBe('Before')
        ->and($blocks[1]['rows'])->toBe([
            [['header' => true, 'html' => 'Heading']],
            [['header' => false, 'html' => '<strong>Cell</strong>']],
        ])
        ->and($blocks[2]['runs'][0]['text'])->toBe('After');
});
