<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WorkPlanData
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     title: string,
     *     project_title: string,
     *     total_duration_months: int,
     *     total_duration_label: string,
     *     planned_start: string,
     *     planned_end: string,
     *     entries: array<int, array{objective: string, expected_output: string, activity: string, months: array<int, int>}>,
     *     prepared_by: string,
     *     prepared_date: string,
     *     verified_by: string,
     *     verified_role: string,
     *     verified_date: string
     * }
     */
    public static function fromValidated(array $validated): array
    {
        $duration = (int) $validated['total_duration_months'];

        return [
            'title' => $validated['title'],
            'project_title' => $validated['project_title'],
            'total_duration_months' => $duration,
            'total_duration_label' => $duration.' '.Str::plural('month', $duration),
            'planned_start' => Carbon::parse($validated['planned_start'])->format('F j, Y'),
            'planned_end' => Carbon::parse($validated['planned_end'])->format('F j, Y'),
            'entries' => collect($validated['entries'])
                ->map(fn (array $entry): array => [
                    'objective' => $entry['objective'],
                    'expected_output' => $entry['expected_output'],
                    'activity' => $entry['activity'],
                    'months' => collect($entry['months'])
                        ->map(fn (string|int $month): int => (int) $month)
                        ->sort()
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'prepared_by' => $validated['prepared_by'],
            'prepared_date' => self::formattedDate($validated['prepared_date'] ?? null),
            'verified_by' => config('work_plan.verifier.name'),
            'verified_role' => config('work_plan.verifier.role'),
            'verified_date' => self::formattedDate($validated['verified_date'] ?? null),
        ];
    }

    private static function formattedDate(?string $date): string
    {
        return filled($date) ? Carbon::parse($date)->format('F j, Y') : '';
    }
}
