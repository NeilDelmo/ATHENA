<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\User;
use App\Services\RmsQuarterlyReportData;

test('RMS combines only current submitted college snapshots for the selected quarter', function () {
    $college = User::COLLEGES['CICS'];
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'submitted_at' => '2026-10-02 09:00:00',
        'presentations' => [['research_title' => 'Unsaved live change']],
    ]);
    CollegeQuarterlyReportSubmission::factory()->create(['report_id' => $report->id, 'version_number' => 1,
        'snapshot' => ['manual_presentations' => [['id' => 'old', 'research_title' => 'Superseded presentation']]], 'returned_at' => now(),
    ]);
    $submission = CollegeQuarterlyReportSubmission::factory()->create(['report_id' => $report->id, 'version_number' => 2,
        'snapshot' => [
            'college' => $college,
            'manual_presentations' => [['id' => 'row-one', 'research_title' => 'Coastal sensors', 'personnel' => 'Dr. Rivera', 'event' => 'Research forum', 'date' => '2026-09-30', 'place' => 'Nasugbu']],
            'manual_publications' => [['id' => 'row-two', 'research_title' => 'Sensor paper', 'personnel' => 'Dr. Reyes', 'event' => 'Engineering Journal', 'date' => '2026-08-01']],
            'ip_assets' => [['id' => 'row-three', 'title' => 'Sensor software', 'type' => 'Copyright', 'inventors' => 'Dr. Rivera', 'date' => '2026-07-01', 'remarks' => 'Submitted', 'evidence_url' => 'https://example.com/ip']],
        ],
    ]);
    CollegeQuarterlyReport::factory()->create(['college' => User::COLLEGES['CTE'], 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'submitted_at' => now(), 'snapshot' => ['presentations' => [['Education forum', 'Teacher study', 'August 2, 2026', 'Batangas', 'Dr. Santos']]],
    ]);
    foreach (['draft', 'returned', 'other-quarter', 'other-year'] as $state) {
        $excluded = CollegeQuarterlyReport::factory()->create([
            'college' => $state, 'reporting_year' => $state === 'other-year' ? 2025 : 2026,
            'reporting_quarter' => $state === 'other-quarter' ? 2 : 3,
            'submitted_at' => $state === 'draft' || $state === 'returned' ? null : now(),
            'snapshot' => ['manual_presentations' => [['id' => $state, 'research_title' => 'Excluded '.$state]]],
        ]);
        if ($state === 'returned') {
            CollegeQuarterlyReportSubmission::factory()->create(['report_id' => $excluded->id, 'returned_at' => now()]);
        }
    }

    $data = app(RmsQuarterlyReportData::class)->build(2026, 3);
    expect($data['presentations'])->toHaveCount(2)
        ->and($data['publications'])->toHaveCount(1)
        ->and($data['ip_assets'])->toHaveCount(1)
        ->and($data['presentations'][0]['values']['presentation_title'])->toBe('Coastal sensors')
        ->and($data['presentations'][0]['values']['college'])->toBe($college)
        ->and($data['presentations'][0]['submission_id'])->toBe($submission->id)
        ->and($data['presentations'][0]['values']['source_of_fund'])->toBe('')
        ->and($data['presentations'][0]['missing'])->toContain('Source of Fund')
        ->and($data['presentations'][1]['values']['date'])->toBe('2026-08-02')
        ->and($data['publications'][0]['values']['journal_title'])->toBe('Engineering Journal')
        ->and($data['ip_assets'][0]['values']['type'])->toBe('Copyright')
        ->and($data['sources'])->toHaveCount(2)
        ->and($data['missing_colleges'])->toContain(User::COLLEGES['CAS'])
        ->and($data['missing_colleges'])->not->toContain($college, User::COLLEGES['CTE']);
});

test('RMS supplements are bound to their submitted source version and cannot replace the source college', function () {
    $report = CollegeQuarterlyReport::factory()->create(['college' => 'College A', 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now()]);
    $submission = CollegeQuarterlyReportSubmission::factory()->create(['report_id' => $report->id,
        'snapshot' => ['manual_presentations' => [['id' => 'same-row', 'research_title' => 'Original title', 'event' => 'Forum', 'personnel' => 'Presenter', 'date' => '2026-08-01', 'place' => 'Venue']]],
    ]);
    $service = app(RmsQuarterlyReportData::class);
    $original = $service->build(2026, 3);
    $key = $original['presentations'][0]['key'];
    $details = [$key => ['source_of_fund' => 'Non-funded', 'project_status' => 'Completed', 'college' => 'Forged college', 'authors' => 'Named author']];
    $filled = $service->build(2026, 3, $details);
    expect($filled['presentations'][0]['values']['source_of_fund'])->toBe('Non-funded')
        ->and($filled['presentations'][0]['values']['college'])->toBe('College A')
        ->and($filled['presentations'][0]['values']['authors'])->toBe('Named author')
        ->and($filled['presentations'][0]['missing'])->not->toContain('Funded Project Status')
        ->and($filled['source_token'])->toBe($original['source_token']);
    CollegeQuarterlyReportSubmission::factory()->create(['report_id' => $report->id, 'version_number' => 2, 'snapshot' => $submission->snapshot]);
    $revised = $service->build(2026, 3, $details);
    expect($revised['source_token'])->not->toBe($original['source_token'])
        ->and($revised['presentations'][0]['key'])->not->toBe($key)
        ->and($revised['presentations'][0]['values']['source_of_fund'])->toBe('');
});

test('RMS keeps identical accomplishments from different colleges traceable instead of silently deduplicating them', function () {
    foreach (['College A', 'College B'] as $college) {
        CollegeQuarterlyReport::factory()->create(['college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
            'snapshot' => ['manual_publications' => [['id' => 'same-id', 'research_title' => 'Shared paper', 'event' => 'Journal', 'personnel' => 'Team', 'date' => '2026-08-15']]],
        ]);
    }
    $data = app(RmsQuarterlyReportData::class)->build(2026, 3);
    expect($data['publications'])->toHaveCount(2)
        ->and($data['publications'][0]['key'])->not->toBe($data['publications'][1]['key']);
});
