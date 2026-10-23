<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));

    foreach (['research_coordinator', 'faculty', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->actingAs($this->coordinator)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->reportInput = ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'save', 'form_complete' => '1'];
});

test('coordinator JSON submissions save every activity beyond the native form input limit', function () {
    $presentations = array_map(fn (int $index): array => [
        'id' => (string) Str::uuid(), 'event' => 'Forum '.$index, 'research_title' => 'Research '.$index,
        'date' => '2026-08-14', 'place' => 'Nasugbu', 'personnel' => 'Ana Researcher',
    ], range(1, 100));
    $details = [
        'presentations' => $presentations,
        'publications' => array_map(fn (array $row): array => Arr::except($row, 'place'), $presentations),
        'seminars' => $presentations,
        'manual_research' => array_map(fn (array $row): array => [
            'id' => $row['id'], 'title' => $row['research_title'], 'duration' => '6 months', 'personnel' => $row['personnel'], 'status' => 'Ongoing',
        ], $presentations),
        'ip_assets' => array_map(fn (array $row): array => [
            'id' => $row['id'], 'title' => $row['research_title'], 'type' => 'Copyright', 'inventors' => $row['personnel'], 'date' => $row['date'],
        ], $presentations),
        'saved_evidence_captions' => (object) [], 'remove_evidence' => [], 'evidence_captions' => [],
    ];

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'report_details_json' => json_encode($details, JSON_THROW_ON_ERROR), 'evidence_file_count' => 0,
    ])->assertSessionHasNoErrors();

    $report = CollegeQuarterlyReport::sole();
    foreach (['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets'] as $section) {
        expect($report->getAttribute($section))->toEqual($details[$section]);
    }
});

test('incomplete coordinator forms cannot replace a saved report', function () {
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'other_accomplishments' => 'Saved narrative',
    ]);
    $input = $this->reportInput;
    unset($input['form_complete']);

    $this->postJson(route('research_coordinator.reports.store'), [...$input, 'other_accomplishments' => 'Truncated narrative'])
        ->assertUnprocessable()->assertJsonValidationErrors('form_complete');

    expect($report->fresh()->other_accomplishments)->toBe('Saved narrative');
});

test('coordinator JSON preserves saved captions and applies image removals', function () {
    Storage::fake('local');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('retained.png'), UploadedFile::fake()->image('removed.png')],
        'evidence_file_count' => 2,
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    [$retained, $removed] = $report->evidence;

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'report_details_json' => json_encode([
            'saved_evidence_captions' => [hash('sha256', $retained['path']) => 'Corrected caption'],
            'remove_evidence' => [hash('sha256', $removed['path'])],
        ], JSON_THROW_ON_ERROR), 'evidence_file_count' => 0,
    ])->assertSessionHasNoErrors();

    expect($report->fresh()->evidence)->toHaveCount(1)
        ->and($report->fresh()->evidence[0]['caption'])->toBe('Corrected caption');
    Storage::disk('local')->assertExists($retained['path']);
    Storage::disk('local')->assertMissing($removed['path']);
});

test('invalid coordinator JSON cannot replace a saved report', function (mixed $json) {
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'other_accomplishments' => 'Saved narrative',
    ]);

    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'other_accomplishments' => 'Changed narrative', 'report_details_json' => $json,
    ])->assertUnprocessable()->assertJsonValidationErrors('report_details_json');

    expect($report->fresh()->other_accomplishments)->toBe('Saved narrative');
})->with([
    'invalid JSON' => ['{'], 'JSON array' => ['[]'], 'JSON scalar' => ['"text"'],
    'non-string' => [['presentations' => []]], 'oversized' => [str_repeat(' ', 5 * 1024 * 1024 + 1)],
    'unexpected input' => ['{"college":"Other college"}'],
]);

