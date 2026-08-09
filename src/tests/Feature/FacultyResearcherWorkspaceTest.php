<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole(['faculty', 'faculty_researcher']);
    $this->call = ResearchCall::create([
        'title' => 'Faculty-only Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
});

test('faculty researcher workspace cannot access faculty proposal or research call functionality', function () {
    $draft = ProposalDraft::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $this->call->id,
        'project_title' => 'Existing faculty draft',
    ]);
    $pending = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $this->call->id,
        'title' => 'Pending faculty proposal',
        'status' => 'pending',
    ]);
    $researcherSession = [User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER];

    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->get(route('faculty.proposal-drafts.index'))
        ->assertForbidden();
    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertForbidden();
    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertForbidden();
    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->post(route('faculty.topics'))
        ->assertForbidden();
    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->get(route('research-calls.index'))
        ->assertForbidden();
    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->get(route('research-calls.reference-image', $this->call))
        ->assertForbidden();
    $this->withSession($researcherSession)->actingAs($this->researcher)
        ->get(route('topics.show', $pending))
        ->assertForbidden();

    $facultySession = [User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY];
    $this->withSession($facultySession)->actingAs($this->researcher)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk();
    $this->withSession($facultySession)->actingAs($this->researcher)
        ->get(route('research-calls.index'))
        ->assertOk();
});

test('faculty researcher workspace separates active awaiting NTP and completed projects', function () {
    $createProject = fn (string $title, string $status, ?string $projectStatus = null, bool $hasNotice = false): TopicProposal => TopicProposal::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $this->call->id,
        'title' => $title,
        'status' => $status,
        'project_status' => $projectStatus,
        'notice_to_proceed_issued_by' => $hasNotice ? $this->head->id : null,
        'notice_to_proceed_issued_at' => $hasNotice ? now() : null,
    ]);

    $awaiting = $createProject('Approved awaiting NTP project', 'approved');
    $active = $createProject('Active NTP project', 'approved', TopicProposal::PROJECT_STATUS_ONGOING, true);
    $delayed = $createProject('Delayed NTP project', 'approved', TopicProposal::PROJECT_STATUS_DELAYED, true);
    $completed = $createProject('Completed archive project', 'approved', TopicProposal::PROJECT_STATUS_COMPLETED, true);
    $pending = $createProject('Hidden pending proposal', 'pending');
    $approvedWithNoticeWithoutExecutionStatus = $createProject('Hidden inactive NTP project', 'approved', null, true);

    $response = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->researcher)->get(route('research.index'));

    $response->assertOk()
        ->assertSeeInOrder(['Active Projects', $active->title, $delayed->title])
        ->assertSeeInOrder(['Awaiting Notice to Proceed', $awaiting->title])
        ->assertSeeInOrder(['Completed / Archive', $completed->title])
        ->assertDontSee($pending->title)
        ->assertDontSee($approvedWithNoticeWithoutExecutionStatus->title);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->researcher)->get(route('research.show', $awaiting))->assertOk();
});

test('monitoring is blocked before NTP and after project completion', function () {
    $awaiting = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $this->call->id,
        'title' => 'Awaiting NTP',
        'status' => 'approved',
    ]);
    $completed = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $this->call->id,
        'title' => 'Completed project',
        'status' => 'approved',
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
        'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_issued_at' => now(),
    ]);
    $researcherSession = [User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER];

    foreach ([$awaiting, $completed] as $topic) {
        $this->withSession($researcherSession)->actingAs($this->researcher)
            ->post(route('project-progress.store', $topic))
            ->assertForbidden();
        $this->withSession($researcherSession)->actingAs($this->researcher)
            ->post(route('project-narrative-reports.store', $topic))
            ->assertForbidden();
    }
});
