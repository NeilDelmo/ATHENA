<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportData;
use App\Services\ProjectDocumentLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
    Storage::fake('local');
    foreach (['research_coordinator', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->input = fn (): array => ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'save', 'form_complete' => '1', 'draft_token' => (CollegeQuarterlyReport::where('college', $this->coordinator->college)->where('reporting_year', 2026)->where('reporting_quarter', 3)->first() ?? new CollegeQuarterlyReport)->draftToken()];
    $this->row = ['id' => (string) Str::uuid(), 'event' => 'Faculty forum', 'research_title' => 'External research', 'date' => '2026-08-14', 'place' => 'Lipa', 'personnel' => 'Ana'];
});

test('manual activities persist and supply formatted presentation and publication rows', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(),
        'presentations' => [$this->row], 'publications' => [array_diff_key($this->row, ['place' => true])],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    expect($report->presentations)->toHaveCount(1)
        ->and($report->activityRows('presentations'))->toBe([['Faculty forum', 'External research', 'August 14, 2026', 'Lipa', 'Ana']])
        ->and($report->activityRows('publications'))->toBe([['Faculty forum', 'External research', 'August 14, 2026', 'Ana']]);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertViewHas('reportData', fn (array $data): bool => $data['presentations'] === $report->activityRows('presentations'));
});

test('final submission requires proof for every manual activity while drafts accept missing proof', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row]])->assertSessionHasNoErrors();
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => [$this->row]])
        ->assertUnprocessable()->assertJsonValidationErrors('row_evidence.presentations.'.$this->row['id']);
    expect(CollegeQuarterlyReport::sole()->submitted_at)->toBeNull();
});

test('unregistered research evidence creates a private title folder and freezes its row metadata', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('proof.png')]]],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $proof = $report->evidence[0];
    expect($proof['section'])->toBe('presentations')->and($proof['row_id'])->toBe($this->row['id'])
        ->and($proof['folder'])->toBe('External research')->and($proof['path'])->toContain('/2026/q3/external-research/presentations/'.$this->row['id'].'/')
        ->and($report->snapshot['manual_presentations'][0]['id'])->toBe($this->row['id'])
        ->and($report->evidenceLabel($proof))->toBe('a. Presentation · Row 1 · External research');
    Storage::disk('local')->assertExists($proof['path']);
    $this->get(route('research_coordinator.reports.evidence', [$report, 0]))->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('row evidence follows its UUID after rows are reordered and removed', function () {
    $other = [...$this->row, 'id' => (string) Str::uuid(), 'research_title' => 'Second research'];
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row, $other],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('first.png')], $other['id'] => [UploadedFile::fake()->image('second.png')]]],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $paths = array_column($report->evidence, 'path');
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => [$other]])->assertSessionHasNoErrors();
    $report->refresh();
    expect($report->evidence)->toHaveCount(1)->and($report->evidence[0]['original_filename'])->toBe('second.png')
        ->and($report->evidenceLabel($report->evidence[0]))->toBe('a. Presentation · Row 1 · Second research');
    Storage::disk('local')->assertExists($paths);
});

test('linked project evidence uses the existing folder and safe image preview without requiring a notice', function () {
    $owner = User::factory()->create(['college' => $this->coordinator->college]);
    $project = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Approved research', 'status' => 'approved']);
    $row = [...$this->row, 'project_id' => $project->id];
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$row],
        'row_evidence' => ['presentations' => [$row['id'] => [UploadedFile::fake()->image('project-proof.png')]]],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $document = ProjectDocument::sole();
    expect($report->evidence[0]['document_id'])->toBe($document->id)->and($document->topic_id)->toBe($project->id)
        ->and($document->category)->toBe(ProjectDocument::CATEGORY_ACCOMPLISHMENT_EVIDENCE)
        ->and($document->file_path)->toContain('/accomplishment-evidence/2026/q3/presentations/'.$row['id'].'/');
    $this->get(route('topics.documents.view', [$project, $document]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $library = app(ProjectDocumentLibrary::class)->build($project->fresh(), $this->coordinator);
    expect($library['documents']->firstWhere('key', 'project-document-'.$document->id)['preview_kind'])->toBe('image');
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertViewHas('evidenceProjects', fn ($projects): bool => $projects->pluck('id')->all() === [$project->id]);
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'remove_evidence' => [hash('sha256', $document->file_path)]])->assertSessionHasNoErrors();
    Storage::disk('local')->assertExists($document->file_path);
});

