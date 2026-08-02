<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * The `college` and `contact_number` columns on a `User` row are the SINGLE
 * source of truth for that person's institutional identity. They are
 * intentionally not duplicated per role, per workspace, or per
 * proposal/paper — even when the same account carries the `faculty`,
 * `research_head`, `faculty_researcher`, or `research_coordinator` role
 * simultaneously. This test file proves that property end-to-end so a
 * future refactor cannot silently break it.
 */
beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    Storage::fake('local');
    $this->withoutVite();
});

test('a user with both faculty and research_head roles uses the same college and contact number in every workspace', function () {
    $user = User::factory()->create([
        'name' => 'Dual Faculty Head',
        'email' => 'dual.head@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
        'contact_number' => '09170000001',
    ]);
    $user->assignRole(['faculty', 'research_head']);

    expect($user->getRoleNames()->sort()->values()->all())->toBe(['faculty', 'research_head'])
        ->and($user->availableWorkspaceKeys())->toContain(User::WORKSPACE_FACULTY)
        ->and($user->availableWorkspaceKeys())->toContain(User::WORKSPACE_RESEARCH_HEAD)
        ->and($user->college)->toBe(User::COLLEGES['CICS'])
        ->and($user->contact_number)->toBe('09170000001');

    session([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    expect($user->activeWorkspace())->toBe(User::WORKSPACE_FACULTY)
        ->and($user->college)->toBe(User::COLLEGES['CICS'])
        ->and($user->contact_number)->toBe('09170000001');

    session([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    expect($user->activeWorkspace())->toBe(User::WORKSPACE_RESEARCH_HEAD)
        ->and($user->college)->toBe(User::COLLEGES['CICS'])
        ->and($user->contact_number)->toBe('09170000001');
});

test('a user with both faculty and faculty_researcher roles shares one college and contact number across both workspaces', function () {
    $user = User::factory()->create([
        'name' => 'Faculty Plus Researcher',
        'email' => 'faculty.researcher@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CAS'],
        'contact_number' => '09170000002',
    ]);
    $user->assignRole(['faculty', 'faculty_researcher']);

    expect($user->getRoleNames()->sort()->values()->all())->toBe(['faculty', 'faculty_researcher'])
        ->and($user->availableWorkspaceKeys())->toContain(User::WORKSPACE_FACULTY)
        ->and($user->availableWorkspaceKeys())->toContain(User::WORKSPACE_FACULTY_RESEARCHER)
        ->and($user->college)->toBe(User::COLLEGES['CAS'])
        ->and($user->contact_number)->toBe('09170000002');

    session([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    expect($user->activeWorkspace())->toBe(User::WORKSPACE_FACULTY_RESEARCHER)
        ->and($user->college)->toBe(User::COLLEGES['CAS'])
        ->and($user->contact_number)->toBe('09170000002');
});

test('a research coordinator who is also a faculty member keeps one locked college and one editable contact number', function () {
    $user = User::factory()->create([
        'name' => 'Coordinator Plus Faculty',
        'email' => 'coordinator.faculty@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
        'contact_number' => '09170000003',
    ]);
    $user->assignRole(['faculty', 'research_coordinator']);

    expect($user->getRoleNames()->sort()->values()->all())->toBe(['faculty', 'research_coordinator'])
        ->and($user->hasRole('research_coordinator'))->toBeTrue()
        ->and($user->hasRole('faculty'))->toBeTrue()
        ->and($user->college)->toBe(User::COLLEGES['CICS'])
        ->and($user->contact_number)->toBe('09170000003');

    $this->actingAs($user)
        ->patch(route('profile.college.update'), ['college' => User::COLLEGES['CTE']])
        ->assertSessionHasErrors('college');

    expect($user->refresh()->college)->toBe(User::COLLEGES['CICS']);

    $this->actingAs($user)
        ->patch(route('profile.contact-number.update'), ['contact_number' => '09170000099'])
        ->assertSessionHas('status', 'contact-number-updated');

    expect($user->refresh()->contact_number)->toBe('09170000099')
        ->and($user->college)->toBe(User::COLLEGES['CICS']);
});

test('issuing a Notice to Proceed promotes a faculty researcher without duplicating identity data', function () {
    $category = ResearchCategory::create(['name' => 'Environment']);
    $call = ResearchCall::create([
        'title' => 'Test Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
    ]);
    $call->categories()->attach($category);

    $owner = User::factory()->create([
        'name' => 'Faculty Owner',
        'email' => 'owner@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
        'contact_number' => '09170000004',
    ]);
    $owner->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $owner->id,
        'research_call_id' => $call->id,
        'research_category_id' => $category->id,
        'title' => 'A promotable topic',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'status' => 'pending',
    ]);

    $head = User::factory()->create();
    $head->assignRole('research_head');

    $version = $topic->versions()->create([
        'submitted_by' => $owner->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'topic.pdf',
        'original_filename' => 'topic.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'approved',
            'evaluation_document' => UploadedFile::fake()->create('evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($owner->fresh()->hasRole('faculty_researcher'))->toBeFalse();

    $this->actingAs($head)
        ->post(route('research_head.topics.notice-to-proceed.store', $topic), [
            'notice_to_proceed' => UploadedFile::fake()->create('notice-to-proceed.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $topic).'#notice-to-proceed');

    $owner->refresh();

    expect($owner->getRoleNames()->sort()->values()->all())->toBe(['faculty', 'faculty_researcher'])
        ->and($owner->college)->toBe(User::COLLEGES['CICS'])
        ->and($owner->contact_number)->toBe('09170000004')
        ->and($owner->availableWorkspaceKeys())->toContain(User::WORKSPACE_FACULTY)
        ->and($owner->availableWorkspaceKeys())->toContain(User::WORKSPACE_FACULTY_RESEARCHER);

    expect(User::query()->where('email', $owner->email)->count())->toBe(1);

    unset($version);
});

test('the detailed proposal form pre-fills the owner college and contact number from the owner user record, not the current user', function () {
    Storage::fake('local');
    $owner = User::factory()->create([
        'name' => 'Proposal Owner',
        'email' => 'owner@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
        'contact_number' => '09170000010',
    ]);
    $owner->assignRole('faculty');

    $head = User::factory()->create();
    $head->assignRole('research_head');

    $category = ResearchCategory::create(['name' => 'Environment']);
    $call = ResearchCall::create([
        'title' => 'Test Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);
    $call->categories()->attach($category);

    $draft = ProposalDraft::create([
        'user_id' => $owner->id,
        'research_call_id' => $call->id,
        'project_title' => 'An owner draft',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => $owner->name,
    ]);

    $this->actingAs($owner)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))
        ->assertOk()
        ->assertSee('\u0022proponent_college\u0022:\u0022'.User::COLLEGES['CICS'].'\u0022', false)
        ->assertSee('\u0022leader_contact\u0022:\u002209170000010\u0022', false);
});
