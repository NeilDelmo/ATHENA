<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Services\CurriculumVitaeDocumentService;
use App\Services\DetailedProposalDocumentService;
use App\Services\ProposalPackageService;
use App\Services\ProposalRevisionSectionMap;
use App\Support\CurriculumVitaeData;
use App\Support\DetailedProposalData;
use App\Support\ProposalRevisionSectionCatalog;
use Illuminate\Support\Facades\Storage;

test('literature revisions use the template label for both current and legacy PDFs', function (string $heading) {
    $xml = '<document><page width="600" height="800"><box xMin="50" xMax="550" yMin="90" yMax="400"/><line xMin="55" xMax="500" yMin="100" yMax="114"><word>'.$heading.'</word></line></page></document>';
    $regions = app(ProposalRevisionSectionMap::class)->fromBbox($xml, ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    expect($regions)->toHaveCount(1)
        ->and($regions[0]['id'])->toBe('section-literature')
        ->and($regions[0]['label'])->toBe('XI. Review of Related Literature:');
})->with(['XI. Review of Related Literature:', 'XI. Introduction:']);

test('saved detailed proposal section maps show current labels without changing PDF coordinates', function () {
    Storage::fake('local');
    $pdf = "%PDF-1.7\nExisting submitted proposal";
    Storage::disk('local')->put('existing-proposal.pdf', $pdf);
    $region = ['id' => 'section-literature', 'label' => 'XI. Introduction and Related Literature', 'pageNumber' => 3, 'x' => .1, 'y' => .2, 'width' => .8, 'height' => .4];
    $source = ['_revision_sections' => ['version' => 1, 'checksum' => hash('sha256', $pdf), 'regions' => [$region]]];
    $file = new ProposalVersionFile([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'mime_type' => 'application/pdf', 'file_path' => 'existing-proposal.pdf', 'source_data' => $source,
    ]);
    expect(app(ProposalRevisionSectionMap::class)->forFile($file))->toBe([
        [...$region, 'label' => 'XI. Review of Related Literature:'],
    ])->and($file->source_data)->toBe($source)
        ->and(Storage::disk('local')->get('existing-proposal.pdf'))->toBe($pdf);
});

test('section maps use box boundaries and continue across pages with greatest overlap winning', function () {
    $xml = '<document>
        <page width="600" height="800">
            <box xMin="50" xMax="550" yMin="90" yMax="200"/>
            <box xMin="50" xMax="550" yMin="200" yMax="760"/>
            <line xMin="55" xMax="340" yMin="100" yMax="114"><word>III. Sustainable Development Goal:</word></line>
            <line xMin="55" xMax="300" yMin="210" yMax="224"><word>IV. Project Leader:</word></line>
        </page>
        <page width="600" height="800">
            <box xMin="50" xMax="550" yMin="40" yMax="300"/>
            <box xMin="50" xMax="550" yMin="300" yMax="500"/>
            <line xMin="55" xMax="340" yMin="50" yMax="64"><word>Staff continued</word></line>
            <line xMin="55" xMax="340" yMin="310" yMax="324"><word>V. Proponent Agency:</word></line>
        </page></document>';
    $service = app(ProposalRevisionSectionMap::class);
    $regions = $service->fromBbox($xml, ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    expect($regions)->toHaveCount(4)
        ->and($regions[0]['y'])->toBe(90 / 800)
        ->and($regions[0]['height'])->toBe(110 / 800)
        ->and($regions[2]['id'])->toBe('section-project-team')
        ->and($regions[2]['pageNumber'])->toBe(2);
    expect($service->match($regions, 1, [['x' => .5, 'y' => .2, 'width' => .2, 'height' => .2]]))->toBe('section-project-team')
        ->and($service->match($regions, 1, [['x' => .5, 'y' => .15, 'width' => .2, 'height' => .05]]))->toBe('section-sdgs')
        ->and($service->match($regions, 2, [['x' => .5, 'y' => .5, 'width' => .2, 'height' => .05]]))->toBe('section-proponent')
        ->and($service->match($regions, 3, [['x' => .5, 'y' => .5, 'width' => .2, 'height' => .05]]))->toBeNull()
        ->and($service->match($regions, 1, [['x' => .01, 'y' => .01, 'width' => .02, 'height' => .02]]))->toBeNull();
});

test('generated detailed forms store section coordinates tied to the exact PDF', function () {
    Storage::fake('local');
    $source = DetailedProposalData::fromValidated([
        'project_title' => 'Section navigation test',
        'project_leader' => 'Test Faculty',
        'executive_brief' => str_repeat('Check the section across a page break. ', 140),
    ]);
    $docx = app(DetailedProposalDocumentService::class)->generate($source);
    $file = app(ProposalPackageService::class)->storeGeneratedDetailedProposal($docx, 'section-test', 'Section navigation test', $source);
    $metadata = $file['source_data']['_revision_sections'];
    $regions = $metadata['regions'];
    expect($metadata['checksum'])->toBe(hash('sha256', Storage::disk('local')->get($file['file_path'])));
    foreach (app(ProposalRevisionSectionCatalog::class)->forType('detailed_proposal') as $section) {
        expect(array_column($regions, 'id'))->toContain($section['value']);
    }
    expect(collect($regions)->where('id', 'section-executive-brief')->count())->toBeGreaterThan(1);
    $sdgs = collect($regions)->firstWhere('id', 'section-sdgs');
    expect(app(ProposalRevisionSectionMap::class)->match($regions, $sdgs['pageNumber'], [[
        'x' => $sdgs['x'] + $sdgs['width'] * .6,
        'y' => $sdgs['y'] + $sdgs['height'] * .4,
        'width' => $sdgs['width'] * .2, 'height' => $sdgs['height'] * .2,
    ]]))->toBe('section-sdgs');
});

test('generated editable attachments retain their category destinations', function (string $type, string $template, array $expected) {
    $source = [];
    if ($type === 'curriculum_vitae') {
        $source = CurriculumVitaeData::fromValidated([
            'people' => [['first_name' => 'First', 'last_name' => 'Faculty'], ['first_name' => 'Second', 'last_name' => 'Faculty']],
        ]);
        $contents = app(CurriculumVitaeDocumentService::class)->generate($source);
    } else {
        $contents = file_get_contents(config($template.'.template_path'));
    }
    $pdf = $type === 'expense_breakdown'
        ? app(DocumentPdfConverter::class)->convertXlsx($contents)
        : app(DocumentPdfConverter::class)->convertDocx($contents);
    if (getenv('ATHENA_SECTION_PREVIEW')) {
        file_put_contents(sys_get_temp_dir().'/athena-section-'.$type.'.pdf', $pdf);
    }
    $regions = app(ProposalRevisionSectionMap::class)->fromPdf($pdf, $type, $source);
    if ($type === 'curriculum_vitae') {
        $expected = array_column(app(ProposalRevisionSectionCatalog::class)->forType($type, $source), 'value');
    }
    foreach ($expected as $id) {
        expect(array_column($regions, 'id'))->toContain($id);
    }
})->with([
    'work plan' => ['work_plan', 'work_plan', ['section-project-information', 'section-schedule', 'section-signatories']],
    'budget' => ['line_item_budget', 'line_item_budget', ['section-project-information', 'section-project-team', 'section-mooe', 'section-co', 'section-totals', 'section-research-office']],
    'expenses' => ['expense_breakdown', 'expense_breakdown', ['section-expense-items', 'section-totals']],
    'CV package' => ['curriculum_vitae', 'curriculum_vitae', ['section-cv-1-personal', 'section-cv-1-academic_background', 'section-cv-2-personal', 'section-cv-2-academic_background']],
]);
