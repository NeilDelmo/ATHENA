<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_secretary'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->researcher = User::factory()->create(['college' => 'CICS']);
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'title' => 'Live project status', 'user_id' => $this->researcher->id,
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 12],
    ]);
    $this->report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => '2026-03-31', 'reporting_year' => 2026, 'reporting_quarter' => 1,
        'version_number' => 1, 'progress_percentage' => 25, 'accomplishments' => 'PRIVATE REPORT CONTENT',
        'review_stage' => 'secretary',
    ]);
    $this->url = route('topics.live-status', $this->topic);
});

test('live topic status is private and follows current workspace project access', function () {
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $state = $this->getJson($this->url)->assertSuccessful()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('workspace', User::WORKSPACE_FACULTY_RESEARCHER)
        ->assertJsonPath('proposal.label', 'Project monitoring')->json();
    expect(json_encode($state))->not->toContain('PRIVATE REPORT CONTENT', 'accomplishments', 'work_plan');
    $outsider = User::factory()->create(['college' => 'CAS']);
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)->getJson($this->url)->assertForbidden();
    $this->topic->collaborators()->create(['user_id' => $outsider->id, 'name' => $outsider->name, 'email' => $outsider->email, 'accepted_at' => now()]);
    $this->getJson($this->url)->assertSuccessful();
    $secretary = User::factory()->create(['college' => 'CAS']);
    $secretary->assignRole('research_secretary');
    $this->actingAs($secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])->getJson($this->url)->assertForbidden();
    $secretary->update(['college' => 'CICS']);
    $this->getJson($this->url)->assertSuccessful();
});

test('live report status hides unforwarded reports from the head and invalidates obsolete actions', function () {
    $key = 'monitoring:'.$this->report->id;
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    expect($this->getJson($this->url)->assertSuccessful()->json('reports'))->toBeEmpty();
    $this->report->update(['review_stage' => 'head']);
    $current = $this->getJson($this->url)->assertSuccessful()->json('reports.'.$key);
    expect($current['current'])->toBeTrue()->and($current['label'])->toBe('Awaiting Research Head review')
        ->and($current['actions'][0]['label'])->toBe('Review Monitoring Tool');
    $this->report->update(['review_status' => 'reviewed']);
    $reviewed = $this->getJson($this->url)->assertSuccessful()->json('reports.'.$key);
    expect($reviewed['scope'])->not->toBe($current['scope'])->and($reviewed['actions'])->toBeEmpty();
    $this->report->update(['review_status' => 'revision_requested']);
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $correction = $this->getJson($this->url)->assertSuccessful()->json('reports.'.$key);
    expect($correction['actions'][0]['url'])->toBe(route('project-progress.create', ['topic' => $this->topic, 'revise_monitoring_report' => $this->report->id]));
    $this->topic->update(['project_status' => 'completed']);
    expect($this->getJson($this->url)->assertSuccessful()->json('reports.'.$key.'.actions'))->toBeEmpty();
});

test('live proposal workflow exposes the current action without returning proposal paper contents', function () {
    $this->topic->update(['status' => 'pending', 'notice_to_proceed_issued_at' => null]);
    $this->researcher->assignRole('faculty');
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $before = $this->getJson($this->url)->assertSuccessful()->json('proposal');
    $this->topic->update(['status' => 'revision_requested']);
    $after = $this->getJson($this->url)->assertSuccessful()->json('proposal');
    expect($after['scope'])->not->toBe($before['scope'])
        ->and($after['label'])->toBe('Research Head revision requested')
        ->and($after['actions'][0]['url'])->toBe(route('faculty.topics.revision', $this->topic));
    $this->topic->update(['status' => 'rejected']);
    expect($this->getJson($this->url)->assertOk()->json('proposal.class'))->toBe('rounded-full px-3 py-1.5 text-sm font-black bg-red-50 text-red-700');
});

