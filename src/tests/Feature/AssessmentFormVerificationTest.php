<?php

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\GADChecklistDocumentService;
use App\Services\InitialScreeningFormDocumentService;
use App\Support\InitialScreeningSubmissionOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::set('local', Storage::fake('assessment-form-verification-'.getmypid()));
    $this->withoutVite();
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    config(['services.openrouter.key' => null, 'services.gemini.key' => null]);
    foreach (['faculty', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->owner = User::factory()->create();
    $this->owner->assignRole('faculty');
    $this->topic = TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Coastal Form Verification', 'status' => TopicProposal::STATUS_GAD_REVIEW]);
    $this->version = $this->topic->versions()->create([
        'version_number' => 1, 'title' => $this->topic->title, 'submitted_by' => $this->owner->id,
        'submission_type' => 'initial', 'file_path' => 'original.pdf', 'original_filename' => 'original.pdf', 'mime_type' => 'application/pdf',
    ]);
    foreach (['gad_checklist', 'initial_screening_form'] as $type) {
        Storage::disk('local')->put('original/'.$type.'.pdf', '%PDF-1.4 original');
        $this->version->files()->create([
            'document_type' => $type, 'file_path' => 'original/'.$type.'.pdf',
            'original_filename' => $type.'.pdf', 'mime_type' => 'application/pdf',
            'source_data' => ['project_title' => $this->topic->title],
        ]);
    }
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
});

function assessmentFormText(string $type, string $title = 'Coastal Form Verification'): string
{
    return $type === 'gad_checklist'
        ? 'Research Project Title: '.$title.' Assessment of Gender-Responsiveness of Program/Project Design Box 7a. Generic Checklist Involvement of women and men Collection of sex-disaggregated data Conduct of gender analysis TOTAL GAD SCORE FOR THE PROJECT IDENTIFICATION AND DESIGN STAGES 12.32 Checked and verified by: Verifier'
        : 'BatStateU-FO-RES-03 INITIAL SCREENING FORM Research Project Title: '.$title.' Project Leader: Lead Faculty Order of Submission Checklist of Submitted Documents Level of Call Narrative Evaluation: Clarify the sampling plan. Prepared by: Co-Evaluator';
}

function assessmentFormPayload(ProposalVersionFile $source, array $extra = []): array
{
    return [
        '_token' => csrf_token(),
        'source_file_id' => $source->id, 'return_to_review' => true,
        'review_file' => UploadedFile::fake()->create('completed.pdf', 100, 'application/pdf'),
        ...($source->document_type === 'gad_checklist'
            ? ['purpose' => 'gad_assessment', 'gad_score' => 12, 'gad_signature_confirmed' => true]
            : ['purpose' => 'evaluation', 'co_evaluator_name' => 'Dr. Santos', 'recommended_action' => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 'narrative_evaluation' => 'Clarify the sampling plan.', 'narrative_evaluation_confirmed' => true]),
        ...$extra,
    ];
}

function allowScreeningVerification(TopicProposal $topic): void
{
    $version = $topic->latestVersion()->firstOrFail();
    $source = $version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    Storage::disk('local')->put('previous/gad.pdf', '%PDF-1.4 passing GAD');
    $version->files()->create([
        'document_type' => 'head_upload', 'source_version_file_id' => $source->id,
        'file_path' => 'previous/gad.pdf', 'original_filename' => 'passing-gad.pdf', 'mime_type' => 'application/pdf',
        'source_data' => ['purpose' => 'gad_assessment', 'target_document_type' => 'gad_checklist', 'gad_score' => 12, 'gad_outcome' => 'passed', 'gad_signature_confirmed' => true],
    ]);
}