test('missing coordinator uploads cannot silently submit only the files PHP retained', function () {
    Storage::fake('local');

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('retained.png')], 'evidence_file_count' => 2,
    ])->assertSessionHasErrors('evidence_file_count');

    expect(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('coordinators without research projects can open an empty calendar quarter report', function () {
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()
        ->assertViewIs('research_coordinator.reports')
        ->assertViewHas('report', null)
        ->assertViewHas('period', fn (array $period): bool => $period['start']->toDateString() === '2026-07-01' && $period['end']->toDateString() === '2026-09-30')
        ->assertViewHas('reportData', fn (array $data): bool => $data['presentations'] === [] && $data['publications'] === [] && $data['research'] === [])
        ->assertViewHas('quarters', fn ($quarters): bool => $quarters->count() === 4);
});

test('the quarterly report has editable manual fields and readable automatic tables', function () {
    $this->mock(CollegeQuarterlyReportData::class)->shouldReceive('build')->once()->andReturn([
        'presentations' => [['Faculty Research Forum', 'A Web and Mobile Healthcare System for Clinical Assessment', 'August 14, 2026', 'Nasugbu / Online', "Ana Researcher\nBen Researcher"]],
        'publications' => [['Journal of Applied Research', 'A Web and Mobile Healthcare System for Clinical Assessment', 'September 10, 2026', 'Ana Researcher']],
        'research' => [['A Web and Mobile Healthcare System for Clinical Assessment', 'July 1, 2026 – June 30, 2027', "Ana Researcher\nBen Researcher", 'Ongoing']],
        'undated_publications' => 0,
    ]);
    $response = $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['other_accomplishments', 'reviewed_by', 'reviewed_by_title', 'evidence_images[]'] as $name) {
        expect($xpath->query('//*[@name="'.$name.'" and not(@disabled) and not(@readonly)]')->length)->toBe(1);
    }
    expect($xpath->query('//form[@id="quarterly-report-form"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-proposal-workspace-toolbar and @role="group"]')->length)->toBe(1)
        ->and($xpath->query('//textarea[@name="other_accomplishments" and @data-semantic-editor]')->length)->toBe(1);
    foreach (['save' => 'Save draft', 'preview' => 'Save and preview', 'download' => 'Save and download Word', 'submit' => 'Submit quarterly report'] as $intent => $label) {
        expect($xpath->query('//button[@type="submit" and @form="quarterly-report-form" and @name="intent" and @value="'.$intent.'" and normalize-space(.)="'.$label.'"]')->length)->toBe(1);
    }
    foreach (['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList', 'undo', 'redo', 'removeFormat'] as $command) {
        expect($xpath->query('//button[@type="button" and @data-document-writing-command="'.$command.'"]')->length)->toBe(1);
    }
    expect($xpath->query('//*[@data-quarterly-reports]//table//input | //*[@data-quarterly-reports]//table//textarea')->length)->toBe(0);
    foreach ($xpath->query('//*[@data-quarterly-reports]//table') as $table) {
        expect($table->getAttribute('class'))->toContain('min-w-[44rem]', 'text-sm');
    }
    expect($xpath->query('//*[@data-quarterly-reports]//label[normalize-space(.)="label"]')->length)->toBe(0);

    if (getenv('ATHENA_EXPORT_QUARTERLY_REPORT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/college-quarterly-report.html'), $response->getContent());
    }
});

test('the report defaults to the current calendar quarter and validates period selection', function () {
    $this->get(route('research_coordinator.reports.index'))->assertOk()->assertViewHas('year', 2026)->assertViewHas('quarter', 4);
    $this->getJson(route('research_coordinator.reports.index', ['year' => 1999, 'quarter' => 5]))
        ->assertUnprocessable()->assertJsonValidationErrors(['year', 'quarter']);
});

test('the report uses the four calendar quarters and shows their month ranges', function () {
    foreach ([1 => ['2026-01-01', '2026-03-31', 'Jan', 'Mar'], 2 => ['2026-04-01', '2026-06-30', 'Apr', 'Jun'], 3 => ['2026-07-01', '2026-09-30', 'Jul', 'Sep'], 4 => ['2026-10-01', '2026-12-31', 'Oct', 'Dec']] as $quarter => [$start, $end, $firstMonth, $lastMonth]) {
        $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => $quarter]))
            ->assertOk()
            ->assertViewHas('period', fn (array $period): bool => $period['start']->toDateString() === $start && $period['end']->toDateString() === $end)
            ->assertSeeText($firstMonth.' – '.$lastMonth.' 2026');
    }
});

