<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProposalRevisionSectionMap;
use App\Support\ProposalRevisionTargetCatalog;
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
        'title' => 'Open Research Call',
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
        'file_path' => 'proposal-packages/coastal-habitat.pdf',
        'original_filename' => 'coastal-habitat.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'checksum' => str_repeat('a', 64),
        'title' => $this->topic->title,
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
    ]);
    Storage::disk('local')->put('proposal-packages/work-plan.pdf', '%PDF-1.4 test');
    $this->file = $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'file_path' => 'proposal-packages/work-plan.pdf',
        'original_filename' => 'work-plan.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'checksum' => str_repeat('b', 64),
        'source_data' => [
            'entries' => [[
                'objective' => 'Restore mangrove plots',
                'expected_output' => 'Mapped and replanted plots',
                'activity' => 'Quarterly planting and monitoring',
                'months' => [1, 4, 7, 10],
            ]],
        ],
        'is_carried_forward' => false,
    ]);
});

test('research head can annotate an exact turned-in PDF while draft comments stay private', function () {
    $this->actingAs($this->head)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]).'?decision=revision_requested')
        ->assertOk()
        ->assertSee('Back to review')
        ->assertDontSee('fixed bottom-4 right-4 z-40', false)
        ->assertDontSee('Annotation mode')
        ->assertDontSee('Back to proposal workspace')
        ->assertSee('Drag over the part that needs revision, then add a comment.')
        ->assertDontSee('Highlight text')
        ->assertDontSee('Mark an area')
        ->assertDontSee('Place a pin')
        ->assertSee('<meta name="app-url" content="'.url('/').'">', false)
        ->assertSee('Add comment')
        ->assertSee('What needs to change?')
        ->assertDontSee('Where should the faculty make this change?')
        ->assertSee('Restore mangrove plots', false)
        ->assertDontSee('Link to an editor field (optional)')
        ->assertDontSee('x-model="draftEditorTarget"', false)
        ->assertSee('Comments')
        ->assertSee('data-edit-annotation', false)
        ->assertSee('data-remove-annotation', false)
        ->assertDontSee('Revision comments')
        ->assertDontSee('highlight(s) on this file')
        ->assertDontSee('Highlights saved')
        ->assertDontSee('paper(s)')
        ->assertDontSee('comment(s)')
        ->assertDontSee('Remove highlight')
        ->assertSee('data-annotation-tools-guide', false)
        ->assertSee(route('topics.show', $this->topic).'?decision=revision_requested#file-review-card-'.$this->file->id, false)
        ->assertSee('Save changes')
        ->assertSee('Expand paper')
        ->assertSee('Focused PDF review workspace')
        ->assertDontSee('Zoom out')
        ->assertDontSee('Edit in proposal workspace');

    $response = $this->actingAs($this->head)->postJson(
        route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]),
        [
            'annotation_type' => ProposalFileAnnotation::TYPE_TEXT,
            'page_number' => 2,
            'selected_text' => 'Revise the sampling method.',
            'rectangles' => [[
                'x' => 0.15,
                'y' => 0.25,
                'width' => 0.4,
                'height' => 0.03,
            ]],
            'comment' => 'State the sample size and selection criteria.',
            'editor_target' => 'objective-1',
        ],
    );

    $response->assertCreated()
        ->assertJsonPath('pageNumber', 2)
        ->assertJsonPath('editorTarget', 'objective-1')
        ->assertJsonPath('editorTargetLabel', 'Restore mangrove plots — objective')
        ->assertJsonPath('state', 'draft');

    $annotation = ProposalFileAnnotation::sole();
    expect($annotation->proposal_version_file_id)->toBe($this->file->id)
        ->and($annotation->reviewer_id)->toBe($this->head->id)
        ->and($annotation->rectangles[0]['x'])->toEqual(0.15)
        ->and($annotation->editor_target)->toBe('objective-1')
        ->and($annotation->comment)->toBe('State the sample size and selection criteria.');

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('savedHighlightCount: 1', false);

    $this->actingAs($this->faculty)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertNotFound();
});

test('research head can annotate a submitted DOCX through its PDF preview', function () {
    Storage::disk('local')->delete($this->file->file_path);
    Storage::disk('local')->put('proposal-packages/work-plan.docx', 'submitted work plan');
    $this->file->update([
        'file_path' => 'proposal-packages/work-plan.docx',
        'original_filename' => 'work-plan.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => 19,
        'checksum' => hash('sha256', 'submitted work plan'),
    ]);

    $this->actingAs($this->head)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()
        ->assertViewHas('annotationConfiguration', fn (array $configuration): bool => $configuration['pdfUrl'] === route(
            'topics.versions.files.view',
            [$this->topic, $this->version, $this->file],
        ));

    $this->actingAs($this->head)
        ->postJson(route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]), [
            'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
            'page_number' => 1,
            'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
            'comment' => 'Clarify this revised activity.',
            'editor_target' => 'activity-1',
        ])
        ->assertCreated()
        ->assertJsonPath('editorTarget', 'activity-1');
});

