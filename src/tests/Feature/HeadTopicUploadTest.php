<?php

use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFeedback;
use App\Support\InitialScreeningSubmissionOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
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

test('research head workspace presents the GAD gate before co-evaluator review', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $workspace = $this->actingAs($this->head)
        ->get(route('topics.head-uploads.index', $this->topic));

    $workspace->assertOk()
        ->assertSee('Back to submitted proposal')
        ->assertSee('data-fixed-back-link', false)
        ->assertSee('fixed bottom-4 right-4 z-40', false)
        ->assertSee('Review progress')
        ->assertSee('data-horizontal-stepper', false)
        ->assertSee('data-route-step', false)
        ->assertDontSee('PROPOSAL ROUTING DOCKET')
        ->assertSee('data-current-review-controls="gad"', false)
        ->assertSee('Drop completed GAD checklist here')
        ->assertSee('Upload &amp; read score', false)
        ->assertDontSee('data-co-evaluator-screening-panel', false)
        ->assertSee('Enter score only if automatic reading fails')
        ->assertSee('Leave this blank first. If ATHENA cannot read the score, enter the final score printed on the completed checklist and upload it again.')
        ->assertDontSee('Score shown on a scanned PDF')
        ->assertSee('Hide workflow')
        ->assertSee('data-review-workflow-toggle', false)
        ->assertSee('Open project folder')
        ->assertSee('data-project-documents-inline-trigger', false)
        ->assertDontSee('data-project-documents-floating-trigger', false)
        ->assertDontSee('Review faculty files')
        ->assertDontSee('Faculty-submitted files')
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

test('queued LREC review uses the floating project folder without the old file review list', function () {
    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_QUEUED,
        'review_stage' => 'lrec',
    ]);
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-project-documents-floating-trigger', false)
        ->assertSee('data-project-document-drawer', false)
        ->assertSee('data-project-document-key="proposal-version-file-'.$workPlan->id.'"', false)
        ->assertSee('data-lrec-waiting-workspace', false)
        ->assertSee('LREC review progress')
        ->assertSee('Committee outcome')
        ->assertSee('Presentation complete — record outcome')
        ->assertSee('Open project folder')
        ->assertDontSee('data-research-head-file-workspace', false)
        ->assertDontSee('Faculty-submitted files')
        ->assertDontSee('No further decision is available.')
        ->assertDontSee('Review PDF')
        ->assertDontSee('View PDF');
});

test('submitted proposal summary distinguishes reviewable papers from automatic assessment forms', function () {
    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('5 proposal papers and 2 automatically generated assessment forms')
        ->assertSee('Proposal papers for review')
        ->assertSee('The generated GAD and screening forms are in the project folder');
});

test('LREC review does not treat an earlier stage revision as a current returned paper', function () {
    $originalDetailedProposal = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
    $revisedVersion = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 2,
        'submission_type' => 'revision',
        'file_path' => 'packages/revised-proposal.pdf',
        'original_filename' => 'revised-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'title' => $this->topic->title,
    ]);
    $revisedDetailedProposal = $revisedVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $originalDetailedProposal->file_path,
        'original_filename' => $originalDetailedProposal->original_filename,
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ]);
    $revisedWorkPlan = $revisedVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 1,
        'file_path' => 'packages/work_plan.pdf',
        'original_filename' => 'work_plan.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ]);
    $initialReview = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => 'revision_requested',
        'review_stage' => 'initial',
    ]);
    $initialReview->fileRevisions()->create([
        'proposal_version_file_id' => $originalDetailedProposal->id,
        'resolved_by_version_file_id' => $revisedDetailedProposal->id,
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'original_filename' => $originalDetailedProposal->original_filename,
        'resolution_type' => 'no_file_change',
        'faculty_response' => 'The earlier methodology note was addressed.',
        'resolved_at' => now(),
    ]);
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Proposal papers for review')
        ->assertSee('Version 2')
        ->assertDontSee('Papers returned for review')
        ->assertDontSee('Faculty responded without replacing this paper')
        ->assertDontSee('Show other submitted papers');

    $lrecReview = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => 'revision_requested',
        'review_stage' => 'lrec',
    ]);
    $lrecReview->fileRevisions()->create([
        'proposal_version_file_id' => $revisedWorkPlan->id,
        'resolved_by_version_file_id' => $revisedWorkPlan->id,
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'original_filename' => $revisedWorkPlan->original_filename,
        'resolution_type' => 'no_file_change',
        'faculty_response' => 'The LREC schedule question was answered.',
        'resolved_at' => now(),
    ]);

    $response = $this->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Papers returned for review (1)')
        ->assertSee('The LREC schedule question was answered.')
        ->assertSee('The earlier methodology note was addressed.');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $fileList = (new DOMXPath($document))->query('//*[@data-revision-file-list]')->item(0);

    expect($fileList)->not->toBeNull()
        ->and($fileList->textContent)->toContain('The LREC schedule question was answered.')
        ->not->toContain('The earlier methodology note was addressed.');
});

