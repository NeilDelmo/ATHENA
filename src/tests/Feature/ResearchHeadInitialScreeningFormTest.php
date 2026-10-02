<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\LibreOfficeProcess;
use App\Support\ResearchHeadScreeningData;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    foreach (['research_head', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create(['name' => 'Dr. Helena Cruz']);
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Submitted Project Leader']);
    $this->faculty->assignRole('faculty');
    $this->topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Current title', 'status' => 'pending']);
    $this->version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial',
        'title' => 'Submitted Research Title', 'estimated_budget' => 50000, 'estimated_duration_months' => 12,
        'file_path' => 'screening.pdf', 'original_filename' => 'screening.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => str_repeat('a', 64),
    ]);
    Storage::disk('local')->put('screening.pdf', 'original submitted form');
    $this->submittedForm = $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'position' => 1,
        'file_path' => 'screening.pdf', 'original_filename' => 'screening.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => str_repeat('a', 64),
        'source_data' => ['project_title' => 'Submitted Research Title', 'project_leader' => 'Submitted Project Leader'],
    ]);
    $this->payload = [
        'order_of_submission' => 'first_submission', 'level_of_call' => 'constituent_campus',
        'requested_budget' => '50000.00', 'duration_months' => 12, 'researcher_count' => 3,
        'department' => 'Computing Sciences', 'college' => 'CICS', 'campus' => 'Alangilan',
        'screening_head' => 'Dr. Helena Cruz', 'screening_center' => 'Dr. Center', 'screening_verifier' => 'Dr. Verifier',
        'documents' => collect(ResearchHeadScreeningData::DOCUMENTS)->mapWithKeys(fn (string $label, string $type): array => [$type => ['attached' => $type !== 'gad_checklist', 'pages' => $type === 'gad_checklist' ? null : 5]])->all(),
        'scores' => ['documents' => 30, 'alignment' => 15, 'content' => 40],
        'recommended_action' => 'minor_revision', 'narrative_evaluation' => "Clarify the sampling plan.\nInclude the consent procedure.",
    ];
    $this->url = fn (string $action) => route('research_head.topics.initial-screening-form.'.$action, [$this->topic, $this->version]);
    $this->documentXPath = function (string $contents): DOMXPath {
        $path = tempnam(sys_get_temp_dir(), 'screening-filled-test-');
        file_put_contents($path, $contents);
        $archive = new ZipArchive;
        try {
            expect($archive->open($path))->toBeTrue();
            $document = new DOMDocument;
            expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            return $xpath;
        } finally {
            $archive->close();
            unlink($path);
        }
    };
});

test('the Research Head fills saves and reopens screening for the submitted version without changing its package or decision', function () {
    $this->actingAs($this->head)->get(($this->url)('edit'))->assertOk()
        ->assertSee('Submitted Research Title')->assertSee('Submitted Project Leader')
        ->assertSee('Save Initial Screening Form')->assertDontSee('Download DOCX');
    $this->get(route('topics.show', $this->topic))->assertOk()
        ->assertSee('Fill Initial Screening Form')->assertSee(($this->url)('edit'), false);
    $this->put(($this->url)('update'), [...$this->payload, 'project_title' => 'Tampered title', 'saved_by' => $this->faculty->id])
        ->assertSessionHasNoErrors()->assertRedirect(($this->url)('edit'));
    $saved = $this->version->fresh()->research_head_screening;
    expect($saved['narrative_evaluation'])->toBe($this->payload['narrative_evaluation'])
        ->and($saved['screening_head'])->toBe('Asst. Prof. DJOANNA MARIE V. SALAC')
        ->and($saved['screening_verifier'])->toBe('Dr. FROILAN G. DESTREZA')
        ->and($saved['scores'])->toEqual($this->payload['scores'])
        ->and($saved['saved_by'])->toBe($this->head->id)
        ->and($saved)->not->toHaveKey('project_title')
        ->and($this->topic->fresh()->status)->toBe('pending')
        ->and($this->submittedForm->fresh()->source_data)->toBe($this->submittedForm->source_data)
        ->and(Storage::disk('local')->get('screening.pdf'))->toBe('original submitted form');
    $this->get(($this->url)('edit'))->assertOk()->assertSee('Clarify the sampling plan.')
        ->assertSee('Download DOCX')->assertSee('Open PDF for printing');
});

