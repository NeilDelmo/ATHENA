<?php

use App\Actions\NotifyOverdueWorkPlanActivities;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    config(['broadcasting.default' => 'null']);
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->leader = User::factory()->create();
    $this->leader->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'user_id' => $this->leader->id, 'title' => 'Work Plan deadline test',
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_duration_months' => 9,
        'notice_to_proceed_issued_at' => '2026-01-15',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 9],
    ]);
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->leader->id, 'version_number' => 1, 'submission_type' => 'initial',
        'title' => $this->topic->title, 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100,
    ]);
    $this->plan = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0,
        'file_path' => 'work-plan.pdf', 'original_filename' => 'work-plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'source_data' => ['total_duration_months' => 9, 'entries' => [
            ['objective' => 'Establish the baseline', 'activity' => 'Conduct interviews', 'expected_output' => 'Baseline dataset', 'months' => [1]],
            ['objective' => 'Build the prototype', 'activity' => 'Develop the prototype', 'expected_output' => 'Working prototype', 'months' => [1, 2, 3]],
            ['objective' => 'Evaluate outcomes', 'activity' => 'Validate the results', 'expected_output' => 'Evaluation report', 'months' => [4, 5]],
        ]],
    ]);
    $this->workPlan = app(ApprovedWorkPlanMonitoringService::class);
    $this->makeReport = fn (array $rows, array $attributes = []) => $this->topic->progressReports()->create([
        'submitted_by' => $this->leader->id, 'reporting_date' => '2026-04-14', 'period_start' => '2026-01-15',
        'version_number' => 1, 'progress_percentage' => 0, 'accomplishments' => 'Recorded progress',
        'work_plan' => $rows, ...$attributes,
    ]);
});

afterEach(function () {
    $this->travelBack();
});

test('alerts begin the day after the final scheduled project month and respect the notice start', function () {
    $this->travelTo('2026-02-14 23:59:59');
    expect($this->workPlan->overdueActivities($this->topic))->toBeEmpty();
    $this->travelTo('2026-02-15 00:00:00');
    $alerts = $this->workPlan->overdueActivities($this->topic);
    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['objective'])->toBe('Establish the baseline')
        ->and($alerts[0]['target_completion_date'])->toBe('2026-02-14')
        ->and($alerts[0]['progress_recorded'])->toBeFalse();
    $this->travelTo('2026-04-15');
    expect($this->workPlan->overdueActivities($this->topic))->toHaveCount(2);
});

test('activity completion is measured against its approved weight instead of 100 project percentage points', function () {
    $this->travelTo('2026-04-15');
    $report = ($this->makeReport)([
        ['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67'],
        ['source_work_plan_index' => 1, 'accomplished_percentage' => 25],
    ]);
    $alerts = $this->workPlan->overdueActivities($this->topic);
    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['source_work_plan_index'])->toBe(1)
        ->and($alerts[0]['completion_percentage'])->toBe(50.0);
    $report->update(['work_plan' => [
        ['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67'],
        ['source_work_plan_index' => 1, 'accomplished_percentage' => 50],
    ]]);
    expect($this->workPlan->overdueActivities($this->topic->fresh()))->toBeEmpty();
});

test('future and prepared reports and saved drafts cannot clear official overdue alerts', function () {
    $this->travelTo('2026-02-15');
    ($this->makeReport)([['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67']]);
    ($this->makeReport)([['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67']], [
        'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_PREPARED, 'reporting_date' => '2026-02-14',
    ]);
    ProjectMonitoringDraft::create([
        'topic_id' => $this->topic->id, 'user_id' => $this->leader->id, 'source_key' => 'new',
        'source_data' => ['reporting_date' => '2026-07-14', 'work_plan' => [['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67']]],
        'lock_version' => 1,
    ]);
    expect($this->workPlan->overdueActivities($this->topic))->toHaveCount(1);
});

test('the latest submitted correction replaces older progress for a quarter', function () {
    $this->travelTo('2026-04-15');
    ($this->makeReport)([['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67']]);
    $latest = ($this->makeReport)([['source_work_plan_index' => 0, 'accomplished_percentage' => 8]], ['version_number' => 2]);
    $alerts = $this->workPlan->overdueActivities($this->topic);
    expect(collect($alerts)->firstWhere('source_work_plan_index', 0)['completion_percentage'])->toBeLessThan(50);
    $latest->update(['work_plan' => []]);
    $alerts = $this->workPlan->overdueActivities($this->topic->fresh());
    expect(collect($alerts)->firstWhere('source_work_plan_index', 0)['progress_recorded'])->toBeFalse();
});