test('the review page reveals controls only for the active stage', function (string $status, string $reviewStage, bool $passingGad, ?string $coEvaluatorAction, int $currentStep, ?string $activeControls, bool $canSendToLrec) {
    $this->topic->update(['status' => $status, 'review_stage' => $reviewStage]);

    if ($passingGad) {
        $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();
        $this->version->files()->create([
            'source_version_file_id' => $gadChecklist->id,
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 90,
            'file_path' => 'head-uploads/completed-gad.pdf',
            'original_filename' => 'completed-gad.pdf',
            'mime_type' => 'application/pdf',
            'source_data' => [
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
                'gad_score' => 12,
                'gad_outcome' => 'passed',
                'gad_signature_confirmed' => true,
            ],
        ]);
    }

    if ($coEvaluatorAction !== null) {
        $screeningForm = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)->sole();
        $this->version->files()->create([
            'source_version_file_id' => $screeningForm->id,
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 91,
            'file_path' => 'head-uploads/completed-screening.pdf',
            'original_filename' => 'completed-screening.pdf',
            'mime_type' => 'application/pdf',
            'source_data' => [
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
                'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
                'narrative_evaluation' => 'The proposed methodology has been evaluated.',
                'recommended_action' => $coEvaluatorAction,
            ],
        ]);
    }

    $fileCount = $this->version->files()->count();
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk()
        ->assertDontSee('REVIEW ROUTING')
        ->assertDontSee('Clear each office in order')
        ->assertDontSee('The GAD Office reviews first.')
        ->assertDontSee('Required next')
        ->assertDontSee('Add supplemental paper')
        ->assertDontSee('Supporting documents · Optional')
        ->assertDontSee('Add supporting document')
        ->assertDontSee('data-supplemental-paper-dropzone', false)
        ->assertDontSee('Complete the ordered review route');

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $reviewTab = '//*[@id="proposal-review-tab"]';

    expect($xpath->query('//*[@data-horizontal-stepper]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-route-step]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-route-step][@aria-current="step"]')->item(0)->getNodePath())
        ->toBe($xpath->query('//*[@data-route-step]')->item($currentStep - 1)->getNodePath())
        ->and($xpath->query($reviewTab.'//summary')->length)->toBe(0)
        ->and($xpath->query($reviewTab.'//*[@data-decision-history]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="version-history-tab"]//*[@data-decision-history]')->length)->toBe(1)
        ->and($xpath->query($reviewTab.'//*[@data-co-evaluator-screening-panel]')->length)->toBe($activeControls === 'co-evaluator' ? 1 : 0)
        ->and($xpath->query($reviewTab.'//input[@name="status"][@value="lrec_queued"]')->length)->toBe($canSendToLrec ? 1 : 0);

    if ($activeControls === null) {
        expect($xpath->query($reviewTab.'//*[@data-current-review-controls]')->length)->toBe(0)
            ->and($xpath->query($reviewTab.'//*[@data-gad-checklist-dropzone]')->length)->toBe(0);
    } else {
        expect($xpath->query($reviewTab.'//*[@data-current-review-controls="'.$activeControls.'"]')->length)->toBe(1);
        if ($activeControls === 'co-evaluator') {
            expect($xpath->query($reviewTab.'//*[@data-current-review-controls]/section[@data-gad-review-card]')->length)->toBe(1)
                ->and($xpath->query($reviewTab.'//*[@data-current-review-controls]/section[@data-co-evaluator-review-card]')->length)->toBe(1)
                ->and($xpath->query($reviewTab.'//*[@data-gad-review-card][@data-initially-expanded="false"]')->length)->toBe(1)
                ->and($xpath->query($reviewTab.'//*[@data-co-evaluator-review-card][@data-initially-expanded="'.($coEvaluatorAction === null ? 'true' : 'false').'"]')->length)->toBe(1)
                ->and($xpath->query($reviewTab.'//*[@data-co-evaluator-screening-panel]//*[@data-co-evaluator-details]/fieldset/div/label')->length)->toBe(3)
                ->and($xpath->query($reviewTab.'//*[@data-co-evaluator-screening-panel]//input[@name="recommended_action"][@type="radio"]')->length)->toBe(3)
                ->and($xpath->query($reviewTab.'//*[@data-co-evaluator-screening-panel]/div[@data-co-evaluator-dropzone]')->length)->toBe(1);
        } else {
            expect($xpath->query($reviewTab.'//*[@data-gad-review-card][@data-initially-expanded="true"]')->length)->toBe(1);
        }
    }

    if ($status === TopicProposal::STATUS_GAD_REVIEW) {
        expect($xpath->query($reviewTab.'//*[@data-review-decision-disclosure]')->length)->toBe($coEvaluatorAction === null ? 0 : 1);
    }

    if (in_array($status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision', TopicProposal::STATUS_GAD_REVIEW, TopicProposal::STATUS_LREC_REVIEW], true)) {
        $preview = $xpath->query($reviewTab.'//*[@data-review-feedback-preview]/button[@data-comment-response-preview-button]');
        expect($preview->length)->toBe(1)
            ->and($preview->item(0)->hasAttribute('x-show'))->toBeFalse()
            ->and($xpath->query($reviewTab.'//a[contains(@href, "draft_version=")]')->length)->toBe(0);
    }

    expect($this->topic->fresh()->status)->toBe($status)
        ->and($this->version->files()->count())->toBe($fileCount)
        ->and($this->topic->reviews()->count())->toBe(0);
})->with([
    'new submission' => ['pending', 'initial', false, null, 1, null, false],
    'faculty resubmission' => ['resubmitted', 'initial', false, null, 1, null, false],
    'legacy active review' => ['expert_review', 'initial', false, null, 1, null, false],
    'legacy final decision' => ['for_final_decision', 'initial', false, null, 1, null, false],
    'waiting for Faculty' => ['revision_requested', 'initial', false, null, 1, null, false],
    'legacy assessments cannot bypass Head clearance' => ['pending', 'initial', true, InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 1, null, false],
    'GAD active' => [TopicProposal::STATUS_GAD_REVIEW, 'gad', false, null, 2, 'gad', false],
    'co-evaluator active' => [TopicProposal::STATUS_GAD_REVIEW, 'gad', true, null, 3, 'co-evaluator', false],
    'co-evaluator cleared awaits explicit LREC routing' => [TopicProposal::STATUS_GAD_REVIEW, 'gad', true, InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 3, 'co-evaluator', true],
    'co-evaluator requests revision' => [TopicProposal::STATUS_GAD_REVIEW, 'gad', true, InitialScreeningSubmissionOrder::MAJOR_REVISION, 3, 'co-evaluator', false],
    'awaiting presentation' => [TopicProposal::STATUS_LREC_QUEUED, 'lrec', true, InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 4, null, false],
    'LREC active' => [TopicProposal::STATUS_LREC_REVIEW, 'lrec', true, InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 4, null, false],
    'signing active' => [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'lrec', true, InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 5, null, false],
]);

