<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WorkPlanData
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     project_title: string,
     *     total_duration_months: int,
     *     total_duration_label: string,
     *     year_count: int,
     *     planned_start: string,
     *     planned_end: string,
     *     entries: array<int, array{objective: string, expected_output: string, activity: string, months: array<int, int>}>,
     *     prepared_by: string,
     *     verified_by: string,
     *     verified_role: string
     * }
     */
    public static function fromValidated(array $validated): array
    {
        $duration = (int) ($validated['total_duration_months'] ?? 0);
        $plannedStart = self::date($validated['planned_start'] ?? null);
        $plannedEnd = self::date($validated['planned_end'] ?? null);

        return [
            'project_title' => (string) ($validated['project_title'] ?? ''),
            'total_duration_months' => $duration,
            'total_duration_label' => $duration > 0 ? $duration.' '.Str::plural('month', $duration) : '',
            'year_count' => $duration > 0 ? (int) ceil($duration / 12) : 0,
            'planned_start' => $plannedStart,
            'planned_end' => $plannedEnd,
            'entries' => collect($validated['entries'] ?? [])
                ->map(fn (array $entry): array => [
                    'objective' => (string) ($entry['objective'] ?? ''),
                    'expected_output' => (string) ($entry['expected_output'] ?? ''),
                    'activity' => (string) ($entry['activity'] ?? ''),
                    'months' => collect($entry['months'] ?? [])
                        ->map(fn (string|int $month): int => (int) $month)
                        ->sort()
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'prepared_by' => (string) ($validated['prepared_by'] ?? ''),
            'verified_by' => config('work_plan.verifier.name'),
            'verified_role' => config('work_plan.verifier.role'),
        ];
    }

    private static function date(mixed $value): string
    {
        return blank($value) ? '' : Carbon::parse($value)->format('F j, Y');
    }
}
