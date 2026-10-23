<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProposalSignatureWorkflow;
use App\Services\WorkspaceActionItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole(['faculty', 'faculty_researcher', 'research_head']);
    $this->actionPayloads = collect();
    foreach ([TopicProposal::class, ProposalVersion::class] as $model) {
        Event::listen('eloquent.retrieved: '.$model, function (Model $record): void {
            $this->actionPayloads->push($record->getAttributes());
        });
    }
});

afterEach(function () {
    $this->travelBack();
});

test('proposal actions retain signature decisions without hydrating proposal bodies', function () {
    Storage::fake('local');
    $expected = [];
    foreach (['pending', 'signed', 'unsigned'] as $state) {
        $topic = TopicProposal::create([
            'user_id' => $this->user->id, 'research_manual_id' => null,
            'title' => 'Proposal '.$state, 'description' => str_repeat('Proposal body. ', 3000),
            'status' => $state === 'pending' ? 'pending' : TopicProposal::STATUS_READY_FOR_SIGNATURE,
        ]);
        $version = $topic->versions()->create([
            'submitted_by' => $this->user->id, 'version_number' => 1, 'submission_type' => 'initial',
            'title' => $topic->title, 'description' => str_repeat('Version body. ', 3000),
            'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
        ]);
        if ($state !== 'pending') {
            foreach (ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES as $documentType) {
                $source = $version->files()->create([
                    'document_type' => $documentType, 'position' => 0, 'file_path' => 'source.pdf',
                    'original_filename' => 'source.pdf', 'mime_type' => 'application/pdf',
                ]);
                if ($state === 'signed') {
                    $path = 'signed/'.$source->id.'.pdf';
                    Storage::disk('local')->put($path, '%PDF-signed');
                    $version->files()->create([
                        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD, 'position' => $source->id,
                        'source_version_file_id' => $source->id, 'file_path' => $path,
                        'original_filename' => 'signed.pdf', 'mime_type' => 'application/pdf',
                        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED],
                    ]);
                }
            }
        }
        if ($state !== 'unsigned') {
            $expected[] = [
                'key' => 'proposal:'.$topic->id,
                'label' => $state === 'pending' ? 'Review submitted proposal' : 'Review completed signed papers',
                'title' => $topic->title, 'url' => route('topics.show', $topic).'#proposal-review',
            ];
        }
    }
    TopicProposal::create(['user_id' => $this->user->id, 'research_manual_id' => null, 'title' => 'No version', 'status' => 'pending']);
    $this->actingAs($this->user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->actionPayloads = collect();

    expect(app(WorkspaceActionItems::class)->for($this->user))->toBe($expected);
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Proposal action hydrated bytes: '.$this->actionPayloads->sum(fn (array $attributes): int => strlen(json_encode($attributes))).PHP_EOL);
    }
    expect($this->actionPayloads)->not->toBeEmpty();
    foreach ($this->actionPayloads as $attributes) {
        expect($attributes)->not->toHaveKey('description')->not->toHaveKey('research_head_screening');
    }
});

