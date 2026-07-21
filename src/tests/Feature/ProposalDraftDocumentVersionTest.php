<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('proposal_papers.expense-breakdown.mode', 'upload');
    config()->set('proposal_papers.expense-breakdown.accepted_extensions', ['pdf']);
    config()->set('proposal_papers.expense-breakdown.accepted_mime_types', ['application/pdf']);
    config()->set('proposal_papers.expense-breakdown.max_kilobytes', 25600);

    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $researchHead = User::factory()->create();
    $researchHead->assignRole('research_head');
    $this->owner = User::factory()->create(['name' => 'History Owner']);
    $this->owner->assignRole('faculty');
    $this->collaborator = User::factory()->create(['name' => 'History Collaborator']);
    $this->collaborator->assignRole('faculty');
    $this->outsider = User::factory()->create(['name' => 'History Outsider']);
    $this->outsider->assignRole('faculty');

    $researchCall = ResearchCall::create([
        'title' => 'Version History Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $researchHead->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->owner->id,
        'research_call_id' => $researchCall->id,
        'project_title' => 'Versioned Coastal Proposal',
        'duration_months' => 6,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-01-31',
        'project_leader' => $this->owner->name,
    ]);
    $this->draft->members()->create([
        'user_id' => $this->collaborator->id,
        'name' => $this->collaborator->name,
        'email' => $this->collaborator->email,
    ]);

    Storage::fake('local');
    $this->withoutVite();
});

test('replaced and removed PDF uploads remain in collaborator-attributed version history', function () {
    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('expenses-v1.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect();

    $firstVersion = ProposalDraftDocumentVersion::query()->sole();
    $firstPath = $firstVersion->file_path;

    expect($firstVersion->version_number)->toBe(1)
        ->and($firstVersion->creator->is($this->owner))->toBeTrue()
        ->and($firstVersion->original_filename)->toBe('expenses-v1.pdf');
    Storage::disk('local')->assertExists($firstPath);

    $this->actingAs($this->collaborator)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 1,
            'documents' => [UploadedFile::fake()->create('expenses-v2.pdf', 120, 'application/pdf')],
        ])
        ->assertRedirect();

    $versions = ProposalDraftDocumentVersion::query()->orderBy('version_number')->get();
    $currentDocument = $this->draft->documents()->sole();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->proposal_draft_document_id)->toBe($currentDocument->id)
        ->and($versions[1]->proposal_draft_document_id)->toBe($currentDocument->id)
        ->and($versions[1]->creator->is($this->collaborator))->toBeTrue()
        ->and($versions[1]->original_filename)->toBe('expenses-v2.pdf')
        ->and($versions[1]->isCurrent())->toBeTrue();
    Storage::disk('local')->assertExists($firstPath);

    $this->actingAs($this->collaborator)
        ->get(route('faculty.proposal-drafts.history.index', $this->draft))
        ->assertOk()
        ->assertSee('Version history')
        ->assertSee('expenses-v1.pdf')
        ->assertSee('expenses-v2.pdf')
        ->assertSee('History Owner')
        ->assertSee('History Collaborator')
        ->assertSee('Current')
        ->assertSee('Previous');

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.history.download', [$this->draft, $firstVersion]))
        ->assertDownload('expenses-v1.pdf');

    $this->actingAs($this->collaborator)
        ->delete(route('faculty.proposal-drafts.papers.remove', [
            $this->draft,
            'expense-breakdown',
            $currentDocument,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Estimated Expense Breakdown file removed. Previous versions remain available in history.');

    expect($this->draft->documents()->count())->toBe(0)
        ->and(ProposalDraftDocumentVersion::query()->count())->toBe(2);
    Storage::disk('local')->assertExists($versions[0]->file_path);
    Storage::disk('local')->assertExists($versions[1]->file_path);

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('expenses-v3.pdf', 130, 'application/pdf')],
        ])
        ->assertRedirect();

    $thirdVersion = ProposalDraftDocumentVersion::query()->latest('version_number')->firstOrFail();

    expect($thirdVersion->version_number)->toBe(3)
        ->and($thirdVersion->original_filename)->toBe('expenses-v3.pdf')
        ->and($thirdVersion->isCurrent())->toBeTrue();
});

