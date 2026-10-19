<?php

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 1));
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->other = User::factory()->create();
    $this->other->assignRole('faculty_researcher');
    $this->topic = TopicProposal::create([
        'title' => 'Recovery research project', 'user_id' => $this->researcher->id,
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_budget' => 50000,
        'estimated_duration_months' => 6, 'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 6],
    ]);
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->stateUrl = fn (string $kind, int $source = 0, ?string $date = null): string => route('project-report-drafts.edit-state', [
        'topic' => $this->topic, 'kind' => $kind, 'reportingDate' => $kind === 'terminal' ? 'terminal' : ($date ?? '2026-03-31'), 'source' => $source,
    ]);
    $this->saveUrl = fn (string $kind): string => route($kind === 'monitoring' ? 'project-progress.draft' : 'project-narrative-reports.draft', $this->topic);
    $this->payload = fn (string $kind, int $version = 0): array => [
        'draft_version' => $version, 'reporting_date' => '2026-03-31',
        ...($kind === 'monitoring' ? ['tracking_number' => 'Local draft'] : ['report_type' => $kind, 'introduction' => 'Local draft']),
    ];
    $this->report = function (string $kind, array $changes = []): ProjectProgressReport|ProjectNarrativeReport {
        $common = ['topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-03-31', 'reporting_quarter' => 1, 'version_number' => 1];
        if ($kind === 'monitoring') {
            return ProjectProgressReport::create([...$common, 'reporting_year' => 2026, 'progress_percentage' => 0, 'accomplishments' => 'Server work', ...$changes]);
        }

        return ProjectNarrativeReport::create([
            ...$common, 'report_type' => $kind, 'submission_date' => '2026-03-31', 'researchers' => $this->researcher->name,
            'implementation_start' => '2026-01-01', 'implementation_end' => '2026-06-30', 'budget' => 50000,
            'funding_agency' => 'University', 'accomplishment_summary' => 'Server work', 'accomplishments' => [],
            'introduction' => 'Server work', 'rationale' => 'Server rationale', 'objectives' => 'Server objective',
            'methodology' => 'Server method', 'results_discussion' => 'Server result', 'photos' => [], ...$changes,
        ]);
    };
});

test('report draft state is private authorized account scoped and carries no report content', function (string $kind) {
    $this->postJson(($this->saveUrl)($kind), ($this->payload)($kind))->assertSuccessful()->assertJsonPath('draft_version', 1);
    $state = $this->getJson(($this->stateUrl)($kind))->assertSuccessful()->assertJsonPath('version', 1)
        ->assertJsonPath('is_editable', true)->assertHeader('Cache-Control', 'no-store, private')->json();
    expect($state)->toHaveKeys(['version', 'scope', 'is_editable'])->toHaveCount(3)
        ->and($state['scope'])->toMatch('/^[a-f0-9]{64}$/');
    $this->actingAs($this->other)->getJson(($this->stateUrl)($kind))->assertForbidden();
    $this->topic->collaborators()->create(['user_id' => $this->other->id, 'name' => $this->other->name, 'email' => $this->other->email, 'accepted_at' => now()]);
    $this->getJson(($this->stateUrl)($kind))->assertSuccessful()->assertJsonPath('version', 0);
    $this->other->assignRole('research_head');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])->getJson(($this->stateUrl)($kind))->assertForbidden();
})->with(['monitoring', 'progress', 'terminal']);

test('stale report recovery cannot overwrite the current saved draft', function (string $kind) {
    $this->postJson(($this->saveUrl)($kind), ($this->payload)($kind))->assertSuccessful();
    $this->postJson(($this->saveUrl)($kind), [...($this->payload)($kind), 'introduction' => 'Stale recovery', 'tracking_number' => 'Stale recovery'])
        ->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    $draft = $kind === 'monitoring' ? ProjectMonitoringDraft::sole() : ProjectNarrativeReportDraft::sole();
    expect($draft->lock_version)->toBe(1)->and(json_encode($draft->source_data))->not->toContain('Stale recovery');
})->with(['monitoring', 'progress', 'terminal']);

