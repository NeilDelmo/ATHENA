<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDeletionRequest;
use App\Models\ProposalDraftMember;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    [$this->leader, $this->firstTeammate, $this->secondTeammate, $this->outsider] = User::factory()->count(4)->create()->each->assignRole('faculty')->all();
    $this->draft = ProposalDraft::query()->create(['user_id' => $this->leader->id, 'project_title' => 'Shared coastal survey']);
    foreach ([$this->firstTeammate, $this->secondTeammate] as $teammate) {
        ProposalDraftMember::factory()->for($this->draft, 'draft')->for($teammate)
            ->create(['name' => $teammate->name, 'email' => $teammate->email, 'accepted_at' => now()]);
    }
    Storage::fake('local');
    $this->withoutVite();
});

test('shared drafts need every accepted teammate to approve and keep the full recovery window', function () {
    $path = $this->draft->storageDirectory().'/method.md';
    Storage::disk('local')->put($path, 'Draft methods');
    $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'file_path' => $path]);
    ProposalDraftMember::factory()->for($this->draft, 'draft')->for($this->outsider)
        ->create(['email' => $this->outsider->email, 'accepted_at' => null]);

    $index = route('faculty.proposal-drafts.index');
    $trash = route('faculty.proposal-drafts.trash');
    $requestForm = route('faculty.proposal-drafts.deletion.create', $this->draft);
    $requestEndpoint = route('faculty.proposal-drafts.deletion.store', $this->draft);
    $this->actingAs($this->leader)->get($index)->assertOk()->assertSeeText('Request deletion')->assertDontSee('data-confirm-button="Move to Trash"', false);
    $this->get($requestForm)->assertOk()->assertSeeText('Reason for deletion')->assertSeeText('30 days');
    $this->delete(route('faculty.proposal-drafts.destroy', $this->draft))->assertForbidden();
    $this->from($requestForm)->post($requestEndpoint, ['reason' => ' '])->assertRedirect($requestForm)->assertSessionHasErrors('reason');
    $this->post($requestEndpoint, ['reason' => 'This draft duplicates the working proposal.'])->assertRedirect();
    $deletion = $this->draft->deletionRequests()->sole();
    $review = route('faculty.proposal-drafts.deletion.show', [$this->draft, $deletion]);
    $response = route('faculty.proposal-drafts.deletion.update', [$this->draft, $deletion]);
    $this->post($requestEndpoint, ['reason' => 'A duplicate submission'])->assertRedirect($review);
    expect($this->draft->deletionRequests()->count())->toBe(1);
    foreach ([$this->firstTeammate, $this->secondTeammate] as $teammate) {
        $notice = $teammate->notifications()->sole();
        expect($notice->data['title'])->toBe('Draft deletion requested')
            ->and($notice->data['url'])->toBe($review);
    }
    expect($this->outsider->notifications()->count())->toBe(0);

    $this->patch($response, ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($this->outsider)->get($review)->assertForbidden();
    $this->patch($response, ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($this->firstTeammate)->get($index)->assertOk()
        ->assertSeeText('Deletion requested · 0 of 2 approvals')->assertSeeText('Review deletion request');
    $this->get($review)->assertOk()->assertSeeText('This draft duplicates the working proposal.')
        ->assertSeeText('Approve')->assertSeeText('Decline');
    $this->patch($response, ['decision' => 'cancel'])->assertForbidden();
    $this->patch($response, ['decision' => 'approve'])->assertRedirect($review);
    $this->patch($response, ['decision' => 'approve'])->assertRedirect($review);
    expect($deletion->fresh()->approved_member_ids)->toHaveCount(1)
        ->and($this->draft->fresh()->trashed())->toBeFalse();
    $this->get($review)->assertOk()->assertSeeText('You approved.');

    $this->actingAs($this->secondTeammate)->patch($response, ['decision' => 'approve'])->assertRedirect($review);
    $this->patch($response, ['decision' => 'approve'])->assertRedirect($review);
    $this->get($review)->assertOk()->assertSeeText('Approved by the team');
    $this->assertSoftDeleted($this->draft);
    Storage::disk('local')->assertExists($path);
    expect($deletion->fresh()->status)->toBe(ProposalDraftDeletionRequest::STATUS_APPROVED);
    foreach ([$this->leader, $this->firstTeammate, $this->secondTeammate] as $teammate) {
        expect($teammate->notifications()->get()->filter(fn ($notice) => $notice->data['title'] === 'Shared draft moved to Trash'
            && $notice->data['url'] === $trash))->toHaveCount(1);
    }
    $this->actingAs($this->leader)->delete(route('faculty.proposal-drafts.trash.destroy', $this->draft), ['confirmed' => '1'])->assertForbidden();
    $this->travel(29)->days();
    $this->artisan('proposal-drafts:purge')->assertSuccessful();
    $this->assertSoftDeleted($this->draft);
    $this->actingAs($this->outsider)->get($trash)->assertOk()->assertDontSee($this->draft->project_title);
    $this->patch($response, ['decision' => 'approve'])->assertNotFound();
    $this->actingAs($this->firstTeammate)->get($trash)->assertOk()->assertSee($this->draft->project_title)
        ->assertSeeText('Approved for deletion by the project team')->assertDontSeeText('Delete permanently');
    $this->post(route('faculty.proposal-drafts.restore', $this->draft))->assertRedirect(route('faculty.proposal-drafts.show', $this->draft));
    expect($this->draft->fresh()->trashed())->toBeFalse();
    Storage::disk('local')->assertExists($path);
    foreach ([$this->leader, $this->secondTeammate] as $teammate) {
        expect($teammate->notifications()->get()->filter(fn ($notice) => $notice->data['title'] === 'Shared draft restored'
            && $notice->data['url'] === route('faculty.proposal-drafts.show', $this->draft)))->toHaveCount(1);
    }
});

