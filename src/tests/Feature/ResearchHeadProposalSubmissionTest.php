<?php

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProposalSignatureWorkflow;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);

    $this->researchHead = User::factory()->create();
    $this->researchHead->assignRole('research_head');
    $this->faculty = User::factory()->create([
        'name' => 'Dr. Elena Santos',
        'email' => 'elena.santos@g.batstate-u.edu.ph',
    ]);
    $this->faculty->assignRole('faculty');
    $this->researchCall = ResearchCall::create([
        'title' => 'Sustainable Communities Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
});

test('research heads can view every initial proposal submission and revision', function () {
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Community Flood Resilience',
        'estimated_budget' => 35000,
        'estimated_duration_months' => 12,
        'status' => 'resubmitted',
    ]);

    $initialSubmission = createProposalSubmission($topic, $this->faculty, [
        'version_number' => 1,
        'submission_type' => 'initial',
        'title' => 'Community Flood Resilience',
    ]);
    $revision = createProposalSubmission($topic, $this->faculty, [
        'version_number' => 2,
        'submission_type' => 'revision',
        'title' => 'Community Flood Resilience',
        'change_summary' => 'Expanded the implementation schedule and revised the budget.',
    ]);

    $initialSubmission->files()->create([
        'document_type' => 'detailed_proposal',
        'position' => 0,
        'file_path' => 'packages/proposal-v1.pdf',
        'original_filename' => 'proposal-v1.pdf',
        'file_size' => 100,
        'is_carried_forward' => false,
    ]);
    foreach (['detailed_proposal', 'work_plan'] as $position => $documentType) {
        $revision->files()->create([
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => "packages/{$documentType}-v2.pdf",
            'original_filename' => "{$documentType}-v2.pdf",
            'file_size' => 100,
            'is_carried_forward' => false,
        ]);
    }
    $this->researchHead->notify(new ProposalActivityNotification(
        title: 'Proposal revision submitted',
        message: 'A revised proposal is ready for review.',
        url: route('topics.show', $topic),
        topicId: $topic->id,
        workspace: User::WORKSPACE_RESEARCH_HEAD,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
    ));

    $this->actingAs($this->researchHead)
        ->get(route('research_head.proposal-submissions.index'))
        ->assertOk()
        ->assertSee('Proposal Submissions')
        ->assertSee('Active proposal queue')
        ->assertSee('Resubmitted')
        ->assertSee('1 needs review')
        ->assertSee('A red dot marks a submission that still needs your review.')
        ->assertSee('data-proposal-attention="unread"', false)
        ->assertSee('data-proposal-unread-dot', false)
        ->assertSee('Needs review')
        ->assertSee('Revised package received')
        ->assertSee('Version 2 · Faculty revision')
        ->assertSee('Open for review')
        ->assertSee('Submission history')
        ->assertSee('Initial submission')
        ->assertSee('Revision')
        ->assertSee('Version 1')
        ->assertSee('Version 2')
        ->assertSee('Expanded the implementation schedule and revised the budget.')
        ->assertSee('Dr. Elena Santos')
        ->assertSee('Sustainable Communities Research Call')
        ->assertSee('1 package file')
        ->assertSee('2 package files')
        ->assertSee(route('topics.show', $topic).'#version-history', false)
        ->assertSeeInOrder([
            'Research Head Dashboard',
            'Proposal Submissions',
            'Project Monitoring',
            'Faculty Directory',
            'Signatory Directory',
            'Research Calls',
        ])
        ->assertDontSee('Similarity Checks')
        ->assertDontSee('aria-label="Proposal Templates"', false)
        ->assertDontSee('aria-label="Athena Knowledge"', false);

    $review = $topic->reviews()->create([
        'reviewer_id' => $this->researchHead->id,
        'decision' => 'revision_requested',
    ]);
    $returnedFile = $revision->files()->where('document_type', 'work_plan')->first();
    $review->fileRevisions()->create([
        'proposal_version_file_id' => $initialSubmission->files()->first()->id,
        'resolved_by_version_file_id' => $returnedFile->id,
        'document_type' => 'work_plan',
        'original_filename' => 'work-plan-v1.pdf',
        'resolution_type' => 'file_revised',
        'resolved_at' => now(),
        'faculty_response' => 'Updated the schedule as requested.',
    ]);

    $page = $this->get(route('topics.show', $topic));
    $page->assertOk()
        ->assertSeeInOrder([
            'Review submitted papers',
            'Papers returned for review',
            'Updated the schedule as requested.',
            'Other submitted papers',
            'Review decision',
        ])
        ->assertSee('form="research-head-decision-form"', false);
    $document = new DOMDocument;
    @$document->loadHTML($page->getContent());
    $xpath = new DOMXPath($document);
    $otherPapers = $xpath->query('//details[summary[contains(., "Other submitted papers")]]');
    expect($otherPapers->length)->toBe(1)
        ->and($otherPapers->item(0)->hasAttribute('open'))->toBeFalse();
});

