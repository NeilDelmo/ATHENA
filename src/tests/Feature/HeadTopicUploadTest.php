<?php

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    Storage::fake('local');
    $this->withoutVite();

    $this->head = User::factory()->create(['name' => 'Research Head']);
    $this->head->assignRole('research_head');

    $this->faculty = User::factory()->create(['name' => 'Lead Faculty']);
    $this->faculty->assignRole('faculty');

    $this->call = ResearchCall::create([
        'title' => 'Open Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);

    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Coastal Habitat Restoration',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'status' => 'pending',
    ]);

    $this->version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'packages/coastal-habitat-restoration.pdf',
        'original_filename' => 'coastal-habitat-restoration.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'checksum' => str_repeat('a', 64),
        'title' => $this->topic->title,
        'estimated_budget' => $this->topic->estimated_budget,
        'estimated_duration_months' => $this->topic->estimated_duration_months,
    ]);

    foreach ([
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        ProposalVersionFile::TYPE_CURRICULUM_VITAE,
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
    ] as $position => $documentType) {
        $path = "packages/{$documentType}.pdf";
        Storage::disk('local')->put($path, $documentType);

        $this->version->files()->create([
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => $path,
            'original_filename' => "{$documentType}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => strlen($documentType),
            'checksum' => hash('sha256', $documentType),
            'is_carried_forward' => false,
        ]);
    }
});

test('research head workspace presents the GAD gate before central evaluation', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $workspace = $this->actingAs($this->head)
        ->get(route('topics.head-uploads.index', $this->topic));

    $workspace->assertOk()
        ->assertSee('Review PDF')
        ->assertSee('Initial review workflow')
        ->assertSee('Drop completed GAD checklist here')
        ->assertSee('Upload &amp; read score', false)
        ->assertSee('Upload a passing, signed GAD assessment to unlock central evaluation.')
        ->assertDontSee('Record evaluation')
        ->assertDontSee('Attach a reviewed copy for revision')
        ->assertDontSee('Upload reviewed copy')
        ->assertDontSee('Administrative and supplemental papers')
        ->assertDontSee('Faculty originals are always preserved.');

    expect(substr_count($workspace->getContent(), 'data-gad-checklist-dropzone'))->toBe(1)
        ->and(substr_count($workspace->getContent(), 'data-co-evaluator-screening-panel="true"'))->toBe(0);

    $response = $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
            'note' => 'Annotated for the faculty revision.',
        ]);

    $response->assertRedirect(route('topics.head-uploads.index', $this->topic))
        ->assertSessionHasErrors(['purpose'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0)
        ->and($this->topic->reviews()->where('decision', 'head_upload')->count())->toBe(0);
});

test('research head can upload a completed GAD checklist and extract its final score', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-gad-test-');
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
        $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:r><w:t>Project Identification and Design Stages</w:t></w:r></w:p>
<w:p><w:r><w:t>TOTAL GAD SCORE FOR THE PROJECT IDENTIFICATION AND DESIGN STAGES</w:t></w:r></w:p>
<w:p><w:r><w:t>12.32</w:t></w:r></w:p>
<w:p><w:r><w:t>Interpretation of GAD Scores</w:t></w:r></w:p>
<w:p><w:r><w:t>Checked and verified by:</w:t></w:r></w:p>
<w:p><w:r><w:drawing /></w:r></w:p>
<w:p><w:r><w:t>Ms. Ellaine G. Lid-Ayan</w:t></w:r></w:p>
<w:p><w:r><w:t>Head Secretariat, GAD</w:t></w:r></w:p>
</w:body></w:document>
XML);
        $archive->close();
        $contents = file_get_contents($temporaryPath);
        expect($contents)->not->toBeFalse();

        $response = $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                'source_file_id' => $gadChecklist->id,
                'review_file' => UploadedFile::fake()->createWithContent('completed-gad-checklist.docx', $contents),
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                'gad_signature_confirmed' => '1',
            ]);

        $response
            ->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
            ->assertSessionHas('success', 'Completed GAD Checklist uploaded. ATHENA extracted a Total GAD Score of 12.32 (Gender-sensitive) and recorded the verifier signature confirmation.');

        $assessment = $this->version->files()
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT)
            ->sole();

        expect($assessment->source_version_file_id)->toBe($gadChecklist->id)
            ->and($assessment->source_data['gad_score'])->toBe(12.32)
            ->and($assessment->source_data['gad_rating'])->toBe('Gender-sensitive')
            ->and($assessment->source_data['gad_interpretation'])->toBe('Proposed project is gender-sensitive (proposal passes the GAD test).')
            ->and($assessment->source_data['gad_outcome'])->toBe('passed')
            ->and($assessment->source_data['gad_signature_detected'])->toBeTrue()
            ->and($assessment->source_data['gad_signature_confirmed'])->toBeTrue()
            ->and($assessment->source_data['gad_signature_detection_method'])->toBe('embedded_signature_object');

        $workspace = $this->actingAs($this->head)
            ->get(route('topics.head-uploads.index', $this->topic));

        $workspace->assertOk()
            ->assertSee('12.32')
            ->assertSee('Gender-sensitive')
            ->assertSee('Proposed project is gender-sensitive (proposal passes the GAD test).')
            ->assertSee('Signature evidence detected in the file and confirmed after preview.')
            ->assertSee('Record evaluation')
            ->assertDontSee('data-co-evaluator-step-locked', false);

        expect(substr_count($workspace->getContent(), 'data-co-evaluator-screening-panel="true"'))->toBe(1);
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('GAD assessment upload requires the Research Head to confirm the verifier signature', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->create('completed-gad-checklist.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
        ])
        ->assertSessionHasErrors(['gad_signature_confirmed'], null, 'headUpload');

    expect($this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->count())->toBe(0);
});