test('revision targets must belong to the annotated paper', function (string $target) {
    $this->actingAs($this->head)->postJson(
        route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]),
        [
            'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
            'page_number' => 1,
            'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
            'comment' => 'Update this field.',
            'editor_target' => $target,
        ],
    )->assertUnprocessable()->assertJsonValidationErrors('editor_target');

    expect($this->file->annotations()->count())->toBe(0);
})->with(['rationale', 'activity-99', 'input[name="secret"]']);

test('unsent annotations cannot open a revision editor', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Private draft comment.',
        'editor_target' => 'activity-1',
    ]);

    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.revision', [
        'topic' => $this->topic,
        'annotation' => $annotation->id,
    ]))->assertNotFound();
    expect(ProposalDraft::query()->where('topic_id', $this->topic->id)->exists())->toBeFalse();
});

test('revision field choices omit hidden controls and tolerate missing rows', function () {
    $catalog = app(ProposalRevisionTargetCatalog::class);
    $budget = new ProposalVersionFile([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'source_data' => ['staff' => null, 'custom_mooe_items' => '', 'mooe_total_override' => 0],
    ]);
    $targets = array_column($catalog->forFile($budget), 'value');
    expect($targets)->toContain('mooe-total-override')->not->toContain('co-total-override', 'project-total-override');

    $expenses = new ProposalVersionFile([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'source_data' => ['items' => [['category' => 'mooe', 'account' => 'Contingency']]],
    ]);
    $targets = array_column($catalog->forFile($expenses), 'value');
    expect($targets)->toContain('expense-purpose-1', 'expense-unit-cost-1')
        ->not->toContain('expense-quantity-1', 'expense-details-1', 'expense-particulars-1');
});

test('revision field links fall back safely when repeated rows change', function () {
    $catalog = app(ProposalRevisionTargetCatalog::class);
    $source = $this->file->source_data;
    $source['entries'][0]['activity'] = 'The corrected activity';
    expect($catalog->targetForDraft($this->file, 'activity-1', $source))->toBe('activity-1');
    $source['entries'][] = $source['entries'][0];
    expect($catalog->targetForDraft($this->file, 'activity-1', $source))->toBeNull()
        ->and($catalog->targetForDraft($this->file, 'work-plan-objectives-heading', $source))->toBe('work-plan-objectives-heading');
});

test('research head can remove an unsent highlight', function () {
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Remove this draft comment.',
    ]);

    $this->actingAs($this->head)
        ->deleteJson(route('topics.versions.files.annotations.destroy', [
            $this->topic,
            $this->version,
            $this->file,
            $annotation,
        ]))
        ->assertNoContent();

    $this->assertDatabaseMissing('proposal_file_annotations', ['id' => $annotation->id]);
});

test('research head can draft highlights while a legacy review is in progress', function () {
    $this->topic->update(['status' => 'expert_review']);

    $this->actingAs($this->head)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()
        ->assertSee('Back to review')
        ->assertDontSee('fixed bottom-4 right-4 z-40', false)
        ->assertDontSee('Annotation mode')
        ->assertSee('Comments remain drafts until you send the revision request.')
        ->assertSee('Drag over the part that needs revision, then add a comment.')
        ->assertDontSee('Highlight text')
        ->assertDontSee('Mark an area')
        ->assertDontSee('Send revision request');

    $this->actingAs($this->head)
        ->postJson(route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]), [
            'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
            'page_number' => 1,
            'rectangles' => [[
                'x' => 0.1,
                'y' => 0.2,
                'width' => 0.3,
                'height' => 0.2,
            ]],
            'comment' => 'Revise this table before the Research Head records the decision.',
        ])
        ->assertCreated()
        ->assertJsonPath('state', 'draft');
});

test('a revision request cannot be sent until every selected PDF has a highlighted comment', function () {
    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'redirect_to' => 'topic',
            'comment' => 'Please revise the work plan.',
            'revision_file_ids' => [$this->file->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('revision_file_ids');

    expect($this->topic->fresh()->status)->toBe('pending')
        ->and($this->topic->reviews()->count())->toBe(0)
        ->and($this->faculty->notifications()->count())->toBe(0);
});

test('a non PDF revision requires exact file specific instructions', function () {
    Storage::disk('local')->put('proposal-packages/expenses.xlsx', 'spreadsheet');
    $spreadsheet = $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'position' => 0,
        'file_path' => 'proposal-packages/expenses.xlsx',
        'original_filename' => 'expenses.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'file_size' => 1024,
        'checksum' => str_repeat('c', 64),
        'is_carried_forward' => false,
    ]);

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Revision instructions')
        ->assertSee('This document cannot be highlighted. Describe the exact location and change needed.');

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'redirect_to' => 'topic',
            'comment' => 'Please correct the expense breakdown.',
            'revision_file_ids' => [$spreadsheet->id],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('revision_file_notes.'.$spreadsheet->id);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'redirect_to' => 'topic',
            'comment' => 'Please correct the expense breakdown.',
            'revision_file_ids' => [$spreadsheet->id],
            'revision_file_notes' => [
                $spreadsheet->id => 'In Sheet 2, correct cells D12 through D18 using the approved travel rates.',
            ],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic));

    $fileRevision = $this->topic->reviews()->latest()->firstOrFail()->fileRevisions()->sole();
    expect($this->topic->fresh()->status)->toBe('revision_requested')
        ->and($fileRevision->proposal_version_file_id)->toBe($spreadsheet->id)
        ->and($fileRevision->revision_note)->toContain('cells D12 through D18')
        ->and($this->faculty->notifications()->sole()->data['url'])
        ->toBe(route('topics.show', $this->topic).'#submit-revision');
});

