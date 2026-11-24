<?php

use App\Actions\ProcessResearchCallOpeningNotifications;
use App\Models\ResearchCall;
use App\Models\ResearchCallDeadlineDismissal;
use App\Models\User;
use App\Notifications\ResearchCallDeadlineReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([User::WORKSPACE_FACULTY, User::WORKSPACE_RESEARCH_HEAD] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-08-25 09:00:00', 'Asia/Manila'));
});

function openResearchCall(string $title, CarbonImmutable $closesAt): ResearchCall
{
    return ResearchCall::create([
        'title' => $title,
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => $closesAt,
        'status' => 'open',
        'faculty_open_notification_sent_at' => now(),
    ]);
}

test('the Research Head can dismiss a deadline banner through the next Manila day', function () {
    $head = User::factory()->create();
    $head->assignRole(User::WORKSPACE_RESEARCH_HEAD);
    $researchCall = openResearchCall('Dismissible Research Call', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));

    $this->actingAs($head)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('Dismiss deadline reminder until tomorrow')
        ->assertSee('x-show="!dismissed"', false)
        ->assertSee('this.dismissed = true;', false)
        ->assertDontSee('this.$el.remove()', false);

    $this->postJson(route('research-calls.deadline-dismissal.store', $researchCall), ['deadline_at' => $researchCall->closes_at->toIso8601String()])
        ->assertOk()
        ->assertJson(['dismissed' => true]);

    $dismissal = ResearchCallDeadlineDismissal::query()->sole();

    expect($dismissal->user_id)->toBe($head->id)
        ->and($dismissal->research_call_id)->toBe($researchCall->id)
        ->and($dismissal->dismissed_on->toDateString())->toBe('2026-08-25')
        ->and($dismissal->deadline_at->toIso8601String())->toBe($researchCall->closes_at->toIso8601String());

    $this->get(route('research-calls.index'))
        ->assertOk()
        ->assertDontSee('data-research-call-deadline-banner', false);

    $this->travelTo(CarbonImmutable::parse('2026-08-26 09:00:00', 'Asia/Manila'));

    $this->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('data-research-call-deadline-banner', false);
});

test('a stale tab cannot dismiss a changed deadline', function () {
    $head = User::factory()->create();
    $head->assignRole(User::WORKSPACE_RESEARCH_HEAD);
    $researchCall = openResearchCall('Updated Deadline Call', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));
    $originalDeadline = $researchCall->closes_at->toIso8601String();
    $dismissalUrl = route('research-calls.deadline-dismissal.store', $researchCall);

    $this->actingAs($head)
        ->postJson($dismissalUrl, ['deadline_at' => $originalDeadline])
        ->assertOk();

    $researchCall->update([
        'closes_at' => CarbonImmutable::parse('2026-08-28 09:00:00', 'Asia/Manila'),
    ]);

    $this->postJson($dismissalUrl, ['deadline_at' => $originalDeadline])->assertConflict();
    expect(ResearchCallDeadlineDismissal::sole()->deadline_at->toIso8601String())->toBe($originalDeadline);

    $this->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('data-research-call-deadline-banner', false)
        ->assertSee('Updated Deadline Call');
});

test('faculty cannot dismiss the Research Head deadline banner', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);
    $researchCall = openResearchCall('Research Head Deadline', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));

    $this->actingAs($faculty)
        ->postJson(route('research-calls.deadline-dismissal.store', $researchCall), ['deadline_at' => $researchCall->closes_at->toIso8601String()])
        ->assertNotFound();

    expect(ResearchCallDeadlineDismissal::count())->toBe(0);
});

test('deadline reminders are sent once at the warning window and once in the final 24 hours', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);
    $researchCall = openResearchCall('Scheduled Reminder Call', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));
    $processor = app(ProcessResearchCallOpeningNotifications::class);

    expect($processor->handle())->toBe(1)
        ->and($faculty->notifications()->count())->toBe(1)
        ->and($faculty->notifications()->sole()->data)->toMatchArray([
            'research_call_id' => $researchCall->id,
            'deadline_notification_stage' => 'approaching',
        ])
        ->and($processor->handle())->toBe(0);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Research call deadline approaching')
        ->assertSee('Scheduled Reminder Call');

    $this->travelTo(CarbonImmutable::parse('2026-08-26 10:00:00', 'Asia/Manila'));

    expect($processor->handle())->toBe(1)
        ->and($faculty->notifications()->count())->toBe(2)
        ->and($faculty->notifications()->where('data->deadline_notification_stage', 'final')->count())->toBe(1)
        ->and($processor->handle())->toBe(0);
});

test('a changed deadline receives an updated notification without duplicating the old one', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);
    $researchCall = openResearchCall('Changed Notification Call', CarbonImmutable::parse('2026-08-29 09:00:00', 'Asia/Manila'));
    $processor = app(ProcessResearchCallOpeningNotifications::class);

    expect($processor->handle())->toBe(1);

    $researchCall->update([
        'closes_at' => CarbonImmutable::parse('2026-08-28 09:00:00', 'Asia/Manila'),
    ]);

    expect($processor->handle())->toBe(1)
        ->and($faculty->notifications()->count())->toBe(2)
        ->and($faculty->notifications()
            ->where('data->deadline_notification_stage', 'approaching')
            ->get()
            ->pluck('data.deadline_at')
            ->unique()
            ->count())->toBe(2);
});

test('deadline notifications only go to faculty submission recipients', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);
    $researchHead = User::factory()->create();
    $researchHead->assignRole(User::WORKSPACE_RESEARCH_HEAD);
    openResearchCall('Faculty Only Reminder Call', CarbonImmutable::parse('2026-08-29 09:00:00', 'Asia/Manila'));

    expect(app(ProcessResearchCallOpeningNotifications::class)->handle())->toBe(1)
        ->and($faculty->notifications()->count())->toBe(1)
        ->and($researchHead->notifications()->count())->toBe(0);
});

test('a failed deadline reminder allows other recipients through and is retried on the next run', function () {
    $firstFaculty = User::factory()->create();
    $secondFaculty = User::factory()->create();
    $firstFaculty->assignRole(User::WORKSPACE_FACULTY);
    $secondFaculty->assignRole(User::WORKSPACE_FACULTY);
    openResearchCall('Retryable Deadline', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));
    Exceptions::fake();
    $failOnce = true;
    Event::listen(NotificationSending::class, function (NotificationSending $event) use ($firstFaculty, &$failOnce): void {
        if ($failOnce && $event->channel === 'database' && $event->notification instanceof ResearchCallDeadlineReminderNotification
            && $event->notifiable->is($firstFaculty)) {
            $failOnce = false;

            throw new RuntimeException('Inbox write failed');
        }
    });

    $processor = app(ProcessResearchCallOpeningNotifications::class);
    expect($processor->handle())->toBe(1)
        ->and($firstFaculty->notifications()->count())->toBe(0)
        ->and($secondFaculty->notifications()->count())->toBe(1)
        ->and($processor->handle())->toBe(1)
        ->and($firstFaculty->notifications()->count())->toBe(1)
        ->and($secondFaculty->notifications()->count())->toBe(1)
        ->and($processor->handle())->toBe(0);
    Exceptions::assertReported(RuntimeException::class);
});
