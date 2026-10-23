<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportFacultyEvidence;
use App\Services\RmsQuarterlyReportData;
use Database\Seeders\QuarterlyReportWorkflowDemoSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    Storage::fake('local');
    Notification::fake();
    foreach (['faculty_researcher', 'research_coordinator', 'research_head', 'research_secretary'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->head = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->head->assignRole(['research_head', 'faculty_researcher']);
    $this->faculty = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->faculty->assignRole('faculty_researcher');
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->secretary = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->secretary->assignRole('research_secretary');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->andReturn("%PDF-1.4\nTest conversion");
});

test('sample faculty evidence reaches the submitted college report and three RMS output categories', function () {
    $this->seed(QuarterlyReportWorkflowDemoSeeder::class);
    $projects = TopicProposal::orderBy('id')->get();
    expect($projects)->toHaveCount(2)
        ->and($projects->pluck('user_id')->all())->toBe([$this->head->id, $this->faculty->id])
        ->and(ProjectNarrativeReport::count())->toBe(2);
    foreach ($projects as $project) {
        $monitoring = $project->progressReports()->where('reporting_quarter', 3)->sole();
        expect($monitoring->isAvailableToResearchHead())->toBeTrue()
            ->and($monitoring->secretary_reviewed_by)->toBe($this->secretary->id)
            ->and($monitoring->reviewed_by)->toBe($this->head->id);
        Storage::disk('local')->assertExists($monitoring->official_pdf_path);
        $this->actingAs($project->user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
            ->get(route('topics.show', $project))->assertOk()->assertSeeText($project->title);
    }
    $report = CollegeQuarterlyReport::where('reporting_quarter', 3)->sole();
    expect($report->submitted_at)->not->toBeNull()
        ->and($report->snapshot['research'])->toHaveCount(2)
        ->and($report->submissions()->sole()->snapshot)->toBe($report->snapshot);
    $frozen = collect($report->evidence)->where('source', 'faculty_report');
    expect($frozen)->toHaveCount(2);
    foreach ($frozen as $file) {
        Storage::disk('local')->assertExists($file['path']);
        expect($file['path'])->toStartWith('college-quarterly-reports/'.$report->id.'/faculty-evidence/');
    }
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertSeeText('DEMO');
    $this->get(route('research_coordinator.reports.print', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertSeeText('DEMO');
    $this->get(route('research_coordinator.reports.download', ['year' => 2026, 'quarter' => 3]))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $draft = RmsQuarterlyReport::sole();
    $data = app(RmsQuarterlyReportData::class)->build(2026, 3, $draft->row_details);
    foreach (['presentations', 'publications', 'ip_assets'] as $section) {
        expect($data[$section])->toHaveCount(1)
            ->and($data[$section][0]['missing'])->toBe([])
            ->and($data[$section][0]['report_id'])->toBe($report->id);
    }
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertSeeText('DEMO');
    foreach (['presentations', 'publications', 'ip_assets'] as $section) {
        $this->get($data[$section][0]['values']['evidence_url'])->assertOk()->assertHeader('Content-Type', 'image/png');
    }
    $this->get(route('research_head.rms-quarterly-reports.download', ['year' => 2026, 'quarter' => 3]))
        ->assertRedirect(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertSessionHasErrors('college_reports');
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026]))->assertOk()
        ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 1);
    expect(CollegeQuarterlyReport::where('reporting_quarter', 4)->sole()->submitted_at)->toBeNull()
        ->and(ProjectProgressReport::where('reporting_quarter', 4)->where('submission_status', 'prepared')->count())->toBe(2)
        ->and(ProjectNarrativeReport::where('review_stage', 'secretary')->where('submission_status', 'submitted')->count())->toBe(1);
    expect(app(CollegeQuarterlyReportFacultyEvidence::class)->forQuarter($this->coordinator, 2026, 4))->toBe([]);
    $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('topics.show', $projects[1]))->assertOk()->assertSeeText('Awaiting Research Office Secretary review');
    Notification::assertNothingSent();
});

test('rerunning the demonstration preserves user edits files accounts and existing work', function () {
    $this->seed(QuarterlyReportWorkflowDemoSeeder::class);
    $draft = CollegeQuarterlyReport::where('reporting_quarter', 4)->sole();
    $draft->update(['other_accomplishments' => 'My edited draft.']);
    $rms = RmsQuarterlyReport::sole();
    $rms->update(['campus' => 'My edited campus']);
    $before = [TopicProposal::count(), ProjectProgressReport::count(), ProjectNarrativeReport::count(), CollegeQuarterlyReport::count()];
    $files = collect(Storage::disk('local')->allFiles())->mapWithKeys(fn (string $path): array => [$path => hash('sha256', Storage::disk('local')->get($path))]);
    $this->seed(QuarterlyReportWorkflowDemoSeeder::class);
    expect([TopicProposal::count(), ProjectProgressReport::count(), ProjectNarrativeReport::count(), CollegeQuarterlyReport::count()])->toBe($before)
        ->and($draft->fresh()->other_accomplishments)->toBe('My edited draft.')
        ->and($rms->fresh()->campus)->toBe('My edited campus')
        ->and(User::count())->toBe(4);
    foreach ($files as $path => $checksum) {
        expect(hash('sha256', Storage::disk('local')->get($path)))->toBe($checksum);
    }
});

test('demonstration refuses occupied reporting periods without changing records', function () {
    $report = CollegeQuarterlyReport::factory()->create(['college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'prepared_by' => $this->coordinator->id]);
    $before = $report->refresh()->getAttributes();
    expect(fn () => $this->seed(QuarterlyReportWorkflowDemoSeeder::class))->toThrow(RuntimeException::class, 'already contains')
        ->and($report->fresh()->getAttributes())->toBe($before)
        ->and(TopicProposal::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('demonstration rolls back database records and removes its files if PDF generation fails', function () {
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->andThrow(new RuntimeException('Converter unavailable'));
    expect(fn () => $this->seed(QuarterlyReportWorkflowDemoSeeder::class))->toThrow(RuntimeException::class, 'Converter unavailable')
        ->and(TopicProposal::count())->toBe(0)
        ->and(CollegeQuarterlyReport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('demonstration cannot run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app()->call([app(QuarterlyReportWorkflowDemoSeeder::class), 'run']))->toThrow(RuntimeException::class, 'local')
            ->and(TopicProposal::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