test('history and retained files are available only to authorized workspace members', function () {
    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('private-history.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect();

    $version = ProposalDraftDocumentVersion::query()->sole();

    $this->actingAs($this->collaborator)
        ->get(route('faculty.proposal-drafts.history.index', $this->draft))
        ->assertOk();
    $this->actingAs($this->collaborator)
        ->get(route('faculty.proposal-drafts.history.download', [$this->draft, $version]))
        ->assertDownload('private-history.pdf');

    $this->actingAs($this->outsider)
        ->get(route('faculty.proposal-drafts.history.index', $this->draft))
        ->assertForbidden();
    $this->actingAs($this->outsider)
        ->get(route('faculty.proposal-drafts.history.download', [$this->draft, $version]))
        ->assertForbidden();

    $otherDraft = $this->draft->replicate()->fill(['user_id' => $this->collaborator->id]);
    $otherDraft->save();
    $this->actingAs($this->collaborator)
        ->put(route('faculty.proposal-drafts.papers.update', [$otherDraft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('other-draft.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect();
    $otherVersion = ProposalDraftDocumentVersion::query()
        ->where('proposal_draft_id', $otherDraft->id)
        ->sole();

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.history.download', [$this->draft, $otherVersion]))
        ->assertNotFound();
});

test('generated paper saves are included in the same collaborator history', function () {
    $this->actingAs($this->collaborator)
        ->put(route('faculty.proposal-drafts.work-plan.update', $this->draft), [
            'document_version' => 0,
            'entries' => [[
                'objective' => 'Create the baseline',
                'expected_output' => 'Baseline report',
                'activity' => 'Conduct field assessment',
                'months' => [1, 2],
            ]],
        ])
        ->assertRedirect();

    $version = ProposalDraftDocumentVersion::query()->sole();

    expect($version->creator->is($this->collaborator))->toBeTrue()
        ->and($version->file_path)->toBeNull()
        ->and($version->source_data['entries'][0]['objective'])->toBe('Create the baseline');

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.history.index', [
            $this->draft,
            'paper' => 'work-plan',
        ]))
        ->assertOk()
        ->assertSee('Attachment A: Work Plan')
        ->assertSee('Structured form data saved')
        ->assertSee('History Collaborator');
});

test('the proposal package links to its chronological history', function () {
    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee('History')
        ->assertSee(route('faculty.proposal-drafts.history.index', $this->draft));

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.history.index', [
            $this->draft,
            'paper' => 'expense-breakdown',
        ]))
        ->assertOk()
        ->assertSee('No saved versions yet');
});

test('an identical upload does not create a duplicate version or retain an unused file', function () {
    $upload = fn (): UploadedFile => UploadedFile::fake()->create(
        'same-expenses.pdf',
        100,
        'application/pdf',
    );

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [$upload()],
        ])
        ->assertRedirect();

    $document = $this->draft->documents()->sole();
    $originalPath = $document->file_path;

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 1,
            'change_note' => 'This should not create a duplicate.',
            'documents' => [$upload()],
        ])
        ->assertRedirect();

    expect(ProposalDraftDocumentVersion::query()->count())->toBe(1)
        ->and($document->fresh()->lock_version)->toBe(1)
        ->and($document->fresh()->file_path)->toBe($originalPath)
        ->and(Storage::disk('local')->allFiles($this->draft->storageDirectory()))->toHaveCount(1);
});

test('save notes automatic summaries and meaningful differences appear in history', function () {
    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 0,
            'change_note' => 'Initial estimate from the finance meeting.',
            'documents' => [UploadedFile::fake()->createWithContent('expenses-original.pdf', "%PDF-1.7\noriginal expense estimate")],
        ])
        ->assertRedirect();

    $this->actingAs($this->collaborator)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 1,
            'change_note' => 'Recalculated travel and supplies.',
            'documents' => [UploadedFile::fake()->createWithContent('expenses-revised.pdf', "%PDF-1.7\nrevised travel and supplies estimate")],
        ])
        ->assertRedirect();

    $latest = ProposalDraftDocumentVersion::query()->latest('version_number')->firstOrFail();

    expect($latest->change_note)->toBe('Recalculated travel and supplies.')
        ->and($latest->change_summary)->toContain('Replaced Estimated Expense Breakdown')
        ->and(collect($latest->changes)->pluck('label')->all())->toContain(
            'File name',
            'File contents',
            'File size',
        );

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.history.index', $this->draft))
        ->assertOk()
        ->assertSee('Recalculated travel and supplies.')
        ->assertSee('See 3 changes')
        ->assertSee('File contents')
        ->assertSee('Restore this version');

    $this->actingAs($this->owner)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee('Recent activity')
        ->assertSee('Recalculated travel and supplies.');
});

