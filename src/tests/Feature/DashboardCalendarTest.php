<?php

use App\Livewire\DashboardCalendar;
use App\Models\PersonalReminder;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\DashboardCalendar as Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->travelTo(now()->setDate(2026, 9, 6)->setTime(10, 0));
    $this->call = ResearchCall::create([
        'title' => 'September Research Call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDays(5), 'closes_at' => now()->addDays(4), 'status' => 'open',
        'paper_revisions_start_date' => '2026-09-15', 'paper_revisions_end_date' => '2026-09-21',
    ]);
    $this->calendarProject = fn (array $attributes = []): TopicProposal => TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'title' => 'Community water study', 'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => '2026-06-16',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-06-16', 'approved_end_date' => '2026-12-15'],
        ...$attributes,
    ]);
    $this->projectEvents = fn (User $user) => app(Calendar::class)
        ->events($user, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2027-01-31')->endOfDay())
        ->where('kind', 'project')->values();
});

test('calendar derives official dates and follows schedule changes without duplicated events', function () {
    $this->actingAs($this->head);
    $calendar = app(Calendar::class);
    $events = $calendar->events($this->head, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')->endOfDay());
    expect($events)->toHaveCount(4)
        ->and($events->firstWhere('title', 'Paper revision deadline')['date'])->toBe('2026-09-21');
    $this->call->update(['closes_at' => '2026-10-02 17:00']);
    $events = $calendar->events($this->head, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')->endOfDay());
    expect($events->where('title', 'Submission deadline'))->toHaveCount(0);
    $this->get(route('research-calls.index', ['call' => $this->call->id]))->assertOk()->assertSee('Oct 2, 2026');
});

test('faculty calendars hide drafts and unrelated closed calls but retain participated calls', function () {
    $draft = $this->call->replicate();
    $draft->title = 'Private Draft Call';
    $draft->status = 'draft';
    $draft->save();
    $closed = $this->call->replicate();
    $closed->title = 'Closed Call';
    $closed->status = 'closed';
    $closed->save();
    $this->actingAs($this->faculty);
    $calendar = app(Calendar::class);
    expect($calendar->calls($this->faculty)->pluck('id')->all())->toBe([$this->call->id]);
    $this->get(route('research-calls.index', ['call' => $draft->id]))->assertNotFound();
    $this->get(route('research-calls.index', ['call' => $closed->id]))->assertNotFound();
    TopicProposal::create(['user_id' => $this->faculty->id, 'research_call_id' => $closed->id, 'title' => 'My research']);
    expect($calendar->calls($this->faculty)->pluck('id')->all())->toContain($closed->id)->not->toContain($draft->id);
    $this->get(route('research-calls.index', ['call' => $closed->id]))->assertOk()->assertSee('Closed Call');
});

test('personal reminders can be added edited and deleted only by their owner', function () {
    $this->actingAs($this->faculty);
    $component = Livewire::test(DashboardCalendar::class)->call('addReminder')
        ->set('title', 'Prepare revision')->set('notes', 'Check the budget')->set('startsAt', '2026-09-12T14:30')
        ->call('saveReminder')->assertHasNoErrors()->assertSet('selectedDate', '2026-09-12');
    $reminder = PersonalReminder::where('user_id', $this->faculty->id)->sole();
    $component->call('editReminder', $reminder->id)->assertSet('notes', 'Check the budget')
        ->set('title', 'Prepare final revision')->call('saveReminder')->assertHasNoErrors();
    expect($reminder->fresh()->title)->toBe('Prepare final revision');
    $this->actingAs($this->head);
    $events = app(Calendar::class)->events($this->head, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-10-01'));
    expect($events->where('kind', 'personal'))->toBeEmpty();
    expect(fn () => Livewire::test(DashboardCalendar::class)->call('editReminder', $reminder->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::test(DashboardCalendar::class)->set('editingId', $reminder->id)->set('title', 'Attack')->set('startsAt', '2026-09-10T14:30')->call('saveReminder'))->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::test(DashboardCalendar::class)->call('deleteReminder', $reminder->id))->toThrow(ModelNotFoundException::class);
    $this->actingAs($this->faculty);
    Livewire::test(DashboardCalendar::class)->call('deleteReminder', $reminder->id);
    expect(PersonalReminder::find($reminder->id))->toBeNull();
});

test('calendar navigation handles year boundaries and validates reminder input', function () {
    $this->actingAs($this->head);
    $component = Livewire::test(DashboardCalendar::class)->assertSee('data-calendar-legend', false)->assertSee('Official schedule')->assertSee('Personal reminder')
        ->assertSee('Upcoming dates')->assertSee('Submission deadline')
        ->assertDontSee('bg-amber-50', false)->assertDontSee('bg-gradient-', false)
        ->set('month', '2026-12')->call('moveMonth', 1)->assertSet('month', '2027-01')
        ->call('moveMonth', -1)->assertSet('month', '2026-12')->call('today')->assertSet('month', '2026-09')
        ->call('selectDate', '2026-09-21')->assertSee('Paper revision deadline')
        ->set('title', '  ')->set('startsAt', 'invalid')->call('saveReminder')->assertHasErrors(['title', 'startsAt']);
    $official = app(Calendar::class)->events($this->head, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'))->firstWhere('title', 'Submission deadline');
    $component->call('openEvent', $official['id'])->assertDispatched('open-modal', 'calendar-event')->assertSee('Open research call');
});

test('a calendar call filter excludes other official schedules while keeping personal reminders', function () {
    $other = $this->call->replicate();
    $other->title = 'Other call';
    $other->save();
    $this->actingAs($this->head);
    $reminder = new PersonalReminder(['title' => 'Private task', 'starts_at' => now()->addDay()]);
    $reminder->user_id = $this->head->id;
    $reminder->save();
    $events = app(Calendar::class)->events($this->head, CarbonImmutable::now(), CarbonImmutable::now()->addMonth(), $this->call->id);
    expect($events->pluck('context')->all())->not->toContain('Other call')->toContain('Only you');
    $this->actingAs($this->faculty)->get(route('faculty.calendar'))->assertOk()->assertSee('Research calendar');
});

test('compact dashboard calendars preserve navigation and reminder actions with a restrained palette', function () {
    $this->actingAs($this->faculty);

    $component = Livewire::test(DashboardCalendar::class, ['compact' => true])
        ->assertSee('data-calendar-compact', false)
        ->assertSee('Upcoming dates')
        ->assertSee('Submission deadline')
        ->call('moveMonth', 1)
        ->assertSet('month', '2026-10')
        ->assertSet('compact', true)
        ->call('today')
        ->call('selectDate', '2026-09-12')
        ->call('addReminder')
        ->assertDispatched('open-modal', 'calendar-reminder')
        ->set('title', 'Prepare proposal package')
        ->set('startsAt', '2026-09-12T14:30')
        ->call('saveReminder')
        ->assertHasNoErrors()
        ->assertSee('Prepare proposal package')
        ->assertSee('Personal reminder')
        ->assertSee('2:30 PM')
        ->assertDontSee('bg-sky-50', false)
        ->assertDontSee('bg-amber-50', false);

    $reminder = PersonalReminder::where('user_id', $this->faculty->id)->sole();

    $component->call('openEvent', 'reminder-'.$reminder->id)
        ->assertDispatched('open-modal', 'calendar-event')
        ->assertSee('Edit reminder');
});

test('calendar derives project periods from the issued notice rather than the research call schedule', function () {
    $topic = ($this->calendarProject)();
    $this->actingAs($this->faculty);
    $events = app(Calendar::class)->events($this->faculty, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-12-31')->endOfDay());

    expect($events->where('kind', 'project'))->not->toBeEmpty()
        ->and($events->firstWhere('title', 'Monitoring Tool Q1 deadline')['date'] ?? null)->toBe('2026-09-15')
        ->and($events->firstWhere('title', 'Progress Report Q2 deadline')['date'] ?? null)->toBe('2026-12-15')
        ->and($events->firstWhere('title', 'Terminal report submissions open')['date'] ?? null)->toBe('2026-12-16')
        ->and($events->firstWhere('title', 'Terminal report submissions open')['deadline'] ?? null)->toBeFalse()
        ->and($events->firstWhere('title', 'Monitoring Tool Q1 deadline')['url'] ?? null)->toBe(route('topics.show', $topic).'#proposal-details');
});

test('calendar follows moved project dates and removes completed project obligations', function () {
    $topic = ($this->calendarProject)();
    $this->actingAs($this->faculty);
    $original = ($this->projectEvents)($this->faculty);
    $topic->update(['notice_to_proceed_data' => ['approved_start_date' => '2026-07-16', 'approved_end_date' => '2027-01-15']]);
    $moved = ($this->projectEvents)($this->faculty);

    expect($moved->firstWhere('title', 'Monitoring Tool Q1 deadline')['date'])->toBe('2026-10-15')
        ->and($moved->firstWhere('title', 'Terminal report submissions open')['date'])->toBe('2027-01-16')
        ->and($moved->count())->toBe($original->count())
        ->and($moved->pluck('id')->unique()->count())->toBe($moved->count());

    $topic->update(['project_status' => 'completed']);
    expect(($this->projectEvents)($this->faculty))->toBeEmpty();
});

test('calendar removes submitted reports and accomplished approved work plan targets', function () {
    $topic = ($this->calendarProject)();
    $version = $topic->versions()->create([
        'submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial',
        'title' => $topic->title,
        'file_path' => 'calendar-proposal.pdf', 'original_filename' => 'calendar-proposal.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => hash('sha256', 'calendar-proposal'),
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0,
        'file_path' => 'calendar-work-plan.pdf', 'original_filename' => 'calendar-work-plan.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => hash('sha256', 'calendar-work-plan'),
        'source_data' => ['total_duration_months' => 6, 'entries' => [
            ['objective' => 'Measure water quality', 'activity' => 'Collect samples', 'expected_output' => 'Sample inventory', 'months' => [3]],
            ['objective' => 'Share water findings', 'activity' => 'Publish analysis', 'expected_output' => 'Community report', 'months' => [6]],
        ]],
    ]);
    $this->actingAs($this->faculty);
    expect(($this->projectEvents)($this->faculty)->firstWhere('title', 'Work Plan target: Collect samples')['date'])->toBe('2026-09-15');

    $report = ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'reporting_date' => '2026-09-15',
        'reporting_year' => 2026, 'reporting_quarter' => 1, 'period_start' => '2026-06-16', 'period_end' => '2026-09-15',
        'version_number' => 1, 'progress_percentage' => 50, 'accomplishments' => 'Samples collected',
        'work_plan' => [['source_work_plan_index' => 0, 'activity' => 'Collect samples', 'accomplished_percentage' => 50]],
    ]);
    $progress = ProjectNarrativeReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'report_type' => 'progress',
        'reporting_date' => '2026-09-15', 'reporting_quarter' => 1, 'version_number' => 1,
        'submission_date' => '2026-09-15', 'researchers' => $this->faculty->name,
        'implementation_start' => '2026-06-16', 'implementation_end' => '2026-09-15',
        'budget' => 1000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Samples collected',
        'introduction' => 'Water quality study.', 'objectives' => 'Measure water quality.',
        'methodology' => 'Collect samples.', 'results_discussion' => 'Inventory recorded.', 'photos' => [],
    ]);
    $events = ($this->projectEvents)($this->faculty);
    expect($events->pluck('title')->all())->not->toContain('Monitoring Tool Q1 deadline', 'Progress Report Q1 deadline', 'Work Plan target: Collect samples')
        ->toContain('Monitoring Tool Q2 deadline', 'Progress Report Q2 deadline', 'Work Plan target: Publish analysis');

    $report->update(['review_status' => 'revision_requested']);
    $progress->update(['review_status' => 'revision_requested']);
    expect(($this->projectEvents)($this->faculty)->pluck('title')->all())->toContain('Monitoring Tool Q1 deadline', 'Progress Report Q1 deadline');
});

test('calendar project access follows the active workspace and accepted collaboration', function () {
    $this->faculty->update(['college' => 'CICS']);
    $outside = User::factory()->create(['college' => 'CAS']);
    $own = ($this->calendarProject)();
    $shared = ($this->calendarProject)(['user_id' => $outside->id, 'title' => 'Shared study']);
    $private = ($this->calendarProject)(['user_id' => $outside->id, 'title' => 'Private study']);
    TopicCollaborator::factory()->create(['topic_id' => $shared->id, 'user_id' => $this->faculty->id, 'accepted_at' => now()]);
    TopicCollaborator::factory()->create(['topic_id' => $private->id, 'user_id' => $this->faculty->id, 'accepted_at' => null]);
    $this->actingAs($this->faculty);
    expect(($this->projectEvents)($this->faculty)->pluck('context')->unique()->values()->all())->toBe([$own->title, $shared->title]);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    expect(($this->projectEvents)($this->head)->pluck('context')->unique())->toHaveCount(3);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    expect(($this->projectEvents)($this->head))->toBeEmpty();

    foreach (['research_secretary' => User::WORKSPACE_RESEARCH_SECRETARY, 'research_coordinator' => User::WORKSPACE_RESEARCH_OFFICE] as $role => $workspace) {
        Role::firstOrCreate(['name' => $role]);
        $viewer = User::factory()->create(['college' => 'CICS']);
        $viewer->assignRole($role);
        $this->actingAs($viewer)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
        expect(($this->projectEvents)($viewer)->pluck('context')->unique()->values()->all())->toBe([$own->title]);
        $viewer->update(['college' => null]);
        expect(($this->projectEvents)($viewer))->toBeEmpty();
    }
});

test('calendar head report dates only reflect reports forwarded for head review', function () {
    $topic = ($this->calendarProject)();
    $report = ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'reporting_date' => '2026-09-15',
        'reporting_year' => 2026, 'reporting_quarter' => 1, 'version_number' => 1,
        'progress_percentage' => 50, 'accomplishments' => 'Samples collected', 'review_stage' => 'secretary',
    ]);
    $this->actingAs($this->head);
    expect(($this->projectEvents)($this->head)->pluck('title')->all())->toContain('Monitoring Tool Q1 deadline');
    $report->update(['review_stage' => 'head']);
    expect(($this->projectEvents)($this->head)->pluck('title')->all())->not->toContain('Monitoring Tool Q1 deadline');
});

