<?php

use App\Actions\LinkProposalDraftMemberships;
use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->owner = User::factory()->create([
        'name' => 'Workspace Owner',
        'email' => 'owner@g.batstate-u.edu.ph',
        'college' => 'CICS',
    ]);
    $this->owner->assignRole('faculty');
    $this->collaborator = User::factory()->create([
        'name' => 'Linked Collaborator',
        'email' => 'collaborator@g.batstate-u.edu.ph',
        'college' => 'CTE',
    ]);
    $this->collaborator->assignRole('faculty');
    $this->outsider = User::factory()->create([
        'name' => 'Unrelated Faculty',
        'email' => 'outsider@g.batstate-u.edu.ph',
    ]);
    $this->outsider->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Collaborative Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->owner->id,
        'research_call_id' => $call->id,
        'project_title' => 'Shared Coastal Research',
        'duration_months' => 3,
        'planned_start' => '2026-08-01',
        'planned_end' => '2026-10-31',
        'project_leader' => $this->owner->name,
    ]);
    $this->workPlan = [
        'entries' => [[
            'objective' => 'Document the shared research baseline',
            'expected_output' => 'Shared baseline report',
            'activity' => 'Conduct the baseline assessment',
            'months' => [1, 2, 3],
        ]],
    ];

    $this->withoutVite();
});

test('an owner can tag an existing account and the collaborator can edit but not control the workspace', function () {
    Notification::fake();

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee('Proposal collaborators')
        ->assertSee($this->collaborator->email)
        ->assertSee('Add collaborator');

    $this->actingAs($this->owner)
        ->post(route('faculty.proposal-drafts.members.store', $this->draft), [
            'email' => $this->collaborator->email,
            'name' => 'This submitted name is ignored for linked accounts',
        ])
        ->assertRedirect(route('faculty.proposal-drafts.show', $this->draft));

    $membership = $this->draft->members()->sole();

    expect($membership->user_id)->toBe($this->collaborator->id)
        ->and($membership->name)->toBe($this->collaborator->name)
        ->and($membership->email)->toBe($this->collaborator->email);
    Notification::assertSentTo($this->collaborator, ProposalActivityNotification::class);

    $this->actingAs($this->collaborator)
        ->get(route('faculty.proposal-drafts.index'))
        ->assertOk()
        ->assertSee('Shared Coastal Research')
        ->assertSee('Collaborator');
    $this->actingAs($this->collaborator)
        ->get(route('faculty.proposal-drafts.details.edit', $this->draft))
        ->assertOk();
    $this->actingAs($this->collaborator)
        ->post(route('faculty.proposal-drafts.members.store', $this->draft), [
            'name' => 'Not Allowed',
            'email' => 'not-allowed@example.test',
        ])
        ->assertForbidden();
    $this->actingAs($this->collaborator)
        ->delete(route('faculty.proposal-drafts.destroy', $this->draft))
        ->assertForbidden();
    $this->actingAs($this->collaborator)
        ->post(route('faculty.proposal-drafts.submit', $this->draft))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->delete(route('faculty.proposal-drafts.members.destroy', [$this->draft, $membership]))
        ->assertRedirect(route('faculty.proposal-drafts.show', $this->draft));
    $this->assertDatabaseMissing('proposal_draft_members', ['id' => $membership->id]);

    $this->actingAs($this->collaborator)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertForbidden();

    $this->actingAs($this->outsider)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertForbidden();
});

test('an unregistered person remains external and is linked after verified Google account matching', function () {
    $this->actingAs($this->owner)
        ->post(route('faculty.proposal-drafts.members.store', $this->draft), [
            'name' => 'Future Account Member',
            'email' => 'future.member@g.batstate-u.edu.ph',
        ])
        ->assertRedirect();

    $membership = $this->draft->members()->sole();

    expect($membership->user_id)->toBeNull()
        ->and($membership->name)->toBe('Future Account Member')
        ->and($membership->isLinked())->toBeFalse();

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $this->draft))
        ->assertOk()
        ->assertSee('Future Account Member')
        ->assertSee('future.member@g.batstate-u.edu.ph');

    $futureAccount = User::factory()->create([
        'name' => 'Verified Future Member',
        'email' => 'future.member@g.batstate-u.edu.ph',
        'email_verified_at' => now(),
    ]);
    $futureAccount->assignRole('faculty');

    expect(app(LinkProposalDraftMemberships::class)->handle($futureAccount))->toBe(1);

    $membership->refresh();
    expect($membership->user_id)->toBe($futureAccount->id)
        ->and($membership->name)->toBe('Verified Future Member');

    $this->actingAs($futureAccount)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee('Shared with you by Workspace Owner');
});

test('workspace account details autofill member fields in project papers', function () {
    $this->draft->members()->create([
        'user_id' => $this->collaborator->id,
        'name' => $this->collaborator->name,
        'email' => $this->collaborator->email,
    ]);

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.details.edit', $this->draft))
        ->assertOk()
        ->assertSee($this->collaborator->name)
        ->assertSee($this->collaborator->email);
    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee($this->collaborator->name)
        ->assertSee($this->collaborator->email)
        ->assertSee('Choose a proposal workspace member');
    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $this->draft))
        ->assertOk()
        ->assertSee($this->owner->email)
        ->assertSee($this->collaborator->email)
        ->assertSee('Add workspace member CV');
});

test('a stale collaborator save cannot overwrite a newer teammate paper or project details', function () {
    $this->draft->members()->create([
        'user_id' => $this->collaborator->id,
        'name' => $this->collaborator->name,
        'email' => $this->collaborator->email,
    ]);

    $ownerWorkPlan = [
        ...$this->workPlan,
        'document_version' => 0,
    ];
    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.work-plan.update', $this->draft), $ownerWorkPlan)
        ->assertRedirect();

    $staleWorkPlan = $ownerWorkPlan;
    $staleWorkPlan['entries'][0]['objective'] = 'Stale overwrite attempt';

    $this->actingAs($this->collaborator)
        ->put(route('faculty.proposal-drafts.work-plan.update', $this->draft), $staleWorkPlan)
        ->assertSessionHasErrors('document_version');

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)
        ->sole();
    expect($document->lock_version)->toBe(1)
        ->and($document->source_data['entries'][0]['objective'])->toBe('Document the shared research baseline');

    $ownerDetails = [
        'draft_version' => 0,
        'project_title' => 'Owner Newer Project Title',
        'duration_months' => 3,
        'planned_start' => '2026-08-01',
        'planned_end' => '2026-10-31',
        'project_leader' => $this->owner->name,
    ];
    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.details.update', $this->draft), $ownerDetails)
        ->assertRedirect();

    $this->actingAs($this->collaborator)
        ->put(route('faculty.proposal-drafts.details.update', $this->draft), [
            ...$ownerDetails,
            'project_title' => 'Stale Project Title',
        ])
        ->assertSessionHasErrors('draft_version');

    expect($this->draft->fresh()->project_title)->toBe('Owner Newer Project Title')
        ->and($this->draft->fresh()->lock_version)->toBe(1);
});
