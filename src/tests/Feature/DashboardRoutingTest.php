<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('each account role is sent to its own dashboard', function (string $role, string $route) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($route));
})->with([
    'faculty' => ['faculty', 'faculty.dashboard'],
    'faculty researcher' => ['faculty_researcher', 'faculty.dashboard'],
    'research head' => ['research_head', 'research_head.dashboard'],
    'research coordinator' => ['research_coordinator', 'research_coordinator.dashboard'],
]);

test('the shared faculty dashboard uses the correct workspace identity for each role', function () {
    $this->withoutVite();

    $faculty = User::factory()->create(['name' => 'Regular Faculty']);
    $faculty->assignRole('faculty');

    $facultyResearcher = User::factory()->create(['name' => 'Faculty Researcher']);
    $facultyResearcher->assignRole('faculty_researcher');

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Research Proposal Workspace')
        ->assertSee('Submit and track your research proposals.')
        ->assertSee('data-dashboard-palette="red-black-white"', false)
        ->assertSee('Proposal overview')
        ->assertDontSee('Faculty Researcher Workspace')
        ->assertDontSee('Manage and track your institutional research submissions.');

    $this->actingAs($facultyResearcher)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Faculty Researcher Workspace')
        ->assertSee('Manage and track your institutional research submissions.')
        ->assertDontSee('Research Proposal Workspace');
});

test('the faculty dashboard shows recent accessible proposal drafts', function () {
    $this->withoutVite();

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create(['name' => 'Dashboard Faculty']);
    $faculty->assignRole('faculty');
    $owner = User::factory()->create(['name' => 'Sharing Faculty']);
    $owner->assignRole('faculty');

    $researchCall = ResearchCall::create([
        'title' => 'Dashboard Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);

    $ownedDraft = ProposalDraft::create([
        'user_id' => $faculty->id,
        'research_call_id' => $researchCall->id,
        'project_title' => 'My Visible Dashboard Draft',
    ]);
    $sharedDraft = ProposalDraft::create([
        'user_id' => $owner->id,
        'research_call_id' => $researchCall->id,
        'project_title' => 'Our Shared Dashboard Draft',
    ]);
    $sharedDraft->members()->create([
        'user_id' => $faculty->id,
        'name' => $faculty->name,
        'email' => $faculty->email,
    ]);
    ProposalDraft::create([
        'user_id' => $owner->id,
        'research_call_id' => $researchCall->id,
        'project_title' => 'Private Draft That Must Stay Hidden',
    ]);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Recent proposal drafts')
        ->assertSee($ownedDraft->project_title)
        ->assertSee($sharedDraft->project_title)
        ->assertSee('Shared by Sharing Faculty')
        ->assertSee(route('faculty.proposal-drafts.show', $ownedDraft))
        ->assertDontSee('Private Draft That Must Stay Hidden');
});

test('the faculty dashboard shows uploaded research call posters in a carousel', function () {
    $this->withoutVite();
    Storage::fake('local');

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    Storage::disk('local')->put('research-calls/first-poster.jpg', 'poster');
    Storage::disk('local')->put('research-calls/second-poster.jpg', 'poster');
    Storage::disk('local')->put('research-calls/closed-poster.jpg', 'closed poster');
    Storage::disk('local')->put('research-calls/expired-poster.jpg', 'expired poster');

    foreach ([
        ['title' => 'First Uploaded Research Call', 'reference_image_path' => 'research-calls/first-poster.jpg'],
        ['title' => 'Second Uploaded Research Call', 'reference_image_path' => 'research-calls/second-poster.jpg'],
    ] as $callData) {
        ResearchCall::create([
            ...$callData,
            'academic_year' => '2026-2027',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addMonth(),
            'max_active_research_per_faculty' => 2,
            'maximum_budget' => 100000,
            'status' => 'open',
            'created_by' => $head->id,
        ]);
    }

    ResearchCall::create([
        'title' => 'Closed Research Call With Poster',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'reference_image_path' => 'research-calls/closed-poster.jpg',
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'closed',
        'created_by' => $head->id,
    ]);

    ResearchCall::create([
        'title' => 'Expired Research Call With Poster',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->subDay(),
        'reference_image_path' => 'research-calls/expired-poster.jpg',
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);

    $firstCall = ResearchCall::query()->where('title', 'First Uploaded Research Call')->firstOrFail();

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-research-call-carousel', false)
        ->assertSee('data-research-call-single-slide', false)
        ->assertSee('data-research-call-lightbox', false)
        ->assertDontSee('data-research-call-lightbox-close', false)
        ->assertSee('data-research-call-indicator', false)
        ->assertSee('data-research-call-submit-overlay', false)
        ->assertSee('max-w-[40rem]', false)
        ->assertSee('transform-gpu object-contain', false)
        ->assertSee(asset('images/maingate.jpg'), false)
        ->assertSee('bg-transparent p-0 shadow-none', false)
        ->assertSee('bg-cover bg-center bg-no-repeat', false)
        ->assertSee('rgba(255, 255, 255, 0.82)', false)
        ->assertSee('inline-flex h-[112%] w-auto', false)
        ->assertSee('sm:h-[20.5rem]', false)
        ->assertSee('h-full w-auto max-w-none', false)
        ->assertSee('transition-transform duration-500', false)
        ->assertSee('duration-700', false)
        ->assertSee('ease-[cubic-bezier(0.22,1,0.36,1)]', false)
        ->assertSee('First Uploaded Research Call')
        ->assertSee('Second Uploaded Research Call')
        ->assertSee('Submit a proposal')
        ->assertSee(route('research-calls.reference-image', $firstCall), false)
        ->assertSee(route('faculty.proposal-drafts.create', ['research_call_id' => $firstCall->id]), false)
        ->assertSee('data-research-call-previous', false)
        ->assertSee('data-research-call-next', false)
        ->assertDontSee('Open research calls')
        ->assertDontSee('Click to view full screen')
        ->assertSee(route('faculty.proposal-drafts.create'), false)
        ->assertSee('id="recent-drafts"', false)
        ->assertDontSee('Closed Research Call With Poster')
        ->assertDontSee(route(
            'research-calls.reference-image',
            ResearchCall::query()->where('title', 'Closed Research Call With Poster')->firstOrFail(),
        ), false)
        ->assertDontSee('Expired Research Call With Poster')
        ->assertDontSee(route(
            'research-calls.reference-image',
            ResearchCall::query()->where('title', 'Expired Research Call With Poster')->firstOrFail(),
        ), false);

    $this->actingAs($faculty)
        ->get(route('faculty.proposal-drafts.create', ['research_call_id' => $firstCall->id]))
        ->assertOk()
        ->assertSee('<option value="'.$firstCall->id.'" selected', false);
});

test('the forbidden response uses the friendly error page', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('research_head.dashboard'))
        ->assertForbidden()
        ->assertSee("This page isn't for your account.")
        ->assertSee('Go to my dashboard');
});

test('missing pages use the friendly error design', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('We could not find that page.')
        ->assertSee('Return home');
});

test('common error views share the ATHENA design', function (string $code, string $heading) {
    $this->view("errors.{$code}")
        ->assertSee("Error {$code}")
        ->assertSee($heading)
        ->assertSee('ATHENA');
})->with([
    'expired session' => ['419', 'Your session has expired.'],
    'too many requests' => ['429', 'Please slow down for a moment.'],
    'server error' => ['500', 'Something went wrong on our side.'],
    'maintenance' => ['503', 'ATHENA is taking a short break.'],
]);

test('an account without a role is signed out instead of being sent to a forbidden page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
});
