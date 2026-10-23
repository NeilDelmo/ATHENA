<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Services\RmsQuarterlyReportStatus;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(9, 0));
    $this->status = app(RmsQuarterlyReportStatus::class);
});

test('readiness counts only current submitted required colleges in the selected period', function () {
    foreach ([
        [User::COLLEGES['CICS'], 2026, 3, now(), false],
        [User::COLLEGES['CTE'], 2026, 3, null, false],
        [User::COLLEGES['CABEIHM'], 2026, 3, now(), true],
        [User::COLLEGES['CCJE'], 2026, 2, now(), false],
        [User::COLLEGES['CAS'], 2025, 3, now(), false],
        ['Unrecognized college', 2026, 3, now(), false],
    ] as [$college, $year, $quarter, $submittedAt, $returned]) {
        $report = CollegeQuarterlyReport::factory()->create([
            'college' => $college, 'reporting_year' => $year, 'reporting_quarter' => $quarter, 'submitted_at' => $submittedAt,
        ]);
        CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create([
            'snapshot' => ['college' => $college], 'returned_at' => $returned ? now() : null,
        ]);
    }
    $status = $this->status->forPeriod(2026, 3);
    expect($status['ready'])->toBeFalse()
        ->and($status['submitted_count'])->toBe(1)
        ->and($status['total_count'])->toBe(6)
        ->and($status['missing_colleges'])->toHaveCount(5)
        ->and($status['colleges'][User::COLLEGES['CICS']])->toBe('submitted')
        ->and($status['colleges'][User::COLLEGES['CTE']])->toBe('pending')
        ->and($status['colleges'][User::COLLEGES['CABEIHM']])->toBe('returned')
        ->and($status['colleges'][User::COLLEGES['CCJE']])->toBe('pending')
        ->and($status['colleges'])->not->toHaveKey('Unrecognized college')
        ->and($status['downloaded_at'])->toBeNull()
        ->and($status['outdated'])->toBeFalse()
        ->and(RmsQuarterlyReport::count())->toBe(0);
});

test('all six submitted colleges are ready even when their snapshots contain no outputs', function () {
    foreach (User::COLLEGES as $college) {
        $report = CollegeQuarterlyReport::factory()->create([
            'college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
        ]);
        CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create(['snapshot' => ['college' => $college]]);
    }
    $status = $this->status->forPeriod(2026, 3);
    expect($status['ready'])->toBeTrue()
        ->and($status['submitted_count'])->toBe(6)
        ->and($status['missing_colleges'])->toBe([])
        ->and(array_values($status['colleges']))->toBe(array_fill(0, 6, 'submitted'));
});

test('legacy submitted college snapshots remain eligible until their latest submission is returned', function () {
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
        'snapshot' => ['college' => User::COLLEGES['CICS']],
    ]);
    expect($this->status->forPeriod(2026, 3)['submitted_count'])->toBe(1);
    CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create(['returned_at' => now()]);
    $report->update(['submitted_at' => null]);
    $status = $this->status->forPeriod(2026, 3);
    expect($status['submitted_count'])->toBe(0)
        ->and($status['colleges'][User::COLLEGES['CICS']])->toBe('returned');
});

test('batch quarter statuses keep saved export metadata in its year and quarter without creating drafts', function () {
    $report = RmsQuarterlyReport::factory()->create(['reporting_year' => 2025, 'reporting_quarter' => 4]);
    $report->forceFill([
        'downloaded_at' => now(),
        'downloaded_fingerprint' => $this->status->fingerprint($this->status->forPeriod(2025, 4, $report), $report),
    ])->save();
    $statuses = $this->status->forYears([2026, 2025], collect([$report]));
    expect($statuses->keys()->all())->toBe(['2026-1', '2026-2', '2026-3', '2026-4', '2025-1', '2025-2', '2025-3', '2025-4'])
        ->and($statuses->get('2026-3')['submitted_count'])->toBe(0)
        ->and($statuses->get('2025-4')['ready'])->toBeFalse()
        ->and($statuses->get('2026-3')['downloaded_at'])->toBeNull()
        ->and($statuses->get('2025-4')['downloaded_at']->equalTo($report->downloaded_at))->toBeTrue()
        ->and($statuses->get('2025-4')['outdated'])->toBeFalse()
        ->and(RmsQuarterlyReport::count())->toBe(1);
});

test('workbook fingerprint covers report contents and source versions but ignores bookkeeping timestamps', function () {
    $report = RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3]);
    $status = $this->status->forPeriod(2026, 3, $report);
    $original = $this->status->fingerprint($status, $report);
    $report->updated_at = now()->addDay();
    expect($this->status->fingerprint($status, $report))->toBe($original);
    foreach (['campus' => 'Changed campus', 'signatories' => ['prepared' => ['name' => 'Changed name']], 'row_details' => ['row-one' => ['authors' => 'Changed author']]] as $field => $value) {
        $changed = clone $report;
        $changed->setAttribute($field, $value);
        expect($this->status->fingerprint($status, $changed))->not->toBe($original);
    }
    $changedStatus = [...$status, 'source_token' => str_repeat('a', 64)];
    expect($this->status->fingerprint($changedStatus, $report))->not->toBe($original);
});