test('project and report pages connect live statuses to their existing review forms', function () {
    $this->report->update(['review_stage' => 'head']);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('data-topic-live-status', false)
        ->assertSee('data-live-snapshot', false)->assertSee('data-live-quarter-kind="monitoring"', false)
        ->assertSee('data-live-report-key="monitoring:'.$this->report->id.'"', false);
    $report = ProjectNarrativeReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id, 'report_type' => 'progress',
        'reporting_date' => '2026-03-31', 'reporting_quarter' => 1, 'submission_date' => '2026-04-01',
        'researchers' => $this->researcher->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
        'budget' => 100, 'funding_agency' => 'University', 'accomplishment_summary' => 'Private narrative content',
        'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives', 'methodology' => 'Methods', 'results_discussion' => 'Results',
        'photos' => [],
        'review_stage' => 'head',
    ]);
    $this->get(route('project-narrative-reports.show', $report))->assertOk()
        ->assertSee('data-topic-live-status', false)->assertSee('data-live-report-key="progress:'.$report->id.'"', false)
        ->assertSee('data-live-report-style="plain"', false);
});

test('status polling reads report metadata with a fixed query count as report history grows', function () {
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->report->update(['review_stage' => 'head']);
    $this->getJson($this->url)->assertOk();
    $counts = [];
    foreach ([1, 12] as $size) {
        while ($this->topic->progressReports()->count() < $size) {
            $copy = $this->report->replicate();
            $copy->version_number = $this->topic->progressReports()->count() + 1;
            $copy->save();
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($this->url)->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $counts[] = $queries->count();
        expect($queries->where('query', '!=', '')->filter(fn ($query) => str_contains($query['query'], 'from `project_progress_reports`'))
            ->contains(fn ($query) => str_contains($query['query'], '`accomplishments`') || preg_match('/^select\s+\*/i', $query['query'])))->toBeFalse();
    }
    expect($counts[1])->toBe($counts[0]);
});

test('prepared report destinations open the existing review form', function (string $kind) {
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    if ($kind === 'monitoring') {
        $report = $this->report;
        $report->update(['submission_status' => 'prepared']);
        $expected = route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']);
    } else {
        $report = ProjectNarrativeReport::create([
            'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id, 'report_type' => $kind,
            'reporting_date' => '2026-03-31', 'reporting_quarter' => 1, 'submission_date' => '2026-04-01',
            'researchers' => $this->researcher->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
            'budget' => 100, 'funding_agency' => 'University', 'accomplishment_summary' => 'Prepared report',
            'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives',
            'methodology' => 'Methods', 'results_discussion' => 'Results', 'photos' => [],
            'review_stage' => 'secretary', 'submission_status' => 'prepared',
        ]);
        $expected = route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31', 'report_type' => $kind]);
    }
    $record = $this->getJson($this->url)->assertOk()->json('reports.'.$kind.':'.$report->id);
    expect($record['url'])->toBe($expected);
    $this->get($record['url'])->assertOk();
})->with(['monitoring', 'progress', 'terminal']);

test('the initial report snapshot matches polling when PDFs are prepared', function () {
    foreach (['progress', 'terminal'] as $kind) {
        ProjectNarrativeReport::create([
            'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id, 'report_type' => $kind,
            'reporting_date' => '2026-03-31', 'reporting_quarter' => 1, 'submission_date' => '2026-04-01',
            'researchers' => $this->researcher->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
            'budget' => 100, 'funding_agency' => 'University', 'accomplishment_summary' => 'Prepared report',
            'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives',
            'methodology' => 'Methods', 'results_discussion' => 'Results', 'photos' => [],
            'review_stage' => 'secretary', 'submission_status' => 'prepared',
        ]);
    }
    $this->report->update(['submission_status' => 'prepared']);
    foreach ([[$this->researcher, User::WORKSPACE_FACULTY_RESEARCHER], [$this->head, User::WORKSPACE_RESEARCH_HEAD]] as [$user, $workspace]) {
        $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
        $page = $this->get(route('topics.show', $this->topic))->assertOk();
        preg_match('/data-live-snapshot="([^"]+)"/', $page->getContent(), $match);
        $snapshot = json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
        $state = $this->getJson($this->url)->assertOk()->json();
        expect($snapshot['proposal'])->toBe($state['proposal']['scope'])
            ->and($snapshot['reports'])->toEqual(collect($state['reports'])->map(fn (array $record): string => $record['scope'])->all());
    }
});