test('project evidence links reject other colleges and inaccessible project stages', function (string $college, string $status) {
    $owner = User::factory()->create(['college' => $college]);
    $project = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Private research', 'status' => $status]);
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [[...$this->row, 'project_id' => $project->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('presentations.0.project_id');
    expect(CollegeQuarterlyReport::count())->toBe(0);
})->with(['other college' => [User::COLLEGES['CTE'], 'approved'], 'unapproved' => [User::COLLEGES['CICS'], 'pending']]);

test('manual activity dates and image identity are validated before creating a report', function () {
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'publications' => [[...array_diff_key($this->row, ['place' => true]), 'date' => '2026-10-01']]])
        ->assertUnprocessable()->assertJsonValidationErrors('publications.0.date');
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [(string) Str::uuid() => [UploadedFile::fake()->image('orphan.png')]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('row_evidence.presentations');
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->create('proof.pdf', 5, 'application/pdf')]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('row_evidence.presentations.'.$this->row['id'].'.0');
    expect(CollegeQuarterlyReport::count())->toBe(0);
});

test('proof requirements do not trust injected evidence metadata or missing retained files', function () {
    $report = CollegeQuarterlyReport::factory()->create(['college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'evidence' => [['path' => 'missing.png', 'mime_type' => 'image/png', 'section' => 'presentations', 'row_id' => $this->row['id']]],
    ]);
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => [$this->row],
        'evidence' => [['path' => 'fake.png', 'section' => 'presentations', 'row_id' => $this->row['id']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('row_evidence.presentations.'.$this->row['id']);
    expect($report->fresh()->submitted_at)->toBeNull();
});

test('row proof may exceed ten images overall but is limited to five per row', function () {
    $rows = collect(range(1, 3))->map(fn (int $number): array => [...$this->row, 'id' => (string) Str::uuid(), 'event' => 'Event '.$number])->all();
    $files = collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => array_map(fn (int $number) => UploadedFile::fake()->image('proof-'.$number.'.png'), range(1, 4))])->all();
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => $rows, 'row_evidence' => ['presentations' => $files]])->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->evidence)->toHaveCount(12);
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'reporting_quarter' => 2,
        'presentations' => [[...$this->row, 'date' => '2026-05-01']],
        'row_evidence' => ['presentations' => [$this->row['id'] => array_map(fn (int $number) => UploadedFile::fake()->image('proof-'.$number.'.png'), range(1, 6))]],
    ])->assertUnprocessable()->assertJsonValidationErrors('row_evidence.presentations.'.$this->row['id']);
});

test('later report failures clean linked project evidence along with the rolled back record', function () {
    $owner = User::factory()->create(['college' => $this->coordinator->college]);
    $project = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Approved research', 'status' => 'approved']);
    $this->mock(CollegeQuarterlyReportData::class)->shouldReceive('build')->once()->andThrow(new RuntimeException('Source unavailable'));
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [[...$this->row, 'project_id' => $project->id]],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('rollback.png')]]],
    ])->assertServerError();
    expect(ProjectDocument::count())->toBe(0)->and(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('project-documents'))->toBe([]);
});

test('long research titles keep readable folder metadata without exceeding directory name limits', function () {
    $title = str_repeat('Research ', 100);
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [[...$this->row, 'research_title' => $title]],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('long-title.png')]]],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $proof = CollegeQuarterlyReport::sole()->evidence[0];
    expect($proof['folder'])->toBe(trim($title));
    Storage::disk('local')->assertExists($proof['path']);
});

