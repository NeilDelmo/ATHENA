<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Services\RmsQuarterlyReportData;
use App\Services\RmsQuarterlyReportDocument;
use App\Services\RmsQuarterlyReportStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
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
        'draft_token' => (RmsQuarterlyReport::where('reporting_year', 2026)->where('reporting_quarter', 3)->first() ?? new RmsQuarterlyReport)->draftToken(),
        'campus' => 'ARASOF-Nasugbu', 'signatories' => [], 'row_details' => [], 'form_complete' => '1',
    ];
    $this->submitMissingColleges = function (): void {
        foreach (User::COLLEGES as $college) {
            if (CollegeQuarterlyReport::where('college', $college)->where('reporting_year', 2026)->where('reporting_quarter', 3)->exists()) {
                continue;
            }
            $source = CollegeQuarterlyReport::factory()->create([
                'college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
            ]);
            CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => ['college' => $college]]);
        }
    };
});

test('an older RMS form without editing state must reload before retrying its edits', function () {
    $url = route('research_head.rms-quarterly-reports.index', $this->period);
    $this->get($url)->assertOk();
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'campus' => 'Newest saved campus'])->assertSessionHasNoErrors();
    $this->withCookie(config('session.cookie'), session()->getId());
    $input = [...($this->draftInput)(), 'campus' => 'Older unsaved campus'];
    unset($input['draft_token']);
    $this->from($url)->post(route('research_head.rms-quarterly-reports.store'), $input)->assertSessionHasErrors('draft_token');
    $this->get($url)->assertOk()->assertSee('Copy any work you need, then reload the report before saving.')
        ->assertSee('name="draft_token" value=""', false)->assertSee('Older unsaved campus');
    $this->get($url)->assertOk()->assertSee('Newest saved campus')
        ->assertSee('name="draft_token" value="'.RmsQuarterlyReport::sole()->draftToken().'"', false);
});

