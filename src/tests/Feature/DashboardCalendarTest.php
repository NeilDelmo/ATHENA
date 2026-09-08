<?php

use App\Livewire\DashboardCalendar;
use App\Models\PersonalReminder;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\DashboardCalendar as Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    Livewire::test(DashboardCalendar::class)->set('month', '2026-12')->call('moveMonth', 1)->assertSet('month', '2027-01')
        ->call('moveMonth', -1)->assertSet('month', '2026-12')->call('today')->assertSet('month', '2026-09')
        ->call('selectDate', '2026-09-21')->assertSee('Paper revision deadline')
        ->set('title', '  ')->set('startsAt', 'invalid')->call('saveReminder')->assertHasErrors(['title', 'startsAt']);
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
    $this->actingAs($this->faculty)->get(route('faculty.dashboard'))->assertOk()->assertSee('Research calendar');
});