test('other submitted paper groups use buttons without native triangle disclosures', function (bool $hasSavedComment) {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => 'revision_requested',
        'review_stage' => 'initial',
    ]);
    $review->fileRevisions()->create([
        'proposal_version_file_id' => $workPlan->id,
        'resolved_by_version_file_id' => $workPlan->id,
        'document_type' => $workPlan->document_type,
        'original_filename' => $workPlan->original_filename,
        'resolution_type' => 'no_file_change',
        'faculty_response' => 'The existing schedule already covers the requested period.',
        'resolved_at' => now(),
    ]);
    $this->topic->update(['status' => 'resubmitted']);

    if ($hasSavedComment) {
        $paper = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
        $paper->annotations()->create([
            'reviewer_id' => $this->head->id,
            'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
            'page_number' => 1,
            'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.04]],
            'comment' => 'Clarify the methodology.',
        ]);
    }

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $otherPapers = '//*[@data-other-submitted-papers]';

    expect($xpath->query('//*[@id="proposal-review-tab"]//summary')->length)->toBe(0)
        ->and($xpath->query($otherPapers.'/button[@type="button"][@aria-controls="other-submitted-papers-'.$this->version->id.'"]')->length)->toBe(1)
        ->and($xpath->query($otherPapers.'/*[@x-show="otherPapersOpen"]/ul/li')->length)->toBe(4)
        ->and($xpath->query($otherPapers.'/*[@x-show="otherPapersOpen"]')->item(0)->hasAttribute('x-cloak'))->toBe(! $hasSavedComment);
})->with([false, true]);

test('research head can upload a completed GAD checklist and extract its final score', function (bool $returnToReview) {
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
                'return_to_review' => $returnToReview,
            ]);

        $response
            ->assertRedirect(route($returnToReview ? 'topics.show' : 'topics.head-uploads.index', $this->topic).'#initial-review-workflow')
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
            ->and($assessment->source_data['gad_score_entry_method'])->toBe('automatic')
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
})->with(['documents page' => false, 'review tab' => true]);

test('GAD upload errors keep the Research Head on the original review tab', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();
    $this->actingAs($this->head)->post(route('topics.head-uploads.store', $this->topic), [
        'source_file_id' => $gadChecklist->id,
        'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
        'return_to_review' => true,
    ])->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow')
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');
    $this->get(route('topics.show', $this->topic))->assertOk()
        ->assertSee('data-review-workflow-toggle', false)
        ->assertSee('name="return_to_review" value="1"', false)
        ->assertSee("'#initial-review-workflow'", false);
});

test('research head can confirm the score from a phone-scanned GAD checklist', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();

    $response = $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->createWithContent('phone-scanned-gad-checklist.pdf', "%PDF-1.4\nimage-only scan"),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => '9.25',
            'gad_signature_confirmed' => '1',
        ]);

    $response
        ->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
        ->assertSessionHas('success', 'Completed GAD Checklist uploaded. ATHENA recorded a confirmed Total GAD Score of 9.25 (Gender-sensitive) and recorded the verifier signature confirmation.');

    $assessment = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT)
        ->sole();

    expect($assessment->source_data['gad_score'])->toBe(9.25)
        ->and($assessment->source_data['gad_rating'])->toBe('Gender-sensitive')
        ->and($assessment->source_data['gad_outcome'])->toBe('passed')
        ->and($assessment->source_data['gad_score_entry_method'])->toBe('manual')
        ->and($assessment->source_data['gad_signature_detected'])->toBeFalse()
        ->and($assessment->source_data['gad_signature_confirmed'])->toBeTrue();

    $this->get(route('topics.head-uploads.index', $this->topic))
        ->assertOk()
        ->assertSee('Confirmed from scanned copy');
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

test('co-evaluator review cannot be uploaded before a passing GAD assessment', function () {
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
            'recommended_action' => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
        ])
        ->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('a passing GAD score without signature confirmation keeps co-evaluator review locked', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();

    $this->version->files()->create([
        'source_version_file_id' => $gadChecklist->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => 'head-uploads/unconfirmed-passing-gad.pdf',
        'original_filename' => 'unconfirmed-passing-gad.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('d', 64),
        'uploaded_by' => $this->head->id,
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 12.5,
            'gad_rating' => 'Gender-sensitive',
            'gad_outcome' => 'passed',
            'gad_signature_detected' => true,
            'gad_signature_confirmed' => false,
        ],
    ]);

    expect($this->version->hasPassingGadAssessment())->toBeFalse();

    $this->actingAs($this->head)
        ->get(route('topics.head-uploads.index', $this->topic))
        ->assertOk()
        ->assertSee('Signature check required')
        ->assertSee('Signature evidence detected, but Research Head confirmation is still required.')
        ->assertSee('Confirm the GAD verifier’s signature before co-evaluator review.')
        ->assertDontSee('data-co-evaluator-screening-panel="true"', false);
});

test('Research Head cannot record an outcome before the co-evaluator review is complete', function (string $decision) {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();

    $this->version->files()->create([
        'source_version_file_id' => $gadChecklist->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => 'head-uploads/completed-gad.pdf',
        'original_filename' => 'completed-gad.pdf',
        'mime_type' => 'application/pdf',
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 12,
            'gad_outcome' => 'passed',
            'gad_signature_confirmed' => true,
        ],
    ]);

    $this->actingAs($this->head)->patch(route('research_head.topics.updateStatus', $this->topic), [
        'status' => $decision,
        'rejection_reason' => 'The proposal does not meet the program requirements.',
        'rejection_confirmed' => '1',
    ])->assertSessionHasErrors('status');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW);
})->with(['revision_requested', 'rejected']);

