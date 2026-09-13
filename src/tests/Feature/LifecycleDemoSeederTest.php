<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use App\Models\User;
use Database\Seeders\LifecycleDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('lifecycle demo promotes prepared drafts into connected process records', function () {
    Storage::fake('local');
    Role::findOrCreate('research_head', 'web');
    Role::findOrCreate('faculty', 'web');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $call = ResearchCall::query()->create([
        'title' => 'Seeder source call', 'academic_year' => '2026-2027', 'term' => 'First',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open', 'created_by' => $head->id,
    ]);

    foreach (range(1, 21) as $draftNumber) {
        $draft = ProposalDraft::query()->create([
            'user_id' => $faculty->id,
            'research_call_id' => $call->id,
            'project_title' => 'Connected lifecycle project '.$draftNumber,
            'duration_months' => 12,
            'planned_start' => now()->subYear(),
            'planned_end' => now(),
            'project_leader' => $faculty->name,
        ]);
        foreach (['detailed_proposal', 'work_plan', 'expense_breakdown', 'line_item_budget', 'curriculum_vitae', 'gad_checklist', 'initial_screening_form'] as $documentType) {
            $contents = 'Prepared PDF for '.$documentType;
            $path = 'proposal-drafts/'.$faculty->id.'/'.$draft->id.'/'.$documentType.'.pdf';
            Storage::disk('local')->put($path, $contents);
            $draft->documents()->create([
                'document_type' => $documentType,
                'position' => 0,
                'file_path' => $path,
                'original_filename' => $documentType.'.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => strlen($contents),
                'checksum' => hash('sha256', $contents),
                'source_data' => match ($documentType) {
                    'line_item_budget' => ['computed_project_total' => 80000],
                    'work_plan' => [
                        'total_duration_months' => 12,
                        'entries' => [
                            [
                                'objective' => 'Establish the approved project baseline',
                                'activity' => 'Collect and validate baseline data',
                                'expected_output' => 'Validated baseline dataset',
                                'months' => [1, 2, 3, 4],
                            ],
                            [
                                'objective' => 'Implement the approved intervention',
                                'activity' => 'Build and pilot the project intervention',
                                'expected_output' => 'Pilot-ready intervention',
                                'months' => [5, 6, 7, 8],
                            ],
                            [
                                'objective' => 'Evaluate and report project outcomes',
                                'activity' => 'Analyze results and prepare final outputs',
                                'expected_output' => 'Outcome evaluation report',
                                'months' => [9, 10, 11, 12],
                            ],
                        ],
                    ],
                    default => [],
                },
                'completed_at' => now(),
            ]);
        }
    }

    $this->seed(LifecycleDemoSeeder::class);
    $this->seed(LifecycleDemoSeeder::class);

    $topics = TopicProposal::query()->where('description', 'like', '[lifecycle-demo:%')->get();
    $implementationReports = ProjectProgressReport::query()
        ->whereHas('topic', fn ($query) => $query->where('description', 'like', '[lifecycle-demo:%'))
        ->get();

    expect($topics)->toHaveCount(21)
        ->and($topics->every(fn (TopicProposal $topic): bool => $topic->versions()->exists()))->toBeTrue()
        ->and($topics->where('project_status', TopicProposal::PROJECT_STATUS_COMPLETED))->toHaveCount(3)
        ->and(ProjectNarrativeReport::query()->where('report_type', 'terminal')->where('review_status', 'reviewed')->count())->toBe(3)
        ->and(ResearchPublication::query()->count())->toBe(9)
        ->and($implementationReports)->toHaveCount(22)
        ->and($implementationReports->every(fn (ProjectProgressReport $report): bool => collect($report->work_plan)->isNotEmpty()))->toBeTrue()
        ->and($implementationReports->every(fn (ProjectProgressReport $report): bool => collect($report->work_plan)->every(
            fn (array $row): bool => array_key_exists('source_work_plan_index', $row)
                && ! str_starts_with($row['activity'], 'Lifecycle milestone'),
        )))->toBeTrue()
        ->and($topics->filter(fn (TopicProposal $topic): bool => $topic->progressReports()->exists() && $topic->notice_to_proceed_issued_at === null))->toBeEmpty()
        ->and(ProposalDraft::query()->count())->toBe(0);
});
