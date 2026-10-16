<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Services\RmsQuarterlyReportData;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(9, 0));
    foreach (['research_head', 'research_coordinator', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->period = ['year' => 2026, 'quarter' => 3];
    $this->draftInput = fn (): array => [
        'reporting_year' => 2026, 'reporting_quarter' => 3,
        'source_token' => app(RmsQuarterlyReportData::class)->build(2026, 3)['source_token'],
        'campus' => 'ARASOF-Nasugbu', 'signatories' => [], 'row_details' => [], 'form_complete' => '1',
    ];
});

test('research head can open the quarterly monitoring workspace with missing colleges clearly shown', function () {
    $this->get(route('research_head.rms-quarterly-reports.index', $this->period))
        ->assertOk()->assertViewIs('research_head.rms-quarterly-reports.index')
        ->assertSeeText('Quarterly Monitoring Report')->assertSeeText('Awaiting college reports')
        ->assertSeeText(User::COLLEGES['CICS'])
        ->assertSee(route('research_head.quarterly-reports.index'));
    $this->getJson(route('research_head.rms-quarterly-reports.index', ['year' => 1999, 'quarter' => 5]))
        ->assertUnprocessable()->assertJsonValidationErrors(['year', 'quarter']);
});

test('only the research head workspace can read save or download monitoring reports', function (string $role, string $workspace) {
    $user = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $user->assignRole($role);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $this->getJson(route('research_head.rms-quarterly-reports.index'))->assertForbidden();
    $this->getJson(route('research_head.rms-quarterly-reports.download'))->assertForbidden();
    $this->postJson(route('research_head.rms-quarterly-reports.store'), [])->assertForbidden();
})->with([
    'coordinator' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
    'faculty' => ['faculty', User::WORKSPACE_FACULTY],
]);

test('head can save a partial quarterly draft and update the same quarter without invented values', function () {
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'signatories' => ['prepared' => ['name' => 'Ana Researcher', 'position' => 'Research Head', 'office' => 'Research Office']],
        'updated_by' => 99999,
    ])->assertSessionHasNoErrors()->assertRedirect(route('research_head.rms-quarterly-reports.index', $this->period));
    $report = RmsQuarterlyReport::sole();
    expect($report->updated_by)->toBe($this->head->id)
        ->and($report->row_details)->toBe([])
        ->and($report->signatories['prepared']['name'])->toBe('Ana Researcher');
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'campus' => 'Updated campus'])
        ->assertSessionHasNoErrors();
    expect(RmsQuarterlyReport::count())->toBe(1)->and($report->fresh()->campus)->toBe('Updated campus');
});

test('a newly submitted coordinator report prevents a stale head form from being saved', function () {
    $input = ($this->draftInput)();
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create();
    $this->postJson(route('research_head.rms-quarterly-reports.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors('source_token');
    expect(RmsQuarterlyReport::count())->toBe(0);
});

test('head drafts reject unknown rows and unsafe header values', function () {
    $this->postJson(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'row_details' => ['removed-row' => ['title' => 'Unrelated record']],
    ])->assertUnprocessable()->assertJsonValidationErrors('row_details');
    $this->postJson(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'campus' => str_repeat('x', 256), 'signatories' => ['unexpected' => ['name' => 'Injected']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['campus', 'signatories']);
    expect(RmsQuarterlyReport::count())->toBe(0);
});

test('head can complete source fields while college provenance stays read only', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => [
        'college' => $source->college,
        'manual_presentations' => [['id' => 'rms-row', 'event' => 'Research Forum', 'research_title' => 'Health Research', 'date' => '2026-09-03', 'place' => 'Nasugbu', 'personnel' => 'Ana Researcher']],
    ]]);
    $row = app(RmsQuarterlyReportData::class)->build(2026, 3)['presentations'][0];
    $this->get(route('research_head.rms-quarterly-reports.index', $this->period))
        ->assertOk()->assertSeeText('Health Research')->assertSee('row_details['.$row['key'].'][source_of_fund]', false)
        ->assertDontSee('name="row_details['.$row['key'].'][college]"', false);
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'row_details' => [$row['key'] => ['source_of_fund' => 'Externally-funded', 'project_status' => 'On-going', 'sdgs' => '3, 9', 'evidence_url' => 'https://example.com/proof']],
        'intent' => 'download',
    ])->assertSessionHasNoErrors()->assertRedirect(route('research_head.rms-quarterly-reports.download', $this->period));
    expect(RmsQuarterlyReport::sole()->row_details[$row['key']]['source_of_fund'])->toBe('Externally-funded');
    $this->get(route('research_head.rms-quarterly-reports.index', $this->period))->assertOk()
        ->assertViewHas('reportData', fn (array $data): bool => $data['presentations'][0]['values']['source_of_fund'] === 'Externally-funded');
    $response = $this->get(route('research_head.rms-quarterly-reports.download', $this->period))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'athena-rms-http-');
    $archive = new ZipArchive;
    try {
        file_put_contents($path, $response->getContent());
        expect($archive->open($path))->toBeTrue();
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain('Externally-funded', 'On-going', '3, 9', 'https://example.com/proof', 'Health Research');
        $archive->close();
    } finally {
        unlink($path);
    }
    foreach (['source_of_fund' => 'Unknown funding', 'sdgs' => '18', 'evidence_url' => 'javascript:alert(1)', 'college' => 'Changed college'] as $field => $value) {
        $this->postJson(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'row_details' => [$row['key'] => [$field => $value]]])
            ->assertUnprocessable()->assertJsonValidationErrors($field === 'college' ? 'row_details.'.$row['key'] : 'row_details.'.$row['key'].'.'.$field);
    }
});

