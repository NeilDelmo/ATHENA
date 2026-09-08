<?php

namespace App\Services;

use App\Models\PersonalReminder;
use App\Models\ResearchCall;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardCalendar
{
    public const MILESTONES = [
        'opens_at' => 'Submissions open',
        'closes_at' => 'Submission deadline',
        'initial_evaluation_start_date' => 'Initial evaluation begins',
        'initial_evaluation_end_date' => 'Initial evaluation ends',
        'paper_revisions_start_date' => 'Paper revisions begin',
        'paper_revisions_end_date' => 'Paper revision deadline',
        'lrec_start_date' => 'LREC begins',
        'lrec_end_date' => 'LREC ends',
        'implementation_start_date' => 'Implementation begins',
        'implementation_end_date' => 'Implementation ends',
    ];

    public function calls(User $user): Builder
    {
        return ResearchCall::query()->when(! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), function (Builder $query) use ($user): void {
            $query->visibleToFaculty()->where(function (Builder $visible) use ($user): void {
                $visible->where(function (Builder $open): void {
                    $open->where('status', 'open')->where('closes_at', '>=', now());
                })->orWhereHas('topics', fn (Builder $topics) => $topics->accessibleTo($user))
                    ->orWhereHas('proposalDrafts', fn (Builder $drafts) => $drafts->accessibleTo($user));
            });
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function events(User $user, CarbonImmutable $from, CarbonImmutable $until, ?int $callId = null): Collection
    {
        $calls = $this->calls($user)->when($callId, fn (Builder $query) => $query->whereKey($callId))
            ->where(function (Builder $query) use ($from, $until): void {
                foreach (array_keys(self::MILESTONES) as $field) {
                    $query->orWhereBetween($field, [$from->startOfDay(), $until->endOfDay()]);
                }
            })->get(['id', 'title', 'status', ...array_keys(self::MILESTONES)]);
        $events = collect();
        foreach ($calls as $call) {
            foreach (self::MILESTONES as $field => $label) {
                if (! $call->{$field}) {
                    continue;
                }
                $at = CarbonImmutable::instance($call->{$field});
                $allDay = ! in_array($field, ['opens_at', 'closes_at'], true);
                if ($allDay) {
                    $at = str_contains($field, '_end_') ? $at->endOfDay() : $at->startOfDay();
                }
                if ($at->lt($from) || $at->gt($until)) {
                    continue;
                }
                $events->push([
                    'id' => 'call-'.$call->id.'-'.$field, 'title' => $label,
                    'context' => $call->title, 'date' => $at->toDateString(),
                    'at' => $at->toDateTimeString(), 'display_at' => $at->format('M j, Y').($allDay ? ' · All day' : $at->format(' · g:i A')),
                    'kind' => 'official', 'draft' => $call->status === 'draft',
                    'deadline' => $field === 'closes_at' || str_contains($field, '_end_'),
                    'url' => route('research-calls.index', ['call' => $call->id]),
                    'notes' => 'Schedule managed through the research call.',
                ]);
            }
        }
        $reminders = PersonalReminder::where('user_id', $user->id)->whereBetween('starts_at', [$from, $until])->get();
        foreach ($reminders as $reminder) {
            $events->push([
                'id' => 'reminder-'.$reminder->id, 'reminder_id' => $reminder->id,
                'title' => $reminder->title, 'context' => 'Only you',
                'date' => $reminder->starts_at->toDateString(), 'at' => $reminder->starts_at->toDateTimeString(),
                'display_at' => $reminder->starts_at->format('M j, Y · g:i A'),
                'kind' => 'personal', 'draft' => false, 'deadline' => false,
                'url' => null, 'notes' => $reminder->notes,
            ]);
        }

        return $events->sortBy('at')->values();
    }
}