test('research head can open the quarterly monitoring workspace with missing colleges clearly shown', function () {
    $response = $this->get(route('research_head.rms-quarterly-reports.index', $this->period))
        ->assertOk()->assertViewIs('research_head.rms-quarterly-reports.index')
        ->assertSeeText('RMS quarterly report · Q3 2026')->assertSeeText('Awaiting college reports')
        ->assertSeeText(User::COLLEGES['CICS'])
        ->assertViewHas('reportStatus', fn (array $status): bool => ! $status['ready'] && $status['submitted_count'] === 0 && $status['total_count'] === 6)
        ->assertSee(route('research_head.quarterly-reports.index'));
    if (getenv('ATHENA_EXPORT_RMS_UI') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/rms-editor-pending.html'), $response->getContent());
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//button[@name="intent" and @value="download" and @disabled]')->length)->toBe(1)
        ->and($xpath->query('//button[@name="intent" and @value="save" and not(@disabled)]')->length)->toBe(1)
        ->and(RmsQuarterlyReport::count())->toBe(0);
    $this->getJson(route('research_head.rms-quarterly-reports.index', ['year' => 1999, 'quarter' => 5]))
        ->assertUnprocessable()->assertJsonValidationErrors(['year', 'quarter']);
});

test('a stale RMS editor cannot replace a newer saved draft', function (string $intent) {
    $html = $this->get(route('research_head.rms-quarterly-reports.index', $this->period))->assertOk()->getContent();
    preg_match('/name="draft_token" value="([^"]*)"/', $html, $matches);
    $input = [...($this->draftInput)(), 'draft_token' => $matches[1] ?? ''];
    $this->post(route('research_head.rms-quarterly-reports.store'), [...$input, 'campus' => 'Newer campus details'])->assertRedirect();

    $this->postJson(route('research_head.rms-quarterly-reports.store'), [...$input, 'intent' => $intent, 'campus' => 'Outdated campus details'])
        ->assertUnprocessable()->assertJsonValidationErrors('draft_token');

    expect(RmsQuarterlyReport::sole()->campus)->toBe('Newer campus details');
})->with(['save', 'download']);

test('the RMS conflict page preserves the stale token until the latest draft is reloaded', function () {
    $url = route('research_head.rms-quarterly-reports.index', $this->period);
    $input = ($this->draftInput)();
    $this->post(route('research_head.rms-quarterly-reports.store'), [...$input, 'campus' => 'Newest saved campus'])
        ->assertSessionHasNoErrors();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->from($url)->post(route('research_head.rms-quarterly-reports.store'), [...$input, 'campus' => 'Older unsaved campus'])
        ->assertSessionHasErrors('draft_token');

    $this->get($url)->assertOk()->assertSee('Your changes were not saved.')
        ->assertSee('name="draft_token" value="'.$input['draft_token'].'"', false)->assertSee('Older unsaved campus');
    $this->get($url)->assertOk()->assertSee('Newest saved campus')
        ->assertSee('name="draft_token" value="'.RmsQuarterlyReport::sole()->draftToken().'"', false);
});

test('RMS report saves require a draft token', function () {
    $input = ($this->draftInput)();
    unset($input['draft_token']);
    $this->postJson(route('research_head.rms-quarterly-reports.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors('draft_token');
    expect(RmsQuarterlyReport::count())->toBe(0);
});

test('RMS editor exposes labeled save actions and category buttons with presentations open by default', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => [
        'college' => $source->college,
        'manual_presentations' => [['id' => 'presentation-row', 'event' => 'Research Forum', 'research_title' => 'Health Research', 'date' => '2026-09-03', 'place' => 'Nasugbu', 'personnel' => 'Ana Researcher']],
        'manual_publications' => [['id' => 'publication-row', 'event' => 'Research Journal', 'research_title' => 'Published Study', 'date' => '2026-09-03', 'personnel' => 'Ana Researcher']],
        'ip_assets' => [['id' => 'ip-row', 'title' => 'Research Asset', 'type' => 'Copyright', 'inventors' => 'Ana Researcher', 'date' => '2026-09-03']],
    ]]);
    ($this->submitMissingColleges)();
    $response = $this->get(route('research_head.rms-quarterly-reports.index', $this->period))->assertOk()
        ->assertViewHas('reportStatus', fn (array $status): bool => $status['ready'] && $status['submitted_count'] === 6)
        ->assertSeeText('All RMS reports')
        ->assertDontSee('data-library-view-surface', false)
        ->assertDontSee('@contextmenu="openViewMenu($event)"', false);
    if (getenv('ATHENA_EXPORT_RMS_UI') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/rms-editor.html'), $response->getContent());
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form[@id="rms-report-form"]')->item(0);
    expect($form->getAttribute('x-data'))->toBe('rmsQuarterlyReportForm')
        ->and($form->getAttribute('x-init'))->toContain('presentations');
    foreach (['save' => 'Save draft', 'download' => 'Save & download Excel'] as $intent => $label) {
        $button = $xpath->query('//button[@type="submit" and @form="rms-report-form" and @name="intent" and @value="'.$intent.'"]')->item(0);
        expect($button)->not->toBeNull();
        expect(trim($button->textContent))->toBe($label)
            ->and($button->hasAttribute('disabled'))->toBeFalse();
    }
    foreach (['presentations' => 'Paper presentations', 'publications' => 'Publications', 'ip_assets' => 'IP assets'] as $section => $label) {
        $button = $xpath->query('//nav[@data-rms-section-navigation]//button[@type="button" and @aria-controls="rms-'.$section.'"]')->item(0);
        expect($button)->not->toBeNull();
        expect($button->textContent)->toContain($label);
        expect($button->hasAttribute(':aria-pressed') || $button->hasAttribute('x-bind:aria-pressed'))->toBeTrue();
    }
    expect($xpath->query('//a[@href="'.route('research_head.rms-quarterly-reports.index').'" and normalize-space(.)="All RMS reports"]')->length)->toBe(1);
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

test('RMS saving rechecks college sources after acquiring the saved draft', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => ['college' => $source->college]]);
    $draft = RmsQuarterlyReport::factory()->create([
        'reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'Saved campus',
    ]);
    $input = [...($this->draftInput)(), 'campus' => 'Stale campus'];
    $changed = false;
    Event::listen('eloquent.retrieved: '.RmsQuarterlyReport::class, function (RmsQuarterlyReport $report) use ($source, $draft, &$changed): void {
        if ($changed || $report->id !== $draft->id) {
            return;
        }

        $changed = true;
        $source->update(['submitted_at' => null]);
    });

    try {
        $this->postJson(route('research_head.rms-quarterly-reports.store'), $input)
            ->assertUnprocessable()->assertJsonValidationErrors('source_token');
    } finally {
        Event::forget('eloquent.retrieved: '.RmsQuarterlyReport::class);
    }

    expect($changed)->toBeTrue()
        ->and($draft->fresh()->campus)->toBe('Saved campus');
});