test('assessment PDF and official DOCX uploads are checked and retain their review data', function (string $type, string $format) {
    if ($type === 'initial_screening_form') {
        allowScreeningVerification($this->topic);
    }
    $source = $this->version->files()->where('document_type', $type)->firstOrFail();
    Process::fake(['*' => assessmentFormText($type)]);
    $payload = assessmentFormPayload($source);
    if ($format === 'docx') {
        $data = ['project_title' => $this->topic->title, 'project_leader' => $this->owner->name, 'verifier_name' => config('gad_checklist.verifier.name'), 'verifier_role' => config('gad_checklist.verifier.role')];
        $contents = $type === 'gad_checklist'
            ? app(GADChecklistDocumentService::class)->generate($data)
            : app(InitialScreeningFormDocumentService::class)->generate($data);
        $payload['review_file'] = UploadedFile::fake()->createWithContent('completed.docx', $contents);
    }
    $this->post(route('topics.head-uploads.store', $this->topic), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow');
    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray([
        'status' => 'matched', 'method' => $format.'_text', 'document_type' => $type, 'checked_by' => $this->head->id, 'project_title' => $this->topic->title,
    ]);
    expect($type === 'gad_checklist' ? $upload->source_data['gad_signature_confirmed'] : $upload->source_data['narrative_evaluation_confirmed'])->toBeTrue();
    $this->get(route('topics.head-uploads.index', $this->topic))->assertOk()->assertSee('Form and project title matched');
})->with(['gad_checklist', 'initial_screening_form'])->with(['pdf', 'docx']);

test('wrong assessment forms remain rejected', function (string $type, bool $signingDemoMode) {
    $this->app->detectEnvironment(fn (): string => 'local');
    config(['proposal_signing.demo_mode' => $signingDemoMode]);
    if ($type === 'initial_screening_form') {
        allowScreeningVerification($this->topic);
    }
    $source = $this->version->files()->where('document_type', $type)->firstOrFail();
    Process::fake(['*' => assessmentFormText($type === 'gad_checklist' ? 'initial_screening_form' : 'gad_checklist')]);
    $fileCount = $this->version->files()->count();
    $storedFiles = Storage::disk('local')->allFiles();
    $this->postJson(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source, ['assessment_form_manually_confirmed' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('review_file')->assertJsonPath('requires_manual_review', false);
    expect($this->version->files()->count())->toBe($fileCount)
        ->and(Storage::disk('local')->allFiles())->toBe($storedFiles)
        ->and($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW);
})->with([
    'Wrong GAD form' => ['gad_checklist', false],
    'Wrong Initial Screening form' => ['initial_screening_form', false],
    'Wrong GAD form during signing demo' => ['gad_checklist', true],
    'Wrong Initial Screening form during signing demo' => ['initial_screening_form', true],
]);

test('a recognized Initial Screening form accepts title differences and arbitrary filenames while reading comments automatically', function (string $text) {
    allowScreeningVerification($this->topic);
    $source = $this->version->files()->where('document_type', 'initial_screening_form')->firstOrFail();
    Process::fake(['*' => $text]);
    $payload = assessmentFormPayload($source, [
        'review_file' => UploadedFile::fake()->create('review-final-copy-2.pdf', 100, 'application/pdf'),
    ]);
    unset($payload['narrative_evaluation'], $payload['narrative_evaluation_confirmed']);
    $this->post(route('topics.head-uploads.store', $this->topic), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow');

    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray([
        'status' => 'form_matched', 'method' => 'pdf_text', 'reason' => 'project_title_mismatch',
        'checked_by' => $this->head->id, 'project_title' => $this->topic->title,
    ])->and($upload->original_filename)->toBe('review-final-copy-2.pdf')
        ->and($upload->source_data['co_evaluator_name'])->toBe('Dr. Santos')
        ->and($upload->source_data['recommended_action'])->toBe(InitialScreeningSubmissionOrder::FOR_ENDORSEMENT)
        ->and($upload->source_data['narrative_evaluation'])->toBe('Clarify the sampling plan.')
        ->and($upload->source_data['narrative_evaluation_entry_method'])->toBe('automatic');
    Storage::disk('local')->assertExists($upload->file_path);
    $this->get(route('topics.head-uploads.index', $this->topic))->assertOk()
        ->assertSee('Initial Screening Form identified')
        ->assertDontSee('Scanned signed form (alternative)')
        ->assertDontSee('Transcribe the Narrative Evaluation');
})->with([
    'Minor wording change' => [assessmentFormText('initial_screening_form', 'Coastal Form Verification Study')],
    'OCR title error' => [assessmentFormText('initial_screening_form', 'CoastaI Form Verificatlon')],
    'Unrecognized title field' => [str_replace('Research Project Title:', 'Title of Research:', assessmentFormText('initial_screening_form'))],
]);

test('a recognized GAD checklist can be uploaded when its title does not match exactly', function (string $text) {
    $source = $this->version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    Process::fake(['*' => $text]);
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source, ['gad_score' => null]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow');

    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray([
        'status' => 'form_matched', 'method' => 'pdf_text', 'reason' => 'project_title_mismatch',
        'checked_by' => $this->head->id, 'project_title' => $this->topic->title,
    ])->and($upload->source_data['gad_score'])->toBe(12.32)
        ->and($upload->source_data['gad_score_entry_method'])->toBe('automatic')
        ->and($upload->source_data['gad_signature_confirmed'])->toBeTrue();
    Storage::disk('local')->assertExists($upload->file_path);
    $this->get(route('topics.head-uploads.index', $this->topic))->assertOk()
        ->assertSee('GAD Checklist identified; project confirmed by uploader')
        ->assertDontSee('Form and project title matched');
})->with([
    'Minor wording change' => [assessmentFormText('gad_checklist', 'Coastal Form Verification Study')],
    'OCR title error' => [assessmentFormText('gad_checklist', 'CoastaI Form Verificatlon')],
    'Unrecognized title field' => [str_replace('Research Project Title:', 'Title of Research:', assessmentFormText('gad_checklist'))],
]);

test('a completed GAD DOCX with a title variation uses the same uploader confirmation', function () {
    $source = $this->version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    $contents = app(GADChecklistDocumentService::class)->generate([
        'project_title' => 'Coastal Form Verification Study', 'project_leader' => $this->owner->name,
        'verifier_name' => config('gad_checklist.verifier.name'), 'verifier_role' => config('gad_checklist.verifier.role'),
    ]);
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source, [
        'review_file' => UploadedFile::fake()->createWithContent('completed.docx', $contents),
    ]))->assertSessionHasNoErrors();

    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray([
        'status' => 'form_matched', 'method' => 'docx_text', 'checked_by' => $this->head->id,
    ])->and($upload->source_data['gad_signature_confirmed'])->toBeTrue();
});

test('a completed Initial Screening DOCX accepts a title variation and reads its typed evaluation', function () {
    allowScreeningVerification($this->topic);
    $source = $this->version->files()->where('document_type', 'initial_screening_form')->firstOrFail();
    $contents = app(InitialScreeningFormDocumentService::class)->generate([
        'project_title' => 'Coastal Form Verification Study', 'project_leader' => $this->owner->name,
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'screening-title-test-');
    file_put_contents($temporaryPath, $contents);
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $document = new DOMDocument;
        expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $heading = $xpath->query('//w:p[contains(string(.), "Narrative Evaluation:")]')->item(0);
        expect($heading)->not->toBeNull();
        $run = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        $text = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $text->appendChild($document->createTextNode(' Clarify the sampling plan.'));
        $run->appendChild($text);
        $heading->appendChild($run);
        $archive->addFromString('word/document.xml', $document->saveXML());
        $archive->close();

        $payload = assessmentFormPayload($source, [
            'review_file' => UploadedFile::fake()->createWithContent('review-final-copy-2.docx', file_get_contents($temporaryPath)),
        ]);
        unset($payload['narrative_evaluation'], $payload['narrative_evaluation_confirmed']);
        $this->post(route('topics.head-uploads.store', $this->topic), $payload)->assertSessionHasNoErrors();

        $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
        expect($upload->source_data['assessment_form_verification'])->toMatchArray([
            'status' => 'form_matched', 'method' => 'docx_text', 'checked_by' => $this->head->id,
        ])->and($upload->source_data['narrative_evaluation'])->toBe('Clarify the sampling plan.')
            ->and($upload->source_data['narrative_evaluation_entry_method'])->toBe('automatic');
        Process::assertNothingRan();
    } finally {
        unlink($temporaryPath);
    }
});

test('a GAD checklist with a different title still requires the project and signature confirmation', function () {
    Process::fake(['*' => assessmentFormText('gad_checklist', 'Different extracted title')]);
    $source = $this->version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source, ['gad_signature_confirmed' => false]))
        ->assertSessionHasErrors('gad_signature_confirmed', null, 'headUpload');
    expect($this->version->files()->count())->toBe(2);
});

