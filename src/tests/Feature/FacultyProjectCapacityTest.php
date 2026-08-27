<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\FacultyProjectCapacityService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create(['name' => 'Research Head']);
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create([
        'name' => 'Faculty B',
        'email' => 'faculty.b@g.batstate-u.edu.ph',
    ]);
    $this->faculty->assignRole('faculty');
    $this->owner = User::factory()->create(['name' => 'Faculty C']);
    $this->owner->assignRole('faculty');
    $this->call = ResearchCall::create([
        'title' => 'Capacity Test Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);

    Notification::fake();
});

function capacityTopic(
    User $owner,
    ResearchCall $call,
    string $status = 'pending',
    ?string $projectStatus = null,
): TopicProposal {
    return TopicProposal::create([
        'user_id' => $owner->id,
        'research_call_id' => $call->id,
        'title' => fake()->unique()->sentence(4),
        'estimated_duration_months' => 6,
        'status' => $status,
        'project_status' => $projectStatus,
    ]);
}

function addAcceptedCapacityCollaborator(TopicProposal $topic, User $collaborator): void
{
    $topic->collaborators()->create([
        'user_id' => $collaborator->id,
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'accepted_at' => now(),
    ]);
}

test('owner and accepted collaborator participation share one distinct capacity calculation', function () {
    $ownerAndCollaboratorTopic = capacityTopic($this->faculty, $this->call, 'approved');
    addAcceptedCapacityCollaborator($ownerAndCollaboratorTopic, $this->faculty);
    capacityTopic($this->faculty, $this->call, 'approved');
    $completedTopic = capacityTopic($this->owner, $this->call, 'approved', TopicProposal::PROJECT_STATUS_COMPLETED);
    addAcceptedCapacityCollaborator($completedTopic, $this->faculty);
    $rejectedTopic = capacityTopic($this->owner, $this->call, 'rejected');
    addAcceptedCapacityCollaborator($rejectedTopic, $this->faculty);

    $workload = app(FacultyProjectCapacityService::class)->workloadFor($this->faculty, $this->call);

    expect($workload)->toBe([
        'approved' => 2,
        'pending' => 0,
        'limit' => 2,
    ]);

    $ownerAndCollaboratorTopic->update(['project_status' => TopicProposal::PROJECT_STATUS_COMPLETED]);

    expect(app(FacultyProjectCapacityService::class)->workloadFor($this->faculty, $this->call))
        ->toMatchArray(['approved' => 1, 'pending' => 0]);
});

test('inviting a collaborator warns without blocking when pending proposals could exceed capacity', function () {
    capacityTopic($this->faculty, $this->call, 'approved');

    foreach (['pending', 'expert_review', 'revision_requested'] as $status) {
        capacityTopic($this->owner, $this->call, $status)->collaborators()->create([
            'user_id' => $this->faculty->id,
            'name' => $this->faculty->name,
            'email' => $this->faculty->email,
            'accepted_at' => now(),
        ]);
    }

    $draft = ProposalDraft::create([
        'user_id' => $this->owner->id,
        'research_call_id' => $this->call->id,
        'project_title' => 'New collaboration invitation',
        'duration_months' => 6,
        'planned_start' => now()->addMonth(),
        'planned_end' => now()->addMonths(7),
        'project_leader' => $this->owner->name,
    ]);

    $this->actingAs($this->owner)
        ->post(route('faculty.proposal-drafts.members.store', $draft), [
            'name' => $this->faculty->name,
            'email' => $this->faculty->email,
        ])
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
        ->assertSessionHas('workload_warning', fn (string $warning): bool => str_contains(
            $warning,
            '1 approved active research project and has 3 proposals pending approval',
        ));

    expect($draft->members()->sole()->accepted_at)->toBeNull();
});

test('collaborator acceptance recalculates a newly conflicting workload without blocking acceptance', function () {
    $draft = ProposalDraft::create([
        'user_id' => $this->owner->id,
        'research_call_id' => $this->call->id,
        'project_title' => 'Acceptance workload check',
        'duration_months' => 6,
        'planned_start' => now()->addMonth(),
        'planned_end' => now()->addMonths(7),
        'project_leader' => $this->owner->name,
    ]);
    $membership = $draft->members()->create([
        'user_id' => $this->faculty->id,
        'name' => $this->faculty->name,
        'email' => $this->faculty->email,
        'accepted_at' => null,
    ]);

    capacityTopic($this->faculty, $this->call, 'approved');
    capacityTopic($this->faculty, $this->call, 'pending');

    $this->actingAs($this->faculty)
        ->withSession(['active_workspace' => User::WORKSPACE_FACULTY])
        ->postJson(route('notifications.proposal-invitations.accept', $membership))
        ->assertOk()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('workload_warning', fn (string $warning): bool => str_contains(
            $warning,
            '1 approved active research project and has 1 proposal pending approval',
        ));

    expect($membership->fresh()->accepted_at)->not->toBeNull();
});

test('final approval is blocked when an accepted collaborator is already at capacity', function () {
    Storage::fake('local');

    capacityTopic($this->faculty, $this->call, 'approved');
    $secondActiveTopic = capacityTopic($this->owner, $this->call, 'approved');
    addAcceptedCapacityCollaborator($secondActiveTopic, $this->faculty);

    $finalizingTopic = capacityTopic($this->owner, $this->call, TopicProposal::STATUS_READY_FOR_SIGNATURE);
    addAcceptedCapacityCollaborator($finalizingTopic, $this->faculty);

    $version = $finalizingTopic->versions()->create([
        'submitted_by' => $this->owner->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/finalizing.pdf',
        'original_filename' => 'finalizing.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 10,
        'checksum' => hash('sha256', 'finalizing'),
        'title' => $finalizingTopic->title,
        'estimated_duration_months' => 6,
    ]);
    $sourceFile = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposals/finalizing.pdf',
        'original_filename' => 'finalizing.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 10,
        'checksum' => hash('sha256', 'finalizing'),
    ]);
    $finalizingTopic->reviews()->create([
        'reviewer_id' => $this->head->id,
        'decision' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
        'required_signature_file_ids' => [$sourceFile->id],
        'signature_proposal_version_id' => $version->id,
    ]);
    Storage::disk('local')->put('proposals/signed-finalizing.pdf', 'signed');
    $version->files()->create([
        'source_version_file_id' => $sourceFile->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 0,
        'file_path' => 'proposals/signed-finalizing.pdf',
        'original_filename' => 'signed-finalizing.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 6,
        'checksum' => hash('sha256', 'signed'),
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED],
        'uploaded_by' => $this->head->id,
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.finalizeApproval', $finalizingTopic))
        ->assertSessionHasErrors('status')
        ->assertSessionHasErrors([
            'status' => 'Approval cannot continue. Faculty B is already participating in the maximum of 2 active approved research projects.',
        ]);

    expect($finalizingTopic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE);
});
