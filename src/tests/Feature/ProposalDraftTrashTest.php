<?php

use App\Actions\CreateProposalRevisionDraft;
use App\Actions\LinkProposalDraftMemberships;
use App\Exceptions\ResearchAssistantDocumentException;
use App\Jobs\PrepareProposalDraftPdfs;
use App\Livewire\ProposalDraftReviewPackage;
use App\Models\ProjectDocument;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalWorkspaceInvitation;
use App\Services\AccessibleRecordSearch;
use App\Services\ProposalPdfPreparation;
use App\Services\ResearchAssistantDocumentService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('faculty');
    $this->colleague = User::factory()->create();
    $this->colleague->assignRole('faculty');
    $this->draft = ProposalDraft::create([
        'user_id' => $this->owner->id,
        'project_title' => 'Recoverable coastal research',
    ]);
    Storage::fake('local');
    Queue::fake();
    $this->withoutVite();
});

test('deleting a proposal draft preserves its papers history and membership for undo', function () {
    $path = $this->draft->storageDirectory().'/paper.pdf';
    Storage::disk('local')->put($path, '%PDF-1.7 paper');
    $document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'file_path' => $path,
        'source_data' => ['summary' => 'Saved content'], 'lock_version' => 3,
    ]);
    $history = $this->draft->documentVersions()->create([
        'proposal_draft_document_id' => $document->id,
        'document_type' => 'detailed_proposal', 'version_number' => 3,
        'file_path' => $path, 'source_data' => $document->source_data,
    ]);
    $member = $this->draft->members()->create([
        'user_id' => $this->colleague->id, 'name' => $this->colleague->name,
        'email' => $this->colleague->email,
    ]);

    $this->actingAs($this->owner)->delete(route('faculty.proposal-drafts.destroy', $this->draft))
        ->assertRedirect(route('faculty.proposal-drafts.index'))
        ->assertSessionHas('deleted_proposal_draft_id', $this->draft->id);

    $this->assertSoftDeleted($this->draft);
    $this->assertModelExists($document);
    $this->assertModelExists($history);
    $this->assertModelExists($member);
    Storage::disk('local')->assertExists($path);
    $this->get(route('faculty.proposal-drafts.index'))->assertOk()->assertSee('Undo')->assertDontSee($this->draft->project_title);
    $this->post(route('faculty.proposal-drafts.restore', $this->draft))
        ->assertRedirect(route('faculty.proposal-drafts.show', $this->draft));
    expect($this->draft->fresh()->trashed())->toBeFalse();
    expect($document->fresh()->source_data)->toBe(['summary' => 'Saved content']);
    Storage::disk('local')->assertExists($path);
});

test('trash is owner only and trashed drafts cannot be opened edited downloaded or accepted', function () {
    $member = $this->draft->members()->create([
        'user_id' => $this->colleague->id, 'name' => $this->colleague->name,
        'email' => $this->colleague->email, 'accepted_at' => now(),
    ]);
    $this->draft->delete();

    $trash = $this->actingAs($this->owner)->get(route('faculty.proposal-drafts.trash'))
        ->assertOk()->assertSee($this->draft->project_title)->assertSee('30 days');
    File::ensureDirectoryExists(storage_path('framework/testing'));
    File::put(storage_path('framework/testing/proposal-trash.html'), $trash->getContent());
    $this->actingAs($this->colleague)->get(route('faculty.proposal-drafts.trash'))
        ->assertOk()->assertDontSee($this->draft->project_title);
    $this->post(route('faculty.proposal-drafts.restore', $this->draft))->assertNotFound();
    foreach ([$this->owner, $this->colleague] as $user) {
        $this->actingAs($user)->get(route('faculty.proposal-drafts.show', $this->draft))->assertNotFound();
        $this->get(route('faculty.proposal-drafts.edit-state', [$this->draft, 'details', 0]))->assertNotFound();
        $this->get(route('faculty.proposal-drafts.submission-files.download', [$this->draft, 'detailed-proposal']))->assertNotFound();
        expect(Gate::forUser($user)->allows('view', $this->draft))->toBeFalse();
        expect(Gate::forUser($user)->allows('prepare', $this->draft))->toBeFalse();
    }
    $this->actingAs($this->colleague)->get(route('notifications.proposal-invitations.show', $member))->assertForbidden();
    $this->post(route('notifications.proposal-invitations.accept', $member))->assertForbidden();
    Livewire::actingAs($this->owner)->test(ProposalDraftReviewPackage::class, ['proposalDraft' => $this->draft])->assertForbidden();
});

test('expired drafts cannot be restored and submitting work cannot enter trash', function () {
    $this->draft->delete();
    $this->draft->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();
    $this->actingAs($this->owner)->post(route('faculty.proposal-drafts.restore', $this->draft))->assertForbidden();
    $submitting = ProposalDraft::create([
        'user_id' => $this->owner->id, 'project_title' => 'Submitting research',
        'status' => ProposalDraft::STATUS_SUBMITTING,
    ]);
    $this->delete(route('faculty.proposal-drafts.destroy', $submitting))->assertForbidden();
    expect($submitting->fresh()->trashed())->toBeFalse();
});

test('queued PDF work leaves a trashed draft and its stored files untouched', function () {
    $path = $this->draft->storageDirectory().'/paper.pdf';
    Storage::disk('local')->put($path, 'saved PDF');
    $this->draft->forceFill(['pdf_preparation_state' => ['status' => 'queued', 'run_id' => 'before-delete']])->saveQuietly();
    $this->draft->delete();
    $before = $this->draft->fresh()->pdf_preparation_state;
    (new PrepareProposalDraftPdfs($this->draft->id, 'before-delete'))->handle(app(ProposalPdfPreparation::class));
    app(ProposalPdfPreparation::class)->fail($this->draft->id, 'before-delete');
    expect($this->draft->fresh()->pdf_preparation_state)->toBe($before);
    Storage::disk('local')->assertExists($path);
});

