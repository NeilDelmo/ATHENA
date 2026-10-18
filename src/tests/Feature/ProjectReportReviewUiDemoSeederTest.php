<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Database\Seeders\ProjectReportReviewUiDemoSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    foreach (['faculty_researcher', 'research_head', 'research_secretary'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->author = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->author->assignRole('faculty_researcher');
    $this->makeTopic = fn (string $scenario): TopicProposal => TopicProposal::create([
        'user_id' => $this->author->id, 'title' => 'Sample '.$scenario,
        'description' => '[lifecycle-demo:'.$scenario.'] Sample only.',
        'status' => 'approved', 'project_status' => str_starts_with($scenario, 'completed') ? 'completed' : 'ongoing',
        'estimated_duration_months' => 12, 'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_issued_by' => $this->head->id,
    ]);
    $this->makeReport = function (TopicProposal $topic, string $type, int $quarter, array $overrides = []): ProjectProgressReport|ProjectNarrativeReport {
        $path = 'sample-reports/'.$topic->id.'/'.$type.'-'.$quarter.'.pdf';
        $pdf = '%PDF-1.7 Original sample '.$path;
        Storage::disk('local')->put($path, $pdf);
        $common = [
            'topic_id' => $topic->id, 'submitted_by' => $topic->user_id,
            'tracking_number' => 'LIFE-'.$topic->id.'-'.$type.'-Q'.$quarter,
            'reporting_date' => '2026-'.str_pad((string) ($quarter * 3), 2, '0', STR_PAD_LEFT).'-28',
            'reporting_quarter' => $quarter, 'version_number' => 1,
            'submission_status' => 'submitted', 'submitted_at' => '2026-04-01 09:00:00',
            'review_status' => 'reviewed', 'reviewed_by' => $this->head->id, 'reviewed_at' => '2026-04-04 09:00:00',
            'research_head_remarks' => 'UI demo: Results reviewed. Record the fully signed Terminal Report before closing the project.',
            'official_pdf_path' => $path, 'official_pdf_filename' => basename($path),
            'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf),
        ];

        $report = $type === 'monitoring'
            ? ProjectProgressReport::create(array_replace([...$common, 'reporting_year' => 2026,
                'progress_percentage' => 50, 'accomplishments' => 'Sample findings.', 'work_plan' => [], 'budget_utilization' => []], $overrides))
            : ProjectNarrativeReport::create(array_replace([...$common, 'report_type' => 'progress',
                'submission_date' => $common['reporting_date'], 'researchers' => $this->author->name,
                'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
                'budget' => 50000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Sample findings.',
                'introduction' => 'Sample background.', 'objectives' => 'Evaluate the sample.',
                'methodology' => 'Sample methods.', 'results_discussion' => 'Sample results.', 'photos' => []], $overrides));

        return $report->refresh();
    };
});