test('head can complete source fields while college provenance stays read only', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => [
        'college' => $source->college,
        'manual_presentations' => [['id' => 'rms-row', 'event' => 'Research Forum', 'research_title' => 'Health Research', 'date' => '2026-09-03', 'place' => 'Nasugbu', 'personnel' => 'Ana Researcher']],
    ]]);
    ($this->submitMissingColleges)();
    $row = app(RmsQuarterlyReportData::class)->build(2026, 3)['presentations'][0];
    $this->get(route('research_head.rms-quarterly-reports.index', $this->period))
        ->assertOk()->assertSeeText('Health Research')->assertSee('row_details['.$row['key'].'][source_of_fund]', false)
        ->assertDontSee('name="row_details['.$row['key'].'][college]"', false);
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'row_details' => [$row['key'] => ['source_of_fund' => 'Externally-funded', 'project_status' => 'On-going', 'sdgs' => '3, 9', 'evidence_url' => 'https://example.com/proof']],
        'intent' => 'download',
    ])->assertSessionHasNoErrors()->assertRedirect(route('research_head.rms-quarterly-reports.index', $this->period))
        ->assertSessionHas('report_download_url', route('research_head.rms-quarterly-reports.download', $this->period));
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
    ($this->submitMissingColleges)();
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'Nasugbu']);
    $response = $this->get(route('research_head.rms-quarterly-reports.download', $this->period))
        ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="RMS-Quarterly-Monitoring-Report-2026-Q3.xlsx"');
    expect(substr($response->getContent(), 0, 2))->toBe('PK');
});

test('direct RMS exports are blocked until every college has a current submitted report', function () {
    $this->mock(RmsQuarterlyReportDocument::class)->shouldNotReceive('generate');
    $this->getJson(route('research_head.rms-quarterly-reports.download', $this->period))
        ->assertUnprocessable()->assertJsonValidationErrors('college_reports');
    $this->get(route('research_head.rms-quarterly-reports.download', $this->period))
        ->assertRedirect(route('research_head.rms-quarterly-reports.index', $this->period))
        ->assertSessionHasErrors('college_reports');
    expect(RmsQuarterlyReport::count())->toBe(0);
});

test('a blocked save and download request preserves the quarterly draft', function (bool $json) {
    $this->mock(RmsQuarterlyReportDocument::class)->shouldNotReceive('generate');
    $input = [...($this->draftInput)(),
        'intent' => 'download', 'campus' => 'Draft kept while waiting',
    ];
    if ($json) {
        $this->postJson(route('research_head.rms-quarterly-reports.store'), $input)
            ->assertUnprocessable()->assertJsonValidationErrors('college_reports');
    } else {
        $this->post(route('research_head.rms-quarterly-reports.store'), $input)
            ->assertRedirect(route('research_head.rms-quarterly-reports.index', $this->period))
            ->assertSessionHasErrors('college_reports');
    }
    expect(RmsQuarterlyReport::sole()->campus)->toBe('Draft kept while waiting')
        ->and(RmsQuarterlyReport::sole()->downloaded_at)->toBeNull();
})->with(['browser' => false, 'JSON' => true]);

test('saving and downloading Excel refreshes the editor before another save', function () {
    ($this->submitMissingColleges)();
    $url = route('research_head.rms-quarterly-reports.index', $this->period);
    $before = $this->get($url)->assertOk()->getContent();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'intent' => 'download', 'campus' => 'Campus saved for download',
    ])->assertSessionHasNoErrors()->assertRedirect($url)
        ->assertSessionHas('report_download_url', route('research_head.rms-quarterly-reports.download', $this->period));

    $after = $this->get($url)->assertOk()
        ->assertSee('Download Excel again')
        ->assertSee('name="draft_token" value="'.RmsQuarterlyReport::sole()->draftToken().'"', false)
        ->assertSee('href="'.e(route('research_head.rms-quarterly-reports.download', $this->period)).'" x-init="window.location.assign($el.href)"', false)->getContent();
    if (getenv('ATHENA_EXPORT_REPORT_DOWNLOAD') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/rms-download-before.html'), $before);
        File::put(storage_path('framework/testing/rms-download-after.html'), $after);
    }
    preg_match('/name="draft_token" value="([^"]*)"/', $after, $matches);
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'draft_token' => $matches[1], 'campus' => 'Further campus edit after download',
    ])->assertSessionHasNoErrors();
    expect(RmsQuarterlyReport::sole()->campus)->toBe('Further campus edit after download');
    $this->get($url)->assertOk()->assertDontSee('window.location.assign($el.href)', false);
});

test('six submitted reports with no outputs can be exported and record the generated version', function () {
    ($this->submitMissingColleges)();
    $this->get(route('research_head.rms-quarterly-reports.download', $this->period))->assertOk();
    $report = RmsQuarterlyReport::sole();
    $status = app(RmsQuarterlyReportStatus::class)->forPeriod(2026, 3, $report);
    expect($report->downloaded_at)->not->toBeNull()
        ->and($report->downloaded_fingerprint)->toHaveLength(64)
        ->and($status['outdated'])->toBeFalse();
});