test('a non-passing GAD result returns the proposal to revision and keeps co-evaluator review locked', function () {
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
        ->assertSee('This result cannot proceed to co-evaluator review.')
        ->assertSee('This result cannot proceed to co-evaluator review.')
        ->assertDontSee('data-co-evaluator-screening-panel="true"', false);

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('value="revision_requested"', false)
        ->assertDontSee('value="rejected"', false)
        ->assertDontSee('value="lrec_queued"', false);

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $initialScreening->id,
            'review_file' => UploadedFile::fake()->create('central-evaluation.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
            'recommended_action' => InitialScreeningSubmissionOrder::MINOR_REVISION,
        ])
        ->assertRedirect(route('topics.head-uploads.index', $this->topic).'#initial-review-workflow')
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->hasPassingGadAssessment())->toBeFalse()
        ->and($this->version->files()
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->count())->toBe(0);
});

test('research head can upload a completed Initial Screening Form and extract its Narrative Evaluation', function (bool $returnToReview, string $format) {
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

        $upload = UploadedFile::fake()->createWithContent('completed-initial-screening.docx', $contents);
        if ($format === 'pdf') {
            Process::fake([Process::result(output: "Narrative Evaluation:\nThe objectives are relevant, but the sampling plan must explain how participants will be selected.\nPrepared by: Dr. Maria Santos")]);
            $upload = UploadedFile::fake()->create('completed-initial-screening.pdf', 100, 'application/pdf');
        }

        $response = $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                'source_file_id' => $initialScreening->id,
                'review_file' => $upload,
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
                'co_evaluator_name' => 'Dr. Maria Santos',
                'recommended_action' => InitialScreeningSubmissionOrder::MAJOR_REVISION,
                'return_to_review' => $returnToReview,
            ]);

        $response->assertRedirect(route($returnToReview ? 'topics.show' : 'topics.head-uploads.index', $this->topic).'#initial-review-workflow')
            ->assertSessionHas('success', 'Completed Initial Screening Form uploaded. Its Narrative Evaluation was recorded for the co-evaluator response.');

        $evaluation = $this->version->files()
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->sole();
        expect($evaluation->source_version_file_id)->toBe($initialScreening->id)
            ->and($evaluation->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->and($evaluation->source_data['co_evaluator_name'])->toBe('Dr. Maria Santos')
            ->and($evaluation->source_data['recommended_action'])->toBe(InitialScreeningSubmissionOrder::MAJOR_REVISION)
            ->and($evaluation->source_data['narrative_evaluation_entry_method'])->toBe('automatic')
            ->and($evaluation->source_data['narrative_evaluation_confirmed'])->toBeFalse()
            ->and($evaluation->source_data['narrative_evaluation'])->toBe('The objectives are relevant, but the sampling plan must explain how participants will be selected.');

        $this->get(route('topics.head-uploads.index', $this->topic))
            ->assertOk()
            ->assertSee('Narrative Evaluation recorded')
            ->assertSee('Read automatically from the uploaded form.')
            ->assertSee('Major Revision')
            ->assertSee('The objectives are relevant, but the sampling plan must explain how participants will be selected.');

        $this->actingAs($this->head)
            ->patch(route('research_head.topics.updateStatus', $this->topic), [
                'status' => TopicProposal::STATUS_LREC_QUEUED,
                'initial_clearance_confirmed' => '1',
            ])
            ->assertSessionHasErrors(['status']);

        expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW);

        $revisionDraft = ProposalDraft::query()->create([
            'user_id' => $this->faculty->id,
            'research_call_id' => $this->call->id,
            'topic_id' => $this->topic->id,
            'project_title' => $this->topic->title,
            'duration_months' => 12,
            'project_leader' => $this->faculty->name,
            'status' => ProposalDraft::STATUS_DRAFT,
        ]);

        expect(app(InitialScreeningSubmissionOrder::class)->forDraft($revisionDraft))
            ->toBe(InitialScreeningSubmissionOrder::REVISED_WITH_MAJOR_CHANGES);

        $this->actingAs($this->faculty)
            ->get(route('faculty.proposal-drafts.initial-screening-form.preview', $revisionDraft))
            ->assertOk()
            ->assertSee('data-screening-order="revised_with_major_changes"', false);
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
})->with([
    'DOCX documents page' => [false, 'docx'],
    'DOCX review tab' => [true, 'docx'],
    'PDF documents page' => [false, 'pdf'],
    'PDF review tab' => [true, 'pdf'],
]);