test('publication and IP dates must fall inside the selected monitoring quarter when filled', function (string $section, array $entry) {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => [$section === 'publications' ? 'manual_publications' : 'ip_assets' => [$entry]]]);
    $row = app(RmsQuarterlyReportData::class)->build(2026, 3)[$section][0];
    foreach (['2026-10-01', 'not-a-date'] as $date) {
        $this->postJson(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'row_details' => [$row['key'] => ['date' => $date]]])
            ->assertUnprocessable()->assertJsonValidationErrors('row_details.'.$row['key'].'.date');
    }
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'row_details' => [$row['key'] => ['date' => '2026-09-30']]])
        ->assertSessionHasNoErrors();
})->with([
    'publication' => ['publications', ['id' => 'publication-row', 'event' => 'Research Journal', 'research_title' => 'Study', 'personnel' => 'Ana Researcher', 'date' => '2026-09-03']],
    'IP' => ['ip_assets', ['id' => 'ip-row', 'title' => 'Asset', 'type' => 'Copyright', 'inventors' => 'Ana Researcher', 'date' => '2026-09-03']],
]);

test('research head can download the original Excel report format with a partial draft', function () {
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'Nasugbu']);
    $response = $this->get(route('research_head.rms-quarterly-reports.download', $this->period))
        ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="RMS-Quarterly-Monitoring-Report-2026-Q3.xlsx"');
    expect(substr($response->getContent(), 0, 2))->toBe('PK');
});

test('JSON submissions save every row from a large college consolidation', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    $activities = array_map(fn (int $index): array => [
        'id' => 'row-'.$index, 'event' => 'Forum '.$index, 'research_title' => 'Study '.$index,
        'date' => '2026-09-03', 'place' => 'Nasugbu', 'personnel' => 'Ana Researcher',
    ], range(1, 80));
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => ['manual_presentations' => $activities]]);
    $rows = app(RmsQuarterlyReportData::class)->build(2026, 3)['presentations'];
    $details = collect($rows)->mapWithKeys(fn (array $row): array => [$row['key'] => [
        'source_of_fund' => 'Non-funded', 'sdgs' => '4, 9', 'evidence_url' => 'https://example.com/'.$row['key'],
    ]])->all();
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'row_details_json' => json_encode($details, JSON_THROW_ON_ERROR)])
        ->assertSessionHasNoErrors();
    expect(RmsQuarterlyReport::sole()->row_details)->toEqual($details);
});

test('invalid JSON payloads cannot erase a saved quarterly draft', function (mixed $json) {
    $draft = RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'Saved campus']);
    $this->postJson(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'campus' => 'Changed campus', 'row_details_json' => $json])
        ->assertUnprocessable()->assertJsonValidationErrors('row_details_json');
    expect($draft->fresh()->campus)->toBe('Saved campus');
})->with([
    'invalid JSON' => ['{'], 'JSON scalar' => ['"not rows"'], 'JSON null' => ['null'],
    'non-string payload' => [['not' => 'a string']], 'oversized payload' => [str_repeat(' ', 5 * 1024 * 1024 + 1)],
]);

test('a truncated browser form is rejected before replacing any saved data', function () {
    $draft = RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'Saved campus']);
    $input = ($this->draftInput)();
    unset($input['form_complete']);
    $this->postJson(route('research_head.rms-quarterly-reports.store'), [...$input, 'campus' => 'Truncated campus'])
        ->assertUnprocessable()->assertJsonValidationErrors('form_complete');
    expect($draft->fresh()->campus)->toBe('Saved campus');
});

test('college report inbox exposes current and previous submitted versions while hiding drafts', function () {
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    $first = CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create(['version_number' => 1, 'returned_at' => now(), 'return_reason' => 'Missing proof']);
    $latest = CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create(['version_number' => 2]);
    CollegeQuarterlyReport::factory()->create(['college' => 'Private unfinished college', 'reporting_year' => 2026, 'reporting_quarter' => 3]);
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026]))
        ->assertOk()->assertSeeText(User::COLLEGES['CICS'])->assertDontSeeText('Private unfinished college')
        ->assertSeeText('Return for correction')->assertSeeText('Version 1')->assertSeeText('Version 2')
        ->assertSee(route('college-quarterly-submissions.print', $first))
        ->assertSee(route('college-quarterly-submissions.download', $latest));
});