test('failed Excel generation does not record a successful export', function (bool $savedDraft) {
    ($this->submitMissingColleges)();
    $report = $savedDraft ? RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3]) : null;
    $this->mock(RmsQuarterlyReportDocument::class)->shouldReceive('generate')->once()->andThrow(new RuntimeException('Workbook generation failed'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->get(route('research_head.rms-quarterly-reports.download', $this->period)))
        ->toThrow(RuntimeException::class, 'Workbook generation failed');
    expect(RmsQuarterlyReport::count())->toBe($savedDraft ? 1 : 0);
    if ($report !== null) {
        expect($report->fresh()->downloaded_at)->toBeNull()
            ->and($report->fresh()->downloaded_fingerprint)->toBeNull();
    }
})->with(['saved draft' => true, 'unsaved report' => false]);

test('saved RMS changes and returned college reports make the downloaded workbook outdated', function () {
    ($this->submitMissingColleges)();
    $url = route('research_head.rms-quarterly-reports.download', $this->period);
    $this->get($url)->assertOk();
    $report = RmsQuarterlyReport::sole();
    $status = app(RmsQuarterlyReportStatus::class);
    $this->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(), 'campus' => 'Updated campus'])
        ->assertSessionHasNoErrors();
    expect($status->forPeriod(2026, 3, $report->fresh())['outdated'])->toBeTrue();
    $this->get($url)->assertOk();
    expect($status->forPeriod(2026, 3, $report->fresh())['outdated'])->toBeFalse();

    $collegeReport = CollegeQuarterlyReport::where('college', User::COLLEGES['CICS'])->sole();
    $this->patch(route('research_head.quarterly-reports.return', $collegeReport), [
        'submission_id' => $collegeReport->latestSubmission->id, 'reason' => 'Correct the submitted report.',
    ])->assertSessionHasNoErrors();
    $returned = $status->forPeriod(2026, 3, $report->fresh());
    expect($returned['ready'])->toBeFalse()->and($returned['outdated'])->toBeTrue();
    $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('college_reports');
    $collegeReport->refresh()->update(['submitted_at' => now()]);
    CollegeQuarterlyReportSubmission::factory()->for($collegeReport, 'report')->create([
        'version_number' => 2, 'snapshot' => ['college' => $collegeReport->college],
    ]);
    expect($status->forPeriod(2026, 3, $report->fresh())['ready'])->toBeTrue()
        ->and($status->forPeriod(2026, 3, $report->fresh())['outdated'])->toBeTrue();
    $this->get($url)->assertOk();
    expect($status->forPeriod(2026, 3, $report->fresh())['outdated'])->toBeFalse();
});

test('invalid input reopens its report category and preserves the saved draft', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => ['manual_publications' => [[
        'id' => 'publication-row', 'event' => 'Research Journal', 'research_title' => 'Study', 'personnel' => 'Ana Researcher', 'date' => '2026-09-03',
    ]]]]);
    $row = app(RmsQuarterlyReportData::class)->build(2026, 3)['publications'][0];
    $saved = RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'Saved campus']);
    $workspace = route('research_head.rms-quarterly-reports.index', $this->period);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->from($workspace)->post(route('research_head.rms-quarterly-reports.store'), [...($this->draftInput)(),
        'row_details_json' => json_encode([$row['key'] => ['evidence_url' => 'not-a-link']], JSON_THROW_ON_ERROR),
    ])->assertRedirect($workspace)->assertSessionHasErrors('row_details.'.$row['key'].'.evidence_url');
    expect($saved->fresh()->campus)->toBe('Saved campus');
    $response = $this->get($workspace)->assertOk()->assertSee('not-a-link');
    if (getenv('ATHENA_EXPORT_RMS_UI') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/rms-editor-errors.html'), $response->getContent());
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form[@id="rms-report-form"]')->item(0);
    expect($form->hasAttribute('novalidate'))->toBeTrue()
        ->and($form->getAttribute('x-init'))->toContain('publications')
        ->and($xpath->query('//*[@id="rms-publications"]//details[@open]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="'.$row['key'].'-evidence_url"]')->item(0)->getAttribute('aria-invalid'))->toBe('true');
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
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'college' => User::COLLEGES['CICS'], 'quarter' => 3]))
        ->assertOk()->assertSeeText(User::COLLEGES['CICS'])->assertDontSeeText('Private unfinished college')
        ->assertSeeText('Return for correction')->assertSeeText('Version 1')->assertSeeText('Version 2')
        ->assertSee(route('college-quarterly-submissions.print', $first))
        ->assertSee(route('college-quarterly-submissions.download', $latest));
});
