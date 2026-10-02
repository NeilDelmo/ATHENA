<?php

use App\Services\CurriculumVitaeDocumentService;
use App\Services\DetailedProposalDocumentService;
use App\Services\InitialScreeningFormDocumentService;
use App\Services\LineItemBudgetDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\DetailedProposalData;
use App\Support\InitialScreeningSubmissionOrder;
use App\Support\LineItemBudgetData;
use App\Support\WordCheckbox;

beforeEach(function () {
    $this->withoutVite();
    $this->documentXPath = function (string $contents): DOMXPath {
        $path = tempnam(sys_get_temp_dir(), 'athena-checkbox-test-');
        file_put_contents($path, $contents);
        $archive = new ZipArchive;
        try {
            expect($archive->open($path))->toBeTrue();
            $document = new DOMDocument;
            expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('w14', 'http://schemas.microsoft.com/office/word/2010/wordml');

            return $xpath;
        } finally {
            $archive->close();
            unlink($path);
        }
    };
});

test('call level crosses agree across the proposal budget and screening papers', function (?string $level) {
    $proposal = DetailedProposalData::fromValidated(['sdgs' => [4]], ['level_of_call' => $level]);
    $budget = LineItemBudgetData::fromValidated([
        'project_title' => 'Coastal Research', 'project_leader' => 'Researcher',
        'planned_start' => '2026-10-01', 'planned_end' => '2027-09-30', 'level_of_call' => $level,
    ]);
    $budget['level_of_call'] = $level;
    $screening = [
        'project_title' => 'Coastal Research', 'project_leader' => 'Researcher',
        'order_of_submission' => InitialScreeningSubmissionOrder::REVISED_WITH_MAJOR_CHANGES,
        'level_of_call' => $level,
    ];
    $proposalXPath = ($this->documentXPath)(app(DetailedProposalDocumentService::class)->generate($proposal));
    foreach (['Central Agency' => 'central_agency', 'Constituent Campus' => 'constituent_campus'] as $label => $value) {
        $checkbox = $proposalXPath->query('//w:p[contains(string(.), "'.$label.'")]//w14:checkbox')->item(0);
        expect($checkbox)->not->toBeNull();
        expect($proposalXPath->evaluate('string(./w14:checked/@w14:val)', $checkbox))->toBe($level === $value ? '1' : '0')
            ->and($proposalXPath->evaluate('string(ancestor::w:sdt[1]/w:sdtContent//w:t)', $checkbox))->toBe($level === $value ? '☒' : '☐');
        $this->view('faculty.detailed-proposals.preview', ['detailedProposal' => $proposal])
            ->assertSeeText(($level === $value ? '☒' : '☐').' '.$label);
        $this->view('faculty.line-item-budgets.preview', ['lineItemBudget' => $budget])
            ->assertSeeText(($level === $value ? '☒' : '☐').' '.$label);
    }
    foreach ([
        'budget' => [app(LineItemBudgetDocumentService::class)->generate($budget), 0],
        'screening' => [app(InitialScreeningFormDocumentService::class)->generate($screening), 3],
    ] as [$contents, $offset]) {
        $xpath = ($this->documentXPath)($contents);
        $checkboxes = $xpath->query('//w:checkBox');
        foreach (['central_agency', 'constituent_campus'] as $index => $value) {
            $checkbox = $checkboxes->item($offset + $index);
            expect($xpath->evaluate('string(./w:checked/@w:val)', $checkbox))->toBe($level === $value ? '1' : '0')
                ->and($xpath->evaluate('string(./w:default/@w:val)', $checkbox))->toBe($level === $value ? '1' : '0');
            expect($xpath->query('//w:t[text()="☒" or text()="☐"]')->length)->toBe(0);
        }
    }
    $screeningXPath = ($this->documentXPath)(app(InitialScreeningFormDocumentService::class)->generate($screening));
    expect($screeningXPath->query('//w:p[contains(string(.), "Revised with Major Changes")]//w:checked[@w:val="1"]')->length)->toBe(1)
        ->and($screeningXPath->query('//w:checkBox/w:checked[@w:val="1"]')->length)->toBe($level === null ? 1 : 2);
    $view = $this->view('faculty.initial-screening-form.preview', ['screeningForm' => $screening]);
    if ($level !== null) {
        $view->assertSee('data-screening-level="'.$level.'"', false);
    } else {
        $view->assertDontSee('data-screening-level=', false);
    }
})->with(['Central Agency' => ['central_agency'], 'Constituent Campus' => ['constituent_campus'], 'Unselected' => [null]]);

