<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringQuarterService;
use App\Services\MonitoringToolDocumentService;
use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;
use App\Services\ProgressReportDocumentService;
use App\Services\ProposalSignatureWorkflow;
use App\Services\TerminalReportDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PostApprovalUiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UI demonstration data may not be seeded in production.');
        }
        $head = User::role('research_head')->orderBy('id')->firstOrFail();
        $viewer = TopicProposal::where('description', 'like', '[lifecycle-demo:ongoing]%')->firstOrFail()->user;
        $scenarios = ['ongoing', 'delayed', 'ongoing-second', 'delayed-second', 'completed-presented', 'completed-published', 'completed-published-second'];
        $topics = collect($scenarios)->mapWithKeys(fn (string $scenario): array => [
            $scenario => TopicProposal::where('description', 'like', '[lifecycle-demo:'.$scenario.']%')->firstOrFail(),
        ]);
        $backupPath = 'post-approval-ui-demo/before-seeding.json';
        if (! Storage::disk('local')->exists($backupPath)) {
            Storage::disk('local')->put($backupPath, $topics->map(fn (TopicProposal $topic): array => $topic->load([
                'progressReports', 'preparedProgressReports', 'narrativeReports', 'collaborators', 'versions.files',
            ])->toArray())->toJson(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        foreach ($scenarios as $scenario) {
            $topic = $topics[$scenario];
            $completed = str_starts_with($scenario, 'completed');
            $terminal = $completed || in_array($scenario, ['ongoing-second', 'delayed-second'], true);
            $start = now()->startOfDay()->subMonthsNoOverflow($terminal ? 12 : 10);
            $months = $terminal ? 9 : 12;
            $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
            $topic->update([
                'status' => 'approved', 'estimated_duration_months' => $months,
                'notice_to_proceed_issued_at' => $start, 'notice_to_proceed_issued_by' => $head->id,
                'project_status' => $completed ? 'completed' : ($scenario === 'delayed' ? 'delayed' : 'ongoing'),
                'notice_to_proceed_data' => array_replace(app(NoticeToProceedDataService::class)->defaults($topic), [
                    'notice_date' => $start->toDateString(), 'approved_start_date' => $start->toDateString(),
                    'approved_end_date' => $end->toDateString(), 'approved_duration_months' => $months,
                    'resolution_number' => 'UI-DEMO-'.$topic->id, 'resolution_year' => $start->year,
                ]),
            ]);
            if ($viewer->id !== $topic->user_id) {
                $topic->collaborators()->updateOrCreate(['user_id' => $viewer->id], [
                    'name' => $viewer->name, 'email' => $viewer->email, 'accepted_at' => $start, 'project_role' => 'secretary',
                ]);
                $topic->update(['research_secretary_id' => $viewer->id]);
            }
            $this->notice($topic);
            $this->signedPapers($topic, $head);
            $periods = app(MonitoringQuarterService::class)->projectPeriods($topic)->take(3);
            foreach ($periods as $index => $period) {
                $prepared = $scenario === 'ongoing' && $index === 2;
                $review = $prepared || ($scenario === 'ongoing' && $index === 1)
                    ? 'pending' : ($scenario === 'delayed' && $index === 2 ? 'revision_requested' : 'reviewed');
                $progress = $terminal ? [35, 70, 100][$index] : [25, 50, 65][$index];
                $rows = $periods->take($index + 1)
                    ->flatMap(fn (array $elapsed): array => app(ApprovedWorkPlanMonitoringService::class)->defaultsForDate($topic, $elapsed['end']))
                    ->unique('source_work_plan_index')->values()->all();
                $rows = collect($rows)->map(fn (array $row): array => [
                    ...$row,
                    'actual_accomplishment' => 'UI demo: '.$row['physical_target'].' documented through pilot testing and team validation.',
                    'accomplished_percentage' => round((float) $row['percent_weight'] * $progress / 100, 2),
                    'findings' => $review === 'revision_requested' ? 'Participant recruitment delayed validation. Revised recovery dates and supporting evidence are required.' : 'Pilot feedback recorded; validation follows the approved schedule.',
                ])->all();
                $report = ProjectProgressReport::updateOrCreate(
                    ['topic_id' => $topic->id, 'tracking_number' => 'LIFE-'.$topic->id.'-Q'.$period['quarter']],
                    [
                        'submitted_by' => $topic->user_id,
                        'reporting_date' => $period['end'], 'reporting_year' => $period['year'],
                        'reporting_quarter' => $period['quarter'], 'period_start' => $period['start'], 'period_end' => $period['end'],
                        'version_number' => 1, 'progress_percentage' => (int) round(array_sum(array_column($rows, 'accomplished_percentage'))),
                        'work_plan' => $rows, 'accomplishments' => collect($rows)->pluck('actual_accomplishment')->implode("\n"),
                        'issues' => $review === 'revision_requested' ? 'Recruitment and procurement delayed the validation phase.' : 'No unresolved issues for this period.',
                        'budget_utilization' => $this->budget($topic, ($index + 1) / 4),
                        'budget_prepared_by' => $prepared ? null : $viewer->id,
                        'budget_prepared_at' => $prepared ? null : $period['opens_at'],
                        'submission_status' => $prepared ? 'prepared' : 'submitted',
                        'prepared_at' => $period['opens_at'], 'submitted_at' => $prepared ? null : $period['opens_at'],
                        'review_status' => $review,
                        'research_head_remarks' => match ($review) {
                            'reviewed' => 'UI demo: Accomplishments and budget evidence reviewed. Continue the approved work plan.',
                            'revision_requested' => 'UI demo: Add revised recruitment dates, explain the variance, and attach validation evidence.',
                            default => null,
                        },
                        'reviewed_by' => $review === 'pending' ? null : $head->id,
                        'reviewed_at' => $review === 'pending' ? null : $period['opens_at']->addDays(2),
                    ],
                );
                $this->pdf($report);
            }
            $this->narrative($topic, $head, 'progress', $scenario === 'delayed' ? 'revision_requested' : 'reviewed');
            if ($terminal) {
                $report = $this->narrative($topic, $head, 'terminal', $scenario === 'ongoing-second' ? 'pending' : 'reviewed');
                if ($completed) {
                    $report->update(['terminal_data' => [...$report->terminal_data, 'signed_copy' => [
                        'path' => $report->official_pdf_path, 'original_filename' => 'ui-demo-signed-terminal-report.pdf',
                        'mime_type' => 'application/pdf', 'size' => $report->official_pdf_size,
                        'checksum' => $report->official_pdf_checksum, 'uploaded_by' => $head->id,
                        'uploaded_at' => now()->toIso8601String(), 'demo' => true,
                    ]]]);
                }
            }
            $this->command?->info($scenario.': #'.$topic->id.' '.$topic->title);
        }
    }

    /** @return list<array<string, mixed>> */
    private function budget(TopicProposal $topic, float $fraction): array
    {
        return collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])
            ->map(fn (string $type, int $index): array => [
                'type' => $type,
                'details' => ['Pilot equipment and consumables', 'Field visits and participant coordination', 'Validation and documentation services'][$index],
                'amount_requested' => floor((float) $topic->estimated_budget / 3 * 100) / 100,
                'actual_amount' => round((float) $topic->estimated_budget / 3 * $fraction, 2),
                'remarks' => 'UI demo: supporting records filed for this reporting period.',
            ])->all();
    }

    private function narrative(TopicProposal $topic, User $head, string $type, string $review): ProjectNarrativeReport
    {
        $window = app(MonitoringQuarterService::class)->reportingWindow($topic);
        $rows = collect($topic->progressReports()->orderByDesc('reporting_date')->first()->work_plan)
            ->map(fn (array $row): array => ['objective' => $row['objective'] ?? '', 'target' => $row['physical_target'], 'actual' => $row['actual_accomplishment']])->all();
        $photoPath = 'post-approval-ui-demo/campus.jpg';
        if ($type === 'terminal' && ! Storage::disk('local')->exists($photoPath)) {
            Storage::disk('local')->put($photoPath, file_get_contents(public_path('images/nasugbu.jpg')));
        }
        $report = ProjectNarrativeReport::updateOrCreate(
            ['topic_id' => $topic->id, 'tracking_number' => 'LIFE-'.$topic->id.($type === 'terminal' ? '-TERMINAL' : '-NARRATIVE')],
            [
                'submitted_by' => $topic->user_id, 'report_type' => $type,
                'submission_date' => $type === 'terminal' ? $window['end']->addDay() : $window['start']->addMonths(3),
                'researchers' => $topic->user->name, 'implementation_start' => $window['start'], 'implementation_end' => $window['end'],
                'budget' => $topic->estimated_budget, 'funding_agency' => 'Batangas State University',
                'accomplishment_summary' => 'UI DEMONSTRATION DATA: The team completed baseline collection, piloted the intervention, and documented validation results for '.$topic->title.'.',
                'accomplishments' => $rows,
                'introduction' => 'UI DEMONSTRATION ONLY — sample records and signature status do not represent actual approvals. This project evaluates an intervention addressing the operational needs identified in the approved proposal.',
                'rationale' => 'Baseline observations identified inconsistent records and avoidable delays. The team tested an accessible intervention with representative users.',
                'objectives' => collect($rows)->pluck('objective')->unique()->implode("\n"),
                'methodology' => 'The team collected baseline measures, implemented a pilot, and compared performance through structured observations and participant feedback. Findings were checked against the approved physical targets.',
                'results_discussion' => 'The sample pilot involved 30 participants. Twenty-six completed the validation activities; four required follow-up. Feedback informed refinements to usability, record consistency, and staff guidance.',
                'photos' => $type === 'terminal' ? [['path' => $photoPath, 'caption' => 'UI demo image: existing campus photo used to preview the documentation layout.', 'section' => 'results_discussion', 'after_paragraph' => 1]] : [],
                ...($type === 'progress' ? app(ProgressReportUiDemoSeeder::class)->exampleData($topic) : []),
                'terminal_data' => $type === 'terminal' ? [
                    'version_number' => 1, 'approved_start' => $window['start']->toDateString(), 'approved_end' => $window['end']->toDateString(),
                    'approved_duration_months' => $topic->estimated_duration_months,
                    'approved_budget' => $topic->estimated_budget, 'total_expenditure' => round((float) $topic->estimated_budget * .75, 2),
                    'abstract' => 'UI demo: The project developed and evaluated the approved intervention. Baseline collection, pilot implementation, and validation provided evidence of improved operational consistency and usability.',
                    'literature_review' => 'The demonstration follows the research themes and references retained in the approved proposal.',
                    'conclusions' => 'The demonstration intervention met the approved targets. Continued staff training and periodic evaluation support adoption.',
                    'recommendations' => 'Present results at a research forum, prepare a manuscript, and document implementation feedback.',
                    'bibliography' => 'Refer to the approved detailed proposal for the source bibliography.',
                    'source_monitoring_report_ids' => $topic->progressReports()->submitted()->pluck('id')->all(),
                ] : null,
                'submission_status' => 'submitted', 'submitted_at' => now()->subDays(3),
                'review_status' => $review, 'reviewed_by' => $review === 'pending' ? null : $head->id,
                'reviewed_at' => $review === 'pending' ? null : now()->subDay(),
                'research_head_remarks' => match ($review) {
                    'reviewed' => 'UI demo: Results reviewed. Record the fully signed Terminal Report before closing the project.',
                    'revision_requested' => 'UI demo: Explain the participant shortfall and provide a revised validation schedule.',
                    default => null,
                },
            ],
        );
        $this->pdf($report);

        return $report;
    }

    private function pdf(ProjectProgressReport|ProjectNarrativeReport $report): void
    {
        $path = 'post-approval-ui-demo/'.$report->topic_id.'/'.($report instanceof ProjectProgressReport ? 'monitoring' : $report->report_type).'-'.$report->id.'.pdf';
        $service = $report instanceof ProjectProgressReport ? MonitoringToolDocumentService::class
            : ($report->report_type === 'terminal' ? TerminalReportDocumentService::class : ProgressReportDocumentService::class);
        $pdf = app(DocumentPdfConverter::class)->convertDocx(app($service)->generate($report));
        if (! Storage::disk('local')->put($path, $pdf)) {
            throw new RuntimeException('Unable to store UI demo report PDF.');
        }
        $report->update(['official_pdf_path' => $path, 'official_pdf_filename' => 'ui-demo-'.basename($path),
            'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf)]);
    }

    private function notice(TopicProposal $topic): void
    {
        if (filled($topic->notice_to_proceed_path) && Storage::disk('local')->exists($topic->notice_to_proceed_path)) {
            return;
        }
        $values = app(NoticeToProceedDataService::class)->documentValues($topic->notice_to_proceed_data);
        $values['PROJECT_TITLE'] = 'UI DEMO ONLY — '.$topic->title;
        $pdf = app(DocumentPdfConverter::class)->convertDocx(app(NoticeToProceedDocumentService::class)->generate($values));
        $path = 'post-approval-ui-demo/'.$topic->id.'/demo-notice-to-proceed.pdf';
        Storage::disk('local')->put($path, $pdf);
        $topic->update(['notice_to_proceed_path' => $path, 'notice_to_proceed_original_filename' => 'ui-demo-notice-to-proceed.pdf']);
    }

    private function signedPapers(TopicProposal $topic, User $head): void
    {
        $version = $topic->latestVersion()->with('files')->firstOrFail();
        foreach (app(ProposalSignatureWorkflow::class)->missingRequiredFiles($version) as $source) {
            if ($source->mime_type !== 'application/pdf' || ! filled($source->file_path) || ! Storage::disk('local')->exists($source->file_path)) {
                continue;
            }
            $path = 'post-approval-ui-demo/'.$topic->id.'/papers/'.$source->document_type.'.pdf';
            Storage::disk('local')->put($path, Storage::disk('local')->get($source->file_path));
            $version->files()->updateOrCreate([
                'source_version_file_id' => $source->id,
                'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
                'original_filename' => 'ui-demo-signed-'.$source->document_type.'.pdf',
            ], [
                'position' => 100 + $source->id, 'file_path' => $path,
                'mime_type' => 'application/pdf', 'file_size' => $source->file_size,
                'checksum' => $source->checksum, 'uploaded_by' => $head->id,
                'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                    'target_document_type' => $source->document_type,
                    'note' => 'UI DEMO ONLY: original sample paper reused to preview signed-copy placement. No actual signature or approval is represented.',
                    'demo' => true,
                ],
            ]);
        }
    }
}
