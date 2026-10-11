<?php

use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('completed projects cannot change or remove their project secretary', function (bool $removeSecretary) {
    Role::firstOrCreate(['name' => User::WORKSPACE_FACULTY_RESEARCHER]);
    $leader = User::factory()->create();
    $leader->assignRole(User::WORKSPACE_FACULTY_RESEARCHER);
    $secretary = User::factory()->create();
    $replacement = User::factory()->create();
    $topic = TopicProposal::create([
        'user_id' => $leader->id,
        'title' => 'Completed project secretary record',
        'status' => 'approved',
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
        'notice_to_proceed_issued_at' => now()->subYear(),
        'research_secretary_id' => $secretary->id,
    ]);
    $secretaryMembership = $topic->collaborators()->create([
        'user_id' => $secretary->id,
        'name' => $secretary->name,
        'email' => $secretary->email,
        'accepted_at' => now()->subYear(),
        'project_role' => TopicCollaborator::ROLE_SECRETARY,
    ]);
    $replacementMembership = $topic->collaborators()->create([
        'user_id' => $replacement->id,
        'name' => $replacement->name,
        'email' => $replacement->email,
        'accepted_at' => now()->subYear(),
    ]);

    $this->actingAs($leader)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->patch(route('project-secretary.assign', $topic), [
            'research_secretary_id' => $removeSecretary ? null : $replacement->id,
        ])
        ->assertForbidden();

    expect($topic->fresh()->research_secretary_id)->toBe($secretary->id)
        ->and($secretaryMembership->fresh()->project_role)->toBe(TopicCollaborator::ROLE_SECRETARY)
        ->and($replacementMembership->fresh()->project_role)->toBeNull();
})->with([
    'change secretary' => false,
    'remove secretary' => true,
]);