test('central evaluation cannot be uploaded before a passing GAD assessment', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $initialScreening = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)
        ->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $initialScreening->id,
            'review_file' => UploadedFile::fake()->create('completed-initial-screening.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
        ])
        ->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('a non-passing GAD result returns the proposal to revision and keeps central evaluation locked', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();
    $initialScreening = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)
        ->sole();

    $this->version->files()->create([
        'source_version_file_id' => $gadChecklist->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 89,
        'file_path' => 'head-uploads/previous-passing-gad.pdf',
        'original_filename' => 'previous-passing-gad.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('e', 64),
        'uploaded_by' => $this->head->id,
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 12.5,
            'gad_rating' => 'Gender-sensitive',
            'gad_outcome' => 'passed',
            'gad_signature_confirmed' => true,
        ],
    ]);

    $this->version->files()->create([
        'source_version_file_id' => $gadChecklist->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => 'head-uploads/conditional-gad.pdf',
        'original_filename' => 'conditional-gad.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('f', 64),
        'uploaded_by' => $this->head->id,
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 6.5,
            'gad_rating' => 'Promising GAD prospects',
            'gad_outcome' => 'conditional_pass',
            'gad_signature_confirmed' => true,
        ],
    ]);

    $this->actingAs($this->head)
        ->get(route('topics.head-uploads.index', $this->topic))
        ->assertOk()
        ->assertSee('Return for revision')
        ->assertSee('This result cannot proceed to central evaluation.')
        ->assertSee('The GAD result requires a faculty revision before central evaluation.')
        ->assertDontSee('data-co-evaluator-screening-panel="true"', false);

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $initialScreening->id,
            'review_file' => UploadedFile::fake()->create('central-evaluation.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
        ])
        ->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->hasPassingGadAssessment())->toBeFalse()
        ->and($this->version->files()
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->count())->toBe(0);
});

