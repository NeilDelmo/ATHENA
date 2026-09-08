<?php

namespace App\Livewire;

use App\Models\PersonalReminder;
use App\Services\DashboardCalendar as Calendar;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class DashboardCalendar extends Component
{
    #[Reactive]
    public ?int $callId = null;

    public string $month = '';

    public string $selectedDate = '';

    public string $eventId = '';

    public ?int $editingId = null;

    public string $title = '';

    public string $notes = '';

    public string $startsAt = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->month = now()->format('Y-m');
        $this->selectedDate = now()->toDateString();
    }

    public function moveMonth(int $direction): void
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonths($direction)->format('Y-m');
        $this->selectedDate = $this->month.'-01';
    }

    public function today(): void
    {
        $this->mount();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->validate(['selectedDate' => ['required', 'date_format:Y-m-d']]);
    }

    public function openEvent(string $id): void
    {
        $this->eventId = $id;
        $this->dispatch('close-modal', 'calendar-expanded');
        $this->dispatch('open-modal', 'calendar-event');
    }

    public function addReminder(): void
    {
        abort_unless(auth()->check(), 403);
        $this->resetValidation();
        $this->reset('editingId', 'title', 'notes');
        $this->startsAt = $this->selectedDate === now()->toDateString()
            ? now()->addHour()->format('Y-m-d\TH:i')
            : $this->selectedDate.'T09:00';
        $this->dispatch('close-modal', 'calendar-event');
        $this->dispatch('open-modal', 'calendar-reminder');
    }

    public function editReminder(int $id): void
    {
        $reminder = PersonalReminder::where('user_id', auth()->id())->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $reminder->id;
        $this->title = $reminder->title;
        $this->notes = $reminder->notes ?? '';
        $this->startsAt = $reminder->starts_at->format('Y-m-d\TH:i');
        $this->dispatch('close-modal', 'calendar-event');
        $this->dispatch('open-modal', 'calendar-reminder');
    }

    public function saveReminder(): void
    {
        abort_unless(auth()->check(), 403);
        $this->title = trim($this->title);
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'startsAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:1900-01-01', 'before:2101-01-01'],
        ]);
        $reminder = $this->editingId
            ? PersonalReminder::where('user_id', auth()->id())->findOrFail($this->editingId)
            : new PersonalReminder;
        $reminder->user_id = auth()->id();
        $reminder->fill(['title' => $this->title, 'notes' => $this->notes, 'starts_at' => $this->startsAt])->save();
        $this->month = $reminder->starts_at->format('Y-m');
        $this->selectedDate = $reminder->starts_at->toDateString();
        $this->dispatch('close-modal', 'calendar-reminder');
        $this->reset('editingId', 'title', 'notes');
    }

    public function deleteReminder(int $id): void
    {
        PersonalReminder::where('user_id', auth()->id())->findOrFail($id)->delete();
        $this->eventId = '';
        $this->dispatch('close-modal', 'calendar-event');
        $this->dispatch('close-modal', 'calendar-reminder');
    }

    public function render(Calendar $calendar): View
    {
        abort_unless(auth()->check(), 403);
        $this->validate(['month' => ['required', 'date_format:Y-m'], 'selectedDate' => ['required', 'date_format:Y-m-d']]);
        $month = CarbonImmutable::parse($this->month.'-01');
        $start = $month->startOfWeek();
        $end = $month->endOfMonth()->endOfWeek();
        $events = $calendar->events(auth()->user(), $start, $end, $this->callId);
        $upcoming = $calendar->events(auth()->user(), CarbonImmutable::now(), CarbonImmutable::now()->addYear(), $this->callId)
            ->filter(fn (array $event) => ($event['deadline'] && ! $event['draft']) || $event['kind'] === 'personal')->take(5);
        $byDate = $events->groupBy('date');
        $days = collect();
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days->push(['date' => $day->toDateString(), 'number' => $day->day, 'current' => $day->month === $month->month,
                'today' => $day->isToday(), 'events' => $byDate->get($day->toDateString(), collect())]);
        }

        return view('livewire.dashboard-calendar', [
            'days' => $days, 'monthLabel' => $month->format('F Y'), 'upcoming' => $upcoming,
            'selectedEvents' => $byDate->get($this->selectedDate, collect()),
            'selectedLabel' => CarbonImmutable::parse($this->selectedDate)->format('M j'),
            'event' => $events->merge($upcoming)->firstWhere('id', $this->eventId),
        ]);
    }
}
