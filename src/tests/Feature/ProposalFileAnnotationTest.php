<?php

use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'expert'] as $role) {
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
        'is_carried_forward' => false,
    ]);
});

test('research head can annotate an exact turned-in PDF while draft comments stay private', function () {
    $this->actingAs($this->head)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()
        ->assertSee('Annotation mode')
        ->assertSee('Select text')
        ->assertSee('Draw area')
        ->assertSee('Highlight &amp; comment', false)
        ->assertSee('What should the faculty revise?')
        ->assertSee('data-annotation-tools-guide', false)
        ->assertSee(route('topics.show', $this->topic).'#review-and-upload-files', false)
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
        ],
    );

    $response->assertCreated()
        ->assertJsonPath('pageNumber', 2)
        ->assertJsonPath('state', 'draft');

    $annotation = ProposalFileAnnotation::sole();
    expect($annotation->proposal_version_file_id)->toBe($this->file->id)
        ->and($annotation->reviewer_id)->toBe($this->head->id)
        ->and($annotation->rectangles[0]['x'])->toEqual(0.15)
        ->and($annotation->comment)->toBe('State the sample size and selection criteria.');

    $this->actingAs($this->faculty)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertNotFound();
});

test('research head can draft highlights while co-evaluator review is in progress', function () {
    $this->topic->update(['status' => 'expert_review']);

    $this->actingAs($this->head)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()
        ->assertSee('Annotation mode')
        ->assertSee('Your highlights and comments are saved as drafts')
        ->assertSee('Select text')
        ->assertSee('Draw area')
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
            'comment' => 'Revise this table after the co-evaluator feedback is complete.',
        ])
        ->assertCreated()
        ->assertJsonPath('state', 'draft');
});

test('sending a revision request publishes highlights for the faculty', function () {
    $annotation = $this->file->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2]],
        'comment' => 'Replace this table with the corrected quarterly schedule.',
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
        ])
        ->assertRedirect(route('topics.show', $this->topic));

    $fileRevision = $this->topic->reviews()->latest()->firstOrFail()->fileRevisions()->sole();
    expect($this->topic->fresh()->status)->toBe('revision_requested')
        ->and($annotation->fresh()->topic_review_file_revision_id)->toBe($fileRevision->id)
        ->and($fileRevision->annotations()->count())->toBe(1);

    $this->actingAs($this->faculty)
        ->get(route('topics.versions.files.annotations.index', [$this->topic, $this->version, $this->file]))
        ->assertOk()
        ->assertSee('Read-only annotations')
        ->assertSee('Replace this table with the corrected quarterly schedule.')
        ->assertSee('Edit in proposal workspace')
        ->assertSee(route('faculty.proposal-drafts.revision', $this->topic), false);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.revision', $this->topic))
        ->assertRedirect(route('faculty.proposal-drafts.show', ProposalDraft::query()->where('topic_id', $this->topic->id)->sole()).'#required-pdf-attachments');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', ProposalDraft::query()->where('topic_id', $this->topic->id)->sole()))
        ->assertOk()
        ->assertSee('Required PDF attachments');
});

test('a downloaded generated paper is staged in its matching revision attachment', function () {
    $this->topic->update(['status' => 'revision_requested']);
    Storage::disk('local')->put('proposal-packages/coastal-habitat.pdf', '%PDF-1.4 primary');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.revision', $this->topic))
        ->assertRedirect();

    $draft = ProposalDraft::query()->where('topic_id', $this->topic->id)->sole();

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

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Automatically uploaded: coastal-work-plan.docx');

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
    expect($this->topic->fresh()->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->original_filename)
        ->toBe('coastal-work-plan.docx');
});