describe('screening narrative transcription', function () {
    beforeEach(function () {
        Process::preventStrayProcesses();
        $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
        $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();
        $this->initialScreening = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)->sole();
        $gadPath = 'head-uploads/passing-gad.pdf';
        Storage::disk('local')->put($gadPath, 'completed GAD checklist');
        $this->passingGad = $this->version->files()->create([
            'source_version_file_id' => $gadChecklist->id,
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 90,
            'file_path' => $gadPath,
            'original_filename' => 'passing-gad.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->head->id,
            'source_data' => [
                'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                'gad_score' => 12,
                'gad_outcome' => 'passed',
                'gad_signature_confirmed' => true,
            ],
        ]);
        $this->transcription = "Clarify the sampling plan.\n\nComments continued on the next page:\nInclude the consent procedure.";
        $this->transcriptionPayload = [
            'source_file_id' => $this->initialScreening->id,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
            'recommended_action' => InitialScreeningSubmissionOrder::MINOR_REVISION,
            'narrative_evaluation' => $this->transcription,
            'narrative_evaluation_confirmed' => '1',
            'return_to_review' => true,
        ];
    });

    test('editable screening DOCX is available to the Research Head and faculty using submitted details', function (string $workspace) {
        $this->initialScreening->update(['source_data' => [
            'project_title' => 'Submitted Research Title',
            'project_leader' => 'Submitted Project Leader',
            'order_of_submission' => InitialScreeningSubmissionOrder::REVISED_WITH_MAJOR_CHANGES,
            'screening_head' => 'Dr. Helena Cruz',
        ]]);
        $this->topic->update(['title' => 'Later Project Title']);
        $viewer = $workspace === 'research_head' ? $this->head : $this->faculty;
        $url = route('topics.versions.files.editable-docx', [$this->topic, $this->version, $this->initialScreening]);
        $download = $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])->actingAs($viewer)
            ->get($url)->assertSuccessful()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload('submitted-research-title-initial-screening-form-v1.docx');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'screening-download-test-');
        file_put_contents($temporaryPath, $download->streamedContent());
        $archive = new ZipArchive;

        try {
            expect($archive->open($temporaryPath))->toBeTrue();
            $document = new DOMDocument;
            expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
            expect($document->textContent)->toContain('Research Project Title: Submitted Research Title', 'Project Leader: Submitted Project Leader', 'Narrative Evaluation:', 'DR. HELENA CRUZ')
                ->not->toContain('Later Project Title');
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            expect($xpath->query('//w:p[contains(string(.), "Revised with Major Changes")]//w:checkBox/w:checked[@w:val="1"]')->length)->toBe(1);
        } finally {
            $archive->close();
            unlink($temporaryPath);
        }

        $page = $this->get(route('topics.show', $this->topic))->assertSuccessful();
        $document = new DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new DOMXPath($document);
        $folderLink = $xpath->query('//*[@data-project-document-key="proposal-version-file-'.$this->initialScreening->id.'"]//a[contains(., "Editable DOCX")]')->item(0);
        expect($folderLink?->getAttribute('href'))->toBe($url);
        if ($workspace === 'research_head') {
            $reviewLink = $xpath->query('//*[@id="co-evaluator-review"]//a[contains(., "Download editable DOCX")]')->item(0);
            expect($reviewLink?->getAttribute('href'))->toBe($url);
            $preferredWorkflow = $xpath->query('//*[@id="co-evaluator-review"]//*[@data-screening-docx-workflow]')->item(0);
            $handwrittenWorkflow = $xpath->query('//*[@id="co-evaluator-review"]//*[@data-screening-narrative-transcription]')->item(0);
            expect($preferredWorkflow?->textContent)->toContain('Recommended: complete the DOCX in Word', 'ATHENA reads the typed comments automatically.')
                ->and($handwrittenWorkflow?->textContent)->toContain('Handwritten or scanned form (alternative)', 'Upload the completed scan')
                ->and($xpath->query('preceding::*[@data-screening-docx-workflow]', $handwrittenWorkflow)->length)->toBe(1)
                ->and($xpath->query('.//textarea[@name="narrative_evaluation"]', $handwrittenWorkflow)->length)->toBe(1)
                ->and($xpath->query('.//input[@name="narrative_evaluation_confirmed"]', $handwrittenWorkflow)->length)->toBe(1);
        }
    })->with(['research_head', 'faculty']);

    test('downloaded screening DOCX can be edited and uploaded for automatic narrative reading', function () {
        $download = $this->actingAs($this->head)
            ->get(route('topics.versions.files.editable-docx', [$this->topic, $this->version, $this->initialScreening]))
            ->assertSuccessful();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'screening-edit-test-');
        file_put_contents($temporaryPath, $download->streamedContent());
        $archive = new ZipArchive;

        try {
            expect($archive->open($temporaryPath))->toBeTrue();
            $document = new DOMDocument;
            expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $heading = $xpath->query('//w:p[contains(string(.), "Narrative Evaluation:")]')->item(0);
            expect($heading)->not->toBeNull();
            $nextParagraph = $heading->nextSibling;
            foreach (['Clarify the sampling plan.', 'Include the consent procedure.'] as $comment) {
                $paragraph = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
                $run = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
                $text = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
                $text->appendChild($document->createTextNode($comment));
                $run->appendChild($text);
                $paragraph->appendChild($run);
                $heading->parentNode->insertBefore($paragraph, $nextParagraph);
            }
            $archive->addFromString('word/document.xml', $document->saveXML());
            $archive->close();
            $editedContents = file_get_contents($temporaryPath);
            $originalPdf = Storage::disk('local')->get($this->initialScreening->file_path);
            $payload = $this->transcriptionPayload;
            unset($payload['narrative_evaluation'], $payload['narrative_evaluation_confirmed']);
            $this->post(route('topics.head-uploads.store', $this->topic), [
                ...$payload,
                'review_file' => UploadedFile::fake()->createWithContent('completed-screening.docx', $editedContents),
            ])->assertSessionHasNoErrors();

            $evaluation = $this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->sole();
            expect($evaluation->source_data['narrative_evaluation'])->toBe("Clarify the sampling plan.\nInclude the consent procedure.")
                ->and($evaluation->source_data['narrative_evaluation_entry_method'])->toBe('automatic')
                ->and(Storage::disk('local')->get($evaluation->file_path))->toBe($editedContents)
                ->and(Storage::disk('local')->get($this->initialScreening->file_path))->toBe($originalPdf);
            Process::assertNothingRan();
        } finally {
            unlink($temporaryPath);
        }
    });

    test('editable screening download rejects unrelated users and mismatched documents', function () {
        $url = route('topics.versions.files.editable-docx', [$this->topic, $this->version, $this->initialScreening]);
        $outsider = User::factory()->create();
        $outsider->assignRole('faculty');
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $paper = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
        $this->actingAs($this->head)
            ->get(route('topics.versions.files.editable-docx', [$this->topic, $this->version, $paper]))->assertNotFound();
        $otherTopic = TopicProposal::create([
            'user_id' => $this->faculty->id,
            'title' => 'Unrelated project',
            'estimated_budget' => 1000,
            'estimated_duration_months' => 1,
            'status' => 'pending',
        ]);
        $this->get(route('topics.versions.files.editable-docx', [$otherTopic, $this->version, $this->initialScreening]))->assertNotFound();
        $otherVersion = $otherTopic->versions()->create([
            'submitted_by' => $this->faculty->id,
            'version_number' => 1,
            'submission_type' => 'initial',
            'title' => $otherTopic->title,
            'file_path' => 'unrelated-proposal.pdf',
            'original_filename' => 'unrelated-proposal.pdf',
        ]);
        $otherForm = $otherVersion->files()->create([
            'document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
            'position' => 0,
            'file_path' => 'unrelated-screening.pdf',
            'original_filename' => 'unrelated-screening.pdf',
            'mime_type' => 'application/pdf',
        ]);
        $this->get(route('topics.versions.files.editable-docx', [$this->topic, $this->version, $otherForm]))->assertNotFound();
    });

    test('verified handwritten comments are recorded with their original form and line breaks', function (string $extension, string $mimeType) {
        $file = UploadedFile::fake()->create('handwritten-screening.'.$extension, 10, $mimeType);
        $originalContents = file_get_contents($file->getRealPath());
        $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => $file,
                'narrative_evaluation' => '  '.str_replace("\n", "\r\n", $this->transcription).'  ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow');

        $evaluation = $this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->sole();
        expect($evaluation->source_data['narrative_evaluation'])->toBe($this->transcription)
            ->and($evaluation->source_data['narrative_evaluation_entry_method'])->toBe('manual')
            ->and($evaluation->source_data['narrative_evaluation_confirmed'])->toBeTrue()
            ->and($evaluation->uploaded_by)->toBe($this->head->id)
            ->and($evaluation->source_version_file_id)->toBe($this->initialScreening->id)
            ->and(Storage::disk('local')->get($evaluation->file_path))->toBe($originalContents);
        Process::assertNothingRan();

        $this->get(route('topics.head-uploads.index', $this->topic))
            ->assertSuccessful()
            ->assertSee('Transcribed from the uploaded form and verified by the Research Head.')
            ->assertSee($this->transcription)
            ->assertDontSee('Read automatically from the uploaded form.');

        $review = $this->topic->reviews()->create([
            'reviewer_id' => $this->head->id,
            'decision' => 'revision_requested',
            'review_stage' => 'gad',
        ]);
        $feedback = app(CommentResponseFeedback::class)->rowsForSource($review, CommentResponseFeedback::FORM_CO_EVALUATOR);
        expect($feedback)->toHaveCount(1)
            ->and($feedback[0]['comment'])->toBe($this->transcription)
            ->and($feedback[0]['form_source'])->toBe(CommentResponseFeedback::FORM_CO_EVALUATOR);
    })->with([
        'scanned PDF' => ['pdf', 'application/pdf'],
        'image-only DOCX' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ]);

    test('manual comments require valid text and confirmation', function (array $overrides, string $errorField) {
        $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
                ...$overrides,
            ])
            ->assertRedirect(route('topics.show', $this->topic).'#initial-review-workflow')
            ->assertSessionHasErrors([$errorField], null, 'headUpload');

        expect($this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->count())->toBe(0);
        Process::assertNothingRan();
    })->with([
        'missing confirmation' => [['narrative_evaluation_confirmed' => null], 'narrative_evaluation_confirmed'],
        'unchecked confirmation' => [['narrative_evaluation_confirmed' => '0'], 'narrative_evaluation_confirmed'],
        'too short after trimming' => [['narrative_evaluation' => ' a '], 'narrative_evaluation'],
        'over 5000 characters' => [['narrative_evaluation' => str_repeat('a', 5001)], 'narrative_evaluation'],
        'array instead of text' => [['narrative_evaluation' => ['comment']], 'narrative_evaluation'],
        'missing original form' => [['review_file' => null], 'review_file'],
        'unsupported original form' => [['review_file' => UploadedFile::fake()->create('screening.txt', 10, 'text/plain')], 'review_file'],
    ]);

    test('transcription validation keeps all comments available when replacing an evaluation', function () {
        $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => UploadedFile::fake()->create('first-screening.pdf', 10, 'application/pdf'),
            ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->head)->followingRedirects()
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
                'narrative_evaluation_confirmed' => null,
            ])->assertSuccessful();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        expect($xpath->query('//*[@data-screening-narrative-transcription]')->item(0)?->textContent)
            ->toContain($this->transcription)
            ->toContain('Your entries were kept. Select the completed form again before submitting.');
        expect($xpath->query('//*[@data-co-evaluator-screening-panel]/parent::*')->item(0)?->getAttribute('x-data'))
            ->toBe('{ replacing: true }');
    });

    test('failed automatic reading explains how to record a scanned narrative', function () {
        Process::fake([Process::result(output: '')]);
        $payload = $this->transcriptionPayload;
        unset($payload['narrative_evaluation'], $payload['narrative_evaluation_confirmed']);

        $response = $this->actingAs($this->head)->followingRedirects()
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$payload,
                'review_file' => UploadedFile::fake()->create('scanned-screening.pdf', 10, 'application/pdf'),
            ])->assertSuccessful();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        expect($xpath->query('//*[@data-research-head-file-workspace]//*[@role="alert"]')->item(0)?->textContent)
            ->toContain('You can enter the full Narrative Evaluation below, confirm it matches the completed form, and select the file again.');

        expect($this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->count())->toBe(0);
    });

    test('blank manual comments retain automatic reading', function () {
        Process::fake([Process::result(output: "Narrative Evaluation:\nThe project is ready for endorsement.\nPrepared by: Dr. Santos")]);
        $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => UploadedFile::fake()->create('typed-screening.pdf', 10, 'application/pdf'),
                'narrative_evaluation' => " \r\n ",
                'narrative_evaluation_confirmed' => null,
            ])->assertSessionHasNoErrors();

        $evaluation = $this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->sole();
        expect($evaluation->source_data['narrative_evaluation'])->toBe('The project is ready for endorsement.')
            ->and($evaluation->source_data['narrative_evaluation_entry_method'])->toBe('automatic')
            ->and($evaluation->source_data['narrative_evaluation_confirmed'])->toBeFalse();
        Process::assertRan(fn () => true);
    });

    test('manual comments cannot bypass the GAD gate or proposal stage', function (string $blockedStage) {
        if ($blockedStage === 'gad') {
            $this->passingGad->update(['source_data' => [
                ...$this->passingGad->source_data,
                'gad_score' => 3,
                'gad_outcome' => 'returned',
            ]]);
        } else {
            $this->topic->update(['status' => 'pending']);
        }

        $this->actingAs($this->head)
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors(['review_file'], null, 'headUpload');

        expect($this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->count())->toBe(0);
        Process::assertNothingRan();
    })->with(['gad', 'proposal']);

    test('faculty cannot record a Research Head transcription', function () {
        $this->actingAs($this->faculty)
            ->post(route('topics.head-uploads.store', $this->topic), [
                ...$this->transcriptionPayload,
                'review_file' => UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
            ])->assertForbidden();

        expect($this->version->files()->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)->count())->toBe(0);
        Process::assertNothingRan();
    });
});

