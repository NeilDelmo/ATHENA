<?php

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
    $this->faculty = User::factory()->create(['name' => 'Faculty Owner']);
    $this->faculty->assignRole('faculty');
    $this->call = ResearchCall::create([
        'title' => 'Notice Workflow Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Approved Coastal Research',
        'estimated_budget' => 75000,
        'estimated_duration_months' => 12,
        'status' => 'approved',
        'project_status' => null,
    ]);
});

test('issuing the Notice to Proceed promotes the faculty member and opens monitoring', function () {
    expect($this->faculty->hasRole('faculty_researcher'))->toBeFalse()
        ->and($this->topic->isMonitoringAvailable())->toBeFalse();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Approved - awaiting notice')
        ->assertSee('Waiting for issuance')
        ->assertDontSee('Project monitoring');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.store', $this->topic), [
            'notice_to_proceed' => UploadedFile::fake()->create('notice-to-proceed.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHas('success', 'Notice to Proceed issued. Faculty Researcher access and project monitoring are now open.');

    $this->topic->refresh();
    $this->faculty->refresh();

    expect($this->topic->notice_to_proceed_issued_by)->toBe($this->head->id)
        ->and($this->topic->notice_to_proceed_original_filename)->toBe('notice-to-proceed.pdf')
        ->and($this->topic->project_status)->toBe('ongoing')
        ->and($this->topic->isMonitoringAvailable())->toBeTrue()
        ->and($this->faculty->hasRole('faculty_researcher'))->toBeTrue()
        ->and($this->faculty->notifications()->firstOrFail()->data['title'])->toBe('Notice to Proceed issued');

    Storage::disk('local')->assertExists($this->topic->notice_to_proceed_path);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.notice-to-proceed.download', $this->topic))
        ->assertDownload('notice-to-proceed.pdf');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Project monitoring');
});

test('an approved paper remains outside monitoring until its notice is issued', function () {
    $this->faculty->assignRole('faculty_researcher');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->post(route('project-progress.store', $this->topic), [
            'reporting_date' => now()->toDateString(),
            'progress_percentage' => 10,
            'accomplishments' => 'This must remain locked.',
        ])
        ->assertForbidden();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('research_head.projects.index'))
        ->assertOk()
        ->assertDontSee($this->topic->title);
});

test('a notice cannot be issued before proposal approval', function () {
    $this->topic->update(['status' => 'pending']);

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('research_head.topics.notice-to-proceed.store', $this->topic), [
            'notice_to_proceed' => UploadedFile::fake()->create('premature-notice.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('notice_to_proceed');

    expect($this->topic->fresh()->notice_to_proceed_issued_at)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse()
        ->and(Storage::disk('local')->allFiles('notices-to-proceed'))->toBeEmpty();
});
