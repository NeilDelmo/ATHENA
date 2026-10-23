<?php

use App\Actions\SaveProposalDraftDetails;
use App\Actions\SaveProposalDraftDocument;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('revision submission preserves newer workspace changes instead of submitting an old snapshot', function (?string $change) {
    Notification::fake();
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $topic = TopicProposal::create([
        'user_id' => $faculty->id, 'title' => 'Concurrent revision', 'status' => 'revision_requested',
        'estimated_budget' => 1000, 'estimated_duration_months' => 12,
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id, 'version_number' => 1, 'submission_type' => 'initial',
        'file_path' => 'proposals/original.pdf', 'original_filename' => 'original.pdf', 'title' => $topic->title,
    ]);
    $original = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'position' => 0,
        'file_path' => 'proposals/original.pdf', 'original_filename' => 'original.pdf',
        'source_data' => ['executive_brief' => 'Original summary'], 'checksum' => hash('sha256', 'Original'),
    ]);
    $review = $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested']);
    $fileRevision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $original->id, 'document_type' => $original->document_type,
        'original_filename' => $original->original_filename, 'revision_note' => 'Clarify the summary.',
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id, 'topic_id' => $topic->id, 'project_title' => $topic->title,
        'project_leader' => $faculty->name, 'duration_months' => 12,
        'lock_version' => 0,
        'planned_start' => '2026-10-01', 'planned_end' => '2027-09-30',
    ]);
    $stagedPath = $draft->storageDirectory().'/revision/saved.pdf';
    Storage::disk('local')->put($stagedPath, '%PDF-1.7 saved revision');
    $staged = $draft->documents()->create([
        'document_type' => $original->document_type, 'position' => 0, 'lock_version' => 1,
        'source_data' => ['executive_brief' => 'Saved revision'], 'completed_at' => now(),
        'file_path' => $stagedPath, 'original_filename' => 'saved.pdf', 'mime_type' => 'application/pdf',
        'checksum' => hash('sha256', '%PDF-1.7 saved revision'),
    ]);
    $changed = false;
    Event::listen('eloquent.retrieved: '.ProposalDraftDocument::class, function (ProposalDraftDocument $document) use ($change, $staged, $draft, $faculty, &$changed): void {
        if ($changed || $change === null || $document->id !== $staged->id) {
            return;
        }

        $changed = true;
        if ($change === 'paper') {
            app(SaveProposalDraftDocument::class)->handle($draft, $faculty, $document->document_type, 0, $document->lock_version, [
                'source_data' => ['executive_brief' => 'Newer teammate revision'], 'completed_at' => now(),
            ]);
        } elseif ($change === 'details') {
            app(SaveProposalDraftDetails::class)->handle($draft, $draft->lock_version, ['project_title' => 'Newer teammate title']);
        } elseif ($change === 'added paper') {
            $draft->documents()->create([
                'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0, 'lock_version' => 1,
                'source_data' => ['entries' => []],
            ]);
        } else {
            $document->delete();
        }
    });

    $response = $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->patch(route('faculty.topics.resubmit', $topic), [
            'revision_draft_id' => $draft->id, 'title' => $topic->title,
            'estimated_budget' => 1000, 'estimated_duration_months' => 12, 'feedback_review_id' => $review->id,
            'feedback_responses' => ['file_'.$fileRevision->id => [
                'response' => 'Clarified the summary.', 'no_change' => false, 'page' => 1, 'paragraph' => 2,
            ]],
        ]);

    if ($change === null) {
        $response->assertRedirect(route('faculty.dashboard'))->assertSessionHasNoErrors();
        expect($topic->fresh()->status)->toBe('resubmitted')
            ->and($topic->versions()->count())->toBe(2)
            ->and(ProposalDraft::query()->whereKey($draft->id)->exists())->toBeFalse();
    } else {
        $response->assertSessionHasErrorsIn('resubmission', 'revision_draft_id');
        expect($changed)->toBeTrue()
            ->and($topic->fresh()->status)->toBe('revision_requested')
            ->and($topic->versions()->count())->toBe(1)
            ->and($fileRevision->fresh()->resolved_at)->toBeNull()
            ->and(ProposalDraft::query()->whereKey($draft->id)->exists())->toBeTrue();
        Storage::disk('local')->assertExists($stagedPath);
    }
})->with([
    'newer paper save' => ['paper'],
    'newer project details save' => ['details'],
    'paper added' => ['added paper'],
    'paper removed' => ['removed paper'],
    'unchanged snapshot' => [null],
]);