test('a coordinator can open and save the next quarter without finishing the current draft', function (int $quarter, int $nextYear, int $nextQuarter) {
    $this->travelTo(now()->setDate(2026, (($quarter - 1) * 3) + 1, 15));
    $current = [...$this->reportInput, 'reporting_quarter' => $quarter, 'other_accomplishments' => 'Current quarter draft'];
    $this->post(route('research_coordinator.reports.store'), $current)->assertSessionHasNoErrors();
    $nextUrl = route('research_coordinator.reports.index', ['year' => $nextYear, 'quarter' => $nextQuarter]);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => $quarter]))
        ->assertOk()->assertSeeText('Open next quarter')->assertSee($nextUrl);
    $this->get($nextUrl)->assertOk()->assertViewHas('report', null);
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'reporting_year' => $nextYear, 'reporting_quarter' => $nextQuarter, 'other_accomplishments' => 'Next quarter draft',
    ])->assertSessionHasNoErrors();

    expect(CollegeQuarterlyReport::count())->toBe(2)
        ->and(CollegeQuarterlyReport::where('reporting_year', 2026)->where('reporting_quarter', $quarter)->sole()->other_accomplishments)->toBe('Current quarter draft')
        ->and(CollegeQuarterlyReport::whereNotNull('submitted_at')->exists())->toBeFalse();
    $this->get($nextUrl)->assertOk()->assertSee('Next quarter draft');
})->with(['January to April' => [1, 2026, 2], 'October to next January' => [4, 2027, 1]]);

test('next quarter navigation stays within the supported reporting years', function () {
    $this->get(route('research_coordinator.reports.index', ['year' => 2100, 'quarter' => 4]))
        ->assertOk()->assertDontSeeText('Open next quarter');
});

test('a January draft detects February records and saving acknowledges the updates', function () {
    $this->travelTo(now()->setDate(2026, 1, 15));
    $owner = User::factory()->create(['college' => $this->coordinator->college]);
    $project = TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Quarterly faculty research', 'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 12,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31'],
    ]);
    $input = [...$this->reportInput, 'reporting_quarter' => 1, 'other_accomplishments' => 'My January narrative'];
    $url = route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 1]);
    $this->post(route('research_coordinator.reports.store'), $input)->assertSessionHasNoErrors();
    $baseline = CollegeQuarterlyReport::sole()->snapshot;
    expect($baseline['research'])->toHaveCount(1);
    $this->get($url)->assertOk()->assertViewHas('hasSourceChanges', false);

    $this->travelTo(now()->setDate(2026, 2, 15));
    $project->update(['title' => 'Updated February faculty research']);
    $this->get($url)->assertOk()->assertViewHas('hasSourceChanges', true)
        ->assertSeeText('Automatic records changed since this draft was saved.')
        ->assertSeeText('Updated February faculty research')
        ->assertSee('My January narrative');
    expect(CollegeQuarterlyReport::sole()->snapshot)->toEqual($baseline);

    $this->post(route('research_coordinator.reports.store'), $input)->assertSessionHasNoErrors();
    $this->get($url)->assertOk()->assertViewHas('hasSourceChanges', false)
        ->assertDontSeeText('Automatic records changed since this draft was saved.')
        ->assertSeeText('Updated February faculty research');
    expect(CollegeQuarterlyReport::sole()->snapshot['research'][0][0])->toBe('Updated February faculty research');
});

test('legacy drafts and reordered source keys do not show a false records changed notice', function (?array $snapshot) {
    CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'prepared_by' => $this->coordinator->id,
        'reporting_year' => 2026, 'reporting_quarter' => 3, 'snapshot' => $snapshot,
    ]);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertViewHas('hasSourceChanges', false)
        ->assertDontSeeText('Automatic records changed since this draft was saved.');
})->with(['legacy draft' => [null], 'unchanged data' => [['undated_publications' => 0, 'research' => [], 'publications' => [], 'presentations' => []]]]);

