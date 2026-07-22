<?php

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
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
        ->assertSee('Replace this table with the corrected quarterly schedule.');
});