test('sending a revision request publishes highlights for the faculty', function () {
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Replace this table with the corrected quarterly schedule.',
        'editor_target' => 'activity-1',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'redirect_to' => 'topic',
            'comment' => 'Please address the highlighted revision comments.',
            'revision_file_ids' => [$this->file->id],
            'revision_file_notes' => [
                $this->file->id => 'See the highlighted comment in ATHENA.',
            ],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic));

    $fileRevision = $this->topic->reviews()->latest()->firstOrFail()->fileRevisions()->sole();
    expect($this->topic->fresh()->status)->toBe('revision_requested')
        ->and($annotation->fresh()->topic_review_file_revision_id)->toBe($fileRevision->id)
        ->and($fileRevision->annotations()->count())->toBe(1);

    $this->actingAs($this->faculty)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()
        ->assertDontSee('Read-only annotations')
        ->assertSee('Replace this table with the corrected quarterly schedule.')
        ->assertSee('Restore mangrove plots', false)
        ->assertSee('annotation.editorTargetLabel', false)
        ->assertSee('Revise Attachment A: Work Plan')
        ->assertSee(route('topics.show', $this->topic).'#submit-revision', false);

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Revisions requested')
        ->assertDontSee('Focus this field')
        ->assertSee('data-revision-pdf-frame', false)
        ->assertSee('data-annotation-id="'.$annotation->id.'"', false);

    $targetedResponse = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.revision', [
            'topic' => $this->topic,
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'annotation' => $annotation,
        ]));
    $revisionDraft = ProposalDraft::query()->where('topic_id', $this->topic->id)->sole();
    $targetedResponse->assertRedirect(route('faculty.proposal-drafts.work-plan.edit', [
        'proposalDraft' => $revisionDraft,
        'revision_target' => 'activity-1',
        'revision_annotation' => $annotation->id,
    ]));

    $this->get($targetedResponse->headers->get('Location'))
        ->assertOk()
        ->assertSee('data-revision-target="activity-1"', false)
        ->assertSee('Replace this table with the corrected quarterly schedule.')
        ->assertSee('Research Head comment')
        ->assertSee('This comment stays visible while you edit the marked section below.')
        ->assertSee('data-revision-context-help', false)
        ->assertSee('All revision tasks');

    $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', [
        'proposalDraft' => $revisionDraft,
        'revision_annotation' => $annotation->id,
    ]))->assertOk()->assertDontSee('data-revision-context', false);

    $fileRevision->update(['resolved_at' => now()]);
    $this->get($targetedResponse->headers->get('Location'))
        ->assertOk()->assertDontSee('data-revision-context', false);
    $this->get(route('faculty.proposal-drafts.revision', [
        'topic' => $this->topic,
        'annotation' => $annotation->id,
    ]))->assertNotFound();
    $fileRevision->update(['resolved_at' => null]);

    $response = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.revision', [
            'topic' => $this->topic,
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        ]));
    $response->assertRedirect(route('faculty.proposal-drafts.work-plan.edit', $revisionDraft));

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $revisionDraft))
        ->assertOk()
        ->assertSee('Required PDF attachments');
});

test('the revision notification deep-links the faculty to the first highlighted comment', function () {
    $firstAnnotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Tighten the work-plan narrative here.',
    ]);
    $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_TEXT,
        'page_number' => 3,
        'rectangles' => [['x' => 0.2, 'y' => 0.4, 'width' => 0.4, 'height' => 0.03]],
        'comment' => 'Replace with a clearer sampling table.',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'redirect_to' => 'topic',
            'comment' => 'Please address the highlighted comments in the work plan.',
            'revision_file_ids' => [$this->file->id],
            'revision_file_notes' => [
                $this->file->id => 'See the highlighted comments in ATHENA.',
            ],
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic));

    $expectedUrl = route('topics.show', ['topic' => $this->topic, 'revision_annotation' => $firstAnnotation->id])
        .'#submit-revision';

    expect($this->faculty->notifications()->sole()->data['url'])->toBe($expectedUrl);

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-annotation-id="'.$firstAnnotation->id.'"', false)
        ->assertSee('Revisions requested')
        ->assertSee('data-revision-pdf-frame', false)
        ->assertDontSee('Focus editor');
});

