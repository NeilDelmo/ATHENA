<?php

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Services\ProposalVersionComparison;
use Tests\TestCase;

uses(TestCase::class);

function comparisonVersion(array $files, int $number): ProposalVersion
{
    $version = new ProposalVersion(['version_number' => $number]);
    $version->setRelation('files', collect($files)->map(fn (array $file): ProposalVersionFile => new ProposalVersionFile([
        'document_type' => 'detailed_proposal', 'position' => 0, ...$file,
    ])));

    return $version;
}

test('submitted comparison identifies changed paragraphs and figures without reading working drafts', function () {
    $previous = comparisonVersion([['source_data' => [
        'rationale' => '<p>Keep this paragraph.</p><p>Original explanation.</p>',
        'methodology_images' => [['id' => 'retained', 'caption' => 'Existing chart', 'path' => 'private/old.png'], ['id' => 'removed', 'caption' => 'Removed chart']],
        'literature_research_history' => 'old internal metadata',
    ]]], 1);
    $latest = comparisonVersion([['source_data' => [
        'rationale' => '<p>Keep this paragraph.</p><p>Revised explanation.</p>',
        'methodology_images' => [['id' => 'retained', 'caption' => 'Updated chart', 'path' => 'private/old.png'], ['id' => 'added', 'caption' => 'New chart']],
        'literature_research_history' => 'new internal metadata',
    ]]], 2);

    $paper = app(ProposalVersionComparison::class)->compare($previous, $latest)[0];

    expect($paper['mode'])->toBe('structured')
        ->and($paper['sections'])->toHaveCount(1)
        ->and($paper['sections'][0])->toMatchArray(['field' => 'rationale', 'label' => 'Rationale'])
        ->and($paper['sections'][0]['before'][0])->toBe(['text' => 'Keep this paragraph.', 'changed' => false])
        ->and($paper['sections'][0]['after'][1])->toBe(['text' => 'Revised explanation.', 'changed' => true])
        ->and(array_column($paper['figures'], 'status'))->toEqualCanonicalizing(['updated', 'removed', 'added'])
        ->and(json_encode($paper))->not->toContain('private/old.png')->not->toContain('internal metadata');
});

test('uploaded and legacy papers stay at file level and missing previous versions are safe', function () {
    $service = app(ProposalVersionComparison::class);
    expect($service->compare(null, comparisonVersion([], 1)))->toBe([]);
    $paper = $service->compare(comparisonVersion([['checksum' => 'old', 'source_data' => null]], 1), comparisonVersion([['checksum' => 'new', 'source_data' => null]], 2))[0];
    expect($paper)->toMatchArray(['mode' => 'file', 'file_changed' => true, 'sections' => [], 'figures' => []]);
});

test('comparison handles structured tables rich text and large content without truncating or rendering unsafe HTML', function () {
    $before = comparisonVersion([['source_data' => ['methodology' => ['research_design' => '<p>Baseline</p>'], 'specific_objectives' => [['description' => 'Previous objective']]]]], 1);
    $long = str_repeat('Research finding. ', 2000);
    $after = comparisonVersion([['source_data' => ['methodology' => ['research_design' => '<p>'.$long.'</p><p>&lt;img src=x onerror=alert(1)&gt;</p>'], 'specific_objectives' => [['description' => 'Updated objective']]]]], 2);
    $papers = app(ProposalVersionComparison::class)->compare($before, $after);
    expect(array_column($papers[0]['sections'], 'field'))->toContain('methodology', 'specific_objectives')
        ->and($papers[0]['sections'][1]['after'][0]['text'] ?? $papers[0]['sections'][0]['after'][0]['text'])->not->toBeEmpty();
    $html = $this->view('components.proposal-version-content-comparison', ['papers' => $papers, 'previousNumber' => 1, 'latestNumber' => 2]);
    $html->assertSee($long)->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x', false);
});

test('unchanged carried papers and generated metadata are omitted while added and removed papers remain visible', function () {
    $previous = comparisonVersion([
        ['source_data' => ['rationale' => 'Same wording'], 'checksum' => 'same'],
        ['document_type' => 'work_plan', 'source_data' => ['entries' => [['objective' => 'Original objective', 'activity' => 'Original work']]], 'checksum' => 'removed'],
    ], 1);
    $latest = comparisonVersion([
        ['source_data' => ['rationale' => 'Same wording', 'document_checklist' => ['complete_documents' => true]], 'checksum' => 'same', 'is_carried_forward' => true],
        ['document_type' => 'curriculum_vitae', 'source_data' => ['people' => [['first_name' => 'New', 'last_name' => 'Researcher']]], 'checksum' => 'added'],
    ], 2);
    $papers = app(ProposalVersionComparison::class)->compare($previous, $latest);
    expect($papers)->toHaveCount(2)
        ->and($papers[0]['sections'][0]['after'])->toBe([])
        ->and($papers[1]['sections'][0]['before'])->toBe([]);
});