test('verified document checklist crosses appear in both Word and HTML', function (bool $completeDocuments, bool $screeningForm) {
    $proposal = DetailedProposalData::fromValidated([], [], [
        'complete_documents' => $completeDocuments,
        'initial_screening_form' => $screeningForm,
    ]);
    $xpath = ($this->documentXPath)(app(DetailedProposalDocumentService::class)->generate($proposal));
    $view = $this->view('faculty.detailed-proposals.preview', ['detailedProposal' => $proposal]);
    foreach (['Complete Documents' => $completeDocuments, 'Initial Screening Form' => $screeningForm] as $label => $selected) {
        $checkbox = $xpath->query('//w:p[contains(string(.), "'.$label.'")]//w14:checkbox')->item(0);
        expect($checkbox)->not->toBeNull()
            ->and($xpath->evaluate('string(./w14:checked/@w14:val)', $checkbox))->toBe($selected ? '1' : '0')
            ->and($xpath->evaluate('string(ancestor::w:sdt[1]/w:sdtContent//w:t)', $checkbox))->toBe($selected ? '☒' : '☐');
        $view->assertSeeText(($selected ? '☒' : '☐').' '.$label);
    }
})->with([[true, true], [false, true], [true, false], [false, false]]);

test('legacy checkbox selections can be changed without adding duplicate boxes or losing labels', function () {
    $xpath = ($this->documentXPath)(app(InitialScreeningFormDocumentService::class)->generate([
        'project_title' => 'Coastal Research', 'project_leader' => 'Researcher',
    ]));
    $checkbox = $xpath->query('//w:checkBox')->item(0);
    foreach ([false, true, false] as $selected) {
        WordCheckbox::setLegacy($xpath, $checkbox, $selected);
        $paragraph = $xpath->query('ancestor::w:p[1]', $checkbox)->item(0);
        expect($xpath->query('.//w:checkBox', $paragraph)->length)->toBe(1)
            ->and($xpath->query('.//w:t[text()="☒" or text()="☐"]', $paragraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(./w:checked/@w:val)', $checkbox))->toBe($selected ? '1' : '0')
            ->and($paragraph->textContent)->toContain('First Submission');
    }
});

test('SDG selections use cross glyphs and leave every other box empty', function () {
    $proposal = DetailedProposalData::fromValidated(['sdgs' => [4, 13, 17]], ['level_of_call' => null]);
    $xpath = ($this->documentXPath)(app(DetailedProposalDocumentService::class)->generate($proposal));
    $boxes = $xpath->query('//w:p[contains(string(.), "SDG")]//w14:checkbox');
    expect($boxes->length)->toBe(17)
        ->and($xpath->query('//w14:checkbox/w14:checked[@w14:val="1"]')->length)->toBe(3);
    foreach ($boxes as $box) {
        $selected = $xpath->evaluate('string(./w14:checked/@w14:val)', $box) === '1';
        expect($xpath->evaluate('string(./w14:checkedState/@w14:val)', $box))->toBe('2612')
            ->and($xpath->evaluate('string(./w14:uncheckedState/@w14:val)', $box))->toBe('2610')
            ->and($xpath->evaluate('string(ancestor::w:sdt[1]/w:sdtContent//w:t)', $box))->toBe($selected ? '☒' : '☐');
    }
});

test('CV gender selections use a cross only for the chosen option', function (string $gender) {
    $data = CurriculumVitaeData::fromValidated(['people' => [['last_name' => 'Researcher', 'first_name' => 'Faculty', 'gender' => $gender]]]);
    $xpath = ($this->documentXPath)(app(CurriculumVitaeDocumentService::class)->generate($data));
    $boxes = $xpath->query('//w14:checkbox');
    expect($boxes->length)->toBe(2);
    foreach (['male', 'female'] as $index => $value) {
        $box = $boxes->item($index);
        expect($xpath->evaluate('string(./w14:checked/@w14:val)', $box))->toBe($gender === $value ? '1' : '0')
            ->and($xpath->evaluate('string(ancestor::w:sdt[1]/w:sdtContent//w:t)', $box))->toBe($gender === $value ? '☒' : '☐');
    }
    $this->view('faculty.curriculum-vitae.preview', ['curriculumVitae' => $data])
        ->assertSeeText($gender === 'male' ? '☒ Male  ☐ Female' : ($gender === 'female' ? '☐ Male  ☒ Female' : '☐ Male  ☐ Female'));
})->with(['male', 'female', '']);