test('a later submitted report can resolve an earlier overdue activity and legacy rows match their activity', function () {
    $this->travelTo('2026-07-15');
    ($this->makeReport)([['source_work_plan_index' => 0, 'accomplished_percentage' => 8]]);
    ($this->makeReport)([['activity' => 'Conduct interviews', 'accomplished_percentage' => '16.67']], [
        'reporting_date' => '2026-07-14', 'period_start' => '2026-04-15',
    ]);
    expect(collect($this->workPlan->overdueActivities($this->topic))->pluck('source_work_plan_index')->all())->toBe([1, 2]);
});

test('altered report targets cannot postpone the approved deadline', function () {
    $this->travelTo('2026-04-15');
    ($this->makeReport)([['source_work_plan_index' => 0, 'target_completion_date' => '2030-01-01', 'percent_weight' => 1, 'accomplished_percentage' => 8]]);
    $alert = $this->workPlan->overdueActivities($this->topic)[0];
    expect($alert['target_completion_date'])->toBe('2026-02-14')
        ->and($alert['percent_weight'])->toBe('16.67');
});

test('completed or unreleased projects do not trigger alerts', function (array $attributes) {
    $this->travelTo('2026-07-15');
    $this->topic->update($attributes);
    expect($this->workPlan->overdueActivities($this->topic))->toBeEmpty()
        ->and(app(NotifyOverdueWorkPlanActivities::class)->handle($this->topic))->toBe(0)
        ->and($this->leader->notifications()->count())->toBe(0)
        ->and($this->head->notifications()->count())->toBe(0);
})->with([
    'completed' => [['project_status' => 'completed']],
    'not approved' => [['status' => 'pending']],
    'notice not issued' => [['notice_to_proceed_issued_at' => null]],
]);

test('monitoring alerts are visible to the faculty and research head with the approved objective and deadline', function (string $viewer) {
    $this->travelTo('2026-02-15');
    $response = $this->actingAs($this->{$viewer})->get(route('topics.show', $this->topic))->assertOk();
    $response->assertSee('Work Plan targets need attention')->assertSee('Establish the baseline')
        ->assertSee('Due Feb 14, 2026')->assertSee('No submitted progress yet');
    expect($response->viewData('overdueWorkPlanActivities'))->toHaveCount(1);
})->with(['leader', 'head']);

test('the monitoring editor shows official overdue alerts and a live per-activity warning', function () {
    $this->travelTo('2026-02-15');
    $response = $this->actingAs($this->leader)->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-04-14']))
        ->assertOk()->assertSee('data-monitoring-work-plan-alert', false)
        ->assertSee('data-monitoring-activity-overdue', false)->assertSee('Past target date');
    $fixtureDirectory = getenv('ATHENA_MONITORING_ALERT_BROWSER_FIXTURE_DIRECTORY');
    if (is_string($fixtureDirectory) && $fixtureDirectory !== '') {
        $document = new DOMDocument;
        @$document->loadHTML(preg_replace('/(?<=\s)@([a-z][\w.-]*)\s*=/', 'x-on:$1=', $response->getContent()));
        $workspace = (new DOMXPath($document))->query('//*[@data-monitoring-workspace]')->item(0);
        File::ensureDirectoryExists($fixtureDirectory);
        File::put($fixtureDirectory.'/monitoring-objective-alert.html', $document->saveHTML($workspace));
    }
});

test('overdue notifications reach the project leader and Research Head once even after being read', function () {
    $this->travelTo('2026-02-15');
    $this->artisan('projects:notify-overdue-objectives')->expectsOutput('Sent 2 overdue Work Plan notification(s).')->assertSuccessful();
    $notice = $this->leader->notifications()->sole();
    $headNotice = $this->head->notifications()->sole();
    expect($notice->data['title'])->toBe('Work Plan target overdue')
        ->and($notice->data['level'])->toBe('warning')
        ->and($notice->data['message'])->toContain('Establish the baseline', 'Feb 14, 2026')
        ->and($notice->data['url'])->toBe(route('topics.show', $this->topic).'#project-monitoring')
        ->and($notice->data['workspace'])->toBe(User::WORKSPACE_FACULTY_RESEARCHER)
        ->and($notice->data['sidebar_area'])->toBe(ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS)
        ->and($headNotice->data['title'])->toBe('Faculty Work Plan target overdue')
        ->and($headNotice->data['level'])->toBe('warning')
        ->and($headNotice->data['message'])->toContain(
            $this->topic->title, $this->leader->name, 'Establish the baseline', 'Conduct interviews',
            'Feb 14, 2026', 'No submitted progress has been recorded.',
        )
        ->and($headNotice->data['url'])->toBe(route('topics.show', $this->topic).'#project-monitoring')
        ->and($headNotice->data['workspace'])->toBe(User::WORKSPACE_RESEARCH_HEAD)
        ->and($headNotice->data['sidebar_area'])->toBe(ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING)
        ->and($headNotice->data['action_data']['work_plan_overdue_key'])->toBe($notice->data['action_data']['work_plan_overdue_key']);
    $notice->markAsRead();
    $headNotice->markAsRead();
    $this->artisan('projects:notify-overdue-objectives')->expectsOutput('Sent 0 overdue Work Plan notification(s).')->assertSuccessful();
    expect($this->leader->notifications()->count())->toBe(1)
        ->and($this->head->notifications()->count())->toBe(1);
});