test('coordinators save one draft per college quarter with only validated fields', function () {
    $seminar = ['event' => 'Research ethics', 'date' => '2026-08-14', 'place' => 'Lipa', 'personnel' => 'College faculty'];
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'seminars' => [$seminar, ['event' => ' ', 'date' => '', 'place' => null, 'personnel' => '']],
        'other_accomplishments' => 'Organized the college research colloquium.',
        'reviewed_by' => 'College Dean', 'reviewed_by_title' => 'Dean',
        'college' => User::COLLEGES['CTE'], 'prepared_by' => 99999,
        'submitted_at' => now()->toISOString(), 'snapshot' => ['research' => [['Injected']]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]));

    $report = CollegeQuarterlyReport::sole();
    expect($report->college)->toBe($this->coordinator->college)
        ->and($report->prepared_by)->toBe($this->coordinator->id)
        ->and(collect($report->seminars)->map(fn (array $row): array => Arr::except($row, 'id'))->all())->toEqual([$seminar])
        ->and($report->submitted_at)->toBeNull()
        ->and($report->snapshot)->toEqual(['presentations' => [], 'publications' => [], 'research' => [], 'undated_publications' => 0]);

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'other_accomplishments' => 'Updated draft'])
        ->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::count())->toBe(1)
        ->and($report->fresh()->other_accomplishments)->toBe('Updated draft');
});

test('drafts may be prepared in current and future quarters', function (int $year, int $quarter) {
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'reporting_year' => $year, 'reporting_quarter' => $quarter])
        ->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->submitted_at)->toBeNull();
})->with(['current quarter' => [2026, 4], 'future quarter' => [2027, 1]]);

test('preview and download save the current draft and evidence before opening the requested output', function (string $intent, string $destination) {
    Storage::fake('local');
    $seminar = ['event' => 'Current workshop', 'date' => '2026-08-14', 'place' => 'Room B', 'personnel' => 'Current faculty'];
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'other_accomplishments' => 'Earlier draft'])
        ->assertSessionHasNoErrors();

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'intent' => $intent, 'seminars' => [$seminar], 'other_accomplishments' => 'Current accomplishment',
        'reviewed_by' => 'Current Dean', 'reviewed_by_title' => 'Dean',
        'evidence_images' => [UploadedFile::fake()->image('current.png')], 'evidence_captions' => ['Current evidence'],
    ])->assertSessionHasNoErrors()->assertRedirect(route($destination, ['year' => 2026, 'quarter' => 3]));

    $report = CollegeQuarterlyReport::sole();
    expect(collect($report->seminars)->map(fn (array $row): array => Arr::except($row, 'id'))->all())->toEqual([$seminar])
        ->and($report->other_accomplishments)->toBe('Current accomplishment')
        ->and($report->reviewed_by)->toBe('Current Dean')
        ->and($report->reviewed_by_title)->toBe('Dean')
        ->and($report->evidence)->toHaveCount(1)
        ->and($report->evidence[0]['caption'])->toBe('Current evidence')
        ->and($report->submitted_at)->toBeNull()
        ->and($report->snapshot)->toEqual(['presentations' => [], 'publications' => [], 'research' => [], 'undated_publications' => 0]);
    Storage::disk('local')->assertExists($report->evidence[0]['path']);
})->with(['preview' => ['preview', 'research_coordinator.reports.print'], 'download' => ['download', 'research_coordinator.reports.download']]);