test('overdue actions retain approved scheduling and progress without hydrating proposal bodies', function (string $workspace) {
    $this->travelTo('2026-04-15');
    $topic = TopicProposal::create([
        'user_id' => $this->user->id, 'research_manual_id' => null, 'title' => 'Implementation project',
        'description' => str_repeat('Proposal body. ', 3000), 'status' => 'approved', 'project_status' => 'ongoing',
        'estimated_duration_months' => 12, 'notice_to_proceed_issued_at' => '2026-01-15',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 9],
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $this->user->id, 'version_number' => 1, 'submission_type' => 'initial',
        'title' => $topic->title, 'description' => str_repeat('Version body. ', 3000),
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0,
        'file_path' => 'work-plan.pdf', 'original_filename' => 'work-plan.pdf', 'mime_type' => 'application/pdf',
        'source_data' => ['total_duration_months' => 9, 'entries' => [
            ['objective' => 'Establish the baseline', 'activity' => 'Conduct interviews', 'expected_output' => 'Dataset', 'months' => [1]],
            ['objective' => 'Build the prototype', 'activity' => 'Develop prototype', 'expected_output' => 'Prototype', 'months' => [1, 2, 3]],
        ]],
    ]);
    $topic->progressReports()->create([
        'submitted_by' => $this->user->id, 'reporting_date' => '2026-04-14', 'period_start' => '2026-01-15',
        'version_number' => 1, 'progress_percentage' => 25, 'accomplishments' => 'Baseline completed',
        'review_status' => 'reviewed', 'work_plan' => [['source_work_plan_index' => 0, 'accomplished_percentage' => 25]],
    ]);
    $this->actingAs($this->user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $this->actionPayloads = collect();

    expect(app(WorkspaceActionItems::class)->for($this->user))->toBe([
        [
            'key' => 'overdue:'.$topic->id.':1:2026-04-14',
            'label' => $workspace === User::WORKSPACE_RESEARCH_HEAD ? 'Review overdue Work Plan target' : 'Address overdue Work Plan target',
            'title' => 'Implementation project · Build the prototype', 'url' => route('topics.show', $topic).'#project-monitoring',
        ],
    ]);
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Overdue action hydrated bytes ('.$workspace.'): '.$this->actionPayloads->sum(fn (array $attributes): int => strlen(json_encode($attributes))).PHP_EOL);
    }
    expect($this->actionPayloads)->not->toBeEmpty();
    foreach ($this->actionPayloads as $attributes) {
        expect($attributes)->not->toHaveKey('description')->not->toHaveKey('research_head_screening');
    }
})->with([User::WORKSPACE_RESEARCH_HEAD, User::WORKSPACE_FACULTY_RESEARCHER]);

test('invitation actions reuse loaded drafts instead of checking each draft again', function () {
    foreach (range(1, 12) as $index) {
        $draft = ProposalDraft::create(['user_id' => $this->user->id, 'research_manual_id' => null, 'project_title' => 'Invitation '.$index]);
        ProposalDraftMember::factory()->create([
            'proposal_draft_id' => $draft->id, 'user_id' => $this->user->id, 'accepted_at' => null,
        ]);
    }
    $this->actingAs($this->user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $actions = app(WorkspaceActionItems::class)->for($this->user);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Invitation action queries: '.$queries->count().' / database ms: '.$queries->sum('time').PHP_EOL);
    }

    expect($actions)->toHaveCount(12);
    expect(array_column($actions, 'label'))->each->toBe('Review project staff invitation');
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'select exists') && str_contains($query['query'], 'proposal_drafts')))->toBeEmpty();
});

test('invitation authorization checks unloaded drafts and rejects deleted drafts and other users', function (bool $eagerLoad) {
    $draft = ProposalDraft::create(['user_id' => $this->user->id, 'research_manual_id' => null, 'project_title' => 'Invitation']);
    $membership = ProposalDraftMember::factory()->create([
        'proposal_draft_id' => $draft->id, 'user_id' => $this->user->id, 'accepted_at' => null,
    ]);
    $this->actingAs($this->user);
    $membership = $membership->fresh();
    if ($eagerLoad) {
        $membership->load('draft');
    }
    expect(Gate::forUser($this->user)->allows('review', $membership))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('accept', $membership))->toBeTrue();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    expect(Gate::forUser($outsider)->allows('review', $membership))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('accept', $membership))->toBeFalse();
    $membership->update(['accepted_at' => now()]);
    expect(Gate::forUser($this->user)->allows('review', $membership))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('accept', $membership))->toBeFalse();
    $draft->delete();
    $membership = $membership->fresh();
    if ($eagerLoad) {
        $membership->load('draft');
    }
    expect(Gate::forUser($this->user)->allows('review', $membership))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('accept', $membership))->toBeFalse();
})->with([false, true]);
