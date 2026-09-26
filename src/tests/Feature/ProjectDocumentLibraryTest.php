<?php

use App\Models\ProjectDocument;
use App\Models\ProposalVersionFile;
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

    $this->faculty = User::factory()->create(['name' => 'Faculty Owner']);
    $this->faculty->assignRole('faculty');
    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Clean Water Research',
        'estimated_budget' => 25_000,
        'estimated_duration_months' => 8,
        'status' => 'pending',
    ]);
});

test('the faculty project leader can drag multiple PDFs into a categorized project folder', function () {
    $response = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)->post(route('topics.documents.store', $this->topic), [
        'category' => ProjectDocument::CATEGORY_REVIEWS_RESPONSES,
        'note' => 'Corrected after the generated form used an outdated logo.',
        'documents' => [
            UploadedFile::fake()->createWithContent('corrected-checklist.pdf', "%PDF-1.7\ncorrected checklist"),
            UploadedFile::fake()->createWithContent('comment-response.pdf', "%PDF-1.7\ncomment response"),
        ],
    ]);

    $response
        ->assertRedirect(route('topics.show', $this->topic).'#project-files')
        ->assertSessionHas('project_documents_open', true);

    $documents = $this->topic->projectDocuments()->oldest()->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('category')->unique()->all())->toBe([ProjectDocument::CATEGORY_REVIEWS_RESPONSES])
        ->and($documents->pluck('uploaded_by')->unique()->all())->toBe([$this->faculty->id]);

    Storage::disk('local')->assertExists($documents->pluck('file_path')->all());

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-project-documents-floating-trigger', false)
        ->assertSee('Reviews &amp; responses', false)
        ->assertSee('corrected-checklist.pdf')
        ->assertSee('comment-response.pdf')
        ->assertSee('Corrected after the generated form used an outdated logo.');
});

test('project document uploads accept only PDFs and no more than ten files', function () {
    $response = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->from(route('topics.show', $this->topic))
        ->post(route('topics.documents.store', $this->topic), [
            'category' => ProjectDocument::CATEGORY_SUPPORTING_FILES,
            'documents' => [UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ]);

    $response->assertSessionHasErrors(['documents.0'], null, 'projectDocuments');
    expect($this->topic->projectDocuments()->count())->toBe(0);
});

test('another faculty member cannot add files to a project they do not own', function () {
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($otherFaculty)
        ->post(route('topics.documents.store', $this->topic), [
            'category' => ProjectDocument::CATEGORY_OTHER,
            'documents' => [UploadedFile::fake()->createWithContent('private.pdf', "%PDF-1.7\nprivate")],
        ])
        ->assertForbidden();

    expect($this->topic->projectDocuments()->count())->toBe(0);
});

test('the folder aggregates proposal papers and the released Notice to Proceed', function () {
    Storage::disk('local')->put('proposal-packages/detailed.pdf', '%PDF-1.7 proposal');
    Storage::disk('local')->put('notices-to-proceed/signed.pdf', '%PDF-1.7 notice');

    $version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposal-packages/detailed.pdf',
        'original_filename' => 'detailed-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', '%PDF-1.7 proposal'),
        'title' => $this->topic->title,
        'estimated_budget' => $this->topic->estimated_budget,
        'estimated_duration_months' => $this->topic->estimated_duration_months,
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposal-packages/detailed.pdf',
        'original_filename' => 'detailed-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', '%PDF-1.7 proposal'),
    ]);
    $this->topic->update([
        'status' => 'approved',
        'project_status' => TopicProposal::PROJECT_STATUS_ONGOING,
        'notice_to_proceed_path' => 'notices-to-proceed/signed.pdf',
        'notice_to_proceed_original_filename' => 'notice-to-proceed.pdf',
        'notice_to_proceed_issued_at' => now(),
    ]);
    $this->faculty->assignRole('faculty_researcher');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Proposal papers')
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Notice to Proceed')
        ->assertSee('notice-to-proceed.pdf');
});

test('project document routes are scoped to their project', function () {
    $otherTopic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Another owned project',
        'estimated_budget' => 10_000,
        'estimated_duration_months' => 4,
        'status' => 'pending',
    ]);
    Storage::disk('local')->put('project-documents/'.$this->topic->id.'/record.pdf', '%PDF-1.7 record');
    $document = $this->topic->projectDocuments()->create([
        'uploaded_by' => $this->faculty->id,
        'category' => ProjectDocument::CATEGORY_OTHER,
        'title' => 'Project record',
        'file_path' => 'project-documents/'.$this->topic->id.'/record.pdf',
        'original_filename' => 'record.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 15,
        'checksum' => hash('sha256', '%PDF-1.7 record'),
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.documents.download', [$otherTopic, $document]))
        ->assertNotFound();
});