test('generated forms skip identical saves and describe changed structured content', function () {
    $payload = [
        'document_version' => 0,
        'change_note' => 'Created the initial timeline.',
        'entries' => [[
            'objective' => 'Create the baseline',
            'expected_output' => 'Baseline report',
            'activity' => 'Conduct field assessment',
            'months' => [1, 2],
        ]],
    ];

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.work-plan.update', $this->draft), $payload)
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.work-plan.update', $this->draft), [
            ...$payload,
            'document_version' => 1,
            'change_note' => 'No actual change.',
        ])
        ->assertRedirect();

    expect(ProposalDraftDocumentVersion::query()->count())->toBe(1)
        ->and($this->draft->documents()->sole()->lock_version)->toBe(1);

    $changedPayload = $payload;
    $changedPayload['document_version'] = 1;
    $changedPayload['change_note'] = 'Expanded the baseline objective.';
    $changedPayload['entries'][0]['objective'] = 'Create and validate the baseline';

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.work-plan.update', $this->draft), $changedPayload)
        ->assertRedirect();

    $latest = ProposalDraftDocumentVersion::query()->latest('version_number')->firstOrFail();

    expect(ProposalDraftDocumentVersion::query()->count())->toBe(2)
        ->and($latest->change_summary)->toBe('Updated Attachment A: Work Plan (1 field changed).')
        ->and($latest->changes)->toHaveCount(1)
        ->and($latest->changes[0]['label'])->toBe('Work-plan entries');
});

test('an earlier PDF can be restored as a new version without overwriting history', function () {
    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('expenses-v1.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->put(route('faculty.proposal-drafts.papers.update', [$this->draft, 'expense-breakdown']), [
            'document_version' => 1,
            'documents' => [UploadedFile::fake()->create('expenses-v2.pdf', 120, 'application/pdf')],
        ])
        ->assertRedirect();

    [$firstVersion, $secondVersion] = ProposalDraftDocumentVersion::query()
        ->orderBy('version_number')
        ->get();

    $this->actingAs($this->collaborator)
        ->post(route('faculty.proposal-drafts.history.restore', [$this->draft, $firstVersion]), [
            'document_version' => 2,
            'change_note' => 'The earlier estimate was approved by the team.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $restored = ProposalDraftDocumentVersion::query()->latest('version_number')->firstOrFail();
    $document = $this->draft->documents()->sole();

    expect(ProposalDraftDocumentVersion::query()->count())->toBe(3)
        ->and($restored->version_number)->toBe(3)
        ->and($restored->action)->toBe('restored')
        ->and($restored->restored_from_version_id)->toBe($firstVersion->id)
        ->and($restored->creator->is($this->collaborator))->toBeTrue()
        ->and($restored->change_note)->toBe('The earlier estimate was approved by the team.')
        ->and($document->lock_version)->toBe(3)
        ->and($document->original_filename)->toBe('expenses-v1.pdf')
        ->and($document->file_path)->not->toBe($firstVersion->file_path)
        ->and($restored->file_path)->toBe($document->file_path);
    Storage::disk('local')->assertExists($firstVersion->file_path);
    Storage::disk('local')->assertExists($secondVersion->file_path);
    Storage::disk('local')->assertExists($restored->file_path);

    $this->actingAs($this->owner)
        ->post(route('faculty.proposal-drafts.history.restore', [$this->draft, $firstVersion]), [
            'document_version' => 3,
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect(ProposalDraftDocumentVersion::query()->count())->toBe(3);

    $this->actingAs($this->outsider)
        ->post(route('faculty.proposal-drafts.history.restore', [$this->draft, $firstVersion]), [
            'document_version' => 3,
        ])
        ->assertForbidden();
});
