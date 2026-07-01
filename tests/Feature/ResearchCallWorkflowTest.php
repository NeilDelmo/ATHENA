<?php

use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'expert'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->expert = User::factory()->create();
    $this->expert->assignRole('expert');
    $this->category = ResearchCategory::create(['name' => 'Environment']);
    $this->call = ResearchCall::create([
        'title' => 'Open Institutional Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_proposals_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $this->call->categories()->attach($this->category);
    Storage::fake('local');
});

test('faculty submissions capture call category budget and duration and obey the per-call limit', function () {
    $payload = fn (string $title) => [
        'research_call_id' => $this->call->id,
        'research_category_id' => $this->category->id,
        'title' => $title,
        'description' => 'An environmental research proposal.',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'document' => UploadedFile::fake()->create($title.'.pdf', 100, 'application/pdf'),
    ];

    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('First'))->assertRedirect(route('faculty.dashboard'));
    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('Second'))->assertRedirect(route('faculty.dashboard'));
    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('Third'))
        ->assertSessionHasErrors('research_call_id', null, 'submission');

    expect($this->faculty->proposals()->count())->toBe(2)
        ->and($this->faculty->proposals()->first()->estimated_duration_months)->toBe(12);
});

test('expert recommendations return a proposal to the research head for a signed final approval', function () {
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'research_category_id' => $this->category->id,
        'title' => 'Coastal habitat restoration',
        'estimated_budget' => 75000,
        'estimated_duration_months' => 18,
        'initial_file_path' => 'proposals/coastal.pdf',
        'status' => 'pending',
    ]);

    $this->actingAs($this->head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'expert_review',
        'expert_ids' => [$this->expert->id],
        'comment' => 'Please evaluate the environmental need and feasibility.',
    ])->assertRedirect(route('research_head.dashboard'));

    $assignment = $topic->expertAssignments()->firstOrFail();
    expect($topic->fresh()->status)->toBe('expert_review');

    $this->actingAs($this->expert)->patch("/expert/assignments/{$assignment->id}", [
        'recommendation' => 'recommend_approval',
        'comment' => 'The project addresses a documented coastal need and is feasible.',
    ])->assertRedirect();

    expect($topic->fresh()->status)->toBe('for_final_decision');

    $this->actingAs($this->head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'approved',
        'comment' => 'Approved based on the completed expert review.',
        'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('research_head.dashboard'));

    $topic->refresh();
    expect($topic->status)->toBe('approved')
        ->and($topic->signed_approval_path)->not->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
    Storage::disk('local')->assertExists($topic->signed_approval_path);
});