test('research head can upload a completed Initial Screening Form and extract its Narrative Evaluation', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();
    $initialScreening = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)
        ->sole();
    $gadPath = 'head-uploads/completed-gad-checklist.pdf';
    Storage::disk('local')->put($gadPath, 'completed GAD checklist');
    $this->version->files()->create([
        'source_version_file_id' => $gadChecklist->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => $gadPath,
        'original_filename' => 'completed-gad-checklist.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 23,
        'checksum' => hash('sha256', 'completed GAD checklist'),
        'uploaded_by' => $this->head->id,
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 12.32,
            'gad_rating' => 'Gender-sensitive',
            'gad_interpretation' => 'Proposed project is gender-sensitive (proposal passes the GAD test).',
            'gad_outcome' => 'passed',
            'gad_signature_confirmed' => true,
        ],
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-screening-test-');
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
        $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:r><w:t>Narrative Evaluation:</w:t></w:r></w:p>
<w:p><w:r><w:t>The objectives are relevant, but the sampling plan must explain how participants will be selected.</w:t></w:r></w:p>
<w:p><w:r><w:t>Prepared by:</w:t></w:r></w:p>
<w:p><w:r><w:t>Dr. Maria Santos</w:t></w:r></w:p>
</w:body></w:document>
XML);
        $archive->close();
        $contents = file_get_contents($temporaryPath);
        expect($contents)->not->toBeFalse();

        $response = $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                'source_file_id' => $initialScreening->id,
                'review_file' => UploadedFile::fake()->createWithContent('completed-initial-screening.docx', $contents),
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
                'co_evaluator_name' => 'Dr. Maria Santos',
            ]);

        $response->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
            ->assertSessionHas('success', 'Completed Initial Screening Form uploaded. Its Narrative Evaluation was recorded for the central evaluator response.');

        $evaluation = $this->version->files()
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->sole();
        expect($evaluation->source_version_file_id)->toBe($initialScreening->id)
            ->and($evaluation->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->and($evaluation->source_data['co_evaluator_name'])->toBe('Dr. Maria Santos')
            ->and($evaluation->source_data['narrative_evaluation'])->toBe('The objectives are relevant, but the sampling plan must explain how participants will be selected.');

        $this->get(route('topics.head-uploads.index', $this->topic))
            ->assertOk()
            ->assertSee('Narrative Evaluation extracted')
            ->assertSee('The objectives are relevant, but the sampling plan must explain how participants will be selected.');
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('replacing a signed copy preserves the superseded audit record before final approval', function () {
    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$gadChecklist->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->create('signed-gad-checklist.pdf', 200, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review')
        ->assertSessionHas('success', 'Research Head file attached to the faculty submission.');

    $originalSignedCopy = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
        ->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->create('corrected-signed-gad-checklist.pdf', 220, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review')
        ->assertSessionHas('success', 'Replacement signed PDF uploaded. The previous signed copy was preserved as superseded audit history.');

    $signedCopies = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
        ->get();
    $replacementSignedCopy = $signedCopies->whereNull('superseded_at')->sole();
    $supersededSignedCopy = $signedCopies->whereNotNull('superseded_at')->sole();

    expect($signedCopies)->toHaveCount(2)
        ->and($replacementSignedCopy->id)->not->toBe($originalSignedCopy->id)
        ->and($replacementSignedCopy->original_filename)->toBe('corrected-signed-gad-checklist.pdf')
        ->and($supersededSignedCopy->id)->toBe($originalSignedCopy->id)
        ->and($supersededSignedCopy->superseded_at)->not->toBeNull()
        ->and($supersededSignedCopy->superseded_by_version_file_id)->toBe($replacementSignedCopy->id)
        ->and(Storage::disk('local')->exists($originalSignedCopy->file_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($replacementSignedCopy->file_path))->toBeTrue();
});

test('Research Head can return a signing-stage paper to revision and supersede its signed copy', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$workPlan->id],
        ])
        ->assertSessionHasNoErrors();

    $signatureReview = $this->topic->reviews()
        ->where('decision', TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('signed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertSessionHasNoErrors();

    $signedCopy = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
        ->sole();

    $workPlan->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.4, 'height' => 0.1]],
        'comment' => 'Replace the incorrect signature page.',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'revision_file_ids' => [$workPlan->id],
        ])
        ->assertSessionHas('success', 'Revision requested. Final signing is paused and existing signed copies were retained as superseded records.');

    expect($this->topic->fresh()->status)->toBe('revision_requested')
        ->and($signatureReview->fresh()->signature_superseded_at)->not->toBeNull()
        ->and($signedCopy->fresh()->superseded_at)->not->toBeNull()
        ->and(Storage::disk('local')->exists($signedCopy->file_path))->toBeTrue();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.versions.files.download', [$this->topic, $this->version, $signedCopy]))
        ->assertNotFound();
});