test('GAD scan identification does not require the reader to transcribe the project title', function (?string $detectedTitle) {
    config(['services.openrouter.key' => 'test-key']);
    Process::fake(function (PendingProcess $process) {
        if (str_contains(implode(' ', $process->command), '-png')) {
            File::put(end($process->command).'-1.png', 'scan page');
        }

        return Process::result(output: '');
    });
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'legible' => true, 'form_heading' => 'Generic Checklist', 'reference_number' => 'Box 7a',
        'field_labels' => ['Assessment of Gender-Responsiveness', 'Involvement of women and men', 'Collection of sex-disaggregated data', 'Gender analysis'],
        'project_title' => $detectedTitle,
    ])]]]])]);
    $source = $this->version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source))
        ->assertSessionHasNoErrors();
    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray([
        'status' => 'form_matched', 'method' => 'scan_reader', 'reason' => 'project_title_mismatch',
    ])->and($upload->source_data['gad_score'])->toBe(12)
        ->and($upload->source_data['gad_score_entry_method'])->toBe('manual')
        ->and($upload->source_data['gad_signature_confirmed'])->toBeTrue()
        ->and(File::glob(storage_path('app/private/signing-verification/*')))->toBeEmpty();
})->with(['Unreadable title' => [null], 'OCR title variation' => ['CoastaI Form Verificatlon']]);