test('a downloaded generated paper is staged in its matching revision attachment', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => 'revision_requested',
    ]);
    $review->fileRevisions()->create([
        'proposal_version_file_id' => $this->file->id,
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'original_filename' => $this->file->original_filename,
        'revision_note' => 'Correct the work plan.',
    ]);
    Storage::disk('local')->put('proposal-packages/coastal-habitat.pdf', '%PDF-1.4 primary');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.revision', $this->topic))
        ->assertRedirect();

    $draft = ProposalDraft::query()->where('topic_id', $this->topic->id)->sole();
    $revisionCollaborator = User::factory()->create();
    $revisionCollaborator->assignRole('faculty');
    $draft->members()->create([
        'user_id' => $revisionCollaborator->id,
        'name' => $revisionCollaborator->name,
        'email' => $revisionCollaborator->email,
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.revision-files.store', $draft), [
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'file' => UploadedFile::fake()->create('coastal-work-plan.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertOk()
        ->assertJsonPath('filename', 'coastal-work-plan.docx')
        ->assertJsonPath('redirect_url', route('topics.show', $this->topic).'#review-and-submit');

    $stagedFile = $draft->fresh()->documents()
        ->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)
        ->firstOrFail();

    expect($stagedFile->original_filename)->toBe('coastal-work-plan.docx')
        ->and($stagedFile->file_path)->not->toBeNull();
    Storage::disk('local')->assertExists($stagedFile->file_path);

    $response = $this->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Replacement ready')
        ->assertSee('coastal-work-plan.docx');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//article[@data-revision-document="work_plan"]//input[@name="work_plan"][not(@required)]')->length)->toBe(1);

    $this->actingAs($this->faculty)
        ->patch(route('faculty.topics.resubmit', $this->topic), [
            'revision_draft_id' => $draft->id,
            'title' => $this->topic->title,
            'description' => 'Updated work plan from the proposal workspace.',
            'estimated_budget' => 50000,
            'estimated_duration_months' => 12,
        ])
        ->assertRedirect(route('faculty.dashboard'));

    expect($this->topic->fresh()->status)->toBe('resubmitted')
        ->and(ProposalDraft::query()->where('topic_id', $this->topic->id)->exists())->toBeFalse();
    expect($this->topic->collaborators()->sole()->user_id)->toBe($revisionCollaborator->id);
    expect($this->topic->fresh()->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->original_filename)
        ->toBe('coastal-work-plan.docx');
});

test('faculty revision cards keep requested feedback and replacement inputs together', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => 'revision_requested',
        'comment' => 'Correct the schedule before submitting.',
    ]);
    $revision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $this->file->id,
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'original_filename' => $this->file->original_filename,
        'revision_note' => 'Move planting to the wet season.',
    ]);
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'topic_review_file_revision_id' => $revision->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Start planting in June.',
    ]);

    $response = $this->actingAs($this->faculty)->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Revisions requested')
        ->assertSee('Update 1 file and submit it for another review.')
        ->assertSee('data-revision-dialog', false)
        ->assertDontSee('Faculty action required')
        ->assertDontSee('What happens next')
        ->assertDontSee('Requested revision tasks')
        ->assertDontSee('Paper-level feedback')
        ->assertSee('Revision in progress')
        ->assertSee('Working draft')
        ->assertSee('including added images')
        ->assertSee('Latest submitted')
        ->assertSee('The current revision is still a working draft.')
        ->assertSee('Submitted version comparison')
        ->assertSee('It does not inspect document content')
        ->assertSeeInOrder(['Revisions requested', 'Start planting in June.', 'Summary of changes', 'Decision history'])
        ->assertDontSee('Replace another file');

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $card = '//form[@id="submit-revision"]//article[@data-revision-document="work_plan"]';

    expect($xpath->query($card)->length)->toBe(1)
        ->and($xpath->query($card.'//input[@name="work_plan"][@required]')->length)->toBe(1)
        ->and($xpath->query($card.'//select[@data-revision-comment]/option[@data-annotation-id="'.$annotation->id.'"]')->length)->toBe(1)
        ->and($xpath->query($card.'//iframe[@data-revision-editor-frame][contains(@src, "revision_embed=1")]')->length)->toBe(1)
        ->and($xpath->query($card.'//dialog//section[contains(@class, "revision-feedback")]/following-sibling::section[contains(@class, "revision-editor-panel")]//iframe[@data-revision-editor-frame]')->length)->toBe(1)
        ->and($xpath->query($card.'//dialog//section[contains(@class, "revision-feedback")]//iframe[@data-revision-pdf-frame]')->length)->toBe(1)
        ->and($xpath->query($card.'//a[contains(@href, "proposal-drafts")]')->length)->toBe(0)
        ->and($xpath->query($card.'//button[@data-revision-open]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-other-revision-files]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="expense_breakdown"]')->length)->toBe(0)
        ->and($xpath->query('//details[@data-revision-proposal-details][not(@open)]')->length)->toBe(1)
        ->and($xpath->query('//details[summary//h3[contains(., "Decision history")]][not(@open)]')->length)->toBe(1)
        ->and($xpath->query('//form[@id="submit-revision"]//button[@type="submit"]')->length)->toBe(1);

    $this->actingAs($this->head)->get(route('topics.show', $this->topic))
        ->assertOk()->assertDontSee('id="submit-revision"', false);
});