test('a prepared submitted or completed report blocks recovery even at version zero', function (string $kind, string $lock) {
    $initial = $this->getJson(($this->stateUrl)($kind))->assertSuccessful()->assertJsonPath('is_editable', true)->json();
    if ($lock === 'completed') {
        $this->topic->update(['project_status' => 'completed']);
    } else {
        ($this->report)($kind, ['submission_status' => $lock]);
    }
    $this->getJson(($this->stateUrl)($kind))->assertSuccessful()->assertJsonPath('version', 0)->assertJsonPath('is_editable', false);
    $response = $this->postJson(($this->saveUrl)($kind), [...($this->payload)($kind), 'recovery_scope' => $initial['scope']]);
    if ($lock === 'completed') {
        $response->assertForbidden();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    }
    expect(ProjectMonitoringDraft::count())->toBe(0)->and(ProjectNarrativeReportDraft::count())->toBe(0);
})->with(['monitoring', 'progress', 'terminal'])->with(['prepared', 'submitted', 'completed']);

test('recovery scope rejects a newly opened correction cycle with the same numeric version', function (string $kind) {
    $initial = $this->getJson(($this->stateUrl)($kind))->assertSuccessful()->json();
    $report = ($this->report)($kind, ['review_status' => 'revision_requested']);
    $source = $kind === 'monitoring' ? $report->id : 0;
    $current = $this->getJson(($this->stateUrl)($kind, $source))->assertSuccessful()->assertJsonPath('version', 0)->assertJsonPath('is_editable', true)->json();
    expect($current['scope'])->not->toBe($initial['scope']);
    $payload = [...($this->payload)($kind), 'recovery_scope' => $initial['scope'], ...($source ? ['source_report_id' => $source] : [])];
    $this->postJson(($this->saveUrl)($kind), $payload)->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    $payload['recovery_scope'] = $current['scope'];
    $this->postJson(($this->saveUrl)($kind), $payload)->assertSuccessful();
})->with(['monitoring', 'progress', 'terminal']);

test('monitoring recovery stays within its requested source and reporting quarter', function () {
    $report = ($this->report)('monitoring', ['review_status' => 'revision_requested']);
    $this->getJson(($this->stateUrl)('monitoring', $report->id))->assertSuccessful()->assertJsonPath('is_editable', true);
    $this->getJson(($this->stateUrl)('monitoring', $report->id, '2026-06-30'))->assertSuccessful()->assertJsonPath('is_editable', false);
    $this->getJson(($this->stateUrl)('monitoring', $report->id + 1))->assertSuccessful()->assertJsonPath('is_editable', false);
    $this->getJson(($this->stateUrl)('monitoring', $report->id, '2026-02-31'))->assertUnprocessable();
});

test('removing a saved report draft changes its edit state and stale retry is rejected', function (string $kind) {
    $this->postJson(($this->saveUrl)($kind), ($this->payload)($kind))->assertSuccessful();
    $draft = $kind === 'monitoring' ? ProjectMonitoringDraft::sole() : ProjectNarrativeReportDraft::sole();
    $draft->delete();
    $this->getJson(($this->stateUrl)($kind))->assertSuccessful()->assertJsonPath('version', 0);
    $this->postJson(($this->saveUrl)($kind), ($this->payload)($kind, 1))->assertUnprocessable()->assertJsonValidationErrors('draft_version');
})->with(['monitoring', 'progress', 'terminal']);

test('each report editor renders an isolated recovery scope and warning', function (string $kind) {
    $url = $kind === 'monitoring'
        ? route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31'])
        : route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => $kind, 'reporting_date' => '2026-03-31']);
    $this->get($url)->assertSuccessful()->assertSee('data-report-version-monitor', false)->assertSee('data-state-scope', false)
        ->assertSee('data-proposal-conflict-recovery', false)->assertSee('name="recovery_scope"', false)->assertSee(($this->stateUrl)($kind), false);
})->with(['monitoring', 'progress', 'terminal']);