test('an open deletion request pauses turn in and links to the team decision', function () {
    $this->actingAs($this->leader)->post(route('faculty.proposal-drafts.deletion.store', $this->draft), ['reason' => 'Close duplicate'])->assertRedirect();
    $deletion = $this->draft->deletionRequests()->sole();
    $review = route('faculty.proposal-drafts.review', $this->draft);
    $decision = route('faculty.proposal-drafts.deletion.show', [$this->draft, $deletion]);

    $this->getJson($review)->assertOk()
        ->assertJsonPath('status', 'deletion_pending')
        ->assertJsonPath('ready_to_submit', false)
        ->assertJsonPath('blockers.0.url', $decision);
    $this->get($review)->assertOk()->assertSee('Review deletion request');
    $this->postJson(route('faculty.proposal-drafts.submit', $this->draft))->assertUnprocessable()
        ->assertJsonValidationErrors('deletion_request');
    expect($this->draft->fresh()->trashed())->toBeFalse();

    $this->patch(route('faculty.proposal-drafts.deletion.update', [$this->draft, $deletion]), ['decision' => 'cancel'])->assertRedirect($decision);
    $this->getJson($review)->assertOk()->assertJsonMissing(['key' => 'deletion_request']);
});

test('a decline closes the request and a new request needs fresh approvals', function () {
    $endpoint = route('faculty.proposal-drafts.deletion.store', $this->draft);
    $this->actingAs($this->leader)->post($endpoint, ['reason' => 'First request'])->assertRedirect();
    $first = $this->draft->deletionRequests()->sole();
    $firstResponse = route('faculty.proposal-drafts.deletion.update', [$this->draft, $first]);
    $this->actingAs($this->firstTeammate)->patch($firstResponse, ['decision' => 'approve'])->assertRedirect();
    $this->actingAs($this->secondTeammate)->patch($firstResponse, ['decision' => 'decline'])->assertRedirect();
    expect($first->fresh()->status)->toBe(ProposalDraftDeletionRequest::STATUS_DECLINED)
        ->and($this->draft->fresh()->trashed())->toBeFalse()
        ->and($this->leader->notifications()->sole()->data['title'])->toBe('Deletion request declined');

    $this->actingAs($this->leader)->post($endpoint, ['reason' => 'Second request'])->assertRedirect();
    $second = $this->draft->deletionRequests()->latest('id')->firstOrFail();
    $secondResponse = route('faculty.proposal-drafts.deletion.update', [$this->draft, $second]);
    $this->actingAs($this->secondTeammate)->patch($firstResponse, ['decision' => 'approve'])->assertRedirect();
    expect($second->fresh()->approved_member_ids)->toBeEmpty();
    $this->actingAs($this->leader)->patch($secondResponse, ['decision' => 'cancel'])->assertRedirect();
    expect($second->fresh()->status)->toBe(ProposalDraftDeletionRequest::STATUS_CANCELLED);
});