test('other accomplishments retain supported formatting while removing unsafe html', function () {
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'other_accomplishments' => '<p onclick="alert(1)"><strong>Bold &amp; clear</strong> <em>Italic</em> <u>Underlined</u><br>Next line</p><ol><li>First</li><li>Second</li></ol><ul><li>Bullet</li></ul><script>alert(1)</script><img src="x" onerror="alert(2)">',
    ])->assertSessionHasNoErrors();

    $formatted = '<p><strong>Bold &amp; clear</strong> <em>Italic</em> <u>Underlined</u><br>Next line</p><ol><li>First</li><li>Second</li></ol><ul><li>Bullet</li></ul>';
    expect(CollegeQuarterlyReport::sole()->other_accomplishments)->toBe($formatted);

    $this->get(route('research_coordinator.reports.print', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertSee($formatted, false)->assertDontSee('alert(1)', false)->assertDontSee('alert(2)', false);

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'intent' => 'submit', 'other_accomplishments' => $formatted,
    ])->assertSessionHasNoErrors();
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertSee($formatted, false);
});

test('other accomplishments limit the visible text without counting formatting markup', function () {
    $formatted = '<p><strong>'.str_repeat('a', 20000).'</strong></p>';
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'other_accomplishments' => $formatted])
        ->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->other_accomplishments)->toBe($formatted);

    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'other_accomplishments' => '<p>'.str_repeat('a', 20001).'</p>',
    ])->assertUnprocessable()->assertJsonValidationErrors('other_accomplishments');
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'other_accomplishments' => '<p title="'.str_repeat('a', 100000).'">Short text</p>',
    ])->assertUnprocessable()->assertJsonValidationErrors('other_accomplishments');
    expect(CollegeQuarterlyReport::sole()->other_accomplishments)->toBe($formatted);
});

test('preview and download validate unsaved fields before saving any files', function (string $intent) {
    Storage::fake('local');
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'intent' => $intent, 'seminars' => [['event' => 'Incomplete workshop']],
        'evidence_images' => [UploadedFile::fake()->image('invalid.png')],
    ])->assertUnprocessable()->assertJsonValidationErrors(['seminars.0.date', 'seminars.0.place', 'seminars.0.personnel']);
    expect(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([]);
})->with(['preview', 'download']);

test('other accomplishments validate their stored byte size before saving reports or evidence', function (string $accomplishments) {
    Storage::fake('local');
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'other_accomplishments' => $accomplishments,
        'evidence_images' => [UploadedFile::fake()->image('oversized.png')],
    ])->assertUnprocessable()->assertJsonValidationErrors('other_accomplishments');

    expect(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([]);
})->with([
    'plain unicode' => [str_repeat('😀', 20000)],
    'formatted unicode' => ['<p>'.str_repeat('😀', 20000).'</p>'],
    'escaped rich text' => ['<p>'.str_repeat('&amp;', 15000).'</p>'],
]);

test('plain accomplishments use their stored size rather than escaped display size', function () {
    $accomplishments = str_repeat('&', 20000);
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'other_accomplishments' => $accomplishments])
        ->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->other_accomplishments)->toBe($accomplishments);
});

test('submission before the calendar quarter ends is rejected without creating a report', function (int $year, int $quarter) {
    Storage::fake('local');
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'reporting_year' => $year, 'reporting_quarter' => $quarter, 'intent' => 'submit',
        'evidence_images' => [UploadedFile::fake()->image('evidence.png')],
    ])->assertUnprocessable()->assertJsonValidationErrors('intent');

    expect(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([]);
})->with(['current quarter' => [2026, 4], 'future quarter' => [2027, 1]]);

test('past quarter submission freezes report data and prevents edits or resubmission', function () {
    $snapshotData = ['presentations' => [], 'publications' => [], 'research' => [['Original research']]];
    $this->mock(CollegeQuarterlyReportData::class)->shouldReceive('build')->once()->andReturn($snapshotData);
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'intent' => 'submit', 'other_accomplishments' => 'Original accomplishment',
    ])->assertSessionHasNoErrors();

    $report = CollegeQuarterlyReport::sole();
    $snapshot = $report->snapshot;
    expect($report->submitted_at)->not->toBeNull()
        ->and($snapshot['research'])->toBe($snapshotData['research'])
        ->and($snapshot['prepared_by_name'])->toBe($this->coordinator->name);

    $this->coordinator->update(['name' => 'Renamed coordinator']);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertViewHas('reportData', $snapshot);
    $this->get(route('research_coordinator.reports.print', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertViewHas('reportData', $snapshot);

    foreach (['save', 'preview', 'download', 'submit'] as $intent) {
        $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput, 'intent' => $intent, 'other_accomplishments' => 'Changed'])
            ->assertUnprocessable()->assertJsonValidationErrors('intent');
    }
    expect($report->fresh()->snapshot)->toBe($snapshot)
        ->and($report->fresh()->other_accomplishments)->toBe('Original accomplishment');
});