test('replacing a signed copy preserves the superseded audit record before final release', function () {
    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();

    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => '1',
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->create('signed-gad-checklist.pdf', 200, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
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
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
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

    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => '1',
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

test('a cleared proposal moves to final signing without a manual approval step', function () {
    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $reviewPage = $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Record the LREC outcome')
        ->assertSee('Clear for signing')
        ->assertSee('Request revisions')
        ->assertSee('@submit.prevent="submitDecision"', false)
        ->assertSee('Required signed proposal papers')
        ->assertSee('Required signed assessment forms')
        ->assertSee('All five listed documents require signed PDFs before final release.')
        ->assertDontSee('Approve proposal');

    $document = new DOMDocument;
    @$document->loadHTML($reviewPage->getContent());
    $xpath = new DOMXPath($document);
    $proposalPapers = $xpath->query('//*[text()="Required signed proposal papers"]/following-sibling::ul')->item(0);
    $assessmentForms = $xpath->query('//*[text()="Required signed assessment forms"]/following-sibling::ul')->item(0);
    $signatureFiles = $this->version->files()->get()->keyBy('document_type');

    expect($proposalPapers)->not->toBeNull()
        ->and($proposalPapers->textContent)->toContain(
            $signatureFiles[ProposalVersionFile::TYPE_DETAILED_PROPOSAL]->label(),
            $signatureFiles[ProposalVersionFile::TYPE_WORK_PLAN]->label(),
            $signatureFiles[ProposalVersionFile::TYPE_LINE_ITEM_BUDGET]->label(),
        )
        ->not->toContain(
            $signatureFiles[ProposalVersionFile::TYPE_GAD_CHECKLIST]->label(),
            $signatureFiles[ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM]->label(),
        )
        ->and($assessmentForms)->not->toBeNull()
        ->and($assessmentForms->textContent)->toContain(
            $signatureFiles[ProposalVersionFile::TYPE_GAD_CHECKLIST]->label(),
            $signatureFiles[ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM]->label(),
        )
        ->not->toContain(
            $signatureFiles[ProposalVersionFile::TYPE_DETAILED_PROPOSAL]->label(),
            $signatureFiles[ProposalVersionFile::TYPE_WORK_PLAN]->label(),
            $signatureFiles[ProposalVersionFile::TYPE_LINE_ITEM_BUDGET]->label(),
        );

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'approved',
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('status');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_REVIEW);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'redirect_to' => 'topic',
            'lrec_clearance_confirmed' => '1',
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHas('topic_tab', 'notice')
        ->assertSessionHas('success', 'LREC cleared. Upload the signed papers and prepare the Notice to Proceed for one final release.');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and($this->topic->fresh()->project_status)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();

    $signingPage = $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-signed-count="0"', false)
        ->assertSee('Signed PDF for')
        ->assertSee('Signing &amp; release', false)
        ->assertDontSee('Need to change a submitted proposal paper?')
        ->assertDontSee('Request paper revision')
        ->assertDontSee('Papers that must be corrected')
        ->assertDontSee('One clear review process')
        ->assertDontSee('Research Head workspace')
        ->assertDontSee('Review faculty files')
        ->assertDontSee('Faculty-submitted files')
        ->assertDontSee('Administrative and supplemental papers')
        ->assertDontSee('<details class="group overflow-hidden rounded-2xl border-2 border-amber-300 shadow-lg" open>', false)
        ->assertDontSee('Upload reviewed copy')
        ->assertDontSee('Record note (optional)');

    $document = new DOMDocument;
    @$document->loadHTML($signingPage->getContent());
    $xpath = new DOMXPath($document);
    $correction = $xpath->query('//*[@id="notice-to-proceed-tab"]//*[@data-signing-correction-disclosure]')->item(0);

    expect($correction)->toBeNull()
        ->and($xpath->query('//*[@id="proposal-review-tab"]//*[@data-signing-correction-disclosure]')->length)->toBe(0);
});

test('review decision buttons require their matching clearance checkbox', function () {
    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $response = $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $button = $xpath->query('//*[@id="research-head-decision-form"]//button[@type="submit"]')->item(0);

    expect($button)->not->toBeNull();
    $disabledWhen = $button->getAttribute(':disabled');
    expect($disabledWhen)->toContain(
        "decision === 'gad_review' && !researchHeadClearanceConfirmed",
        "decision === 'lrec_queued' && !initialClearanceConfirmed",
        "decision === 'ready_for_signature' && !lrecClearanceConfirmed",
        "decision === 'rejected' && !rejectionConfirmed",
    );

    foreach ([
        'research_head_clearance_confirmed' => 'researchHeadClearanceConfirmed',
        'initial_clearance_confirmed' => 'initialClearanceConfirmed',
        'lrec_clearance_confirmed' => 'lrecClearanceConfirmed',
        'rejection_confirmed' => 'rejectionConfirmed',
    ] as $name => $model) {
        $checkbox = $xpath->query('//*[@id="research-head-decision-form"]//input[@name="'.$name.'"]')->item(0);
        expect($checkbox)->not->toBeNull()
            ->and($checkbox->getAttribute('x-model'))->toBe($model);
    }

    $this->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
        ])
        ->assertSessionHasErrors(['lrec_clearance_confirmed']);

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_REVIEW);
});

