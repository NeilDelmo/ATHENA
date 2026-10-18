<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringQuarterService;
use App\Services\ProposalSignatureWorkflow;
use Database\Seeders\LaboratoryProgressReportUiDemoSeeder;
use Database\Seeders\PostApprovalUiDemoSeeder;
use Database\Seeders\ProgressReportUiDemoSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('laboratory samples provide one accessible progress report per quarter without changing existing reports', function () {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->andReturn("%PDF-1.4\nSample quarterly report\n%%EOF");
    Role::findOrCreate('research_head', 'web');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $owner = User::factory()->create();
    $topic = TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Predictive Maintenance Dashboard for University Laboratory Equipment',
        'description' => '[lifecycle-demo:completed-published-second] Sample project',
        'status' => 'approved', 'project_status' => 'completed', 'estimated_duration_months' => 9,
        'notice_to_proceed_issued_at' => '2025-10-01', 'notice_to_proceed_issued_by' => $head->id,
    ]);
    $legacy = ProjectNarrativeReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $owner->id, 'report_type' => 'progress',
        'tracking_number' => 'LIFE-'.$topic->id.'-NARRATIVE', 'submission_date' => '2026-01-01',
        'researchers' => $owner->name, 'implementation_start' => '2025-10-01', 'implementation_end' => '2026-06-30',
        'budget' => 25100, 'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'UI DEMONSTRATION DATA: Legacy report', 'introduction' => 'Original introduction',
        'objectives' => 'Original objectives', 'methodology' => 'Original methods', 'results_discussion' => 'Original results',
        'photos' => [], 'review_status' => 'reviewed', 'reviewed_by' => $head->id,
    ]);
    $terminal = $legacy->replicate()->fill(['tracking_number' => 'LIFE-'.$topic->id.'-TERMINAL', 'report_type' => 'terminal']);
    $terminal->save();
    $realTopic = $topic->replicate()->fill(['description' => 'An actual research project']);
    $realTopic->save();
    $realReport = $legacy->replicate()->fill(['topic_id' => $realTopic->id, 'tracking_number' => 'LIFE-'.$realTopic->id.'-NARRATIVE']);
    $realReport->save();
    $originals = [$legacy->fresh()->getAttributes(), $terminal->fresh()->getAttributes(), $realReport->fresh()->getAttributes()];

    $this->seed(LaboratoryProgressReportUiDemoSeeder::class);
    $this->seed(LaboratoryProgressReportUiDemoSeeder::class);
    $reports = $topic->narrativeReports()->whereNotNull('reporting_quarter')->reorder('reporting_quarter')->get();
    expect($reports)->toHaveCount(3)
        ->and($legacy->fresh()->getAttributes())->toBe($originals[0])
        ->and($terminal->fresh()->getAttributes())->toBe($originals[1])
        ->and($realReport->fresh()->getAttributes())->toBe($originals[2])
        ->and($reports->pluck('results_discussion')->unique())->toHaveCount(3);
    $this->actingAs($head)->withSession(['workspace' => User::WORKSPACE_RESEARCH_HEAD]);
    $page = $this->get(route('topics.show', $topic))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$page->getContent());
    $xpath = new DOMXPath($document);
    foreach ($reports as $index => $report) {
        $period = app(MonitoringQuarterService::class)->projectPeriods($topic)[$index];
        expect($report->reporting_quarter)->toBe($index + 1)
            ->and($report->period_start->toDateString())->toBe($period['start']->toDateString())
            ->and($report->period_end->toDateString())->toBe($period['end']->toDateString())
            ->and($report->reporting_date->toDateString())->toBe($period['end']->toDateString())
            ->and($report->submission_date->toDateString())->toBe($period['opens_at']->toDateString())
            ->and($report->review_status)->toBe('reviewed')
            ->and(Storage::disk('local')->get($report->official_pdf_path))->toStartWith('%PDF')
            ->and($xpath->query('//*[@data-progress-quarter="'.($index + 1).'"]//button[@aria-controls="narrative-report-'.$report->id.'"]')->length)->toBe(1)
            ->and($xpath->query('//*[@id="narrative-report-'.$report->id.'"]//a[@href="'.route('project-narrative-reports.show', $report).'"]')->length)->toBe(1);
        $this->get(route('project-narrative-reports.show', $report))->assertSuccessful()->assertSee(explode("\n\n", $report->results_discussion)[0]);
        $this->get(route('project-narrative-reports.download', $report))->assertSuccessful();
    }
});