test('a changed team requires a new deletion request before a draft can go to trash', function () {
    $this->actingAs($this->leader)->post(route('faculty.proposal-drafts.deletion.store', $this->draft), ['reason' => 'Close duplicate'])->assertRedirect();
    $deletion = $this->draft->deletionRequests()->sole();
    $review = route('faculty.proposal-drafts.deletion.show', [$this->draft, $deletion]);
    $response = route('faculty.proposal-drafts.deletion.update', [$this->draft, $deletion]);
    $originalTeammate = $this->draft->members()->forUser($this->firstTeammate)->sole();
    $this->from($review)->delete(route('faculty.proposal-drafts.members.destroy', [$this->draft, $originalTeammate]))
        ->assertRedirect($review)->assertSessionHasErrors('member');
    $newTeammate = ProposalDraftMember::factory()->for($this->draft, 'draft')->for($this->outsider)
        ->create(['name' => $this->outsider->name, 'email' => $this->outsider->email, 'accepted_at' => null]);
    $this->actingAs($this->firstTeammate)->patch($response, ['decision' => 'approve'])->assertRedirect($review);
    $this->actingAs($this->outsider)->postJson(route('notifications.proposal-invitations.accept', $newTeammate))->assertOk();
    $this->actingAs($this->secondTeammate)->from($review)->patch($response, ['decision' => 'approve'])
        ->assertRedirect($review)->assertSessionHasErrors('decision');
    $this->get($review)->assertOk()->assertSeeText('The accepted team changed.');
    expect($this->draft->fresh()->trashed())->toBeFalse();
    $this->actingAs($this->leader)->patch($response, ['decision' => 'cancel'])->assertRedirect($review);
});

test('archive removes the draft from the active list and keeps it accessible to the team', function () {
    $archive = route('faculty.proposal-drafts.archive', $this->draft);
    $active = route('faculty.proposal-drafts.index');
    $archived = route('faculty.proposal-drafts.index', ['view' => 'archived']);
    $this->actingAs($this->firstTeammate)->patch($archive, ['archived' => '1'])->assertForbidden();
    $this->actingAs($this->leader)->patch($archive, ['archived' => '1'])->assertRedirect($active);
    $this->get($active)->assertOk()->assertDontSee($this->draft->project_title);
    $this->get($archived)->assertOk()->assertSee($this->draft->project_title)->assertSeeText('Unarchive');
    $this->actingAs($this->firstTeammate)->get($archived)->assertOk()
        ->assertSee($this->draft->project_title)->assertSeeText('Resume')->assertDontSeeText('Unarchive');
    $this->get(route('faculty.proposal-drafts.show', $this->draft))->assertOk();
    $this->actingAs($this->outsider)->get($archived)->assertOk()->assertDontSee($this->draft->project_title);
    $this->actingAs($this->leader)->patch($archive, ['archived' => '0'])->assertRedirect($archived);
    $this->get($active)->assertOk()->assertSee($this->draft->project_title);
    $this->get($archived)->assertOk()->assertDontSee($this->draft->project_title);
});