test('final signing always requires the fixed five-paper signing package', function () {
    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => '1',
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasNoErrors();

    $signatureReview = $this->topic->reviews()
        ->where('decision', TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->sole();

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and($signatureReview->required_signature_file_ids)->toHaveCount(5);
});

test('signed copies are limited to signature papers in the signing stage', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    $expenseBreakdown = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN)->sole();

    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

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
            'lrec_clearance_confirmed' => '1',
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

test('final release stays locked until every required signed PDF is uploaded', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => '1',
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
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
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
            ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed');
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
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHas('success', 'Signed papers are ready. Upload the signed Notice to Proceed to release the complete package to faculty.');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and($this->topic->fresh()->project_status)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();

    $facultyResponse = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic));

    $facultyResponse
        ->assertOk()
        ->assertDontSee('signed-work_plan.pdf')
        ->assertDontSee('Work Plan (signed official copy)');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.versions.files.download', [$this->topic, $this->version, $signedWorkPlan]))
        ->assertNotFound();
});

test('review uploads reject arbitrary documents while earlier records remain accessible', function () {
    $legacyPath = 'head-uploads/regional-endorsement.pdf';
    Storage::disk('local')->put($legacyPath, 'Earlier office document');
    $legacyRecord = $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => $legacyPath,
        'original_filename' => 'regional-endorsement.pdf',
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->head->id,
        'source_data' => [
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL,
            'document_title' => 'Regional Endorsement Memorandum',
            'issuing_office' => 'Office of the Regional Director',
        ],
    ]);
    $fileCount = $this->version->files()->count();
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('unrelated-paper.pdf', 120, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL,
            'document_title' => 'Unrelated paper',
            'issuing_office' => 'Another office',
        ])
        ->assertSessionHasErrors(['purpose'], null, 'headUpload');

    expect($this->version->files()->count())->toBe($fileCount)
        ->and($this->topic->reviews()->count())->toBe(0)
        ->and($legacyRecord->fresh()->source_data['document_title'])->toBe('Regional Endorsement Memorandum')
        ->and($legacyRecord->source_data['issuing_office'])->toBe('Office of the Regional Director')
        ->and(Storage::disk('local')->get($legacyPath))->toBe('Earlier office document');

    foreach (['topics.show', 'topics.head-uploads.index'] as $routeName) {
        $this->get(route($routeName, $this->topic))->assertOk()
            ->assertDontSee('Supporting documents · Optional')
            ->assertDontSee('Add supporting document')
            ->assertDontSee('data-supporting-document-actions', false)
            ->assertDontSee('data-supplemental-paper-dropzone', false)
            ->assertDontSee('name="document_title"', false);
    }

    $page = $this->get(route('topics.show', $this->topic))->assertOk();
    $library = $page->viewData('projectDocumentLibrary');
    expect($library['documents']->firstWhere('filename', 'regional-endorsement.pdf')['title'])->toBe('Regional Endorsement Memorandum');
    $this->get(route('topics.versions.files.download', [$this->topic, $this->version, $legacyRecord]))
        ->assertOk()->assertDownload('regional-endorsement.pdf');
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
            'recommended_action' => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
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
            'recommended_action' => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $initialScreening->id,
            'review_file' => UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'co_evaluator_name' => 'Dr. Maria Santos',
            'recommended_action' => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
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
        ->assertSee('Open revision workspace')
        ->assertDontSee('id="submit-revision"', false);
});

