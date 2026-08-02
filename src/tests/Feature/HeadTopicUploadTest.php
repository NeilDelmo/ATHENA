<?php

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

test('research head can attach reviewed files to exact faculty submissions', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $response = $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
            'note' => 'Annotated for the faculty revision.',
        ]);

    $response->assertRedirect(route('topics.show', $this->topic).'#proposal-review')
        ->assertSessionHas('success', 'Research Head file attached to the faculty submission.');

    $headUpload = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->sole();

    expect($headUpload->uploaded_by)->toBe($this->head->id)
        ->and($headUpload->source_version_file_id)->toBe($workPlan->id)
        ->and($headUpload->original_filename)->toBe('reviewed-work-plan.pdf')
        ->and($headUpload->source_data['target_document_type'])->toBe(ProposalVersionFile::TYPE_WORK_PLAN)
        ->and($headUpload->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION)
        ->and($headUpload->source_data['note'])->toBe('Annotated for the faculty revision.')
        ->and(Storage::disk('local')->exists($headUpload->file_path))->toBeTrue();

    expect($this->topic->reviews()->where('decision', 'head_upload')->count())->toBe(1);
});

test('signed copy can be replaced after the proposal is approved', function () {
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
        ->patch(route('research_head.topics.finalizeApproval', $this->topic))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->create('corrected-signed-gad-checklist.pdf', 220, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review');

    $replacementSignedCopy = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
        ->sole();

    expect($replacementSignedCopy->id)->toBe($originalSignedCopy->id)
        ->and($replacementSignedCopy->original_filename)->toBe('corrected-signed-gad-checklist.pdf')
        ->and(Storage::disk('local')->exists($originalSignedCopy->file_path))->toBeFalse()
        ->and(Storage::disk('local')->exists($replacementSignedCopy->file_path))->toBeTrue();
});

test('a clean proposal moves to signing before it can be approved', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Which papers need a signed final PDF?')
        ->assertSee('Nothing is selected automatically.')
        ->assertDontSee('Final signature required')
        ->assertDontSee('No final signature required')
        ->assertDontSee('Record note (optional)');

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'redirect_to' => 'topic',
            'signature_file_ids' => [$workPlan->id, $gadChecklist->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
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
        ->assertSessionHas('success', 'Proposal approved. The signed final copies are available; monitoring will open after the Notice to Proceed is issued.');

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
        ->assertSee('Work Plan (signed copy)');

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
        ->assertSee('Administrative and supplemental papers')
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
            'review_file' => UploadedFile::fake()->create('reviewed.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertSessionHasErrors(['source_file_id'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload rejects unsupported file types and oversize files', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed.txt', 100, 'text/plain'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('the proposal review shows files shared by the Research Head', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
            'note' => 'Use these annotations for the next revision.',
        ]);

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertOk()
        ->assertSee('Review & decision')
        ->assertSee('Evaluation and decision documents')
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

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertSee('signed-work-plan.pdf')
        ->assertSee('Work Plan (signed copy)');
});