test('a clean proposal moves to signing before it can be approved', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Choose the next step')
        ->assertSee('Select papers → upload signed PDFs → approval unlocks')
        ->assertSee('Mark files → add highlights/comments → request revision')
        ->assertSee("x-show=\"decision === 'revision_requested'\"", false)
        ->assertSee("x-bind:disabled=\"decision !== 'revision_requested'\"", false)
        ->assertSee('@submit.prevent="submitDecision"', false)
        ->assertSee('Continue to final signing?')
        ->assertSee('Reject this proposal?')
        ->assertSee("confirmButtonColor: '#dc2626'", false)
        ->assertDontSee("confirmButtonColor: '#111827'", false)
        ->assertSee("decision === signingDecision ? 'border-red-700 bg-red-50 shadow-md shadow-red-100", false)
        ->assertSee('Which papers need a signed final PDF?')
        ->assertSee('Nothing is selected automatically.')
        ->assertDontSee('Approve — no signed copies needed')
        ->assertSee('Reject proposal')
        ->assertSee('Why is this proposal being rejected?')
        ->assertSee('I confirm that this rejection is final.')
        ->assertDontSee('Final signature required')
        ->assertDontSee('No final signature required')
        ->assertDontSee('Record note (optional)');

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'approved',
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('status');

    expect($this->topic->fresh()->status)->toBe('pending');

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'redirect_to' => 'topic',
            'signature_file_ids' => [$workPlan->id, $gadChecklist->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review')
        ->assertSessionHas('success', 'Review completed. Upload the required signed PDFs, then finalize approval.');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and($this->topic->fresh()->project_status)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Upload the selected signed PDFs')
        ->assertSee('0/2 uploaded')
        ->assertSee('Signed final PDF')
        ->assertSee('Finalize approval')
        ->assertDontSee('One clear review process')
        ->assertDontSee('Research Head workspace')
        ->assertDontSee('Review faculty files')
        ->assertDontSee('Faculty-submitted files')
        ->assertDontSee('Administrative and supplemental papers')
        ->assertDontSee('<details class="group overflow-hidden rounded-2xl border-2 border-amber-300 shadow-lg" open>', false)
        ->assertDontSee('Upload reviewed copy')
        ->assertDontSee('Record note (optional)');
});

test('final signing never assumes which papers require a signature', function () {
    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('signature_file_ids');

    expect($this->topic->fresh()->status)->toBe('pending')
        ->and($this->version->files()
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->count())->toBe(0);
});

test('signed copies are limited to signature papers in the signing stage', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    $expenseBreakdown = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN)->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('too-early.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertSessionHasErrors(['purpose'], null, 'headUpload');

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$workPlan->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $expenseBreakdown->id,
            'review_file' => UploadedFile::fake()->create('unneeded-signature.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertSessionHasErrors(['source_file_id'], null, 'headUpload');

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('signed-work-plan.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
        ->count())->toBe(0);
});

test('approval stays locked until every required signed PDF is uploaded', function () {
    $detailedProposal = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$detailedProposal->id, $workPlan->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.finalizeApproval', $this->topic))
        ->assertSessionHasErrors('status');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE);

    $requiredDocumentTypes = [
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
    ];

    foreach ($requiredDocumentTypes as $documentType) {
        $sourceFile = $this->version->files()->where('document_type', $documentType)->sole();

        $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                'source_file_id' => $sourceFile->id,
                'review_file' => UploadedFile::fake()->create("signed-{$documentType}.pdf", 100, 'application/pdf'),
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                'note' => 'Signed by the Research Head.',
            ])
            ->assertRedirect(route('topics.show', $this->topic).'#proposal-review');
    }

    $signedWorkPlan = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_version_file_id', $workPlan->id)
        ->sole();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertDontSee('signed-work_plan.pdf');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.versions.files.download', [$this->topic, $this->version, $signedWorkPlan]))
        ->assertNotFound();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->patch(route('research_head.topics.finalizeApproval', $this->topic))
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review')
        ->assertSessionHas('success', 'Signed documents finalized and released. Monitoring will open after the Notice to Proceed is issued.');

    expect($this->topic->fresh()->status)->toBe('approved')
        ->and($this->topic->fresh()->project_status)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();

    $facultyResponse = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic));

    $facultyResponse
        ->assertOk()
        ->assertSee('signed-work_plan.pdf')
        ->assertSee('Work Plan (signed official copy)');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.versions.files.download', [$this->topic, $this->version, $signedWorkPlan]))
        ->assertDownload('signed-work_plan.pdf');
});