test('unclear assessment scans require an explicit audited form check', function (string $type) {
    if ($type === 'initial_screening_form') {
        allowScreeningVerification($this->topic);
    }
    Process::fake(['*' => '']);
    $source = $this->version->files()->where('document_type', $type)->firstOrFail();
    $fileCount = $this->version->files()->count();
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source))
        ->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow')
        ->assertSessionHasErrors('assessment_form_manually_confirmed', null, 'headUpload');
    expect($this->version->files()->count())->toBe($fileCount);
    expect(session()->getOldInput('purpose'))->toBe($type === 'gad_checklist' ? 'gad_assessment' : 'evaluation')
        ->and(session('errors')->getBag('headUpload')->has('assessment_form_manually_confirmed'))->toBeTrue();
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('data-assessment-manual-confirmation', false)->assertSee('Preview selected form');
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source, ['assessment_form_manually_confirmed' => true]))
        ->assertSessionHasNoErrors();
    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray(['status' => 'manually_confirmed', 'method' => 'manual', 'checked_by' => $this->head->id]);
    $this->get(route('topics.head-uploads.index', $this->topic))->assertOk()->assertSee('Form manually checked by uploader');
})->with(['gad_checklist', 'initial_screening_form']);

test('the scan reader identifies GAD and Initial Screening forms from visible evidence', function (string $type) {
    if ($type === 'initial_screening_form') {
        allowScreeningVerification($this->topic);
    }
    config(['services.openrouter.key' => 'test-key']);
    Process::fake(function (PendingProcess $process) {
        if (str_contains(implode(' ', $process->command), '-png')) {
            File::put(end($process->command).'-1.png', 'scan page');
        }

        return Process::result(output: '');
    });
    $evidence = $type === 'gad_checklist'
        ? ['form_heading' => 'Generic Checklist', 'reference_number' => 'Box 7a', 'field_labels' => ['Assessment of Gender-Responsiveness', 'Involvement of women and men', 'Collection of sex-disaggregated data', 'Gender analysis']]
        : ['form_heading' => 'INITIAL SCREENING FORM', 'reference_number' => 'BatStateU-FO-RES-03', 'field_labels' => ['Order of Submission', 'Checklist of Submitted Documents', 'Level of Call', 'Narrative Evaluation']];
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([...$evidence, 'legible' => true, 'project_title' => $this->topic->title])]]]])]);
    $source = $this->version->files()->where('document_type', $type)->firstOrFail();
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source))->assertSessionHasNoErrors();
    $upload = $this->version->files()->where('source_version_file_id', $source->id)->latest('id')->firstOrFail();
    expect($upload->source_data['assessment_form_verification'])->toMatchArray(['status' => 'matched', 'method' => 'scan_reader'])
        ->and(File::glob(storage_path('app/private/signing-verification/*')))->toBeEmpty();
})->with(['gad_checklist', 'initial_screening_form']);

test('invalid assessment DOCX files cannot bypass verification', function () {
    $source = $this->version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    $payload = assessmentFormPayload($source, ['review_file' => UploadedFile::fake()->create('invalid.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), 'assessment_form_manually_confirmed' => true]);
    $this->postJson(route('topics.head-uploads.store', $this->topic), $payload)->assertUnprocessable()->assertJsonValidationErrors('review_file');
    expect($this->version->files()->count())->toBe(2);
});

test('manual form review remains available when the scanned GAD score needs transcription', function () {
    Process::fake(['*' => '']);
    $source = $this->version->files()->where('document_type', 'gad_checklist')->firstOrFail();
    $this->post(route('topics.head-uploads.store', $this->topic), assessmentFormPayload($source, ['assessment_form_manually_confirmed' => true, 'gad_score' => null]))
        ->assertSessionHasErrors('review_file', null, 'headUpload')
        ->assertSessionHas('assessment_form_manual_review', 'gad_assessment');
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('data-assessment-manual-confirmation', false);
    expect($this->version->files()->count())->toBe(2);
});