test('a successful revision return shows a clear server-confirmed receipt', function () {
    $this->topic->update(['status' => 'revision_requested']);

    $this->actingAs($this->faculty)
        ->patch(route('faculty.topics.resubmit', $this->topic), [
            'title' => $this->topic->title,
            'estimated_budget' => 50000,
            'estimated_duration_months' => 12,
            'redirect_to' => 'topic',
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHas('revision_submitted', true)
        ->assertSessionHas('success', 'Revised proposal submitted for another review.');

    expect($this->topic->fresh()->status)->toBe('resubmitted');

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Revision sent successfully')
        ->assertSee('It is now waiting for the Research Head’s review.')
        ->assertSee('data-revision-submission-success', false);
});

test('multiple requested curriculum vitae share one replacement input', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => 'revision_requested',
    ]);
    foreach ([0, 1] as $position) {
        $file = $this->version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            'position' => $position,
            'file_path' => 'proposal-packages/cv-'.$position.'.pdf',
            'original_filename' => 'cv-'.$position.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        $review->fileRevisions()->create([
            'proposal_version_file_id' => $file->id,
            'document_type' => $file->document_type,
            'original_filename' => $file->original_filename,
            'revision_note' => 'Update researcher '.$position.' qualifications.',
        ]);
    }
    $response = $this->actingAs($this->faculty)->get(route('topics.show', $this->topic))
        ->assertOk()->assertSee('Update researcher 0 qualifications.')->assertSee('Update researcher 1 qualifications.');

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//article[@data-revision-document="curriculum_vitae"]')->length)->toBe(1)
        ->and($xpath->query('//form[@id="submit-revision"]//input[@name="curricula_vitae[]"][@multiple][@required]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-other-revision-files]')->length)->toBe(0);
});

test('revision validation opens invalid metadata and rejects unrequested files', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $this->actingAs($this->faculty)
        ->from(route('topics.show', $this->topic))
        ->patch(route('faculty.topics.resubmit', $this->topic), [
            'title' => $this->topic->title,
            'estimated_budget' => -1,
            'estimated_duration_months' => 12,
            'curricula_vitae' => [UploadedFile::fake()->create('invalid.txt', 1, 'text/plain')],
        ])->assertSessionHasErrorsIn('resubmission', ['estimated_budget', 'curricula_vitae.0']);

    $response = $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('topics.show', $this->topic))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//details[@data-revision-proposal-details][@open]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-other-revision-files]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="estimated_budget"][@value="-1"]')->length)->toBe(1)
        ->and($response->getContent())->not->toContain('Replace another file');
});

test('revision editors open inside the feedback page with no application navigation', function (string $documentType, string $slug) {
    $this->topic->update(['status' => 'revision_requested']);
    $response = $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.revision', [
        'topic' => $this->topic,
        'document_type' => $documentType,
        'revision_embed' => 1,
    ]))->assertRedirect();

    $draft = ProposalDraft::query()->where('topic_id', $this->topic->id)->sole();
    $url = route('faculty.proposal-drafts.'.$slug.'.edit', [$draft, 'revision_embed' => 1]);
    $response->assertRedirect($url);
    $page = $this->get($url)->assertOk()
        ->assertViewIs('faculty.proposal-drafts.'.$slug.'.edit')
        ->assertSee('data-revision-embedded', false)
        ->assertSee('data-paper-form', false)
        ->assertSee('data-revision-editor-context', false)
        ->assertSee('data-original-source', false)
        ->assertDontSee('data-app-shell', false)
        ->assertDontSee('Exit editor');

    $dom = new DOMDocument;
    @$dom->loadHTML($page->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//nav')->length)->toBe(0)
        ->and($xpath->query('//*[@data-revision-editor-context][@data-topic-id="'.$this->topic->id.'"]')->length)->toBe(1);

    $originalPage = $this->get(route('faculty.proposal-drafts.'.$slug.'.edit', $draft))
        ->assertOk()->assertViewIs('faculty.proposal-drafts.'.$slug.'.edit');
    $originalDom = new DOMDocument;
    @$originalDom->loadHTML($originalPage->getContent());
    $originalXpath = new DOMXPath($originalDom);
    $fontSelector = '//head/link[@rel="stylesheet"][contains(@href, "fonts.bunny.net")]';
    expect($xpath->query($fontSelector)->length)->toBe(1)
        ->and($xpath->query($fontSelector)->item(0)->getAttribute('href'))
        ->toBe($originalXpath->query($fontSelector)->item(0)->getAttribute('href'));

    $fieldStyles = function (DOMXPath $document): array {
        $fields = [];
        foreach ($document->query('//form[@data-paper-form]//*[self::input or self::textarea or self::select or self::label]') as $field) {
            $fields[] = [$field->nodeName, $field->getAttribute('name'), $field->getAttribute('id'), $field->getAttribute('class')];
        }

        return $fields;
    };
    expect($fieldStyles($xpath))->not->toBeEmpty()->toBe($fieldStyles($originalXpath));

    $this->get(route('faculty.proposal-drafts.revision', [
        'topic' => $this->topic, 'document_type' => $documentType, 'revision_embed' => 1,
    ]))->assertRedirect($url);
    expect(ProposalDraft::query()->where('topic_id', $this->topic->id)->count())->toBe(1);
    $this->actingAs($this->head)->get($url)->assertForbidden();
})->with([
    ['work_plan', 'work-plan'],
    ['detailed_proposal', 'detailed-proposal'],
    ['line_item_budget', 'line-item-budget'],
    ['expense_breakdown', 'expense-breakdown'],
    ['curriculum_vitae', 'curriculum-vitae'],
]);

