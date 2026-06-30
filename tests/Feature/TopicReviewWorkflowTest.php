<?php

use App\Models\TopicProposal;
use App\Models\TopicReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
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

    $response = $this->actingAs($faculty)->patch("/faculty/topics/{$topic->id}/resubmit", [
        'title' => 'Revised proposal',
        'description' => 'Updated methodology',
        'estimated_budget' => 8500,
        'document' => UploadedFile::fake()->create('revised-proposal.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect(route('faculty.dashboard'));

    $topic->refresh();

    expect($topic->status)->toBe('resubmitted')
        ->and($topic->title)->toBe('Revised proposal')
        ->and($topic->estimated_budget)->toBe('8500.00')
        ->and($topic->final_file_path)->not->toBeNull();

    Storage::disk('local')->assertExists('proposals/original.pdf');
    Storage::disk('local')->assertExists($topic->final_file_path);
});

test('a research head can approve a resubmitted proposal', function () {
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
    ]);

    $response->assertRedirect(route('research_head.dashboard'));

    expect($topic->fresh()->status)->toBe('approved')
        ->and($topic->reviews()->count())->toBe(2)
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
});

test('a rejected proposal remains final', function () {
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
        ['status' => 'approved'],
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