test('Research Head overdue notifications report official incomplete activity progress', function () {
    $this->travelTo('2026-04-15');
    ($this->makeReport)([
        ['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67'],
        ['source_work_plan_index' => 1, 'accomplished_percentage' => 25],
    ]);
    expect(app(NotifyOverdueWorkPlanActivities::class)->handle($this->topic))->toBe(2);
    $notice = $this->head->notifications()->sole();
    expect($notice->data['message'])->toContain(
        $this->topic->title, $this->leader->name, 'Build the prototype', 'Develop the prototype',
        'Apr 14, 2026', '50% complete in submitted monitoring.',
    )->not->toContain('Establish the baseline');
});

test('an existing faculty overdue reminder does not prevent the Research Head notification', function () {
    $this->travelTo('2026-02-15');
    $key = $this->topic->id.':0:2026-02-14';
    $this->leader->notify(new ProposalActivityNotification(
        title: 'Work Plan target overdue', message: 'Previously sent faculty reminder.',
        url: route('topics.show', $this->topic).'#project-monitoring', level: 'warning', topicId: $this->topic->id,
        actionData: ['work_plan_overdue_key' => $key], workspace: User::WORKSPACE_FACULTY_RESEARCHER,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
    ));
    expect(app(NotifyOverdueWorkPlanActivities::class)->handle($this->topic))->toBe(1)
        ->and($this->leader->notifications()->count())->toBe(1)
        ->and($this->head->notifications()->sole()->data['action_data']['work_plan_overdue_key'])->toBe($key);
});

test('each Research Head gets overdue work once including a head assigned after the first reminder', function () {
    $this->travelTo('2026-02-15');
    $secondHead = User::factory()->create();
    $secondHead->assignRole('research_head');
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty_researcher');
    $action = app(NotifyOverdueWorkPlanActivities::class);
    expect($action->handle($this->topic))->toBe(3)
        ->and($secondHead->notifications()->count())->toBe(1)
        ->and($otherFaculty->notifications()->count())->toBe(0)
        ->and($action->handle($this->topic))->toBe(0);
    $newHead = User::factory()->create();
    $newHead->assignRole('research_head');
    expect($action->handle($this->topic))->toBe(1)
        ->and($newHead->notifications()->count())->toBe(1)
        ->and($this->head->notifications()->count())->toBe(1)
        ->and($secondHead->notifications()->count())->toBe(1)
        ->and($this->leader->notifications()->count())->toBe(1);
});

test('a project leader who is also a Research Head receives separate workspace reminders', function () {
    $this->travelTo('2026-02-15');
    $this->leader->assignRole('research_head');
    $action = app(NotifyOverdueWorkPlanActivities::class);
    expect($action->handle($this->topic))->toBe(3)
        ->and($this->leader->notifications()->count())->toBe(2)
        ->and($action->handle($this->topic))->toBe(0);
    $this->actingAs($this->leader)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->getJson(route('notifications.index'))->assertSuccessful()->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.data.title', 'Work Plan target overdue');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson(route('notifications.index'))->assertSuccessful()->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.data.title', 'Faculty Work Plan target overdue');
});

test('completed approved activities do not generate Research Head reminders', function () {
    $this->travelTo('2026-04-15');
    ($this->makeReport)([
        ['source_work_plan_index' => 0, 'accomplished_percentage' => '16.67'],
        ['source_work_plan_index' => 1, 'accomplished_percentage' => 50],
    ]);
    expect(app(NotifyOverdueWorkPlanActivities::class)->handle($this->topic))->toBe(0)
        ->and($this->leader->notifications()->count())->toBe(0)
        ->and($this->head->notifications()->count())->toBe(0);
});

test('the objective alert command is scheduled hourly without overlapping runs', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'projects:notify-overdue-objectives'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
