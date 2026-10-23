<?php

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\InitialScreeningSubmissionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'research_head']);
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $faculty = User::factory()->create();
    $this->topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Concurrent assessment review',
        'status' => TopicProposal::STATUS_GAD_REVIEW,
        'review_stage' => 'gad',
    ]);
    $this->version = $this->topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/concurrent-review.pdf',
        'original_filename' => 'concurrent-review.pdf',
        'title' => $this->topic->title,
    ]);
    Storage::disk('local')->put('proposals/concurrent-review.pdf', '%PDF-1.7 submitted proposal');
    $this->detailedProposal = $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposals/concurrent-review.pdf',
        'original_filename' => 'concurrent-review.pdf',
        'mime_type' => 'application/pdf',
    ]);
    foreach ([
        'gad' => [ProposalVersionFile::TYPE_GAD_CHECKLIST, [
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_score' => 12,
            'gad_outcome' => 'passed',
            'gad_signature_confirmed' => true,
        ]],
        'evaluation' => [ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, [
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'narrative_evaluation' => 'The completed proposal is endorsed for LREC review.',
            'recommended_action' => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
        ]],
    ] as $key => [$type, $data]) {
        $source = $this->version->files()->create([
            'document_type' => $type,
            'position' => 0,
            'file_path' => 'proposals/'.$type.'.pdf',
            'original_filename' => $type.'.pdf',
        ]);
        $this->{$key} = $this->version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => $key === 'gad' ? 1 : 2,
            'source_version_file_id' => $source->id,
            'file_path' => 'proposals/completed-'.$type.'.pdf',
            'original_filename' => 'completed-'.$type.'.pdf',
            'source_data' => [...$data, 'target_document_type' => $type],
        ]);
    }
});

test('a review decision rejects assessments changed while waiting for its locks', function (string $assessment, bool $replace) {
    $changed = false;
    $transactionLevel = DB::transactionLevel();
    Event::listen('eloquent.retrieved: '.TopicProposal::class, function (TopicProposal $topic) use ($assessment, $replace, $transactionLevel, &$changed): void {
        if ($changed || $topic->id !== $this->topic->id || DB::transactionLevel() <= $transactionLevel) {
            return;
        }

        $changed = true;
        $file = $this->{$assessment};
        $attributes = [
            ...$file->only(['document_type', 'source_version_file_id', 'file_path', 'original_filename']),
            'source_data' => [
                ...$file->source_data,
                ...($assessment === 'gad'
                    ? ['gad_score' => 3, 'gad_outcome' => 'returned']
                    : ['recommended_action' => InitialScreeningSubmissionOrder::MINOR_REVISION]),
            ],
        ];
        if ($replace) {
            $this->version->files()->create([...$attributes, 'position' => 3]);
        } else {
            $file->update($attributes);
        }
    });

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_LREC_QUEUED,
            'initial_clearance_confirmed' => true,
        ])->assertSessionHasErrors('status');

    expect($changed)->toBeTrue()
        ->and($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_GAD_REVIEW)
        ->and($this->topic->reviews()->count())->toBe(0);
})->with([
    'new returned GAD assessment' => ['gad', true],
    'new co-evaluator revision recommendation' => ['evaluation', true],
    'changed existing recommendation' => ['evaluation', false],
]);

test('unchanged assessments still allow the proposal to proceed to LREC', function () {
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => TopicProposal::STATUS_LREC_QUEUED,
            'initial_clearance_confirmed' => true,
        ])->assertSessionHasNoErrors();

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_QUEUED);
});

test('LREC committee comments without selected papers return the detailed proposal for revision', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route('research_head.topics.updateStatus', $this->topic), [
            'status' => 'revision_requested',
            'committee_comments' => [['comment' => 'Clarify the sampling procedure.']],
        ])->assertSessionHasNoErrors();

    $review = $this->topic->reviews()->where('decision', 'revision_requested')->sole();
    expect($review->fileRevisions()->sole()->proposal_version_file_id)->toBe($this->detailedProposal->id)
        ->and($review->committee_comments[0]['comment'])->toBe('Clarify the sampling procedure.');
});