test('embedded editors expose only current published feedback and preserve the revision lock', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested',
    ]);
    $revision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $this->file->id,
        'document_type' => 'work_plan',
        'original_filename' => $this->file->original_filename,
    ]);
    $published = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'topic_review_file_revision_id' => $revision->id,
        'annotation_type' => 'area', 'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'editor_target' => 'activity-1',
        'comment' => 'Move fieldwork to the wet season.',
    ]);
    $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => 'area', 'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Unpublished private note.',
    ]);
    $response = $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.revision', [
        'topic' => $this->topic, 'document_type' => 'work_plan', 'revision_embed' => 1,
    ]))->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk()
        ->assertSee('Move fieldwork to the wet season.')
        ->assertSee('activity-1')
        ->assertDontSee('Unpublished private note.');

    $this->get(route('topics.versions.files.annotations.index', [
        $this->topic, $this->version, $this->file,
        'revision_embed' => 1, 'annotation' => $published->id,
    ]))->assertOk()->assertDontSee('data-app-shell', false)->assertViewHas('annotationConfiguration', fn ($config) => $config['revisionUrl'] === null);

    $draft = ProposalDraft::query()->where('topic_id', $this->topic->id)->sole();
    $document = $draft->documents()->where('document_type', 'work_plan')->sole();
    $entries = $this->file->source_data['entries'];
    $entries[0]['activity'] = 'Wet season planting and monitoring';
    $saved = $this->putJson(route('faculty.proposal-drafts.work-plan.update', $draft), [
        'document_version' => $document->lock_version,
        'entries' => $entries,
    ])->assertOk();

    $this->postJson(route('faculty.proposal-drafts.revision-files.store', $draft), [
        'document_type' => 'work_plan',
        'document_version' => $document->lock_version,
        'file' => UploadedFile::fake()->create('outdated.docx', 50),
    ])->assertUnprocessable()->assertJsonValidationErrors('document_version');

    $pdfConverter = new class implements DocumentPdfConverter
    {
        public ?string $receivedDocx = null;

        public function convertDocx(string $contents): string
        {
            $this->receivedDocx = $contents;

            return "%PDF-1.7\nconverted revision work plan";
        }

        public function convertXlsx(string $contents): string
        {
            throw new LogicException('An XLSX conversion was not expected.');
        }
    };
    app()->instance(DocumentPdfConverter::class, $pdfConverter);

    $download = $this->withHeader('X-Revision-PDF', '1')
        ->postJson(route('faculty.proposal-drafts.work-plan.download', $draft), [
            'entries' => $entries,
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('coastal-habitat-restoration-work-plan.pdf');

    $this->postJson(route('faculty.proposal-drafts.revision-files.store', $draft), [
        'document_type' => 'work_plan',
        'document_version' => $saved->json('document_version'),
        'file' => UploadedFile::fake()->createWithContent('coastal-habitat-restoration-work-plan.pdf', $download->streamedContent()),
    ])->assertOk()->assertJsonPath('draft_id', $draft->id)->assertJsonPath('document_type', 'work_plan');

    expect($this->topic->fresh()->status)->toBe('revision_requested')
        ->and($revision->fresh()->resolved_at)->toBeNull()
        ->and($draft->fresh()->documents()->where('document_type', 'work_plan')->sole()->source_data['entries'][0]['activity'])->toBe('Wet season planting and monitoring');

    $this->actingAs($this->head)->patch(route('research_head.topics.updateStatus', $this->topic), [
        'status' => 'revision_requested',
        'revision_file_ids' => [$this->file->id],
    ])->assertSessionHasErrors('status');
    expect($this->topic->reviews()->count())->toBe(1);

    Storage::disk('local')->put('proposal-packages/coastal-habitat.pdf', '%PDF-1.4 primary');
    $this->actingAs($this->faculty)->patch(route('faculty.topics.resubmit', $this->topic), [
        'revision_draft_id' => $draft->id,
        'title' => $this->topic->title,
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'redirect_to' => 'topic',
    ])->assertRedirect(route('topics.show', $this->topic));

    $revisedFile = $this->topic->fresh()->latestVersion->files->firstWhere('document_type', 'work_plan');
    expect($this->topic->fresh()->status)->toBe('resubmitted')
        ->and($revision->fresh()->resolved_at)->not->toBeNull()
        ->and($revisedFile->source_data['entries'][0]['activity'])->toBe('Wet season planting and monitoring')
        ->and($revisedFile->original_filename)->toBe('coastal-habitat-restoration-work-plan.pdf')
        ->and($revisedFile->mime_type)->toBe('application/pdf')
        ->and(Storage::disk('local')->get($revisedFile->file_path))->toBe("%PDF-1.7\nconverted revision work plan")
        ->and($pdfConverter->receivedDocx)->toStartWith('PK');
});

