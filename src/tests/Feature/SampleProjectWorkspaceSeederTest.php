<?php

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Models\User;
use Database\Seeders\SampleProjectWorkspaceSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('sample workspaces preserve old projects and drafts and provide editable proposals and all reports without duplicates', function () {
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 3)->startOfDay());
    Role::findOrCreate('faculty_researcher', 'web');
    Role::findOrCreate('faculty', 'web');
    $owner = User::factory()->create();
    $owner->assignRole(['faculty', 'faculty_researcher']);
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty_researcher');
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $real = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Real project', 'status' => 'pending']);
    $topics = collect();
    Storage::disk('local')->put('sample-proposal.pdf', '%PDF sample proposal');
    foreach (['ongoing', 'completed'] as $status) {
        $topic = TopicProposal::create([
            'user_id' => $owner->id, 'title' => 'Old '.$status.' example',
            'description' => '[lifecycle-demo:'.$status.'] Existing demo',
            'estimated_budget' => 24000, 'estimated_duration_months' => 6,
            'status' => 'approved', 'project_status' => $status,
            'notice_to_proceed_issued_at' => '2026-01-01',
        ]);
        $version = $topic->versions()->create([
            'submitted_by' => $owner->id, 'version_number' => 1, 'submission_type' => 'initial',
            'title' => $topic->title, 'estimated_budget' => 24000, 'estimated_duration_months' => 6,
            'file_path' => 'sample-proposal.pdf', 'original_filename' => 'sample-proposal.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 20, 'checksum' => hash('sha256', 'sample'),
        ]);
        foreach (['detailed_proposal', 'work_plan'] as $type) {
            $version->files()->create([
                'document_type' => $type, 'position' => 0, 'uploaded_by' => $owner->id,
                'file_path' => 'sample-proposal.pdf', 'original_filename' => $type.'.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 20, 'checksum' => hash('sha256', 'sample'),
                'source_data' => $type === 'work_plan' ? [
                    'project_title' => $topic->title, 'total_duration_months' => 6,
                    'planned_start' => '2026-01-01', 'planned_end' => '2026-06-30',
                    'entries' => [
                        ['objective' => 'Establish baseline', 'activity' => 'Collect baseline data', 'expected_output' => 'Baseline dataset', 'months' => [1, 2, 3]],
                        ['objective' => 'Evaluate pilot', 'activity' => 'Validate the prototype', 'expected_output' => 'Pilot evaluation', 'months' => [4, 5, 6]],
                    ],
                ] : ['project_title' => $topic->title, 'introduction' => 'An existing project introduction.', 'methodology' => []],
            ]);
        }
        $topic->collaborators()->create([
            'user_id' => $collaborator->id, 'name' => $collaborator->name, 'email' => $collaborator->email,
            'accepted_at' => now(), 'project_role' => 'secretary',
        ]);
        $topics->put($status, $topic);
    }
    $saved = ProjectNarrativeReportDraft::create([
        'topic_id' => $topics['ongoing']->id, 'user_id' => $owner->id, 'report_type' => 'progress',
        'source_data' => ['reporting_date' => '2026-03-31', 'introduction' => 'Keep my private writing'], 'lock_version' => 4,
    ]);
    $before = $topics->map(fn ($topic) => $topic->fresh()->getAttributes())->all();
    $this->seed(SampleProjectWorkspaceSeeder::class);
    $draft = ProposalDraft::where('project_title', 'Old ongoing example')->firstOrFail();
    $draft->update(['project_title' => '[Sample draft] Old ongoing example']);
    $document = $draft->documents()->first();
    $document->update([
        'source_data' => ['project_title' => '[Sample draft] Old ongoing example', 'introduction' => 'My edited proposal'],
        'file_path' => 'old-prepared-title.pdf',
    ]);
    $practice = TopicProposal::where('description', 'like', '[report-draft-demo:'.$topics['completed']->id.']%')->firstOrFail();
    $practice->update(['title' => 'My edited practice title']);
    $this->seed(SampleProjectWorkspaceSeeder::class);

    expect(TopicProposal::count())->toBe(5)
        ->and(ProposalDraft::count())->toBe(2)
        ->and(ProjectMonitoringDraft::count())->toBe(6)
        ->and(ProjectNarrativeReportDraft::count())->toBe(12)
        ->and(ProjectProgressReport::count())->toBe(0)
        ->and(ProjectNarrativeReport::count())->toBe(0)
        ->and($real->fresh()->status)->toBe('pending')
        ->and($topics->map(fn ($topic) => $topic->fresh()->getAttributes())->all())->toBe($before)
        ->and($saved->fresh()->source_data['introduction'])->toBe('Keep my private writing')
        ->and($saved->fresh()->lock_version)->toBe(4)
        ->and($draft->fresh()->project_title)->toBe('Old ongoing example')
        ->and($document->fresh()->source_data['project_title'])->toBe('Old ongoing example')
        ->and($document->fresh()->file_path)->toBeNull()
        ->and($document->fresh()->lock_version)->toBe(1)
        ->and($draft->documents()->first()->source_data['introduction'])->toBe('My edited proposal')
        ->and($practice->fresh()->title)->toBe('My edited practice title');

    $this->actingAs($owner)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $this->get(route('faculty.proposal-drafts.show', $draft))->assertOk();
    $this->get(route('topics.show', $topics['completed']))->assertOk()
        ->assertSee('Open sample proposal draft')->assertViewHas('sampleProposalDraft', fn ($sample): bool => $sample->project_title === 'Old completed example');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->get(route('topics.show', $topics['completed']))->assertOk()
        ->assertSee('Open editable report sample')
        ->assertSee(route('research.show', $practice), false);
    $this->get(route('topics.show', $practice))->assertOk()->assertSee('Fill draft');
    $this->get(route('project-progress.create', $practice))->assertOk()->assertSee('SAMPLE-MT-')->assertSee('submissionOpen: false', false);
    foreach (['progress', 'terminal'] as $type) {
        $this->get(route('project-narrative-reports.create', ['topic' => $practice, 'report_type' => $type]))
            ->assertOk()->assertSee('SAMPLE ONLY')->assertSee('submissionOpen: false', false);
        $sample = ProjectNarrativeReportDraft::where('topic_id', $practice->id)->where('user_id', $owner->id)->where('report_type', $type)->firstOrFail();
        $this->postJson(route('project-narrative-reports.draft', $practice), [...$sample->source_data, 'draft_version' => 1])->assertOk();
    }
    $monitoring = ProjectMonitoringDraft::where('topic_id', $practice->id)->where('user_id', $owner->id)->firstOrFail();
    $this->postJson(route('project-progress.draft', $practice), [...$monitoring->source_data, 'draft_version' => 1])->assertOk();
    $this->actingAs($collaborator)->get(route('project-progress.create', $practice))->assertOk()->assertSee('SAMPLE-MT-');
    $this->actingAs($outsider)->get(route('project-progress.create', $practice))->assertForbidden();
    $this->actingAs($owner)->get(route('project-progress.create', $topics['completed']))->assertNotFound();
    Notification::assertNothingSent();
});

test('sample workspaces cannot be seeded in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app()->call([app(SampleProjectWorkspaceSeeder::class), 'run']))->toThrow(RuntimeException::class, 'Sample workspaces may not be added in production.');
        expect(TopicProposal::count())->toBe(0)->and(ProposalDraft::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