test('legacy activity clients receive stable saved row identities', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(),
        'seminars' => [['event' => 'Workshop', 'date' => '2026-08-14', 'place' => 'Lipa', 'personnel' => 'Ana']],
    ])->assertSessionHasNoErrors();
    $row = CollegeQuarterlyReport::sole()->seminars[0];
    expect(Str::isUuid($row['id']))->toBeTrue();
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'seminars' => [$row],
        'row_evidence' => ['seminars' => [$row['id'] => [UploadedFile::fake()->image('workshop.png')]]],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    expect($report->evidenceLabel($report->evidence[0]))->toBe('c. Seminar/Training · Row 1 · Workshop');
});

test('retained row images count toward the five image limit', function () {
    $files = array_map(fn (int $number) => UploadedFile::fake()->image('proof-'.$number.'.png'), range(1, 5));
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [$this->row['id'] => $files]],
    ])->assertSessionHasNoErrors();
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('sixth.png')]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('row_evidence.presentations.'.$this->row['id']);
    expect(CollegeQuarterlyReport::sole()->evidence)->toHaveCount(5);
});

test('selecting a project after saving proof files the retained images into that project', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('retained.png')]]],
    ])->assertSessionHasNoErrors();
    $originalPath = CollegeQuarterlyReport::sole()->evidence[0]['path'];
    $owner = User::factory()->create(['college' => $this->coordinator->college]);
    $project = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Newly selected project', 'status' => 'approved']);
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => [[...$this->row, 'project_id' => $project->id]]])
        ->assertSessionHasNoErrors();
    $proof = CollegeQuarterlyReport::sole()->evidence[0];
    expect($proof['project_id'] ?? null)->toBe($project->id)->and($proof['path'])->toStartWith('project-documents/'.$project->id.'/accomplishment-evidence/')
        ->and(ProjectDocument::sole()->file_path)->toBe($proof['path']);
    Storage::disk('local')->assertExists([$originalPath, $proof['path']]);
});

test('external faculty research saves prints exports and submits without an Athena project or activity proof', function () {
    $row = ['id' => (string) Str::uuid(), 'title' => 'Unfunded community study', 'duration' => 'January–December 2026', 'personnel' => 'Ana', 'status' => 'Ongoing'];
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'manual_research' => [$row]])->assertRedirect()->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    expect($report->manual_research)->toEqual([$row])->and($report->activityRows('manual_research'))
        ->toBe([['Unfunded community study', 'January–December 2026', 'Ana', 'Ongoing']]);
    $this->get(route('research_coordinator.reports.print', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertSeeText('Unfunded community study');
    $this->get(route('research_coordinator.reports.download', ['year' => 2026, 'quarter' => 3]))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'manual_research' => [$row]])->assertSessionHasNoErrors();
    $report->refresh();
    expect($report->submitted_at)->not->toBeNull()->and($report->snapshot['manual_research'])->toEqual([$row])
        ->and($report->snapshot['research'])->toBe([['Unfunded community study', 'January–December 2026', 'Ana', 'Ongoing']])
        ->and(TopicProposal::count())->toBe(0)->and($report->evidence)->toBe([]);
});

test('automatic and manually entered faculty research coexist without duplicating editable manual rows', function () {
    $owner = User::factory()->create(['college' => $this->coordinator->college]);
    TopicProposal::create(['user_id' => $owner->id, 'title' => 'Monitored project', 'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 12,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31'],
    ]);
    $row = ['title' => 'Independent study', 'duration' => '2026', 'personnel' => 'Ana', 'status' => 'Completed'];
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'manual_research' => [$row]])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    expect(Str::isUuid($report->manual_research[0]['id']))->toBeTrue()->and($report->snapshot['research'])->toHaveCount(2)->and(TopicProposal::count())->toBe(1);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk()
        ->assertViewHas('automaticResearch', fn (array $rows): bool => count($rows) === 1 && $rows[0][0] === 'Monitored project')
        ->assertViewHas('reportData', fn (array $data): bool => count($data['research']) === 2 && $data['research'][1][0] === 'Independent study');
});