test('proposal submissions can be searched and filtered by type and status', function () {
    $initialTopic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Initial Coastal Survey',
        'status' => 'pending',
    ]);
    createProposalSubmission($initialTopic, $this->faculty, [
        'version_number' => 1,
        'submission_type' => 'initial',
        'title' => 'Initial Coastal Survey',
    ]);

    $revisedTopic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Revised Mangrove Mapping',
        'status' => 'resubmitted',
    ]);
    createProposalSubmission($revisedTopic, $this->faculty, [
        'version_number' => 2,
        'submission_type' => 'revision',
        'title' => 'Revised Mangrove Mapping',
    ]);

    $this->actingAs($this->researchHead)
        ->get(route('research_head.proposal-submissions.index', [
            'search' => 'Mangrove',
            'type' => 'revision',
            'status' => 'resubmitted',
        ]))
        ->assertOk()
        ->assertSee('Revised Mangrove Mapping')
        ->assertSee('Revision')
        ->assertSee('data-proposal-id="'.$revisedTopic->id.'"', false)
        ->assertDontSee('data-proposal-id="'.$initialTopic->id.'"', false);
});

test('proposal submissions are restricted to research heads', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->get(route('research_head.proposal-submissions.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($faculty)
        ->get(route('research_head.proposal-submissions.index'))
        ->assertForbidden();
});

test('signing automatically requires five papers and exempts CV and expense breakdown', function (bool $sendOldSelection) {
    Notification::fake();
    Storage::fake('local');
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Fixed signature requirements',
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);
    $version = createProposalSubmission($topic, $this->faculty);
    foreach (app(ProposalPaperCatalog::class)->all() as $paper) {
        $path = 'signature-tests/'.$paper['document_type'].'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 original');
        $version->files()->create([
            'document_type' => $paper['document_type'], 'position' => 0,
            'file_path' => $path, 'original_filename' => basename($path),
            'mime_type' => 'application/pdf', 'file_size' => 20,
        ]);
    }
    $exemptFiles = $version->files()->whereIn('document_type', ['curriculum_vitae', 'expense_breakdown'])->get();
    $payload = ['status' => TopicProposal::STATUS_READY_FOR_SIGNATURE, 'lrec_clearance_confirmed' => '1'];
    if ($sendOldSelection) {
        $payload['signature_file_ids'] = $exemptFiles->pluck('id')->all();
    }
    $this->actingAs($this->researchHead)->get(route('topics.show', $topic))
        ->assertOk()->assertDontSee('name="signature_file_ids[]"', false);
    $this->patch(route('research_head.topics.updateStatus', $topic), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    $workflow = app(ProposalSignatureWorkflow::class);
    $required = $workflow->requiredFiles($version->fresh());
    expect($required)->toHaveCount(5)
        ->and($required->pluck('document_type')->all())->not->toContain('curriculum_vitae', 'expense_breakdown')
        ->and($topic->reviews()->latest('id')->firstOrFail()->required_signature_file_ids)->toHaveCount(5);
    $this->get(route('topics.show', $topic))->assertOk()->assertSee('Upload the required signed PDFs');

    foreach ($exemptFiles as $file) {
        $this->post(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $file->id, 'purpose' => 'signed',
            'review_file' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('source_file_id', null, 'headUpload');
    }
    foreach ($required as $index => $file) {
        $this->patch(route('research_head.topics.finalizeApproval', $topic))->assertSessionHasErrors('status');
        $this->post(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $file->id, 'purpose' => 'signed',
            'review_file' => UploadedFile::fake()->create('signed-'.$index.'.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        expect($workflow->isComplete($version->fresh()))->toBe($index === 4);
    }
    $this->patch(route('research_head.topics.finalizeApproval', $topic))
        ->assertSessionHasNoErrors()->assertRedirect(route('topics.show', $topic).'#notice-to-proceed');
    expect($topic->fresh()->notice_to_proceed_issued_at)->toBeNull();

    $incompleteVersion = $version->fresh('files');
    $incompleteVersion->setRelation('files', $incompleteVersion->files->where('document_type', '!=', 'initial_screening_form'));
    expect($workflow->isComplete($incompleteVersion))->toBeFalse();
})->with([false, true]);

function createProposalSubmission(TopicProposal $topic, User $submitter, array $overrides = []): ProposalVersion
{
    return $topic->versions()->create(array_merge([
        'submitted_by' => $submitter->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'packages/proposal.pdf',
        'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
    ], $overrides));
}

test('GAD uploads are blocked until the Research Head clears the proposal', function () {
    Storage::fake('local');
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Research Head Clearance Gate',
        'status' => 'resubmitted',
    ]);
    $version = createProposalSubmission($topic, $this->faculty, [
        'version_number' => 2,
        'submission_type' => 'revision',
    ]);
    $gadChecklist = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
        'position' => 1,
        'file_path' => 'packages/gad-checklist.pdf',
        'original_filename' => 'gad-checklist.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('b', 64),
        'is_carried_forward' => false,
    ]);

    $this->actingAs($this->researchHead)
        ->post(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $gadChecklist->id,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'review_file' => UploadedFile::fake()->create('completed-gad.pdf', 100, 'application/pdf'),
            'gad_signature_confirmed' => '1',
        ])
        ->assertSessionHasErrors('review_file', null, 'headUpload');

    expect($version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('Research Head clearance opens GAD review before central evaluation and LREC', function () {
    Notification::fake();
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Revision Before LREC',
        'status' => 'pending',
    ]);
    createProposalSubmission($topic, $this->faculty);

    $this->actingAs($this->researchHead)->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSeeInOrder([
            'Research Office screening',
            'Faculty revision',
            'GAD Office review',
            'Central evaluation',
            'LREC review',
            'Signing and release',
        ])
        ->assertSee('value="gad_review"', false)
        ->assertSee('value="revision_requested"', false)
        ->assertSee('value="rejected"', false)
        ->assertDontSee('value="lrec_queued"', false);

    foreach (['pending', 'expert_review', 'for_final_decision', 'resubmitted'] as $status) {
        $topic->update(['status' => $status]);
        $this->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_LREC_QUEUED,
            'initial_clearance_confirmed' => '1',
        ])->assertSessionHasErrors('status');
        expect($topic->fresh()->status)->toBe($status);
    }

    $topic->update(['status' => 'revision_requested']);
    expect($topic->canRecordDecision(TopicProposal::STATUS_LREC_QUEUED))->toBeFalse();
    $revisionVersion = createProposalSubmission($topic, $this->faculty, [
        'version_number' => 2,
        'submission_type' => 'revision',
    ]);
    $topic->update(['status' => 'resubmitted']);
    $this->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('value="gad_review"', false)
        ->assertDontSee('value="lrec_queued"', false);

    $this->patch(route('research_head.topics.updateStatus', $topic), [
        'status' => TopicProposal::STATUS_GAD_REVIEW,
    ])->assertSessionHasErrors('research_head_clearance_confirmed');

    $this->patch(route('research_head.topics.updateStatus', $topic), [
        'status' => TopicProposal::STATUS_GAD_REVIEW,
        'research_head_clearance_confirmed' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW)
        ->and($topic->fresh()->review_stage)->toBe('gad');
    $this->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('value="lrec_queued"', false)
        ->assertDontSee('value="gad_review"', false);

    $this->patch(route('research_head.topics.updateStatus', $topic), [
        'status' => TopicProposal::STATUS_LREC_QUEUED,
        'initial_clearance_confirmed' => '1',
    ])->assertSessionHasErrors('status');

    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW);

    $gadChecklist = $revisionVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
        'position' => 1,
        'file_path' => 'packages/revision-gad-checklist.pdf',
        'original_filename' => 'revision-gad-checklist.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('b', 64),
        'is_carried_forward' => false,
    ]);
    $initialScreening = $revisionVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
        'position' => 2,
        'file_path' => 'packages/revision-initial-screening.pdf',
        'original_filename' => 'revision-initial-screening.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('c', 64),
        'is_carried_forward' => false,
    ]);
    $gadAssessment = $revisionVersion->files()->create([
        'source_version_file_id' => $gadChecklist->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90,
        'file_path' => 'head-uploads/completed-gad.pdf',
        'original_filename' => 'completed-gad.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('d', 64),
        'uploaded_by' => $this->researchHead->id,
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 6.5,
            'gad_outcome' => 'conditional_pass',
            'gad_signature_confirmed' => true,
        ],
    ]);
    $revisionVersion->files()->create([
        'source_version_file_id' => $initialScreening->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 91,
        'file_path' => 'head-uploads/completed-initial-screening.pdf',
        'original_filename' => 'completed-initial-screening.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('e', 64),
        'uploaded_by' => $this->researchHead->id,
        'source_data' => [
            'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'narrative_evaluation' => 'The proposal is ready for LREC presentation.',
        ],
    ]);

    $this->patch(route('research_head.topics.updateStatus', $topic), [
        'status' => TopicProposal::STATUS_LREC_QUEUED,
        'initial_clearance_confirmed' => '1',
    ])->assertSessionHasErrors('status');
    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW);

    $gadAssessment->update(['source_data' => [
        ...$gadAssessment->source_data,
        'gad_score' => 12.32,
        'gad_outcome' => 'passed',
    ]]);

    $this->patch(route('research_head.topics.updateStatus', $topic), [
        'status' => TopicProposal::STATUS_LREC_QUEUED,
        'initial_clearance_confirmed' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_QUEUED)
        ->and($topic->fresh()->review_stage)->toBe('lrec');
});