test('post approval UI demos provide accessible reports and completion stages without duplicates', function () {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->andReturn("%PDF-1.4\nUI demo PDF\n%%EOF");
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $viewer = User::factory()->create();
    $viewer->assignRole(['faculty', 'faculty_researcher']);
    $other = User::factory()->create();
    $other->assignRole(['faculty', 'faculty_researcher']);
    $untouched = TopicProposal::create(['user_id' => $viewer->id, 'title' => 'Real project', 'status' => 'pending']);
    $topics = collect();
    Storage::disk('local')->put('demo-work-plan.pdf', '%PDF demo source');
    foreach (['ongoing', 'delayed', 'ongoing-second', 'delayed-second', 'completed-presented', 'completed-published', 'completed-published-second'] as $scenario) {
        $topic = TopicProposal::create([
            'user_id' => $scenario === 'ongoing' ? $viewer->id : $other->id,
            'title' => 'Demo '.$scenario, 'description' => '[lifecycle-demo:'.$scenario.'] Existing demo',
            'estimated_budget' => 24000, 'estimated_duration_months' => 9, 'status' => 'approved',
        ]);
        $version = $topic->versions()->create([
            'submitted_by' => $topic->user_id, 'version_number' => 1, 'submission_type' => 'initial',
            'title' => $topic->title, 'estimated_budget' => 24000, 'estimated_duration_months' => 9,
            'file_path' => 'demo-proposal.pdf', 'original_filename' => 'demo-proposal.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 12, 'checksum' => hash('sha256', 'demo'),
        ]);
        $version->files()->create([
            'document_type' => 'work_plan', 'position' => 0, 'uploaded_by' => $topic->user_id,
            'file_path' => 'demo-work-plan.pdf', 'original_filename' => 'demo-work-plan.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 12, 'checksum' => hash('sha256', 'demo'),
            'source_data' => ['total_duration_months' => 9, 'entries' => [
                ['objective' => 'Establish baseline', 'activity' => 'Collect baseline data', 'expected_output' => 'Baseline dataset', 'months' => [1, 2, 3]],
                ['objective' => 'Develop intervention', 'activity' => 'Build and pilot intervention', 'expected_output' => 'Validated pilot', 'months' => [4, 5, 6]],
                ['objective' => 'Evaluate outcomes', 'activity' => 'Analyze and report results', 'expected_output' => 'Final evaluation', 'months' => [7, 8, 9]],
            ]],
        ]);
        foreach (['detailed_proposal', 'line_item_budget', 'gad_checklist', 'initial_screening_form'] as $documentType) {
            $version->files()->create([
                'document_type' => $documentType, 'position' => 0, 'uploaded_by' => $topic->user_id,
                'file_path' => 'demo-work-plan.pdf', 'original_filename' => $documentType.'.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 16, 'checksum' => hash('sha256', '%PDF demo source'),
                'source_data' => [],
            ]);
        }
        $topics->put($scenario, $topic);
    }
    $this->seed(PostApprovalUiDemoSeeder::class);
    $this->seed(PostApprovalUiDemoSeeder::class);
    $realReport = ProjectNarrativeReport::create([
        'topic_id' => $untouched->id, 'submitted_by' => $viewer->id, 'report_type' => 'progress',
        'submission_date' => now(), 'researchers' => $viewer->name, 'implementation_start' => now(),
        'implementation_end' => now()->addMonths(3), 'budget' => 24000, 'funding_agency' => 'Research grant',
        'introduction' => 'Real research introduction.', 'objectives' => 'Real research objectives.',
        'methodology' => 'Real research methods.', 'results_discussion' => 'Real research results.',
        'tracking_number' => 'REAL-PROGRESS', 'photos' => [], 'accomplishment_summary' => 'Real period results.',
    ]);
    $review = $topics['delayed']->narrativeReports()->firstWhere('report_type', 'progress');
    $review->update(['research_head_remarks' => 'Keep this reviewer feedback.']);
    $this->seed(ProgressReportUiDemoSeeder::class);
    $this->seed(ProgressReportUiDemoSeeder::class);
    expect($realReport->fresh()->introduction)->toBe('Real research introduction.')
        ->and($realReport->fresh()->photos)->toBe([])
        ->and($review->fresh()->research_head_remarks)->toBe('Keep this reviewer feedback.')
        ->and($review->fresh()->review_status)->toBe('revision_requested')
        ->and(Storage::disk('local')->exists('post-approval-ui-demo/progress-reader-before-refresh.json'))->toBeTrue();

    expect(TopicProposal::count())->toBe(8)
        ->and(ProjectProgressReport::count())->toBe(21)
        ->and(ProjectNarrativeReport::count())->toBe(13)
        ->and($untouched->fresh()->status)->toBe('pending')
        ->and($untouched->progressReports()->count())->toBe(0);
    foreach ($topics as $scenario => $topic) {
        $topic->refresh();
        expect($topic->isAccessibleTo($viewer))->toBeTrue()
            ->and(Storage::disk('local')->exists($topic->notice_to_proceed_path))->toBeTrue()
            ->and(app(ProposalSignatureWorkflow::class)->isComplete($topic->latestVersion()->with('files')->first()))->toBeTrue();
        foreach ($topic->progressReports as $report) {
            expect($report->work_plan)->not->toBeEmpty()
                ->and($report->progress_percentage)->toBe((int) round(array_sum(array_column($report->work_plan, 'accomplished_percentage'))))
                ->and($report->budget_utilization)->toHaveCount(3)
                ->and(array_sum(array_column($report->budget_utilization, 'amount_requested')))->toBeLessThanOrEqual(24000)
                ->and(Storage::disk('local')->get($report->official_pdf_path))->toStartWith('%PDF');
        }
        foreach ($topic->narrativeReports as $report) {
            if ($report->report_type === 'progress') {
                expect(collect($report->photos)->where('section', 'methodology'))->toHaveCount(4)
                    ->and(collect($report->photos)->where('section', 'results_discussion'))->toHaveCount(3);
                foreach ($report->photos as $photo) {
                    expect($photo['original_name'])->toEndWith('.png')
                        ->and(Storage::disk('local')->exists($photo['path']))->toBeTrue();
                }
            }
            expect(Storage::disk('local')->exists($report->official_pdf_path))->toBeTrue()
                ->and($report->photos)->toHaveCount($report->report_type === 'progress' ? 7 : 1)
                ->and(Storage::disk('local')->exists($report->photos[0]['path']))->toBeTrue();
        }
        if (str_starts_with($scenario, 'completed')) {
            expect($topic->latestProgressReport->progress_percentage)->toBe(100)
                ->and($topic->narrativeReports->firstWhere('report_type', 'terminal')->hasSignedCopy())->toBeTrue();
        }
    }
    expect(ProjectProgressReport::whereBelongsTo($topics['ongoing'], 'topic')->orderBy('reporting_quarter')->pluck('submission_status')->all())
        ->toBe(['submitted', 'submitted', 'prepared']);
    $prepared = $topics['ongoing']->preparedProgressReports()->firstOrFail();
    expect($prepared->hasPreparedBudget())->toBeFalse()
        ->and($topics['ongoing']->progressReports()->where('review_status', 'pending')->submitted()->count())->toBe(1)
        ->and($topics['delayed']->progressReports()->where('review_status', 'revision_requested')->count())->toBe(1)
        ->and($topics['ongoing-second']->narrativeReports()->where('report_type', 'terminal')->first()->review_status)->toBe('pending')
        ->and($topics['delayed-second']->narrativeReports()->where('report_type', 'terminal')->first()->hasSignedCopy())->toBeFalse();

    $this->actingAs($viewer)->withSession(['workspace' => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('topics.show', $topics['ongoing']))->assertSuccessful()->assertSee('Complete budget');
    $this->get(route('project-budget.edit', [$topics['ongoing'], $prepared]))->assertSuccessful();
    $readerResponse = $this->get(route('topics.show', $topics['delayed']))->assertSuccessful()->assertSee('Report corrections requested');
    $this->get(route('topics.show', $topics['completed-presented']))->assertSuccessful()->assertSee('Signed terminal PDF uploaded');
    $this->actingAs($head)->withSession(['workspace' => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('project-narrative-reports.show', $topics['delayed-second']->narrativeReports()->where('report_type', 'terminal')->first()))->assertSuccessful()->assertSee('Signed terminal report');

    $clinic = $topics['ongoing-second'];
    $interim = $clinic->narrativeReports()->where('report_type', 'progress')->firstOrFail();
    $final = $clinic->narrativeReports()->where('report_type', 'terminal')->firstOrFail();
    expect($interim->submission_date->lt($final->submission_date))->toBeTrue()
        ->and($interim->accomplishments)->toHaveCount(2)
        ->and($final->accomplishments)->toHaveCount(3);
    $response = $this->get(route('topics.show', $clinic))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $entries = (new DOMXPath($document))->query('//*[@data-narrative-history-entry]');
    expect(collect(iterator_to_array($entries))->map(fn ($entry) => $entry->getAttribute('data-narrative-history-entry'))->all())
        ->toContain((string) $interim->id, (string) $final->id);

    $delayed = $topics['delayed']->fresh();
    $reports = $delayed->progressReports()->reorder('reporting_date')->get();
    $original = $reports->map(fn ($report) => $report->only(['id', 'reporting_date', 'review_status', 'budget_utilization', 'submitted_at', 'research_head_remarks']));
    app(PostApprovalUiDemoSeeder::class)->repairDelayedMonitoringSample($delayed);
    $repaired = $delayed->progressReports()->reorder('reporting_date')->get();
    expect($repaired->map(fn ($report) => $report->only(['id', 'reporting_date', 'review_status', 'budget_utilization', 'submitted_at', 'research_head_remarks']))->all())->toEqual($original->all())
        ->and(app(ApprovedWorkPlanMonitoringService::class)->quarterCompletionPercentage($delayed, $repaired->first()))->toBe(100)
        ->and($repaired->first()->progress_percentage)->toBe(33)
        ->and(app(ApprovedWorkPlanMonitoringService::class)->overdueActivities($delayed->fresh()))->toHaveCount(1)
        ->and(Storage::disk('local')->exists('post-approval-ui-demo/'.$delayed->id.'/before-monitoring-repair.json'))->toBeTrue();
    foreach ($repaired as $report) {
        expect(Storage::disk('local')->get($report->official_pdf_path))->toStartWith('%PDF')
            ->and($report->official_pdf_checksum)->toBe(hash('sha256', Storage::disk('local')->get($report->official_pdf_path)));
    }
    app(PostApprovalUiDemoSeeder::class)->repairDelayedMonitoringSample($delayed);
    expect($delayed->progressReports()->count())->toBe(3);
    expect(fn () => app(PostApprovalUiDemoSeeder::class)->repairDelayedMonitoringSample($untouched))->toThrow(RuntimeException::class);
    $legacyProgress = $delayed->narrativeReports()->where('report_type', 'progress')->firstOrFail();
    $legacyPath = $legacyProgress->official_pdf_path;
    app(PostApprovalUiDemoSeeder::class)->repairQuarterlyProgressSample($delayed);
    $quarterlyProgress = $delayed->narrativeReports()->where('report_type', 'progress')->reorder('reporting_quarter')->get();
    expect($quarterlyProgress->pluck('reporting_quarter')->all())->toBe([1, 2, 3])
        ->and($quarterlyProgress->first()->id)->toBe($legacyProgress->id)
        ->and(Storage::disk('local')->exists($legacyPath))->toBeTrue()
        ->and(Storage::disk('local')->exists('post-approval-ui-demo/'.$delayed->id.'/before-quarterly-progress-repair.json'))->toBeTrue();
    foreach ($quarterlyProgress as $index => $report) {
        $tool = $repaired[$index];
        expect($report->period_start->toDateString())->toBe($tool->period_start->toDateString())
            ->and($report->period_end->toDateString())->toBe($tool->period_end->toDateString())
            ->and($report->review_status)->toBe($tool->review_status)
            ->and($report->results_discussion)->toContain($tool->accomplishments, $tool->issues)
            ->and($report->official_pdf_checksum)->toBe(hash('sha256', Storage::disk('local')->get($report->official_pdf_path)));
        foreach ([$viewer, $head] as $user) {
            $this->actingAs($user)->withSession(['workspace' => $user->id === $head->id ? User::WORKSPACE_RESEARCH_HEAD : User::WORKSPACE_FACULTY_RESEARCHER]);
            $this->get(route('project-narrative-reports.show', $report))->assertSuccessful();
            $this->get(route('project-narrative-reports.download', $report))->assertOk();
        }
    }
    expect(app(MonitoringQuarterService::class)->narrativeProgressPeriods($delayed)->take(3)->pluck('report.id')->all())->toBe($quarterlyProgress->pluck('id')->all());
    app(PostApprovalUiDemoSeeder::class)->repairQuarterlyProgressSample($delayed);
    expect($delayed->narrativeReports()->where('report_type', 'progress')->count())->toBe(3)
        ->and($realReport->fresh()->introduction)->toBe('Real research introduction.');
    expect(fn () => app(PostApprovalUiDemoSeeder::class)->repairQuarterlyProgressSample($untouched))->toThrow(RuntimeException::class);
    $this->actingAs($head)->withSession(['workspace' => User::WORKSPACE_RESEARCH_HEAD]);
    $page = $this->get(route('topics.show', $delayed))->assertSuccessful()->assertDontSee('data-monitoring-budget-section', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$page->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-reporting-quarter="1"]//*[@role="progressbar"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-reporting-quarter="1"]')->item(0)->textContent)->toContain('Targets completed')
        ->and($xpath->query('//*[@data-reporting-quarter="3"]')->item(0)->textContent)->toContain('Targets still incomplete');
    if (getenv('ATHENA_EXPORT_MONITORING_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/monitoring-delayed-head.html'), $page->getContent());
    }

});
