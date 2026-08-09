<?php

namespace App\Services;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use NumberFormatter;

class NoticeToProceedDataService
{
    /** @return array<string, mixed> */
    public function defaults(TopicProposal $topic): array
    {
        $topic->loadMissing(['user', 'researchCall', 'versions.files']);

        $latestVersion = $topic->versions->sortByDesc('version_number')->first();
        $detailedProposal = $latestVersion?->files
            ->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)?->source_data ?? [];
        $lineItemBudget = $latestVersion?->files
            ->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)?->source_data ?? [];
        $workPlan = $latestVersion?->files
            ->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN)?->source_data ?? [];

        $startDate = $this->dateValue(
            $lineItemBudget['planned_start']
                ?? $workPlan['planned_start']
                ?? $topic->researchCall?->implementation_start_date,
        );
        $endDate = $this->dateValue(
            $lineItemBudget['planned_end']
                ?? $workPlan['planned_end']
                ?? $topic->researchCall?->implementation_end_date,
        );

        $duration = (int) ($latestVersion?->estimated_duration_months
            ?? $topic->estimated_duration_months
            ?? 0);

        if ($duration < 1 && $startDate && $endDate) {
            $duration = max(1, (int) round($startDate->floatDiffInMonths($endDate)));
        }

        $budget = $lineItemBudget['project_total']
            ?? $lineItemBudget['computed_project_total']
            ?? $latestVersion?->estimated_budget
            ?? $topic->estimated_budget
            ?? 0;

        $defaults = [
            'notice_date' => now()->toDateString(),
            'researcher_names' => $this->researcherNames($topic, $detailedProposal, $lineItemBudget),
            'campus_line' => (string) config('notice_to_proceed.campus_line'),
            'project_title' => $latestVersion?->title ?: $topic->title,
            'resolution_number' => trim((string) ($lineItemBudget['resolution_number'] ?? '')),
            'resolution_year' => (int) ($lineItemBudget['resolution_year'] ?? now()->year),
            'approved_start_date' => $startDate?->toDateString(),
            'approved_end_date' => $endDate?->toDateString(),
            'approved_duration_months' => $duration > 0 ? $duration : null,
            'approved_budget' => number_format((float) $budget, 2, '.', ''),
            'issuing_officer_name' => (string) config('notice_to_proceed.issuing_officer.name'),
            'issuing_officer_title' => (string) config('notice_to_proceed.issuing_officer.title'),
            'issuing_officer_committee_role' => (string) config('notice_to_proceed.issuing_officer.committee_role'),
            'verifying_officer_name' => (string) config('notice_to_proceed.verifying_officer.name'),
            'verifying_officer_title' => (string) config('notice_to_proceed.verifying_officer.title'),
            'verifying_officer_committee_role' => (string) config('notice_to_proceed.verifying_officer.committee_role'),
        ];

        return array_replace($defaults, $topic->notice_to_proceed_data ?? []);
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    public function snapshot(array $validated): array
    {
        return [
            ...Arr::only($validated, [
                'notice_date',
                'researcher_names',
                'campus_line',
                'project_title',
                'resolution_number',
                'resolution_year',
                'approved_start_date',
                'approved_end_date',
                'approved_duration_months',
                'approved_budget',
                'issuing_officer_name',
                'issuing_officer_title',
                'issuing_officer_committee_role',
                'verifying_officer_name',
                'verifying_officer_title',
                'verifying_officer_committee_role',
            ]),
            'researcher_names' => collect($validated['researcher_names'])
                ->map(fn (mixed $name): string => trim((string) $name))
                ->filter()
                ->unique(fn (string $name): string => mb_strtolower($name))
                ->values()
                ->all(),
            'approved_budget' => number_format((float) $validated['approved_budget'], 2, '.', ''),
            'approved_duration_months' => (int) $validated['approved_duration_months'],
            'resolution_year' => (int) $validated['resolution_year'],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    public function documentValues(array $data): array
    {
        $duration = (int) $data['approved_duration_months'];
        $budget = round((float) $data['approved_budget'], 2);

        return [
            'NOTICE_DATE' => $this->formatDate($data['notice_date']),
            'RESEARCHER_NAMES' => implode("\n", $data['researcher_names']),
            'CAMPUS_LINE' => (string) $data['campus_line'],
            'PROJECT_TITLE' => trim((string) $data['project_title'], " \t\n\r\0\x0B.\"'"),
            'RESOLUTION_NUMBER' => (string) $data['resolution_number'],
            'RESOLUTION_YEAR' => (string) $data['resolution_year'],
            'DURATION_WORDS' => $this->spellNumber($duration),
            'DURATION_MONTHS' => (string) $duration,
            'DURATION_UNIT' => $duration === 1 ? 'month' : 'months',
            'START_DATE' => $this->formatDate($data['approved_start_date']),
            'END_DATE' => $this->formatDate($data['approved_end_date']),
            'BUDGET_WORDS' => $this->moneyInWords($budget),
            'BUDGET_AMOUNT' => number_format($budget, 2),
            'ISSUING_OFFICER_NAME' => (string) $data['issuing_officer_name'],
            'ISSUING_OFFICER_TITLE' => (string) $data['issuing_officer_title'],
            'ISSUING_OFFICER_COMMITTEE_ROLE' => (string) $data['issuing_officer_committee_role'],
            'VERIFYING_OFFICER_NAME' => (string) $data['verifying_officer_name'],
            'VERIFYING_OFFICER_TITLE' => (string) $data['verifying_officer_title'],
            'VERIFYING_OFFICER_COMMITTEE_ROLE' => (string) $data['verifying_officer_committee_role'],
        ];
    }

    /** @param array<string, mixed> $detailedProposal @param array<string, mixed> $lineItemBudget @return list<string> */
    private function researcherNames(TopicProposal $topic, array $detailedProposal, array $lineItemBudget): array
    {
        $leader = trim((string) ($detailedProposal['project_leader_display'] ?? ''));

        if ($leader === '') {
            $leader = trim((string) ($lineItemBudget['project_leader_display'] ?? $lineItemBudget['project_leader'] ?? ''));
        }

        $names = collect([$leader]);
        $staff = Arr::wrap($detailedProposal['staff'] ?? []);

        if ($staff === []) {
            $staff = Arr::wrap($lineItemBudget['staff'] ?? []);
        }

        foreach ($staff as $member) {
            if (! is_array($member)) {
                continue;
            }

            $display = trim((string) ($member['display_name'] ?? ''));
            $title = trim((string) ($member['title'] ?? ''));
            $name = trim((string) ($member['name'] ?? ''));
            $names->push($display !== '' ? $display : trim($title.' '.$name));
        }

        $unique = $names
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values();

        if ($unique->isEmpty() && $topic->user?->name) {
            $unique->push($topic->user->name);
        }

        return $unique->all();
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('F j, Y');
    }

    private function spellNumber(int $number): string
    {
        $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);

        return str_replace('-', ' ', (string) $formatter->format($number));
    }

    private function moneyInWords(float $amount): string
    {
        $centavos = (int) round(($amount - floor($amount)) * 100);
        $words = $this->spellNumber((int) floor($amount)).' pesos';

        if ($centavos > 0) {
            $words .= ' and '.$this->spellNumber($centavos).' centavos';
        }

        return $words;
    }
}
