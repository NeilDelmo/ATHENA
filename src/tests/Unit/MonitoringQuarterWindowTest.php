<?php

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

test('quarter list covers the released project period across years and allows late reporting', function () {
    $this->travelTo(now()->setDate(2026, 9, 7)->startOfDay());
    $topic = new TopicProposal([
        'notice_to_proceed_issued_at' => '2025-11-20',
        'notice_to_proceed_data' => ['approved_start_date' => '2025-11-15', 'approved_end_date' => '2026-08-10'],
    ]);
    $rows = app(MonitoringQuarterService::class)->summaryRows(collect(), $topic);

    expect($rows->map(fn ($row) => $row['year'].'-'.$row['label'])->sort()->values()->all())
        ->toBe(['2025-Q1', '2026-Q2', '2026-Q3'])
        ->and($rows->firstWhere('label', 'Q3')['reporting_date'])->toBe('2026-08-10')
        ->and($rows->firstWhere('year', 2025)['reporting_date'])->toBe('2026-02-19');
});

test('a future project start does not open the current calendar quarter early', function () {
    $this->travelTo(now()->setDate(2026, 9, 7)->startOfDay());
    $topic = new TopicProposal([
        'notice_to_proceed_issued_at' => '2026-09-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-09-20', 'approved_end_date' => '2026-12-31'],
    ]);
    $rows = app(MonitoringQuarterService::class)->summaryRows(collect(), $topic);

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('reporting_date')->filter())->toBeEmpty()
        ->and($rows->pluck('state')->unique()->all())->toBe(['not_yet_due']);
});

test('all approved project periods allow drafting before the project starts while submission stays closed', function () {
    $this->travelTo(now()->setDate(2026, 9, 7)->startOfDay());
    $topic = new TopicProposal([
        'status' => 'approved', 'estimated_duration_months' => 9,
        'notice_to_proceed_issued_at' => '2026-09-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-10-01', 'approved_end_date' => '2027-06-30'],
    ]);
    $schedule = app(MonitoringQuarterService::class);
    $monitoring = $schedule->summaryRows(collect(), $topic);
    $progress = $schedule->narrativeProgressPeriods($topic, collect());

    expect($monitoring)->toHaveCount(3)
        ->and($monitoring->pluck('drafting_date')->filter())->toHaveCount(3)
        ->and($monitoring->pluck('reporting_date')->filter())->toBeEmpty()
        ->and($progress->pluck('drafting_date')->filter())->toHaveCount(3)
        ->and($progress->pluck('submission_open')->unique()->all())->toBe([false]);
    foreach ($monitoring as $row) {
        expect($schedule->canDraftForDate($topic, $row['drafting_date']))->toBeTrue()
            ->and($schedule->canSubmitForDate($topic, $row['drafting_date']))->toBeFalse();
    }
    expect($schedule->canDraftForDate($topic, '2026-09-30'))->toBeFalse()
        ->and($schedule->canDraftForDate($topic, '2027-07-01'))->toBeFalse()
        ->and($schedule->canSubmitTerminal($topic))->toBeFalse();
});

test('revised reports stay in their original quarter and the latest version is shown', function () {
    $this->travelTo(now()->setDate(2026, 9, 7)->startOfDay());
    $original = new ProjectProgressReport(['reporting_date' => '2026-03-31', 'reporting_year' => 2026, 'reporting_quarter' => 1, 'version_number' => 1, 'submission_status' => 'submitted', 'review_status' => 'revision_requested']);
    $original->id = 10;
    $revision = new ProjectProgressReport(['reporting_date' => '2026-03-31', 'reporting_year' => 2026, 'reporting_quarter' => 1, 'version_number' => 2, 'submission_status' => 'submitted', 'review_status' => 'pending']);
    $revision->id = 11;
    $rows = app(MonitoringQuarterService::class)->summaryRows(collect([$original, $revision]));

    expect($rows->firstWhere('quarter', 1)['report'])->toBe($revision)
        ->and($rows->firstWhere('quarter', 1)['state'])->toBe('resubmitted')
        ->and($rows->firstWhere('quarter', 3)['report'])->toBeNull();
});

test('project quarters unlock in their final week and terminal opens after the duration', function () {
    $topic = new TopicProposal(['status' => 'approved', 'estimated_duration_months' => 4, 'notice_to_proceed_issued_at' => '2026-01-15']);
    $schedule = app(MonitoringQuarterService::class);
    $this->travelTo(now()->setDate(2026, 4, 7)->endOfDay());
    expect($schedule->canSubmitForDate($topic, '2026-04-14'))->toBeFalse()
        ->and($schedule->canSubmitTerminal($topic))->toBeFalse();
    $this->travelTo(now()->setDate(2026, 4, 8)->startOfDay());
    expect($schedule->canSubmitForDate($topic, '2026-04-14'))->toBeTrue()
        ->and($schedule->canSubmitForDate($topic, '2026-04-15'))->toBeFalse()
        ->and($schedule->projectPeriods($topic))->toHaveCount(2)
        ->and($schedule->terminalOpensAt($topic)->toDateString())->toBe('2026-05-15');
    $this->travelTo(now()->setDate(2026, 5, 15)->startOfDay());
    expect($schedule->canSubmitTerminal($topic))->toBeTrue()
        ->and($schedule->canSubmitForDate($topic, '2026-05-14'))->toBeTrue();
});

test('quarterly reports open in the final week and become overdue only after the deadline', function (string $today, bool $open, string $status) {
    $this->travelTo(CarbonImmutable::parse($today, 'Asia/Manila'));
    $topic = new TopicProposal(['status' => 'approved', 'estimated_duration_months' => 12, 'notice_to_proceed_issued_at' => '2026-01-01']);
    $schedule = app(MonitoringQuarterService::class);
    $monitoring = $schedule->summaryRows(collect(), $topic)->first();
    $progress = $schedule->narrativeProgressPeriods($topic, collect())->first();

    expect($schedule->canSubmitForDate($topic, '2026-03-31'))->toBe($open)
        ->and($monitoring['reporting_date'])->toBe($open ? '2026-03-31' : null)
        ->and($monitoring['status'])->toBe($status)
        ->and($progress['submission_open'])->toBe($open)
        ->and($progress['status'])->toBe($status)
        ->and($schedule->canSubmitTerminal($topic))->toBeFalse();
})->with([
    'before the final week' => ['2026-03-24 23:59:59', false, 'Upcoming'],
    'first day of the final week' => ['2026-03-25 00:00:00', true, 'Due Mar 31, 2026'],
    'last moment of the deadline' => ['2026-03-31 23:59:59.999999', true, 'Due Mar 31, 2026'],
    'first overdue moment' => ['2026-04-01 00:00:00', true, 'Overdue'],
]);

test('a short final reporting period never opens before that period starts', function () {
    $this->travelTo(now()->setDate(2026, 4, 1)->startOfDay());
    $topic = new TopicProposal(['status' => 'approved', 'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-04-03']]);
    $schedule = app(MonitoringQuarterService::class);

    expect($schedule->projectPeriods($topic)->last()['opens_at']->toDateString())->toBe('2026-04-01')
        ->and($schedule->canSubmitForDate($topic, '2026-04-03'))->toBeTrue();
});