test('existing sample reports demonstrate office checking corrections clearance and Head acceptance', function (string $type) {
    $ongoing = ($this->makeTopic)('ongoing');
    $accepted = ($this->makeReport)($ongoing, $type, 1);
    $pending = ($this->makeReport)($ongoing, $type, 2);
    $prepared = ($this->makeReport)($ongoing, $type, 3, ['submission_status' => 'prepared', 'submitted_at' => null, 'review_status' => 'pending']);
    $delayed = ($this->makeTopic)('delayed');
    $correction = ($this->makeReport)($delayed, $type, 3);
    $readyTopic = ($this->makeTopic)('delayed-second');
    $ready = ($this->makeReport)($readyTopic, $type, 3);
    $completed = ($this->makeReport)(($this->makeTopic)('completed-published'), $type, 3);
    $realTopic = ($this->makeTopic)('real');
    $realTopic->update(['description' => 'An actual project']);
    $real = ($this->makeReport)($realTopic, $type, 1);
    $personal = ($this->makeReport)($ongoing, $type, 4, ['tracking_number' => 'PERSONAL-001']);
    $alreadyReviewed = ($this->makeReport)($readyTopic, $type, 1, ['review_stage' => 'head', 'research_head_remarks' => 'Keep my actual review.']);
    $terminal = ($this->makeReport)(($this->makeTopic)('completed-terminal'), 'progress', 1, ['report_type' => 'terminal']);
    $controls = collect([$real, $personal, $alreadyReviewed, $terminal]);
    $unchanged = $controls->map->getAttributes()->all();
    $reports = collect([$accepted, $pending, $prepared, $correction, $ready, $completed]);
    $files = $reports->mapWithKeys(fn ($report): array => [$report->official_pdf_path => Storage::disk('local')->get($report->official_pdf_path)]);
    $content = $reports->map(fn ($report): array => Arr::except($report->getAttributes(), [
        'review_stage', 'review_status', 'research_head_remarks', 'reviewed_by', 'reviewed_at',
        'secretary_remarks', 'secretary_reviewed_by', 'secretary_reviewed_at', 'forwarded_at', 'updated_at',
    ]))->all();

    $this->seed(ProjectReportReviewUiDemoSeeder::class);
    expect($pending->fresh()->review_stage)->toBe('secretary');
    $secretary = User::role('research_secretary')->where('college', User::COLLEGES['CICS'])->sole();
    expect($secretary->getRoleNames()->all())->toBe(['research_secretary'])
        ->and($secretary->id)->not->toBeIn([$this->author->id, $this->head->id]);
    foreach ([$accepted, $completed] as $report) {
        expect($report->refresh()->review_stage)->toBe('head')->and($report->review_status)->toBe('reviewed')
            ->and($report->secretary_reviewed_by)->toBe($secretary->id)
            ->and($report->secretary_reviewed_at->lte($report->forwarded_at))->toBeTrue()
            ->and($report->forwarded_at->lte($report->reviewed_at))->toBeTrue();
    }
    expect($pending->refresh()->isAwaitingSecretaryReview())->toBeTrue()
        ->and($pending->forwarded_at)->toBeNull()->and($pending->reviewed_by)->toBeNull()
        ->and($prepared->refresh()->isPrepared())->toBeTrue()->and($prepared->isAvailableToResearchHead())->toBeFalse()
        ->and($correction->refresh()->review_stage)->toBe('secretary')->and($correction->review_status)->toBe('revision_requested')
        ->and($correction->secretary_remarks)->not->toBeEmpty()->and($correction->research_head_remarks)->toBeNull()
        ->and($ready->refresh()->review_stage)->toBe('head')->and($ready->review_status)->toBe('pending')
        ->and($ready->forwarded_at)->not->toBeNull()->and($ready->reviewed_by)->toBeNull();
    $backup = Storage::disk('local')->get('post-approval-ui-demo/before-secretary-review.json');
    Notification::assertNothingSent();
    $this->actingAs($secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->patch(route($type === 'monitoring' ? 'research_secretary.monitoring-reports.review' : 'research_secretary.narrative-reports.review', $pending), [
            'decision' => 'forward', 'secretary_remarks' => 'Keep my later review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
    $this->seed(ProjectReportReviewUiDemoSeeder::class);
    expect($pending->refresh()->secretary_remarks)->toBe('Keep my later review.')
        ->and(Storage::disk('local')->get('post-approval-ui-demo/before-secretary-review.json'))->toBe($backup)
        ->and($controls->map(fn ($report): array => $report->fresh()->getAttributes())->all())->toBe($unchanged)
        ->and($reports->map(fn ($report): array => Arr::except($report->fresh()->getAttributes(), [
            'review_stage', 'review_status', 'research_head_remarks', 'reviewed_by', 'reviewed_at',
            'secretary_remarks', 'secretary_reviewed_by', 'secretary_reviewed_at', 'forwarded_at', 'updated_at',
        ]))->all())->toBe($content)
        ->and(User::role('research_secretary')->count())->toBe(1)
        ->and($this->author->fresh()->getRoleNames()->all())->toBe(['faculty_researcher']);
    foreach ($files as $path => $bytes) {
        expect(Storage::disk('local')->get($path))->toBe($bytes);
    }
})->with(['monitoring', 'progress']);

test('sample report review updates stop if an original PDF is missing', function () {
    $report = ($this->makeReport)(($this->makeTopic)('ongoing'), 'monitoring', 1);
    $original = $report->getAttributes();
    Storage::disk('local')->delete($report->official_pdf_path);
    expect(fn () => $this->seed(ProjectReportReviewUiDemoSeeder::class))->toThrow(RuntimeException::class, 'PDF is missing')
        ->and($report->fresh()->getAttributes())->toBe($original)
        ->and(User::count())->toBe(2);
});

test('sample report review updates require a college before making changes', function () {
    $topic = ($this->makeTopic)('ongoing');
    $report = ($this->makeReport)($topic, 'monitoring', 1);
    $original = $report->getAttributes();
    $this->author->update(['college' => null]);
    expect(fn () => $this->seed(ProjectReportReviewUiDemoSeeder::class))->toThrow(RuntimeException::class, 'college')
        ->and($report->fresh()->getAttributes())->toBe($original)
        ->and(User::count())->toBe(2);
});

test('sample report review updates cannot run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app()->call([app(ProjectReportReviewUiDemoSeeder::class), 'run']))->toThrow(RuntimeException::class, 'production');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
