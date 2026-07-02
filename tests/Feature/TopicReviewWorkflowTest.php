<?php

use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\TopicReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    Role::firstOrCreate(['name' => 'research_head']);
    Role::firstOrCreate(['name' => 'expert']);

    $this->category = ResearchCategory::create(['name' => 'Environment']);
    $this->researchCall = ResearchCall::create([
        'title' => 'Test Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_proposals_per_faculty' => 2,
        'status' => 'open',
    ]);
    $this->researchCall->categories()->attach($this->category);

    TopicProposal::creating(function (TopicProposal $topic) {
        $topic->research_call_id ??= $this->researchCall->id;
        $topic->research_category_id ??= $this->category->id;
        $topic->estimated_duration_months ??= 12;
    });
});

test('a research head can request a revision with comments', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Original proposal',
        'estimated_budget' => 10000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'revision_requested',
        'comment' => 'Clarify the methodology and reduce the travel budget.',
    ]);

    $response->assertRedirect(route('research_head.dashboard'));
    expect($topic->fresh()->status)->toBe('revision_requested');

    $this->assertDatabaseHas('topic_reviews', [
        'topic_id' => $topic->id,
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Clarify the methodology and reduce the travel budget.',
    ]);
});

test('revision and rejection decisions require review comments', function (string $decision) {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal requiring feedback',
        'estimated_budget' => 5000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        ['status' => $decision],
    );

    $response->assertRedirect(route('research_head.dashboard'));
    $response->assertSessionHasErrors('comment');
    expect($topic->fresh()->status)->toBe('pending');
    expect(TopicReview::count())->toBe(0);
})->with(['revision_requested', 'rejected']);

test('faculty can revise and resubmit a proposal after feedback', function () {
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    Storage::disk('local')->put('proposals/original.pdf', 'original document');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Original proposal',
        'description' => 'Original description',
        'estimated_budget' => 10000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'revision_requested',
    ]);

    $originalVersion = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/original.pdf',
        'original_filename' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', 'original document'),
        'title' => 'Original proposal',
        'description' => 'Original description',
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
    ]);

    $response = $this->actingAs($faculty)->patch("/faculty/topics/{$topic->id}/resubmit", [
        'title' => 'Revised proposal',
        'description' => 'Updated methodology',
        'estimated_budget' => 8500,
        'estimated_duration_months' => 10,
        'document' => UploadedFile::fake()->create('revised-proposal.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect(route('faculty.dashboard'));

    $topic->refresh();

    expect($topic->status)->toBe('resubmitted')
        ->and($topic->title)->toBe('Revised proposal')
        ->and($topic->estimated_budget)->toBe('8500.00')
        ->and($topic->versions()->count())->toBe(2)
        ->and($topic->latestVersion->version_number)->toBe(2)
        ->and($topic->latestVersion->title)->toBe('Revised proposal')
        ->and($topic->latestVersion->estimated_budget)->toBe('8500.00')
        ->and($topic->latestVersion->checksum)->toHaveLength(64);

    Storage::disk('local')->assertExists('proposals/original.pdf');
    Storage::disk('local')->assertExists($topic->latestVersion->file_path);

    $this->actingAs($faculty)
        ->get(route('topics.versions.download', [$topic, $originalVersion]))
        ->assertOk()
        ->assertDownload('original.pdf');
});

test('a research head can approve a resubmitted proposal', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Revised proposal',
        'estimated_budget' => 8500,
        'initial_file_path' => 'proposals/original.pdf',
        'final_file_path' => 'proposals/revisions/revised.pdf',
        'status' => 'resubmitted',
    ]);

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Make a small methodology revision.',
    ]);

    $response = $this->actingAs($head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'approved',
        'comment' => 'The requested changes have been addressed.',
        'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect(route('research_head.dashboard'));

    expect($topic->fresh()->status)->toBe('approved')
        ->and($topic->reviews()->count())->toBe(2)
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
});

