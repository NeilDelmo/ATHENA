<?php

use App\Actions\ProcessResearchCallOpeningNotifications;
use App\Models\ResearchCall;
use App\Models\ResearchCallDeadlineDismissal;
use App\Models\User;
use Carbon\CarbonImmutable;
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
    ]);
}

test('a faculty member can dismiss a deadline banner through the next Manila day', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);
    $researchCall = openResearchCall('Dismissible Research Call', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Dismiss deadline reminder until tomorrow')
        ->assertSee('x-show="!dismissed"', false)
        ->assertSee('this.dismissed = true;', false)
        ->assertDontSee('this.$el.remove()', false);

    $this->actingAs($faculty)
        ->postJson(route('research-calls.deadline-dismissal.store', $researchCall))
        ->assertOk()
        ->assertJson(['dismissed' => true]);

    $dismissal = ResearchCallDeadlineDismissal::query()->sole();

    expect($dismissal->user_id)->toBe($faculty->id)
        ->and($dismissal->research_call_id)->toBe($researchCall->id)
        ->and($dismissal->dismissed_on->toDateString())->toBe('2026-08-25')
        ->and($dismissal->deadline_at->toIso8601String())->toBe($researchCall->closes_at->toIso8601String());

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertDontSee('data-research-call-deadline-banner', false);

    $this->travelTo(CarbonImmutable::parse('2026-08-26 09:00:00', 'Asia/Manila'));

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-research-call-deadline-banner', false);
});

test('changing a deadline makes its previously dismissed banner visible again', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);
    $researchCall = openResearchCall('Updated Deadline Call', CarbonImmutable::parse('2026-08-27 09:00:00', 'Asia/Manila'));

    $this->actingAs($faculty)
        ->postJson(route('research-calls.deadline-dismissal.store', $researchCall))
        ->assertOk();

    $researchCall->update([
        'closes_at' => CarbonImmutable::parse('2026-08-28 09:00:00', 'Asia/Manila'),
    ]);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-research-call-deadline-banner', false)
        ->assertSee('Updated Deadline Call');
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
