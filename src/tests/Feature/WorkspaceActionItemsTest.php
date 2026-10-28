<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\WorkspaceActionItems;
use Spatie\Permission\Models\Role;

function actionNarrativeReport(TopicProposal $topic, User $user, array $overrides = []): ProjectNarrativeReport
{
    return ProjectNarrativeReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $user->id, 'report_type' => 'progress',
        'reporting_date' => now(), 'reporting_quarter' => 1, 'submission_date' => now(),
        'researchers' => $user->name, 'implementation_start' => now()->subMonth(), 'implementation_end' => now()->addMonth(),
        'budget' => 1000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Completed work',
        'introduction' => 'Introduction', 'objectives' => 'Objectives', 'methodology' => 'Methodology',
        'results_discussion' => 'Results', 'photos' => [], ...$overrides,
    ]);
}

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_secretary', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->withoutVite();
    $this->faculty = User::factory()->create(['college' => 'College A']);
    $this->faculty->assignRole(['faculty', 'faculty_researcher']);
});

test('current actions remain after reading notifications and resolve when the underlying work is completed', function () {
    $owner = User::factory()->create();
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Invited project']);
    $member = ProposalDraftMember::create(['proposal_draft_id' => $draft->id, 'user_id' => $this->faculty->id, 'name' => $this->faculty->name, 'email' => $this->faculty->email, 'accepted_at' => null]);
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Correct the proposal', 'status' => 'revision_requested']);
    $this->faculty->notify(new ProposalActivityNotification('Revision requested', 'Please revise.', route('topics.show', $topic), topicId: $topic->id));
    $notification = $this->faculty->notifications()->sole();
    $notification->markAsRead();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])->actingAs($this->faculty);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(2, 'pending_actions')->assertJsonPath('unread_count', 0);
    $member->update(['accepted_at' => now()]);
    $topic->update(['status' => 'resubmitted']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions')->assertJsonCount(1, 'notifications');
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('pending actions follow the active workspace and never expose unrelated projects', function () {
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Private revision', 'status' => 'revision_requested']);
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $this->actingAs($other)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions')->assertDontSee('Private revision');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty_researcher'])->actingAs($this->faculty)
        ->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])->get(route('notifications.index'))
        ->assertOk()->assertSee('Needs your action')->assertSee('Address proposal revisions')->assertSee('Private revision');
});

test('report tasks move from secretary to head and disappear after acceptance', function () {
    $secretary = User::factory()->create(['college' => 'College A']);
    $secretary->assignRole('research_secretary');
    $outsideSecretary = User::factory()->create(['college' => 'College B']);
    $outsideSecretary->assignRole('research_secretary');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Report project', 'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now()]);
    $report = ProjectProgressReport::create(['topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'reporting_date' => now(), 'progress_percentage' => 25, 'accomplishments' => 'Completed work', 'review_stage' => 'secretary', 'review_status' => 'pending']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_secretary'])->actingAs($secretary);
    expect(app(WorkspaceActionItems::class)->for($secretary))->toHaveCount(1);
    $this->actingAs($outsideSecretary)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions')->assertDontSee('Report project');
    $report->update(['review_stage' => 'head']);
    $this->actingAs($secretary)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])->actingAs($head)
        ->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'pending_actions');
    $report->update(['review_status' => 'reviewed']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
});

test('researcher report corrections stop appearing once a newer version exists and prepared reports belong to the leader', function () {
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Implementation project', 'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now()]);
    $report = actionNarrativeReport($topic, $this->faculty, ['review_status' => 'revision_requested']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty_researcher'])->actingAs($this->faculty);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'pending_actions');
    $newer = actionNarrativeReport($topic, $this->faculty, ['submission_status' => 'prepared']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'pending_actions')->assertJsonPath('pending_actions.0.label', 'Review and submit prepared report');
    $newer->update(['submission_status' => 'submitted', 'review_status' => 'pending']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
});

test('research office actions respect college routing and leave notice issuance to the secretary', function () {
    $office = User::factory()->create(['college' => 'College A']);
    $office->assignRole('research_coordinator');
    $outsideOffice = User::factory()->create(['college' => 'College B']);
    $outsideOffice->assignRole('research_coordinator');
    $lrec = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'LREC project', 'status' => 'lrec_queued', 'review_stage' => 'lrec']);
    $release = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Release project', 'status' => 'approved']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_office'])->actingAs($office)
        ->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'pending_actions')->assertDontSee('Release project');
    $this->actingAs($outsideOffice)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions')->assertDontSee('LREC project')->assertDontSee('Release project');
    $secretary = User::factory()->create(['college' => 'College A']);
    $secretary->assignRole('research_secretary');
    $this->actingAs($secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'pending_actions')
        ->assertJsonPath('pending_actions.0.url', route('topics.show', $release).'#notice-to-proceed');
    $lrec->update(['status' => 'revision_requested']);
    $release->update(['notice_to_proceed_issued_at' => now()]);
    $this->actingAs($office)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
});

test('head proposal tasks stop when revisions are requested and completed projects leave report tasks', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Review this proposal', 'status' => 'pending']);
    $topic->versions()->create(['submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial', 'title' => $topic->title, 'file_path' => 'private/submitted.pdf', 'original_filename' => 'submitted.pdf', 'mime_type' => 'application/pdf']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])->actingAs($head)
        ->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'pending_actions');
    $topic->update(['status' => 'revision_requested']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
    $topic->update(['status' => 'approved', 'notice_to_proceed_issued_at' => now(), 'project_status' => 'completed']);
    actionNarrativeReport($topic, $this->faculty, ['report_type' => 'terminal', 'review_status' => 'pending']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'pending_actions');
});