test('a rejected proposal remains final', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Rejected proposal',
        'estimated_budget' => 25000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        [
            'status' => 'approved',
            'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
        ],
    );

    $response->assertRedirect(route('research_head.dashboard'));
    $response->assertSessionHasErrors('status');
    expect($topic->fresh()->status)->toBe('rejected');
});

test('review feedback and revision controls are visible on both dashboards', function () {
    $this->withoutVite();

    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal awaiting revision',
        'estimated_budget' => 12000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'revision_requested',
    ]);

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Please tighten the literature review.',
    ]);

    $this->actingAs($faculty)
        ->get('/faculty/dashboard')
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Revise and resubmit proposal');

    $this->actingAs($head)
        ->get('/research-head/dashboard')
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Waiting for faculty revision');
});

test('faculty researchers can browse and open only their own research records', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher']);

    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole(['faculty', 'faculty_researcher']);

    $ownTopic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'My catalogued research',
        'description' => 'A visible research record.',
        'estimated_budget' => 14500,
        'initial_file_path' => 'proposals/own.pdf',
        'status' => 'revision_requested',
    ]);

    $ownTopic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/own.pdf',
        'original_filename' => 'own.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $ownTopic->title,
        'description' => $ownTopic->description,
        'estimated_budget' => $ownTopic->estimated_budget,
        'estimated_duration_months' => $ownTopic->estimated_duration_months,
    ]);

    $otherTopic = TopicProposal::create([
        'user_id' => $otherFaculty->id,
        'title' => 'Another faculty research',
        'estimated_budget' => 9000,
        'initial_file_path' => 'proposals/other.pdf',
        'status' => 'pending',
    ]);

    $this->actingAs($faculty)
        ->get('/research')
        ->assertOk()
        ->assertSee('My catalogued research')
        ->assertDontSee('Another faculty research');

    $this->actingAs($faculty)
        ->get("/research/{$ownTopic->id}")
        ->assertOk()
        ->assertSee('revision requested')
        ->assertSee('PHP 14,500.00')
        ->assertSee('Proposal version history')
        ->assertSee('Version 1');

    $this->actingAs($faculty)
        ->get("/research/{$otherTopic->id}")
        ->assertForbidden();
});

test('the research catalog is unavailable to regular faculty and the research head', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($head)
        ->get('/research')
        ->assertForbidden();

    $this->actingAs($faculty)
        ->get('/research')
        ->assertForbidden();
});

test('research support is available only to faculty researchers', function () {
    $this->withoutVite();

    $researcher = User::factory()->create();
    $researcher->assignRole(['faculty', 'faculty_researcher']);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $head = User::factory()->create();
    $head->assignRole('research_head');

    $this->actingAs($researcher)
        ->get('/research-support')
        ->assertOk()
        ->assertSee('Research Support');

    $this->actingAs($faculty)->get('/research-support')->assertForbidden();
    $this->actingAs($head)->get('/research-support')->assertForbidden();
});

test('proposal versions are downloadable only by authorized topic participants', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $topic = TopicProposal::create([
        'user_id' => $owner->id,
        'title' => 'Audited proposal',
        'estimated_budget' => 20000,
        'initial_file_path' => 'proposals/audited.pdf',
        'status' => 'pending',
    ]);

    Storage::disk('local')->put('proposals/audited.pdf', 'audited document');
    $version = $topic->versions()->create([
        'submitted_by' => $owner->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/audited.pdf',
        'original_filename' => 'audited-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 16,
        'checksum' => hash('sha256', 'audited document'),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);
    $packageFile = $version->files()->create([
        'document_type' => 'detailed_proposal',
        'position' => 0,
        'file_path' => 'proposals/audited.pdf',
        'original_filename' => 'audited-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 16,
        'checksum' => hash('sha256', 'audited document'),
        'is_carried_forward' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertDownload('audited-proposal.pdf');

    $this->actingAs($head)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertDownload('audited-proposal.pdf');

    $this->actingAs($head)
        ->get(route('topics.versions.files.download', [$topic, $version, $packageFile]))
        ->assertDownload('audited-proposal.pdf');

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.files.download', [$topic, $version, $packageFile]))
        ->assertForbidden();
});
