<?php

namespace App\Actions;

use App\Models\ResearchCall;
use App\Models\User;
use App\Notifications\ResearchCallDeadlineReminderNotification;
use App\Notifications\ResearchCallOpeningReminderNotification;
use App\Notifications\ResearchCallPublishedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProcessResearchCallOpeningNotifications
{
    public function handle(): int
    {
        $now = now();
        $processed = 0;

        ResearchCall::query()
            ->where('status', 'open')
            ->where('closes_at', '>=', $now)
            ->where(function ($query) use ($now): void {
                $query
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('faculty_open_notification_sent_at')
                            ->where('opens_at', '<=', $now);
                    })
                    ->orWhere(function ($query) use ($now): void {
                        $query->whereNull('opening_reminder_sent_at')
                            ->where('opens_at', '>', $now)
                            ->where('opens_at', '<=', $now->copy()->addDay());
                    });
            })
            ->eachById(function (ResearchCall $researchCall) use (&$processed): void {
                if ($this->process($researchCall)) {
                    $processed++;
                }
            });

        ResearchCall::query()
            ->acceptingSubmissions()
            ->where('closes_at', '<=', $now->copy()->addDays(7))
            ->eachById(function (ResearchCall $researchCall) use (&$processed): void {
                $processed += $this->processDeadlineReminder($researchCall);
            });

        return $processed;
    }

    public function process(ResearchCall $researchCall, bool $includeReminder = true): bool
    {
        $notification = DB::transaction(function () use ($researchCall, $includeReminder): ?array {
            $lockedCall = ResearchCall::query()
                ->lockForUpdate()
                ->find($researchCall->id);

            if ($lockedCall === null || $lockedCall->status !== 'open' || $lockedCall->closes_at->isPast()) {
                return null;
            }

            $now = now();

            if ($lockedCall->opens_at->lte($now)) {
                if ($lockedCall->faculty_open_notification_sent_at !== null) {
                    return null;
                }

                $lockedCall->update(['faculty_open_notification_sent_at' => $now]);

                return ['type' => 'faculty', 'researchCall' => $lockedCall];
            }

            if (! $includeReminder
                || $lockedCall->opening_reminder_sent_at !== null
                || $lockedCall->opens_at->gt($now->copy()->addDay())) {
                return null;
            }

            $lockedCall->update(['opening_reminder_sent_at' => $now]);

            return ['type' => 'research_head', 'researchCall' => $lockedCall];
        }, attempts: 3);

        if ($notification === null) {
            return false;
        }

        /** @var ResearchCall $lockedCall */
        $lockedCall = $notification['researchCall'];

        if ($notification['type'] === 'faculty') {
            Notification::sendNow(
                User::assignedToRole(User::WORKSPACE_FACULTY)->get(),
                new ResearchCallPublishedNotification(
                    $lockedCall->id,
                    $lockedCall->title,
                    route('faculty.dashboard'),
                ),
            );

            return true;
        }

        Notification::sendNow(
            $this->researchHeadRecipients($lockedCall),
            new ResearchCallOpeningReminderNotification(
                $lockedCall->id,
                $lockedCall->title,
                $lockedCall->opens_at,
                route('research-calls.index'),
            ),
        );

        return true;
    }

    public function processDeadlineReminder(ResearchCall $researchCall): int
    {
        $notification = DB::transaction(function () use ($researchCall): ?array {
            $lockedCall = ResearchCall::query()
                ->lockForUpdate()
                ->find($researchCall->id);

            if ($lockedCall === null || ! $lockedCall->isAcceptingSubmissions()) {
                return null;
            }

            $stage = now()->greaterThanOrEqualTo($lockedCall->closes_at->copy()->subDay())
                ? 'final'
                : 'approaching';
            $deadlineAt = $lockedCall->closes_at->toIso8601String();
            $pendingRecipients = User::assignedToRole(User::WORKSPACE_FACULTY)
                ->whereDoesntHave('notifications', function (Builder $query) use ($lockedCall, $stage, $deadlineAt): void {
                    $query
                        ->where('data->research_call_id', $lockedCall->id)
                        ->where('data->deadline_notification_stage', $stage)
                        ->where('data->deadline_at', $deadlineAt);
                })
                ->get();

            if ($pendingRecipients->isEmpty()) {
                return null;
            }

            return [
                'researchCall' => $lockedCall,
                'stage' => $stage,
                'recipients' => $pendingRecipients,
            ];
        }, attempts: 3);

        if ($notification === null) {
            return 0;
        }

        /** @var ResearchCall $lockedCall */
        $lockedCall = $notification['researchCall'];

        Notification::sendNow(
            $notification['recipients'],
            new ResearchCallDeadlineReminderNotification(
                $lockedCall->id,
                $lockedCall->title,
                $lockedCall->closes_at,
                $notification['stage'],
                route('research-calls.index'),
            ),
        );

        return $notification['recipients']->count();
    }

    /** @return Collection<int, User> */
    private function researchHeadRecipients(ResearchCall $researchCall): Collection
    {
        $researchHeads = User::assignedToRole(User::WORKSPACE_RESEARCH_HEAD)->get();

        if ($researchCall->created_by === null) {
            return $researchHeads;
        }

        $creator = User::query()->find($researchCall->created_by);

        return $creator === null
            ? $researchHeads
            : $researchHeads->push($creator)->unique('id')->values();
    }
}
