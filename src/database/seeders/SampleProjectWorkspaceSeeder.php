<?php

namespace Database\Seeders;

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringQuarterService;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use App\Support\TerminalReportRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SampleProjectWorkspaceSeeder extends Seeder
{
    public function run(
        MonitoringQuarterService $schedule,
        ApprovedWorkPlanMonitoringService $workPlan,
        ProgressReportData $progressData,
        TerminalReportData $terminalData,
    ): void {
        if (app()->environment('production')) {
            throw new RuntimeException('Sample workspaces may not be added in production.');
        }

        $topics = TopicProposal::query()
            ->where('description', 'like', '[lifecycle-demo:%')
            ->with(['user', 'latestVersion.files', 'collaborators'])
            ->orderBy('id')
            ->get();

        foreach ($topics as $topic) {
            DB::transaction(function () use ($topic, $schedule, $workPlan, $progressData, $terminalData): void {
                $this->proposalDraft($topic);

                if ($topic->isMonitoringAvailable()) {
                    $this->reportDrafts($topic, $schedule, $workPlan, $progressData, $terminalData);
                }

                if ($topic->hasIssuedNoticeToProceed()) {
                    $practice = $this->practiceProject($topic, $schedule);
                    $this->reportDrafts($practice, $schedule, $workPlan, $progressData, $terminalData);
                }
            });

            $this->command?->info('Sample drafts ready: '.$topic->title);
        }

        $this->command?->info('Existing submissions and saved drafts were preserved.');
    }

    private function proposalDraft(TopicProposal $topic): void
    {
        $title = Str::limit($topic->title, 255, '');
        $legacyTitle = Str::limit('[Sample draft] '.$topic->title, 255, '');
        $existing = ProposalDraft::query()
            ->where('user_id', $topic->user_id)->whereNull('topic_id')
            ->whereIn('project_title', [$title, $legacyTitle])->first();

        if ($existing !== null) {
            if ($existing->project_title === $legacyTitle) {
                $existing->update(['project_title' => $title, 'lock_version' => $existing->lock_version + 1]);
                foreach ($existing->documents as $document) {
                    $data = $document->source_data ?? [];
                    if (($data['project_title'] ?? null) !== $legacyTitle) {
                        continue;
                    }
                    $data['project_title'] = $title;
                    $changes = ['source_data' => $data, 'lock_version' => $document->lock_version + 1];
                    if ($document->document_type !== 'curriculum_vitae') {
                        $changes += [
                            'file_path' => null, 'original_filename' => null, 'mime_type' => null,
                            'file_size' => null, 'checksum' => null,
                        ];
                    }
                    $document->update($changes);
                }
            }

            return;
        }

        $start = today()->addDays(14);
        $duration = max(1, (int) ($topic->estimated_duration_months ?: 9));
        $draft = ProposalDraft::query()->firstOrCreate(
            ['user_id' => $topic->user_id, 'project_title' => $title, 'topic_id' => null],
            [
                'duration_months' => $duration,
                'planned_start' => $start,
                'planned_end' => $start->copy()->addMonthsNoOverflow($duration)->subDay(),
                'project_leader' => $topic->user->name,
                'signatory_selections' => [],
            ],
        );

        if (! $draft->wasRecentlyCreated) {
            return;
        }

        foreach ($topic->latestVersion?->files ?? [] as $file) {
            if (! in_array($file->document_type, ['detailed_proposal', 'work_plan', 'expense_breakdown', 'line_item_budget', 'curriculum_vitae'], true)) {
                continue;
            }

            $data = $file->source_data ?? [];
            $data['project_title'] = $title;
            if (in_array($file->document_type, ['work_plan', 'line_item_budget'], true)) {
                $data['planned_start'] = $draft->planned_start->toDateString();
                $data['planned_end'] = $draft->planned_end->toDateString();
            }
            $draft->documents()->firstOrCreate(
                ['document_type' => $file->document_type, 'position' => $file->position],
                ['source_data' => $data, 'completed_at' => now(), 'lock_version' => 0],
            );
        }

        foreach ($topic->collaborators as $collaborator) {
            if ($collaborator->user_id === $topic->user_id) {
                continue;
            }
            $draft->members()->create([
                'user_id' => $collaborator->user_id, 'name' => $collaborator->name,
                'email' => $collaborator->email, 'accepted_at' => $collaborator->accepted_at,
            ]);
        }
    }

    private function practiceProject(TopicProposal $source, MonitoringQuarterService $schedule): TopicProposal
    {
        $marker = '[report-draft-demo:'.$source->id.']';
        $existing = TopicProposal::query()->where('description', 'like', $marker.'%')->first();
        if ($existing !== null) {
            return $existing;
        }

        $duration = max(1, (int) ($source->estimated_duration_months ?: 9));
        $start = today()->addDays(14);
        $topic = TopicProposal::query()->create([
            'user_id' => $source->user_id,
            'research_call_id' => $source->research_call_id,
            'research_category_id' => $source->research_category_id,
            'title' => Str::limit('[Draft sample] '.$source->title, 255, ''),
            'description' => $marker.' UI DEMONSTRATION ONLY. Editable report practice using project #'.$source->id.' as a reference; no actual approval or research outcomes are represented.',
            'estimated_budget' => $source->estimated_budget,
            'estimated_duration_months' => $duration,
            'status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_ONGOING,
            'notice_to_proceed_issued_at' => now(),
            'notice_to_proceed_data' => [
                'approved_start_date' => $start->toDateString(),
                'approved_end_date' => $start->copy()->addMonthsNoOverflow($duration)->subDay()->toDateString(),
                'approved_duration_months' => $duration,
            ],
        ]);

        if ($source->latestVersion !== null) {
            $version = $topic->versions()->create([
                ...$source->latestVersion->only(['file_path', 'original_filename', 'mime_type', 'file_size', 'checksum', 'estimated_budget', 'estimated_duration_months']),
                'title' => $topic->title, 'submitted_by' => $topic->user_id,
                'version_number' => 1, 'submission_type' => 'initial',
                'change_summary' => 'UI sample: reference proposal documents copied for private report drafting practice.',
            ]);
            $window = $schedule->reportingWindow($topic);
            foreach ($source->latestVersion->files as $file) {
                if ($file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD) {
                    continue;
                }
                $data = $file->source_data ?? [];
                $data['project_title'] = $topic->title;
                if (in_array($file->document_type, ['work_plan', 'line_item_budget'], true)) {
                    $data['planned_start'] = $window['start']->toDateString();
                    $data['planned_end'] = $window['end']->toDateString();
                }
                $version->files()->create([
                    ...$file->only(['document_type', 'position', 'file_path', 'original_filename', 'mime_type', 'file_size', 'checksum']),
                    'source_data' => $data, 'uploaded_by' => $topic->user_id,
                ]);
            }
        }

        foreach ($source->collaborators as $collaborator) {
            $topic->collaborators()->create($collaborator->only(['user_id', 'name', 'email', 'accepted_at', 'project_role']));
        }

        return $topic;
    }

    private function reportDrafts(
        TopicProposal $topic,
        MonitoringQuarterService $schedule,
        ApprovedWorkPlanMonitoringService $workPlan,
        ProgressReportData $progressData,
        TerminalReportData $terminalData,
    ): void {
        $window = $schedule->reportingWindow($topic);
        $monitoringPeriod = $schedule->summaryRows($topic->progressReports()->get(), $topic)
            ->first(fn (array $row): bool => $row['report'] === null || $row['report']->isPrepared());
        $progressPeriod = $schedule->narrativeProgressPeriods($topic)
            ->first(fn (array $row): bool => $row['drafting_date'] !== null);
        $users = $topic->collaborators()->whereNotNull('accepted_at')->whereNotNull('user_id')->pluck('user_id')->push($topic->user_id)->unique();

        foreach ($users as $userId) {
            if ($monitoringPeriod !== null) {
                $rows = collect($workPlan->defaultsForDate($topic, $monitoringPeriod['end']))
                    ->map(fn (array $row): array => [
                        ...$row, 'actual_accomplishment' => 'SAMPLE ONLY: Initial baseline records compiled for team validation.',
                        'accomplished_percentage' => round((float) ($row['percent_weight'] ?? 0) / 4, 2),
                        'findings' => 'SAMPLE ONLY: Validate the dataset and document any schedule adjustments.',
                    ])->all();
                ProjectMonitoringDraft::query()->firstOrCreate(
                    ['topic_id' => $topic->id, 'user_id' => $userId, 'source_key' => 'new'],
                    ['source_data' => [
                        'reporting_date' => $monitoringPeriod['end']->toDateString(),
                        'tracking_number' => 'SAMPLE-MT-'.$topic->id,
                        'work_plan' => $rows,
                        'budget_utilization' => collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])
                            ->map(fn (string $type): array => [
                                'type' => $type, 'details' => 'SAMPLE ONLY: Pilot materials and field coordination.',
                                'amount_requested' => round((float) $topic->estimated_budget / 4, 2),
                                'actual_amount' => 0, 'remarks' => 'Draft example; no actual expenditure recorded.',
                            ])->all(),
                    ], 'lock_version' => 1],
                );
            }

            foreach (['progress', 'terminal'] as $type) {
                if ($type === 'progress' && $progressPeriod === null) {
                    continue;
                }
                $data = $type === 'progress' ? $progressData->defaults($topic) : $terminalData->defaults($topic);
                $data = collect($data)->except(['missing_monitoring_periods', 'monitoring_reference', 'signatory_options', 'objectives_from_work_plan', 'source_proposal_version'])->all();
                $data = [
                    ...$data, 'report_type' => $type, 'tracking_number' => 'SAMPLE-'.strtoupper($type).'-'.$topic->id,
                    'submission_date' => today()->toDateString(),
                    'introduction' => 'SAMPLE ONLY: This editable '.$type.' report illustrates the documentation for '.$topic->title.'. '.($data['introduction'] ?? ''),
                    'results_discussion' => 'SAMPLE ONLY: An illustrative pilot invited 30 participants and completed 26 sessions. The four remaining sessions and the limited sample should be discussed when interpreting the findings.',
                    'accomplishments' => collect($data['accomplishments'] ?? [])->map(fn (array $row): array => [
                        ...$row, 'actual' => 'SAMPLE ONLY: Initial pilot activities documented; replace with verified project results.',
                    ])->all(),
                ];
                if ($type === 'progress') {
                    $data['reporting_date'] = $progressPeriod['end']->toDateString();
                    $data['implementation_start'] = $window['start']->toDateString();
                    $data['implementation_end'] = $window['end']->toDateString();
                } else {
                    $data['terminal_data'] = [
                        ...$data['terminal_data'],
                        'abstract' => 'SAMPLE ONLY: This study develops and evaluates the approved intervention through baseline collection, pilot testing, and stakeholder feedback.',
                        'conclusions' => 'SAMPLE ONLY: Pilot findings indicate potential improvements in usability and record consistency. Validate these conclusions against the actual results.',
                        'recommendations' => 'SAMPLE ONLY: Extend validation, document study limitations, and train the intended users before wider adoption.',
                    ];
                    $data['terminal_data'] = collect($data['terminal_data'])->only([
                        ...TerminalReportRules::NARRATIVES, 'authors', 'signatories', 'tables',
                        'collaborating_agency', 'total_expenditure',
                    ])->all();
                }
                ProjectNarrativeReportDraft::query()->firstOrCreate(
                    ['topic_id' => $topic->id, 'user_id' => $userId, 'report_type' => $type],
                    ['source_data' => $data, 'lock_version' => 1],
                );
            }
        }
    }
}