test('calendar call filters cover project dates and leave personal reminders visible', function () {
    $topic = ($this->calendarProject)();
    ($this->calendarProject)(['research_call_id' => null, 'title' => 'Unfiltered project']);
    $this->actingAs($this->faculty);
    $reminder = new PersonalReminder(['title' => 'My calendar note', 'starts_at' => '2026-09-15 12:00']);
    $reminder->user_id = $this->faculty->id;
    $reminder->save();
    $calendar = app(Calendar::class);
    $events = $calendar->events($this->faculty, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')->endOfDay(), $this->call->id);
    expect($events->where('kind', 'project')->pluck('context')->unique()->values()->all())->toBe([$topic->title])
        ->and($events->pluck('title')->all())->toContain('My calendar note');
    expect($calendar->events($this->faculty, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')->endOfDay(), 0)->pluck('kind')->all())->toBe(['personal']);
});

test('compact calendars open project report dates with their own labels and links', function () {
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $this->faculty->assignRole('faculty_researcher');
    $topic = ($this->calendarProject)(['notice_to_proceed_data' => ['approved_start_date' => '2026-06-16', 'approved_end_date' => '2026-09-15']]);
    $this->actingAs($this->faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    Livewire::test(DashboardCalendar::class, ['compact' => true])
        ->call('selectDate', '2026-09-15')->assertSee('Report deadline')->assertSee('Project date')
        ->call('openEvent', 'project-'.$topic->id.'-monitoring-1')
        ->assertSee('Open project reports')->assertSee(route('topics.show', $topic).'#project-monitoring', false)
        ->call('openEvent', 'project-'.$topic->id.'-terminal-open')
        ->assertSee('Report opening')->assertSee('Terminal report submissions open')->assertDontSee('Edit reminder');
});

test('calendar project queries are batched as more projects are added', function () {
    ($this->calendarProject)();
    $this->actingAs($this->faculty);
    $this->faculty->getRoleNames();
    DB::enableQueryLog();
    DB::flushQueryLog();
    ($this->projectEvents)($this->faculty);
    $queriesForOne = count(DB::getQueryLog());
    ($this->calendarProject)(['title' => 'Second study']);
    ($this->calendarProject)(['title' => 'Third study']);
    DB::flushQueryLog();
    $events = ($this->projectEvents)($this->faculty);
    $queriesForThree = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($events->pluck('context')->unique())->toHaveCount(3)
        ->and($queriesForThree)->toBe($queriesForOne);
});

test('calendar does not invent project deadlines when no valid schedule is recorded', function () {
    ($this->calendarProject)(['notice_to_proceed_data' => null, 'estimated_duration_months' => null]);
    $this->actingAs($this->faculty);

    expect(($this->projectEvents)($this->faculty))->toBeEmpty();
});

test('calendar work plan dates do not hydrate proposal narrative or screening bodies', function () {
    $topic = ($this->calendarProject)();
    $body = str_repeat('Full proposal narrative outside calendar events. ', 1000);
    $version = $topic->versions()->create([
        'submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial',
        'title' => $topic->title, 'file_path' => 'calendar-proposal.pdf', 'original_filename' => 'calendar-proposal.pdf',
        'description' => $body, 'change_summary' => $body,
    ]);
    $version->forceFill(['research_head_screening' => ['narrative' => $body]])->save();
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'file_path' => 'calendar-work-plan.pdf',
        'original_filename' => 'calendar-work-plan.pdf',
        'source_data' => ['total_duration_months' => 6, 'entries' => [[
            'objective' => 'Measure water quality', 'activity' => 'Collect calendar samples',
            'expected_output' => 'Sample inventory', 'months' => [3],
        ]]],
    ]);
    $this->actingAs($this->faculty);
    $retrieved = [];
    Event::listen('eloquent.retrieved: '.ProposalVersion::class, function (ProposalVersion $record) use (&$retrieved): void {
        $retrieved[] = $record->getAttributes();
    });
    try {
        $events = ($this->projectEvents)($this->faculty);
    } finally {
        Event::forget('eloquent.retrieved: '.ProposalVersion::class);
    }
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Calendar proposal bytes: '.strlen(json_encode($retrieved, JSON_THROW_ON_ERROR)).PHP_EOL);
    }

    $event = $events->firstWhere('title', 'Work Plan target: Collect calendar samples');
    expect($event['date'])->toBe('2026-09-15')
        ->and($event['notes'])->toBe("Measure water quality\nExpected output: Sample inventory")
        ->and($event['url'])->toBe(route('topics.show', $topic).'#proposal-details')
        ->and($retrieved)->toHaveCount(1)
        ->and($retrieved[0]['id'])->toBe($version->id)
        ->and($retrieved[0])->not->toHaveKeys(['description', 'change_summary', 'research_head_screening']);
});