test('a finished quarter is available immediately after its final day', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'intent' => 'submit'])
        ->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->submitted_at)->not->toBeNull();
});

test('seminars require complete fields and dates within the selected calendar quarter', function (array $seminar, string $field) {
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput, 'seminars' => [$seminar]])
        ->assertUnprocessable()->assertJsonValidationErrors('seminars.0'.($field !== '' ? '.'.$field : ''));
    expect(CollegeQuarterlyReport::count())->toBe(0);
})->with([
    'partial row' => [['event' => 'Workshop', 'date' => '2026-08-14', 'place' => 'Lipa'], 'personnel'],
    'previous quarter' => [['event' => 'Workshop', 'date' => '2026-06-30', 'place' => 'Lipa', 'personnel' => 'Faculty'], 'date'],
    'following quarter' => [['event' => 'Workshop', 'date' => '2026-10-01', 'place' => 'Lipa', 'personnel' => 'Faculty'], 'date'],
    'unexpected nested data' => [['event' => 'Workshop', 'date' => '2026-08-14', 'place' => 'Lipa', 'personnel' => 'Faculty', 'path' => '/secret'], ''],
]);

test('coordinator accounts require an assigned college for every report action', function () {
    $this->coordinator->update(['college' => null]);
    foreach (['index', 'print', 'template', 'download'] as $action) {
        $this->get(route('research_coordinator.reports.'.$action))->assertForbidden();
    }
    $this->postJson(route('research_coordinator.reports.store'), $this->reportInput)->assertForbidden();
});

test('faculty and secretary accounts cannot enter coordinator reporting', function (string $role, string $workspace) {
    $user = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $user->assignRole($role);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    foreach (['index', 'print', 'template', 'download'] as $action) {
        $this->get(route('research_coordinator.reports.'.$action))->assertForbidden();
    }
    $this->postJson(route('research_coordinator.reports.store'), $this->reportInput)->assertForbidden();
})->with(['faculty' => ['faculty', User::WORKSPACE_FACULTY], 'secretary' => ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY]]);

test('college report queries and writes stay within the active coordinators college', function () {
    $other = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CTE'], 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'other_accomplishments' => 'Private CTE accomplishment',
    ]);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3, 'college' => $other->college]))
        ->assertOk()->assertViewHas('report', null)->assertDontSee('Private CTE accomplishment');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'college' => $other->college])
        ->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::count())->toBe(2)
        ->and($other->fresh()->other_accomplishments)->toBe('Private CTE accomplishment');
});

test('supporting images are private and only the college coordinator can retrieve them', function () {
    Storage::fake('local');
    $image = UploadedFile::fake()->image('seminar.png');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [$image], 'evidence_captions' => ['Faculty research workshop'],
        'evidence' => [['path' => '/private/file', 'caption' => 'Injected']],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    expect($report->evidence)->toHaveCount(1)
        ->and($report->evidence[0]['caption'])->toBe('Faculty research workshop')
        ->and($report->evidence[0]['original_filename'])->toBe('seminar.png');
    Storage::disk('local')->assertExists($report->evidence[0]['path']);
    $this->get(route('research_coordinator.reports.evidence', [$report, 0]))->assertOk();
    $this->get(route('research_coordinator.reports.evidence', [$report, 9]))->assertNotFound();

    $other = User::factory()->create(['college' => User::COLLEGES['CTE']]);
    $other->assignRole('research_coordinator');
    $this->actingAs($other)->get(route('research_coordinator.reports.evidence', [$report, 0]))->assertForbidden();
});