test('manual faculty research requires complete bounded fields and rejects project payloads', function (array $row, string $field) {
    $this->postJson(route('research_coordinator.reports.store'), [...($this->input)(), 'manual_research' => [$row]])
        ->assertUnprocessable()->assertJsonValidationErrors('manual_research.0'.($field === '' ? '' : '.'.$field));
    expect(CollegeQuarterlyReport::count())->toBe(0);
})->with([
    'missing status' => [['title' => 'Research', 'duration' => '2026', 'personnel' => 'Ana'], 'status'],
    'long duration' => [['title' => 'Research', 'duration' => str_repeat('x', 256), 'personnel' => 'Ana', 'status' => 'Ongoing'], 'duration'],
    'unexpected project' => [['title' => 'Research', 'duration' => '2026', 'personnel' => 'Ana', 'status' => 'Ongoing', 'project_id' => 1], ''],
]);

test('malformed activity fields return readable validation feedback after redirect', function (string $section, mixed $rows) {
    $this->from(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))->followingRedirects()
        ->post(route('research_coordinator.reports.store'), [...($this->input)(), $section => $rows])
        ->assertOk()->assertSeeText('Check the report before saving.');
    expect(CollegeQuarterlyReport::count())->toBe(0);
})->with([
    'scalar presentations' => ['presentations', 'oops'],
    'scalar presentation row' => ['presentations', ['oops']],
    'scalar manual research' => ['manual_research', 'oops'],
    'scalar manual research row' => ['manual_research', ['oops']],
]);

test('clearing a project association refiles retained proof under the report research title', function () {
    $owner = User::factory()->create(['college' => $this->coordinator->college]);
    $project = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Project folder', 'status' => 'approved']);
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [[...$this->row, 'project_id' => $project->id]],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('retained.png')]]],
    ])->assertSessionHasNoErrors();
    $originalPath = ProjectDocument::sole()->file_path;
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'intent' => 'submit', 'presentations' => [$this->row]])->assertRedirect()->assertSessionHasNoErrors();
    $proof = CollegeQuarterlyReport::sole()->evidence[0];
    expect($proof['path'])->toStartWith('college-quarterly-reports/')->toContain('/external-research/presentations/')
        ->and($proof)->not->toHaveKeys(['project_id', 'document_id'])->and(ProjectDocument::count())->toBe(1);
    Storage::disk('local')->assertExists([$originalPath, $proof['path']]);
});

test('renaming unregistered research refiles retained proof under the updated title', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [$this->row],
        'row_evidence' => ['presentations' => [$this->row['id'] => [UploadedFile::fake()->image('retained.png')]]],
    ])->assertSessionHasNoErrors();
    $originalPath = CollegeQuarterlyReport::sole()->evidence[0]['path'];
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'presentations' => [[...$this->row, 'research_title' => 'Corrected title']]])
        ->assertRedirect()->assertSessionHasNoErrors();
    $proof = CollegeQuarterlyReport::sole()->evidence[0];
    expect($proof['folder'])->toBe('Corrected title')->and($proof['path'])->toContain('/corrected-title/presentations/');
    Storage::disk('local')->assertExists([$originalPath, $proof['path']]);
});

test('legacy appendix removal works without querying a submission history table that is not installed', function () {
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'evidence_images' => [UploadedFile::fake()->image('legacy.png')]])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $path = $report->evidence[0]['path'];
    Schema::partialMock()->shouldReceive('hasTable')->with('college_quarterly_report_submissions')->andReturnFalse();
    $historyQueries = [];
    DB::listen(function ($query) use (&$historyQueries): void {
        if (str_contains($query->sql, 'college_quarterly_report_submissions')) {
            $historyQueries[] = $query->sql;
        }
    });
    $this->post(route('research_coordinator.reports.store'), [...($this->input)(), 'remove_evidence' => [hash('sha256', $path)]])->assertSessionHasNoErrors();
    expect($historyQueries)->toBe([])->and($report->fresh()->evidence)->toBe([]);
    Storage::disk('local')->assertMissing($path);
});