test('research head can upload a standalone supplemental paper after faculty turn in', function () {
    $response = $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'review_file' => UploadedFile::fake()->create('regional-endorsement.pdf', 120, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL,
            'document_title' => 'Regional Endorsement Memorandum',
            'issuing_office' => 'Office of the Regional Director',
            'note' => 'Received through the Research Head for the proposal record.',
        ]);

    $response->assertRedirect(route('topics.show', $this->topic).'#proposal-review')
        ->assertSessionHas('success', 'Supplemental paper uploaded by the Research Head.');

    $supplementalPaper = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->sole();

    expect($supplementalPaper->uploaded_by)->toBe($this->head->id)
        ->and($supplementalPaper->source_version_file_id)->toBeNull()
        ->and($supplementalPaper->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL)
        ->and($supplementalPaper->source_data['document_title'])->toBe('Regional Endorsement Memorandum')
        ->and($supplementalPaper->source_data['issuing_office'])->toBe('Office of the Regional Director')
        ->and(Storage::disk('local')->exists($supplementalPaper->file_path))->toBeTrue();

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Supplemental records')
        ->assertSee('data-supplemental-paper-dropzone', false)
        ->assertDontSee('Administrative and supplemental papers')
        ->assertDontSee('data-supplemental-papers-disclosure', false)
        ->assertSee('Regional Endorsement Memorandum')
        ->assertSee('Office of the Regional Director');
});

test('faculty cannot attach a signed copy through the research head upload endpoint', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->faculty)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('rogue.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertForbidden();

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload requires an exact faculty file from the latest version', function () {
    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => 999999,
            'review_file' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
        ])
        ->assertSessionHasErrors(['source_file_id'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload rejects unsupported file types and oversize files', function () {
    $initialScreening = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)
        ->sole();

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $initialScreening->id,
            'review_file' => UploadedFile::fake()->create('completed-evaluation.txt', 100, 'text/plain'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $initialScreening->id,
            'review_file' => UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('the proposal review still shows historical Research Head revision copies', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    $path = 'head-uploads/reviewed-work-plan.pdf';
    Storage::disk('local')->put($path, 'legacy reviewed work plan');

    $this->version->files()->create([
        'source_version_file_id' => $workPlan->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => $path,
        'original_filename' => 'reviewed-work-plan.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 25,
        'checksum' => hash('sha256', 'legacy reviewed work plan'),
        'uploaded_by' => $this->head->id,
        'source_data' => [
            'source_version_file_id' => $workPlan->id,
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
            'note' => 'Use these annotations for the next revision.',
        ],
    ]);

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertOk()
        ->assertSee('Review & decision')
        ->assertSee('data-review-decision-disclosure', false)
        ->assertSee('aria-controls="review-decision-content"', false)
        ->assertSee('Research Head documents')
        ->assertSee('data-review-documents-disclosure', false)
        ->assertSee('reviewed-work-plan.pdf')
        ->assertSee('Work Plan (for revision)');
});

test('the second proposal tab matches the active workspace', function () {
    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Review & decision')
        ->assertSee("@click=\"setTopicTab('review', 'proposal-review')\"", false)
        ->assertDontSee('Review status');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Review status')
        ->assertSee("@click=\"setTopicTab('review', 'proposal-review')\"", false)
        ->assertDontSee('Review & decision');
});

test('a dual-role proposal owner only sees the faculty revision module in a faculty workspace', function () {
    $this->head->assignRole('faculty');
    $this->topic->update([
        'user_id' => $this->head->id,
        'status' => 'revision_requested',
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Review & decision')
        ->assertDontSee('id="submit-revision"', false);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Review status')
        ->assertSee('Submit your revision')
        ->assertSee('id="submit-revision"', false);
});

test('the shared document list records Research Head uploads', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$workPlan->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('signed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            'note' => 'Co-signed on 2026-07-21.',
        ]);

    $signedWorkPlan = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
        ->sole();

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertSee('signed-work-plan.pdf')
        ->assertSee('Work Plan (signed official copy)')
        ->assertSee('Uploaded signed copy')
        ->assertSee('Preview signed PDF')
        ->assertSee('Signed PDF preview')
        ->assertSee('data-signed-copy-preview', false)
        ->assertSee(route('topics.versions.files.view', [$this->topic, $this->version, $signedWorkPlan]));
});