test('pins can be saved without choosing an editor field and require one location', function () {
    $payload = [
        'annotation_type' => 'pin',
        'page_number' => 1,
        'rectangles' => [['x' => 0.3, 'y' => 0.4, 'width' => 0.001, 'height' => 0.001]],
        'comment' => 'Clarify this activity.',
    ];
    $url = route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]);
    $this->actingAs($this->head)->postJson($url, $payload)
        ->assertCreated()
        ->assertJsonPath('type', 'pin')
        ->assertJsonPath('editorTarget', null)
        ->assertJsonPath('canEdit', true);

    $payload['rectangles'][] = $payload['rectangles'][0];
    $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('rectangles');
    expect($this->file->annotations()->count())->toBe(1);
});

test('draft comments can be edited without changing their marked location or author', function () {
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => 'area',
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Original instruction.',
        'editor_target' => 'activity-1',
    ]);
    $url = route('topics.versions.files.annotations.update', [$this->topic, $this->version, $this->file, $annotation]);
    $this->actingAs($this->head)->patchJson($url, [
        'comment' => 'Move the planting activity to June.',
        'editor_target' => null,
        'page_number' => 10,
        'reviewer_id' => $this->faculty->id,
    ])->assertOk()->assertJsonPath('comment', 'Move the planting activity to June.')->assertJsonPath('editorTarget', null);

    expect($annotation->fresh()->page_number)->toBe(1)
        ->and($annotation->fresh()->reviewer_id)->toBe($this->head->id)
        ->and($annotation->fresh()->rectangles)->toBe($annotation->rectangles)
        ->and($this->file->annotations()->count())->toBe(1);

    $this->patchJson($url, ['comment' => ''])->assertUnprocessable()->assertJsonValidationErrors('comment');
    $this->patchJson($url, ['comment' => 'Valid instruction', 'editor_target' => 'not-a-field'])
        ->assertUnprocessable()->assertJsonValidationErrors('editor_target');
});

test('comment updates reject other authors and files and lock after sending', function () {
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => 'pin',
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.001, 'height' => 0.001]],
        'comment' => 'Original instruction.',
    ]);
    $url = route('topics.versions.files.annotations.update', [$this->topic, $this->version, $this->file, $annotation]);
    $otherHead = User::factory()->create();
    $otherHead->assignRole('research_head');
    $this->actingAs($otherHead)->patchJson($url, ['comment' => 'Changed'])->assertForbidden();
    $this->actingAs($this->faculty)->patchJson($url, ['comment' => 'Changed'])->assertForbidden();

    $otherFile = $this->file->replicate();
    $otherFile->position = 1;
    $otherFile->save();
    $this->actingAs($this->head)->patchJson(
        route('topics.versions.files.annotations.update', [$this->topic, $this->version, $otherFile, $annotation]),
        ['comment' => 'Changed'],
    )->assertNotFound();

    $this->patch(route('research_head.topics.updateStatus', $this->topic), [
        'status' => 'revision_requested',
        'redirect_to' => 'topic',
        'revision_file_ids' => [$this->file->id],
    ])->assertSessionHasNoErrors();
    expect($annotation->fresh()->topic_review_file_revision_id)->not->toBeNull();
    $this->patchJson($url, ['comment' => 'Changed after sending'])->assertForbidden();
    $this->deleteJson(route('topics.versions.files.annotations.destroy', [$this->topic, $this->version, $this->file, $annotation]))->assertForbidden();
    expect($annotation->fresh()->comment)->toBe('Original instruction.');
});

test('a highlight automatically opens the matching section and comment for faculty without a field picker', function () {
    $regions = [
        ['id' => 'section-project-information', 'label' => 'Project Information', 'pageNumber' => 1, 'x' => .1, 'y' => .1, 'width' => .8, 'height' => .2],
        ['id' => 'section-schedule', 'label' => 'Objectives and Gantt Schedule', 'pageNumber' => 1, 'x' => .1, 'y' => .3, 'width' => .8, 'height' => .6],
    ];
    $source = $this->file->source_data;
    $source['_revision_sections'] = [
        'version' => 1,
        'checksum' => hash('sha256', Storage::disk('local')->get($this->file->file_path)),
        'regions' => $regions,
    ];
    $this->file->update(['source_data' => $source]);
    $response = $this->actingAs($this->head)->postJson(
        route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]),
        ['annotation_type' => 'area', 'page_number' => 1,
            'rectangles' => [['x' => .2, 'y' => .25, 'width' => .3, 'height' => .25]],
            'comment' => 'Move the planting activity to June.',
            'editor_target' => 'section-project-information'],
    )->assertCreated()->assertJsonPath('editorTarget', 'section-schedule');
    $annotation = ProposalFileAnnotation::findOrFail($response->json('id'));
    $this->patchJson(route('topics.versions.files.annotations.update', [$this->topic, $this->version, $this->file, $annotation]),
        ['comment' => 'Move planting to June.', 'editor_target' => null])
        ->assertOk()->assertJsonPath('editorTarget', 'section-schedule');
    $this->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()->assertDontSee('x-model="draftEditorTarget"', false)->assertSee('section-schedule');
    $this->patch(route('research_head.topics.updateStatus', $this->topic), [
        'status' => 'revision_requested', 'redirect_to' => 'topic',
        'revision_file_ids' => [$this->file->id],
        'revision_file_notes' => [$this->file->id => 'See the marked section.'],
    ])->assertRedirect();
    $response = $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.revision', [
        'topic' => $this->topic, 'annotation' => $annotation->id,
    ]))->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk()
        ->assertSee('data-revision-target="section-schedule"', false)
        ->assertSee('data-revision-section="section-schedule"', false)
        ->assertSee('Move planting to June.')
        ->assertSee('View exact PDF highlight');
    $draft = ProposalDraft::where('topic_id', $this->topic->id)->sole();
    $this->get(route('faculty.proposal-drafts.work-plan.edit', [
        'proposalDraft' => $draft, 'revision_embed' => 1,
    ]))->assertOk()->assertSee('section-schedule')->assertSee('Move planting to June.');
});

