<?php

use App\Models\ResearchCall;
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

test('research call date inputs are saved in the exact Manila date and time selected', function () {
    $researchHead = User::factory()->create();
    $researchHead->assignRole(User::WORKSPACE_RESEARCH_HEAD);

    $this->actingAs($researchHead)
        ->post(route('research-calls.store'), [
            'title' => 'Local Date Input Call',
            'academic_year' => '2026-2027',
            'opens_at' => '2026-09-01T08:15',
            'closes_at' => '2026-09-05T17:45',
            'initial_evaluation_start_date' => '2026-09-06',
            'initial_evaluation_end_date' => '2026-09-08',
            'max_active_research_per_faculty' => 2,
            'status' => 'draft',
        ])
        ->assertRedirect(route('research-calls.index'));

    $researchCall = ResearchCall::query()
        ->where('title', 'Local Date Input Call')
        ->firstOrFail();

    expect($researchCall->opens_at->format('Y-m-d\\TH:i'))->toBe('2026-09-01T08:15')
        ->and($researchCall->closes_at->format('Y-m-d\\TH:i'))->toBe('2026-09-05T17:45')
        ->and($researchCall->initial_evaluation_start_date->toDateString())->toBe('2026-09-06')
        ->and($researchCall->initial_evaluation_end_date->toDateString())->toBe('2026-09-08');

    $this->actingAs($researchHead)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('2026-09-01T08:15')
        ->assertSee('2026-09-05T17:45');
});

test('a header banner shows the nearest research call deadline within seven days', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);

    ResearchCall::create([
        'title' => 'Later Closing Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(6),
        'status' => 'open',
    ]);
    $nearestCall = ResearchCall::create([
        'title' => 'Priority Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(2)->setTime(17, 30),
        'status' => 'open',
    ]);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-research-call-deadline-banner', false)
        ->assertSee($nearestCall->title)
        ->assertSee('Aug 27, 2026 at 5:30 PM PHT')
        ->assertDontSee('Later Closing Call')
        ->assertSee(route('research-calls.index'), false);
});

test('the deadline banner stays hidden when no open call is due within seven days', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(User::WORKSPACE_FACULTY);

    ResearchCall::create([
        'title' => 'Future Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(8),
        'status' => 'open',
    ]);
    ResearchCall::create([
        'title' => 'Draft Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'status' => 'draft',
    ]);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertDontSee('data-research-call-deadline-banner', false);
});