test('the shared document list records Research Head uploads', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->topic->update([
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => '1',
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
        ->assertSee('Signed copy saved')
        ->assertSee('Preview')
        ->assertDontSee('data-signed-copy-preview', false)
        ->assertSee(route('topics.versions.files.view', [$this->topic, $this->version, $signedWorkPlan]));

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-signing-document and @data-upload-state="uploaded"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-signing-document and @data-upload-state="awaiting"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-upload-state="uploaded"]//*[@data-uploaded-badge]')->item(0)->textContent)->toContain('Uploaded');
});

test('automatic signed uploads return saved files without redirecting and preserve all papers', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);
    $sources = $this->version->files()->whereIn('document_type', [
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
    ])->get();

    foreach ($sources as $source) {
        $this->actingAs($this->head)->postJson(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $source->id,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            'review_file' => UploadedFile::fake()->create('signed-'.$source->id.'.pdf', 100, 'application/pdf'),
        ])->assertOk()->assertJsonPath('source_file_id', $source->id)
            ->assertJsonPath('filename', 'signed-'.$source->id.'.pdf')
            ->assertJsonPath('complete', false)
            ->assertJsonStructure(['view_url', 'download_url']);
    }

    expect($this->version->files()->where('source_data->purpose', 'signed')->whereNull('superseded_at')->count())->toBe(3);
    $this->actingAs($this->head)->postJson(route('topics.head-uploads.store', $this->topic), [
        'source_file_id' => $sources->first()->id,
        'purpose' => 'signed',
        'review_file' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
    ])->assertOk()->assertJsonPath('filename', 'replacement.pdf');
    expect($this->version->files()->where('source_data->purpose', 'signed')->whereNull('superseded_at')->count())->toBe(3)
        ->and($this->version->files()->where('source_data->purpose', 'signed')->whereNotNull('superseded_at')->count())->toBe(1);

    $assessments = $this->version->files()->whereIn('document_type', [
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
    ])->get();
    foreach ($assessments as $index => $source) {
        $this->postJson(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $source->id,
            'purpose' => 'signed',
            'review_file' => UploadedFile::fake()->create('signed-assessment.pdf', 100, 'application/pdf'),
        ])->assertOk()->assertJsonPath('complete', $index === 1);
    }

    $this->topic->update(['status' => 'pending']);
    $this->postJson(route('topics.head-uploads.store', $this->topic), [
        'source_file_id' => $sources->first()->id,
        'purpose' => 'signed',
        'review_file' => UploadedFile::fake()->create('blocked.pdf', 100, 'application/pdf'),
    ])->assertUnprocessable()->assertJsonValidationErrors('purpose');
});
