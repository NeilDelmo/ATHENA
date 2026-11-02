<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Services\ProposalPackageService;
use App\Services\ProposalVersionComparison;
use Illuminate\Support\Facades\Storage;
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

test('older revision snapshots do not turn missing identity metadata or responsibility settings into paper edits', function () {
    $responsibility = ['name' => 'Test Researcher', 'percentage' => '100', 'duties' => '<p>Coordinate the research.</p>'];
    $previous = comparisonVersion([['checksum' => 'old', 'source_data' => [
        'project_title' => 'Sleep quality and academic performance', 'project_leader' => 'Test Researcher',
        'responsibilities' => [$responsibility], 'references' => '<p>[1] Original study.</p>',
    ]]], 2);
    $latest = comparisonVersion([['checksum' => 'new', 'source_data' => [
        'responsibilities' => [[...$responsibility, 'percentage_fixed' => '1']],
        'references' => '<p>[1] Replacement study.</p>',
        'methodology_images' => [['id' => 'new-chart', 'caption' => 'Research design', 'section' => 'research_design']],
    ]]], 3);
    $previous->title = $latest->title = 'Sleep quality and academic performance';
    $savedSource = $latest->files->first()->source_data;

    $paper = app(ProposalVersionComparison::class)->compare($previous, $latest)[0];

    expect(array_column($paper['sections'], 'field'))->toBe(['references'])
        ->and($paper['sections'][0]['before'])->toBe([['text' => '[1] Original study.', 'changed' => true]])
        ->and($paper['sections'][0]['after'])->toBe([['text' => '[1] Replacement study.', 'changed' => true]])
        ->and(array_column($paper['figures'], 'status'))->toBe(['added'])
        ->and($latest->files->first()->source_data)->toBe($savedSource);

    $html = $this->view('components.proposal-version-content-comparison', ['papers' => [$paper], 'previousNumber' => 2, 'latestNumber' => 3]);
    $html->assertSee('1 changed section')->assertSee('1 figure change')
        ->assertSee('Original study.')->assertSee('Replacement study.')->assertSee('Figure added')
        ->assertDontSee('Project Title comparison')->assertDontSee('Project Leader comparison')->assertDontSee('Percentage Fixed');
});

test('comparison uses each submitted version title when paper metadata is missing', function () {
    $previous = comparisonVersion([['source_data' => ['rationale' => 'Unchanged explanation']]], 2);
    $latest = comparisonVersion([['source_data' => ['rationale' => 'Unchanged explanation']]], 3);
    $previous->title = 'Original research title';
    $latest->title = 'Revised research title';

    $paper = app(ProposalVersionComparison::class)->compare($previous, $latest)[0];

    expect(array_column($paper['sections'], 'field'))->toBe(['project_title'])
        ->and($paper['sections'][0]['before'][0]['text'])->toBe('Original research title')
        ->and($paper['sections'][0]['after'][0]['text'])->toBe('Revised research title');
});

test('real identity and responsibility edits remain visible while percentage locking stays internal', function () {
    $previous = comparisonVersion([['source_data' => [
        'project_title' => 'Original research title', 'project_leader' => 'Original Leader',
        'responsibilities' => [['name' => 'Original Leader', 'percentage' => '100', 'duties' => 'Original duty']],
    ]]], 2);
    $latest = comparisonVersion([['source_data' => [
        'project_title' => 'Revised research title', 'project_leader' => 'Revised Leader',
        'responsibilities' => [['name' => 'Revised Leader', 'percentage' => '50', 'duties' => 'Revised duty', 'percentage_fixed' => '1']],
    ]]], 3);

    $paper = app(ProposalVersionComparison::class)->compare($previous, $latest)[0];

    expect(array_column($paper['sections'], 'field'))->toEqualCanonicalizing(['project_title', 'project_leader', 'responsibilities'])
        ->and(json_encode($paper))->toContain('Revised duty')->toContain('Percentage: 50')
        ->not->toContain('Percentage Fixed');
});

test('known empty identity values and removed papers are still reported as removals', function () {
    $previous = comparisonVersion([['source_data' => ['project_title' => 'Original title', 'project_leader' => 'Original Leader']]], 2);
    $latest = comparisonVersion([['source_data' => ['project_title' => '', 'project_leader' => '']]], 3);
    $latest->title = 'A version title must not override an explicitly empty paper field';
    $service = app(ProposalVersionComparison::class);

    $changed = $service->compare($previous, $latest)[0];
    $removed = $service->compare($previous, comparisonVersion([], 3))[0];

    expect(array_column($changed['sections'], 'field'))->toBe(['project_title', 'project_leader'])
        ->and($changed['sections'][0]['after'])->toBe([])
        ->and(array_column($removed['sections'], 'field'))->toBe(['project_title', 'project_leader']);
});

test('copied revision papers retain submitted identity metadata without inventing structured content for uploads', function (?array $sourceData) {
    Storage::fake('local');
    Storage::disk('local')->put('revision/prepared.pdf', '%PDF-1.7 Test prepared revision');
    $draft = new ProposalDraft(['project_title' => 'Current project title', 'project_leader' => 'Current Project Leader']);
    $document = new ProposalDraftDocument([
        'document_type' => 'detailed_proposal', 'position' => 0, 'source_data' => $sourceData,
        'file_path' => 'revision/prepared.pdf', 'original_filename' => 'paper.pdf', 'mime_type' => 'application/pdf',
    ]);
    $document->setRelation('draft', $draft);

    $copy = app(ProposalPackageService::class)->copyDraftDocument($document, 'submitted');

    if ($sourceData === null || $sourceData === []) {
        expect($copy['source_data'])->toBe($sourceData);
    } else {
        expect($copy['source_data'])->toMatchArray([
            'project_title' => 'Current project title', 'project_leader' => 'Current Project Leader', 'references' => 'Kept references',
        ]);
    }
    expect($document->source_data)->toBe($sourceData);
    Storage::disk('local')->assertExists($copy['file_path']);
})->with([
    'structured paper' => [['references' => 'Kept references']],
    'uploaded PDF' => [null],
    'empty legacy source' => [[]],
]);