test('trashed content is excluded from search assistant analysis and queued invitations', function () {
    $path = $this->draft->storageDirectory().'/paper.pdf';
    Storage::disk('local')->put($path, 'saved PDF');
    $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'file_path' => $path,
        'original_filename' => 'Recoverable coastal research.pdf',
    ]);
    $member = $this->draft->members()->create([
        'name' => $this->colleague->name, 'email' => $this->colleague->email,
        'accepted_at' => null,
    ]);
    $mail = new ProposalWorkspaceInvitation(
        $this->colleague->name, $this->owner->name, $this->draft->project_title,
        $member->email, route('notifications.proposal-invitations.show', $member), false,
    );
    expect($mail->shouldSend($this->colleague, 'mail'))->toBeTrue();
    $pendingInvitation = clone $mail;
    $pendingInvitation->requiresAcceptance = true;
    $member->update(['accepted_at' => now()]);
    expect($mail->shouldSend($this->colleague, 'mail'))->toBeTrue();
    expect($pendingInvitation->shouldSend($this->colleague, 'mail'))->toBeFalse();
    $this->draft->delete();
    $this->actingAs($this->owner);

    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Recoverable coastal research')->total())->toBe(0);
    expect(fn () => app(ResearchAssistantDocumentService::class)->availableDocuments($this->owner, null, $this->draft->id))
        ->toThrow(ResearchAssistantDocumentException::class);
    expect($mail->shouldSend($this->colleague, 'mail'))->toBeFalse();
    expect(app(LinkProposalDraftMemberships::class)->handle($this->colleague))->toBe(0);
    expect($member->fresh()->user_id)->toBeNull();
});

test('revision creation sends owners to restore their trashed workspace instead of colliding with its topic', function () {
    $topic = TopicProposal::create([
        'user_id' => $this->owner->id, 'title' => 'Submitted topic', 'status' => 'revision_requested',
    ]);
    $this->draft->update(['topic_id' => $topic->id]);
    $this->draft->delete();
    $this->actingAs($this->owner);

    expect(fn () => app(CreateProposalRevisionDraft::class)->handle($topic, $this->owner))
        ->toThrow(ValidationException::class);
    expect(ProposalDraft::withTrashed()->where('topic_id', $topic->id)->count())->toBe(1);
});

test('purging expires only standalone drafts and retains referenced files and official history', function () {
    $expired = $this->draft;
    $privatePath = $expired->storageDirectory().'/private.pdf';
    $sharedPath = $expired->storageDirectory().'/shared.pdf';
    $sharedImage = $expired->storageDirectory().'/methodology.png';
    $officialSharedPath = $expired->storageDirectory().'/official-reference.pdf';
    $externalPath = 'proposal-packages/official.pdf';
    Storage::disk('local')->put($privatePath, 'private');
    Storage::disk('local')->put($sharedPath, 'shared');
    Storage::disk('local')->put($sharedImage, 'image');
    Storage::disk('local')->put($officialSharedPath, 'official reference');
    Storage::disk('local')->put($externalPath, 'official');
    $expired->documents()->create(['document_type' => 'detailed_proposal', 'file_path' => $privatePath]);
    $history = $expired->documentVersions()->create(['document_type' => 'detailed_proposal', 'version_number' => 1, 'file_path' => $privatePath]);
    $other = ProposalDraft::create(['user_id' => $this->owner->id, 'project_title' => 'Live work']);
    $other->documents()->create(['document_type' => 'detailed_proposal', 'file_path' => $sharedPath]);
    $other->documents()->create(['document_type' => 'work_plan', 'source_data' => ['images' => [['stored_path' => $sharedImage]]]]);
    $expired->documents()->create(['document_type' => 'work_plan', 'file_path' => $externalPath]);
    $expired->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();
    $recent = ProposalDraft::create(['user_id' => $this->owner->id, 'project_title' => 'Recently deleted']);
    $recent->delete();
    $topic = TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Official record', 'status' => 'revision_requested']);
    $projectDocument = ProjectDocument::factory()->create([
        'topic_id' => $topic->id, 'uploaded_by' => $this->owner->id, 'file_path' => $officialSharedPath,
    ]);
    $linked = ProposalDraft::create(['user_id' => $this->owner->id, 'topic_id' => $topic->id, 'project_title' => 'Linked work']);
    $linked->delete();
    $linked->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();
    $officialHistory = $linked->documentVersions()->create([
        'topic_id' => $topic->id, 'document_type' => 'detailed_proposal', 'version_number' => 1,
    ]);

    $this->artisan('proposal-drafts:purge')->assertSuccessful();

    expect(ProposalDraft::withTrashed()->find($expired->id))->toBeNull();
    expect(ProposalDraftDocumentVersion::find($history->id))->toBeNull();
    $this->assertModelExists($recent);
    $this->assertModelExists($linked);
    $this->assertModelExists($topic);
    $this->assertModelExists($projectDocument);
    $this->assertModelExists($officialHistory);
    Storage::disk('local')->assertMissing($privatePath);
    Storage::disk('local')->assertExists($sharedPath);
    Storage::disk('local')->assertExists($sharedImage);
    Storage::disk('local')->assertExists($officialSharedPath);
    Storage::disk('local')->assertExists($externalPath);
});