test('filled screening downloads put each result in its official template cell and preserve handwritten signature dates', function (string $recommendation, int $checkbox) {
    $this->actingAs($this->head)->put(($this->url)('update'), [...$this->payload, 'recommended_action' => $recommendation])->assertSessionHasNoErrors();
    $download = $this->get(($this->url)('download'))->assertOk()
        ->assertDownload('submitted-research-title-research-head-initial-screening-v1.docx');
    $xpath = ($this->documentXPath)($download->streamedContent());
    $text = $xpath->document->textContent;
    expect($text)->toContain('Submitted Research Title', 'Submitted Project Leader', '50,000.00', 'Duration (months): 12', 'Number of Researchers Involved: 3', 'Computing Sciences', 'CICS', 'Alangilan', 'Clarify the sampling plan.', 'Include the consent procedure.', 'ASST. PROF. DJOANNA MARIE V. SALAC', 'DR. CENTER', 'DR. FROILAN G. DESTREZA')
        ->not->toContain('Current title', 'Tampered title');
    foreach ([2 => '30', 4 => '15', 6 => '40', 8 => '85'] as $row => $score) {
        expect($xpath->query('//w:tbl[w:tr[1]/w:tc[1]//w:t[text()="Criteria"]]/w:tr['.$row.']/w:tc[last()]')->item(0)->textContent)->toBe($score);
    }
    expect($xpath->query('//w:tbl[w:tr[1]/w:tc[1]//w:t[text()="Particulars"]]/w:tr[2]/w:tc[2]')->item(0)->textContent)->toBe('×')
        ->and($xpath->query('//w:tbl[w:tr[1]/w:tc[1]//w:t[text()="Particulars"]]/w:tr[2]/w:tc[3]')->item(0)->textContent)->toBe('5')
        ->and($xpath->query('//w:tbl[w:tr[1]/w:tc[1]//w:t[text()="Particulars"]]/w:tr[6]/w:tc[2]')->item(0)->textContent)->toBe('');
    $boxes = $xpath->query('//w:body//w:checkBox');
    foreach ([5, 6, 7] as $index) {
        expect($xpath->query('./w:checked[@w:val="1"]', $boxes->item($index))->length)->toBe($index === $checkbox ? 1 : 0);
    }
    foreach ($xpath->query('//w:p[w:r/w:t[text()="Date Signed:"]]') as $date) {
        expect(trim($date->textContent))->toBe('Date Signed:');
    }
})->with(['endorsement' => ['for_endorsement', 5], 'minor revision' => ['minor_revision', 6], 'major revision' => ['major_revision', 7]]);