test('research head records both feedback sources and publishes them together with attribution', function () {
    Notification::fake();
    $this->mock(ProposalRevisionSectionMap::class)->shouldReceive('forFile')->andReturn([]);
    $url = route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]);
    $payload = ['annotation_type' => 'area', 'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Clarify the timeline.'];
    $this->actingAs($this->head)->postJson($url, $payload)->assertCreated()->assertJsonPath('feedbackSource', 'research_head');
    $co = $this->postJson($url, $payload + ['feedback_source' => 'co_evaluator', 'co_evaluator_name' => 'Dr. Maria Santos', 'reviewer_id' => $this->faculty->id])
        ->assertCreated()->assertJsonPath('feedbackAuthor', 'Dr. Maria Santos')->assertJsonPath('reviewer', $this->head->name)->json();
    expect(ProposalFileAnnotation::find($co['id'])->reviewer_id)->toBe($this->head->id);
    $index = route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]);
    $preview = $this->get($index)->assertOk()->assertViewHas('annotationConfiguration', fn ($config) => $config['coEvaluatorName'] === 'Dr. Maria Santos');
    if ($path = getenv('ATHENA_REVIEW_PREVIEW')) {
        file_put_contents($path, $preview->getContent());
    }
    $this->actingAs($this->faculty)->get($index)->assertNotFound();
    $this->actingAs($this->head)->patch(route('research_head.topics.updateStatus', $this->topic), ['status' => 'revision_requested', 'revision_file_ids' => [$this->file->id]])->assertSessionHasNoErrors();
    expect($this->topic->fresh()->status)->toBe('revision_requested')
        ->and(ProposalFileAnnotation::whereNotNull('topic_review_file_revision_id')->count())->toBe(2);
    $this->actingAs($this->faculty)->get($index)->assertOk()->assertViewHas('annotationConfiguration', fn ($config) => count($config['annotations']) === 2 && $config['annotations'][1]['feedbackLabel'] === 'Co-evaluator · Dr. Maria Santos');
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Co-evaluator · Dr. Maria Santos');
    $this->actingAs($this->head)->patchJson(route('topics.versions.files.annotations.update', [$this->topic, $this->version, $this->file, $co['id']]), ['comment' => 'Change published feedback'])->assertForbidden();
});

test('co evaluator feedback requires a name and faculty cannot enter it', function () {
    $payload = ['annotation_type' => 'area', 'page_number' => 1, 'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]], 'comment' => 'Feedback', 'feedback_source' => 'co_evaluator'];
    $url = route('topics.versions.files.annotations.store', [$this->topic, $this->version, $this->file]);
    $this->actingAs($this->head)->postJson($url, $payload + ['co_evaluator_name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('co_evaluator_name');
    $this->postJson($url, array_replace($payload, ['feedback_source' => 'administrator']))->assertUnprocessable()->assertJsonValidationErrors('feedback_source');
    $this->actingAs($this->faculty)->postJson($url, $payload + ['co_evaluator_name' => 'Dr. Santos'])->assertForbidden();
});

test('editing feedback preserves the original co evaluator and recorder', function () {
    $annotation = $this->file->annotations()->create(['reviewer_id' => $this->head->id, 'feedback_source' => 'co_evaluator', 'co_evaluator_name' => 'Dr. Santos', 'annotation_type' => 'area', 'page_number' => 1, 'rectangles' => [], 'comment' => 'Original feedback']);
    $this->actingAs($this->head)->patchJson(route('topics.versions.files.annotations.update', [$this->topic, $this->version, $this->file, $annotation]), ['comment' => 'Corrected transcription', 'feedback_source' => 'research_head', 'co_evaluator_name' => 'Different name', 'reviewer_id' => $this->faculty->id])
        ->assertOk()->assertJsonPath('feedbackSource', 'co_evaluator')->assertJsonPath('feedbackAuthor', 'Dr. Santos')->assertJsonPath('comment', 'Corrected transcription');
    expect($annotation->fresh()->reviewer_id)->toBe($this->head->id);
    $this->deleteJson(route('topics.versions.files.annotations.destroy', [$this->topic, $this->version, $this->file, $annotation]))->assertNoContent();
});