test('submitted reports reject new images before storing files', function () {
    Storage::fake('local');
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'submitted_at' => now(), 'snapshot' => ['presentations' => [], 'publications' => [], 'research' => []],
    ]);
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('ignored.png')],
    ])->assertUnprocessable();
    expect(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([])
        ->and($report->fresh()->evidence)->toBeNull();
});

test('failed submission rolls back the report and removes newly stored images', function () {
    Storage::fake('local');
    $this->mock(CollegeQuarterlyReportData::class)->shouldReceive('build')->once()->andThrow(new RuntimeException('Report source unavailable'));
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'intent' => 'submit', 'evidence_images' => [UploadedFile::fake()->image('rollback.png')],
    ])->assertServerError();

    expect(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([]);
});

test('supporting uploads reject nonimages and enforce the total report image limit', function () {
    Storage::fake('local');
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->create('report.pdf', 5, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors('evidence_images.0');

    CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'evidence' => array_fill(0, 10, ['path' => 'existing.png', 'caption' => 'Existing', 'mime_type' => 'image/png', 'original_filename' => 'existing.png']),
    ]);
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('eleventh.png')],
    ])->assertUnprocessable()->assertJsonValidationErrors('evidence_images');
    expect(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([]);
});

test('coordinators may remove saved draft images and replace them without leaving files behind', function () {
    Storage::fake('local');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')],
        'evidence_captions' => ['Remove this', 'Keep this'],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $original = $report->evidence;

    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'remove_evidence' => [hash('sha256', $original[0]['path'])], 'evidence_images' => [UploadedFile::fake()->image('replacement.png')],
    ])->assertSessionHasNoErrors();
    $evidence = $report->fresh()->evidence;
    expect($evidence)->toHaveCount(2)
        ->and($evidence[0]['original_filename'])->toBe('second.png')
        ->and($evidence[1]['original_filename'])->toBe('replacement.png');
    Storage::disk('local')->assertMissing($original[0]['path']);
    Storage::disk('local')->assertExists($original[1]['path']);
});

test('image removal rejects submitted reports and never selects another college report', function () {
    Storage::fake('local');
    $path = 'college-quarterly-reports/retained.png';
    Storage::disk('local')->put($path, 'image');
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'submitted_at' => now(), 'evidence' => [['path' => $path, 'caption' => '', 'mime_type' => 'image/png', 'original_filename' => 'retained.png']],
    ]);
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput, 'remove_evidence' => [hash('sha256', $path)]])
        ->assertUnprocessable()->assertJsonValidationErrors('intent');
    Storage::disk('local')->assertExists($path);

    $other = User::factory()->create(['college' => User::COLLEGES['CTE']]);
    $other->assignRole('research_coordinator');
    $this->actingAs($other)->postJson(route('research_coordinator.reports.store'), [...$this->reportInput,
        'report_id' => $report->id, 'college' => $report->college, 'remove_evidence' => [hash('sha256', $path)],
    ])->assertUnprocessable()->assertJsonValidationErrors('remove_evidence');
    Storage::disk('local')->assertExists($path);
    expect(CollegeQuarterlyReport::count())->toBe(1)
        ->and($report->fresh()->evidence)->toHaveCount(1);
});

test('a stale photo removal cannot delete another photo after the list changes', function () {
    Storage::fake('local');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $original = $report->evidence;
    $removal = [...$this->reportInput, 'remove_evidence' => [hash('sha256', $original[0]['path'])]];

    $this->post(route('research_coordinator.reports.store'), $removal)->assertSessionHasNoErrors();
    $this->postJson(route('research_coordinator.reports.store'), $removal)
        ->assertUnprocessable()->assertJsonValidationErrors('remove_evidence');

    expect($report->fresh()->evidence)->toHaveCount(1)
        ->and($report->fresh()->evidence[0]['path'])->toBe($original[1]['path']);
    Storage::disk('local')->assertExists($original[1]['path']);
});