test('saved screening can be converted to a PDF using the same filled document', function () {
    $this->actingAs($this->head)->put(($this->url)('update'), $this->payload)->assertSessionHasNoErrors();
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->withArgs(function (string $contents): bool {
        $xpath = ($this->documentXPath)($contents);

        return str_contains($xpath->document->textContent, 'Clarify the sampling plan.');
    })->andReturn('%PDF-1.7 filled screening');
    $this->get(($this->url)('pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertContent('%PDF-1.7 filled screening');
});

test('the filled screening renders as a printable PDF with the configured converter', function (bool $longNarrative) {
    if (! is_file(app(LibreOfficeProcess::class)->binary())) {
        $this->markTestSkipped('The configured LibreOffice binary is unavailable.');
    }
    $payload = $this->payload;
    if ($longNarrative) {
        $payload['narrative_evaluation'] = str_repeat("Clarify the sampling plan and describe how participants will provide informed consent.\n", 50);
    }
    $this->actingAs($this->head)->put(($this->url)('update'), $payload)->assertSessionHasNoErrors();
    $pdf = $this->get(($this->url)('pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');

    if ($directory = getenv('ATHENA_SCREENING_QA_DIR')) {
        $suffix = $longNarrative ? '-long' : '';
        file_put_contents($directory.'/filled-screening'.$suffix.'.pdf', $pdf->getContent());
        file_put_contents($directory.'/filled-screening'.$suffix.'.docx', $this->get(($this->url)('download'))->streamedContent());
        if (! $longNarrative) {
            file_put_contents($directory.'/screening-editor.html', $this->get(($this->url)('edit'))->getContent());
        }
    }
})->with(['short narrative' => [false], 'long narrative' => [true]]);

test('screening rejects invalid ratings recommendations and nested values without saving', function () {
    $this->actingAs($this->head)->put(($this->url)('update'), [
        ...$this->payload, 'scores' => ['documents' => 100, 'alignment' => 0, 'content' => 40],
        'recommended_action' => 'approved', 'narrative_evaluation' => '',
        'documents' => ['detailed_proposal' => ['attached' => 1, 'pages' => -5, 'injected' => 'bad']],
    ])->assertSessionHasErrors(['scores.documents', 'scores.alignment', 'recommended_action', 'narrative_evaluation', 'documents.detailed_proposal', 'documents.detailed_proposal.pages']);
    expect($this->version->fresh()->research_head_screening)->toBeNull();
});

test('faculty and versions outside the proposal cannot access the Research Head screening form', function () {
    $this->actingAs($this->faculty);
    foreach (['edit', 'download', 'pdf'] as $action) {
        $this->get(($this->url)($action))->assertForbidden();
    }
    $this->put(($this->url)('update'), $this->payload)->assertForbidden();
    $otherTopic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Other proposal', 'status' => 'pending']);
    $this->actingAs($this->head)->get(route('research_head.topics.initial-screening-form.edit', [$otherTopic, $this->version]))->assertNotFound();
    $this->put(route('research_head.topics.initial-screening-form.update', [$otherTopic, $this->version]), $this->payload)->assertForbidden();
});

test('closed screening versions remain downloadable but cannot be overwritten', function (string $status) {
    $this->actingAs($this->head)->put(($this->url)('update'), $this->payload)->assertSessionHasNoErrors();
    $this->topic->update(['status' => $status]);
    $this->put(($this->url)('update'), [...$this->payload, 'narrative_evaluation' => 'Changed after decision'])->assertForbidden();
    $this->get(($this->url)('edit'))->assertOk()->assertSee('This version is closed for editing.')->assertDontSee('Save Initial Screening Form');
    $this->get(($this->url)('download'))->assertOk();
    expect($this->version->fresh()->research_head_screening['narrative_evaluation'])->toBe($this->payload['narrative_evaluation']);
})->with(['revision_requested', 'approved', 'rejected', 'ready_for_signature']);

test('a new version starts a separate screening form and keeps earlier results locked', function () {
    $this->actingAs($this->head)->put(($this->url)('update'), $this->payload)->assertSessionHasNoErrors();
    $newVersion = $this->version->replicate(['research_head_screening']);
    $newVersion->version_number = 2;
    $newVersion->save();
    $this->put(($this->url)('update'), $this->payload)->assertForbidden();
    $this->get(route('research_head.topics.initial-screening-form.edit', [$this->topic, $newVersion]))->assertOk()
        ->assertDontSee('Clarify the sampling plan.')->assertDontSee('Download DOCX');
    $this->get(($this->url)('download'))->assertOk();
    expect($newVersion->fresh()->research_head_screening)->toBeNull();
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Saved Initial Screening Form · Version 1');
});

test('unsaved screening cannot be downloaded as a completed form', function () {
    $this->actingAs($this->head)->get(($this->url)('download'))->assertConflict();
    $this->get(($this->url)('pdf'))->assertConflict();
});
