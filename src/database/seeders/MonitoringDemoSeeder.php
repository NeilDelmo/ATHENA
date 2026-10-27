<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\CollegeQuarterlyReportData;
use App\Services\CollegeQuarterlyReportFacultyEvidence;
use App\Services\CurriculumVitaeDocumentService;
use App\Services\DetailedProposalDocumentService;
use App\Services\ExpenseBreakdownDocumentService;
use App\Services\GADChecklistDocumentService;
use App\Services\InitialScreeningFormDocumentService;
use App\Services\LineItemBudgetDocumentService;
use App\Services\MonitoringQuarterService;
use App\Services\MonitoringToolDocumentService;
use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;
use App\Services\ProgressReportDocumentService;
use App\Services\ProposalPackageService;
use App\Services\ProposalSignatureWorkflow;
use App\Services\RmsQuarterlyReportData;
use App\Services\WorkPlanDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\DetailedProposalData;
use App\Support\ExpenseBreakdownData;
use App\Support\GADChecklistData;
use App\Support\LineItemBudgetData;
use App\Support\TerminalReportData;
use App\Support\TerminalReportRules;
use App\Support\WorkPlanData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MonitoringDemoSeeder extends Seeder
{
    private const MARKER = 'monitoring-proposal-demo';

    /** @var list<string> */
    private array $storedPaths = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Monitoring demonstrations may only run in local or testing environments.');
        }
        $existing = RmsQuarterlyReport::where('reporting_year', 2026)->where('reporting_quarter', 2)->first();
        if (($existing?->signatories['prepared']['office'] ?? null) === self::MARKER) {
            $this->command?->info('The Q2 2026 monitoring demonstration already exists. Saved edits were preserved.');

            return;
        }
        if ($existing || CollegeQuarterlyReport::where('reporting_year', 2026)->where('reporting_quarter', 2)->exists()) {
            throw new RuntimeException('Q2 2026 already contains a report. Existing work was left unchanged.');
        }
        $head = User::role('research_head')->orderBy('id')->firstOrFail();
        $coordinator = User::role('research_coordinator')->whereIn('college', array_values(User::COLLEGES))->orderBy('id')->firstOrFail();
        $faculty = User::role('faculty_researcher')->where('college', $coordinator->college)
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', 'research_head'))->orderBy('id')->firstOrFail();
        $secretary = User::role('research_secretary')->where('college', $coordinator->college)->orderBy('id')->firstOrFail();
        $this->storedPaths = [];
        try {
            DB::transaction(function () use ($head, $coordinator, $faculty, $secretary): void {
                $call = ResearchCall::create([
                    'title' => '[DEMO] Proposal to monitoring cohort', 'academic_year' => 'DEMO 2025-2026', 'term' => 'Demonstration',
                    'description' => self::MARKER.' — fictional proposal, implementation and reporting examples.',
                    'opens_at' => '2025-11-01', 'closes_at' => '2025-12-20', 'status' => 'closed', 'created_by' => $head->id,
                ]);
                $completed = $this->project($call, $faculty, $head, $secretary, true);
                $ongoing = $this->project($call, $faculty, $head, $secretary, false);
                $this->collegeReports($coordinator, $head, [$completed, $ongoing]);
                $this->targets($head);
                $this->command?->info('Completed archive / editable labels: #'.$completed->id.' '.$completed->title);
                $this->command?->info('Ongoing monitoring / status changes: #'.$ongoing->id.' '.$ongoing->title);
                $this->command?->info('Faculty: '.$faculty->email.' | Coordinator: '.$coordinator->email.' | Head: '.$head->email);
                $this->command?->info('Select Q2 2026 for college reports and RMS. Targets / analytics cohort: DEMO 2025-2026.');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($this->storedPaths);
            throw $exception;
        }
    }

    private function project(ResearchCall $call, User $faculty, User $head, User $secretary, bool $completed): TopicProposal
    {
        $months = $completed ? 6 : 12;
        $end = $completed ? '2026-06-30' : '2026-12-31';
        $topic = TopicProposal::create([
            'research_call_id' => $call->id, 'user_id' => $faculty->id,
            'title' => $completed ? '[DEMO] Community Water Quality Toolkit' : '[DEMO] Campus Energy Monitoring',
            'description' => '['.self::MARKER.'] Fictional end-to-end example. Proposal papers, reviews, approvals, signatures and results are simulated for demonstration only.',
            'estimated_budget' => 75000, 'estimated_duration_months' => $months, 'status' => 'pending', 'created_at' => '2025-12-01 09:00:00',
        ]);
        $common = ['project_title' => $topic->title, 'project_leader' => $faculty->name,
            'planned_start' => '2026-01-01', 'planned_end' => $end, 'total_duration_months' => $months,
            'prepared_by' => $faculty->name, 'verified_by' => 'DEMO verifier', 'verified_role' => 'Demonstration only'];
        $entries = [
            ['objective' => 'DEMO: Establish baseline needs.', 'activity' => 'DEMO: Baseline assessment', 'expected_output' => 'DEMO baseline dataset', 'months' => range(1, $completed ? 3 : 6)],
            ['objective' => 'DEMO: Validate the prototype.', 'activity' => 'DEMO: Pilot and evaluation', 'expected_output' => 'DEMO validated toolkit and adoption guide', 'months' => range($completed ? 4 : 7, $months)],
        ];
        $expense = ['project_title' => $topic->title, 'items' => [[
            'category' => 'mooe', 'account' => 'Supplies and Materials Expenses', 'sub_account' => 'Other Supplies and Materials Expenses',
            'particulars' => 'DEMO prototype supplies', 'details' => 'Fictional supplies', 'purpose' => 'Pilot evaluation', 'unit' => 'lot', 'quantity' => 1, 'unit_cost' => 75000,
        ]]];
        $budget = [...$common, 'leader_campus' => 'ARASOF-Nasugbu', 'leader_college' => $faculty->college,
            'custom_mooe_items' => [['particular' => 'DEMO prototype supplies', 'amount' => 75000]], 'certified_by' => 'DEMO certifier', 'certified_role' => 'Demonstration only'];
        $detail = [...$common, 'proponent_college' => $faculty->college, 'proponent_campus' => 'ARASOF-Nasugbu', 'sdgs' => [6, 9, 11],
            'general_objective' => 'DEMO: Develop and evaluate '.$topic->title.'.', 'specific_objectives' => array_column($entries, 'objective'),
            'executive_brief' => 'DEMO ONLY: A fictional pilot study demonstrating a complete research workflow.',
            'rationale' => 'DEMO ONLY: Establish the baseline and validate a practical prototype.',
            'methodology' => ['research_design' => 'DEMO: Developmental research with baseline and pilot evaluation.', 'specific_methods' => 'DEMO: Record baseline observations, build a prototype, and evaluate pilot results.', 'data_analysis' => 'DEMO: Compare baseline and pilot measurements.'],
            'references' => 'DEMO ONLY: No real sources or findings are claimed.'];
        $sources = [
            'detailed-proposal' => [DetailedProposalDocumentService::class, DetailedProposalData::fromValidated($detail, ['mooe_total' => 75000])],
            'work-plan' => [WorkPlanDocumentService::class, WorkPlanData::fromValidated([...$common, 'entries' => $entries])],
            'expense-breakdown' => [ExpenseBreakdownDocumentService::class, ExpenseBreakdownData::fromValidated($expense)],
            'line-item-budget' => [LineItemBudgetDocumentService::class, LineItemBudgetData::fromValidated($budget)],
            'curriculum-vitae' => [CurriculumVitaeDocumentService::class, CurriculumVitaeData::fromValidated(['people' => CurriculumVitaeData::seedPeople([$faculty->name])])],
            'gad-checklist' => [GADChecklistDocumentService::class, GADChecklistData::fromValidated([...$common, 'verifier_name' => 'DEMO verifier', 'verifier_role' => 'Demonstration only'])],
            'initial-screening-form' => [InitialScreeningFormDocumentService::class, $common],
        ];
        $papers = [];
        foreach ($sources as $slug => [$service, $data]) {
            $papers[$slug] = ['contents' => app($service)->generate($data), 'source_data' => $data];
        }
        $files = app(ProposalPackageService::class)->storeGeneratedPapers($papers, self::MARKER.'/'.$topic->id.'/proposal', $topic->title);
        array_push($this->storedPaths, ...array_column($files, 'file_path'));
        $primary = collect($files)->firstWhere('document_type', 'detailed_proposal');
        $initialFiles = [];
        foreach ([1, 2] as $number) {
            $version = $topic->versions()->create([
                'submitted_by' => $faculty->id, 'version_number' => $number, 'submission_type' => $number === 1 ? 'initial' : 'revision',
                'title' => $topic->title, 'estimated_budget' => 75000, 'estimated_duration_months' => $months,
                ...collect($primary)->only(['file_path', 'original_filename', 'mime_type', 'file_size', 'checksum'])->all(),
                'change_summary' => $number === 1 ? 'DEMO: Initial proposal package.' : 'DEMO: Proposal resubmitted after initial review; papers carried forward.',
                'created_at' => $number === 1 ? '2025-12-01 09:00:00' : '2025-12-08 09:00:00',
            ]);
            foreach ($files as $file) {
                $saved = $version->files()->create([...$file, 'uploaded_by' => $faculty->id, 'position' => 0, 'is_carried_forward' => $number === 2,
                    'source_version_file_id' => $number === 2 ? $initialFiles[$file['document_type']] : null]);
                if ($number === 1) {
                    $initialFiles[$file['document_type']] = $saved->id;
                }
            }
        }
        foreach (app(ProposalSignatureWorkflow::class)->requiredFiles($version) as $file) {
            $assessment = match ($file->document_type) {
                'gad_checklist' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT, 'gad_score' => 9.25,
                    'gad_outcome' => 'passed', 'gad_rating' => 'Gender-sensitive', 'gad_signature_confirmed' => true],
                'initial_screening_form' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
                    'narrative_evaluation' => 'DEMO ONLY: Simulated favorable evaluation.', 'recommended_action' => 'for_endorsement'],
                default => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED],
            };
            $version->files()->create([
                ...$file->only(['file_path', 'mime_type', 'file_size', 'checksum']), 'document_type' => 'head_upload', 'position' => $file->id,
                'source_version_file_id' => $file->id, 'uploaded_by' => $head->id, 'original_filename' => 'DEMO-simulated-approved-'.$file->original_filename,
                'source_data' => [...$assessment, 'target_document_type' => $file->document_type, 'document_title' => 'DEMO ONLY: Simulated approval or assessment'],
            ]);
        }
        foreach (['revision_requested', 'resubmitted', 'gad_review', 'lrec_queued', 'lrec_review', 'ready_for_signature', 'approved'] as $index => $status) {
            $date = CarbonImmutable::parse('2025-12-05')->addDays($index * 3);
            $topic->stageTransitions()->create(['from_status' => $topic->status, 'to_status' => $status, 'previous_started_at' => $topic->status_started_at, 'changed_at' => $date]);
            $stage = $index >= 3 ? 'lrec' : ($status === 'gad_review' ? 'gad' : 'initial');
            $topic->forceFill(['status' => $status, 'review_stage' => $stage, 'status_started_at' => $date])->saveQuietly();
            if ($status !== 'resubmitted') {
                $topic->reviews()->create(['reviewer_id' => $head->id, 'review_stage' => $stage, 'decision' => $status,
                    'comment' => 'DEMO ONLY: Simulated '.str_replace('_', ' ', $status).' decision.', 'created_at' => $date]);
            }
        }
        $topic->update(['project_status' => 'ongoing', 'signed_approval_path' => $primary['file_path'],
            'notice_to_proceed_issued_by' => $head->id, 'notice_to_proceed_issued_at' => '2025-12-31 09:00:00',
            'notice_to_proceed_data' => ['notice_date' => '2025-12-31', 'project_title' => $topic->title, 'researcher_names' => [$faculty->name],
                'approved_start_date' => '2026-01-01', 'approved_end_date' => $end, 'approved_duration_months' => $months,
                'approved_budget' => '75000.00', 'resolution_number' => 'DEMO-SIMULATED', 'resolution_year' => 2025]]);
        $notice = app(NoticeToProceedDataService::class);
        $pdf = app(DocumentPdfConverter::class)->convertDocx(app(NoticeToProceedDocumentService::class)->generate($notice->documentValues($notice->defaults($topic->fresh()))));
        $path = $this->store($topic, 'DEMO-notice-to-proceed.pdf', $pdf);
        $topic->update(['notice_to_proceed_path' => $path, 'notice_to_proceed_original_filename' => basename($path)]);
        $this->reports($topic->fresh(), $head, $secretary, $completed);
        if ($completed) {
            $topic->update(['project_status' => 'completed']);
            $topic->researchOutcome()->create(['leader_id' => $faculty->id, 'funding_source' => 'school', 'labels' => ['presentation', 'utilization'], 'lock_version' => 1]);
        }

        return $topic->fresh();
    }

    private function reports(TopicProposal $topic, User $head, User $secretary, bool $completed): void
    {
        $schedule = app(MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        foreach ($schedule->projectPeriods($topic)->take($completed ? 2 : 3) as $period) {
            $date = $period['end']->toDateString();
            $review = ['review_stage' => 'head', 'review_status' => 'reviewed', 'submission_status' => 'submitted',
                'submitted_at' => $period['end']->addDay(), 'secretary_reviewed_by' => $secretary->id, 'secretary_reviewed_at' => $period['end']->addDays(2),
                'forwarded_at' => $period['end']->addDays(2), 'reviewed_by' => $head->id, 'reviewed_at' => $period['end']->addDays(3),
                'research_head_remarks' => 'DEMO ONLY: Simulated report acceptance.', 'secretary_remarks' => 'DEMO ONLY: Simulated completeness checking.'];
            $proof = $this->evidence($topic, $date);
            $rows = array_map(function (array $row) use ($period, $proof): array {
                $elapsed = min(count($row['work_plan_months']), max(0, $period['quarter'] * 3 - min($row['work_plan_months']) + 1));
                $fraction = $elapsed / count($row['work_plan_months']);

                return [...$row, 'actual_accomplishment' => 'DEMO: '.round($fraction * 100).'% of this activity completed in the fictional pilot.',
                    'accomplished_percentage' => round((float) $row['percent_weight'] * $fraction, 2),
                    'findings' => $fraction >= 1 ? 'DEMO: Scheduled activity completed.' : 'DEMO: Activity continues next quarter.', 'evidence' => [$proof]];
            }, app(ApprovedWorkPlanMonitoringService::class)->defaultsForDate($topic, $date));
            $tool = $topic->progressReports()->create([
                'submitted_by' => $topic->user_id, 'reporting_date' => $date, 'reporting_year' => 2026, 'reporting_quarter' => $period['quarter'],
                'period_start' => $period['start'], 'period_end' => $period['end'], 'version_number' => 1,
                'tracking_number' => 'DEMO-'.$topic->id.'-Q'.$period['quarter'].'-TOOL', 'progress_percentage' => min(100, (int) round($period['quarter'] * ($completed ? 50 : 25))),
                'accomplishments' => implode(' ', array_column($rows, 'actual_accomplishment')), 'issues' => 'DEMO: No unresolved issues for this period.',
                'work_plan' => $rows, 'budget_utilization' => [['type' => 'Purchase Request', 'details' => 'DEMO supplies', 'amount_requested' => 10000, 'actual_amount' => 9000, 'remarks' => 'Fictional amounts.']],
                'budget_prepared_by' => $topic->user_id, 'budget_prepared_at' => $period['end'], ...$review,
            ]);
            $this->reportPdf($tool);
            $progress = $topic->narrativeReports()->create([
                'submitted_by' => $topic->user_id, 'report_type' => 'progress', 'submission_date' => $period['end']->addDay(),
                'reporting_date' => $date, 'reporting_quarter' => $period['quarter'], 'period_start' => $period['start'], 'period_end' => $period['end'],
                'tracking_number' => 'DEMO-'.$topic->id.'-Q'.$period['quarter'].'-PROGRESS', 'version_number' => 1,
                'researchers' => $topic->user->name, 'implementation_start' => $window['start'], 'implementation_end' => $window['end'],
                'budget' => 75000, 'funding_agency' => 'DEMO school-funded research', 'accomplishment_summary' => $tool->accomplishments,
                'accomplishments' => array_map(fn (array $row): array => ['objective' => $row['objective'], 'target' => $row['physical_target'], 'actual' => $row['actual_accomplishment']], $rows),
                'introduction' => 'DEMO ONLY: Fictional implementation of '.$topic->title, 'rationale' => 'DEMO: Demonstrate the approved plan.',
                'objectives' => implode("\n", array_column($rows, 'objective')), 'methodology' => 'DEMO: Baseline and pilot measurements.',
                'results_discussion' => 'DEMO: Scheduled targets were achieved; no real findings are claimed.', 'photos' => [], ...$review,
            ]);
            $this->reportPdf($progress);
        }
        if ($completed) {
            $defaults = app(TerminalReportData::class)->defaults($topic->fresh());
            $abstract = 'DEMO ONLY: This fictional study demonstrates the complete development and evaluation of a community water quality toolkit. All observations, approvals, expenditure figures, and outcomes are simulated for a software presentation and must not be interpreted as actual research findings. The proposed work began with a baseline assessment of community monitoring needs and continued with prototype preparation, pilot observation, and evaluation. Two approved objectives linked the activities to a baseline dataset and a validated toolkit with an adoption guide. The first quarterly reporting period documented the baseline work. The second period documented the pilot and evaluation activities. Each period contains a Monitoring Tool, a Progress Report, a sample observation log, and a simulated review record. The terminal report consolidates both periods and preserves their relationship to the approved proposal and work plan. A fictional expenditure of eighteen thousand pesos illustrates reconciliation against the approved budget of seventy five thousand pesos. The demonstration treats completion, dissemination, and utilization as separate records so that changing an output label does not alter the archived reports. The example supports a walkthrough by the faculty researcher, college coordinator, and Research Head, including college accomplishment reporting and campus RMS export. It concludes that the fictional objectives have been documented and recommends using the connected records to demonstrate evidence review, archival retrieval, and subsequent utilization tracking.';
            $terminalData = [...$defaults['terminal_data'], 'abstract' => $abstract, 'total_expenditure' => 18000,
                'literature_review' => 'DEMO ONLY: The approved proposal describes baseline assessment and prototype evaluation. No external literature or real findings are claimed.',
                'conclusions' => 'DEMO ONLY: Both approved objectives were achieved in the fictional scenario.',
                'recommendations' => 'DEMO ONLY: Present the toolkit, demonstrate utilization tracking, and retain all quarterly evidence.',
                'bibliography' => 'DEMO ONLY: Internal demonstration proposal, approved Work Plan, Q1 and Q2 Monitoring Tools and Progress Reports.',
                'signatories' => collect(TerminalReportRules::SIGNATORY_ROLES)->map(fn (array $role): array => ['name' => 'DEMO '.$role[1], 'date_signed' => '2026-07-08'])->all()];
            $terminal = $progress->replicate(['official_pdf_path', 'official_pdf_filename', 'official_pdf_checksum', 'official_pdf_size']);
            $terminal->fill(['report_type' => 'terminal', 'tracking_number' => 'DEMO-'.$topic->id.'-TERMINAL', 'submission_date' => '2026-07-05',
                'reporting_date' => null, 'reporting_quarter' => null, 'period_start' => null, 'period_end' => null,
                'accomplishments' => $defaults['accomplishments'], 'objectives' => $defaults['objectives'],
                'accomplishment_summary' => implode(' ', array_column($defaults['accomplishments'], 'actual')),
                'review_stage' => null, 'secretary_reviewed_by' => null, 'secretary_reviewed_at' => null, 'secretary_remarks' => null, 'forwarded_at' => null,
                'submitted_at' => '2026-07-05 09:00:00', 'reviewed_at' => '2026-07-07 09:00:00',
                'terminal_data' => $terminalData])->save();
            $this->reportPdf($terminal);
            $terminal->update(['terminal_data' => [...$terminal->terminal_data, 'signed_copy' => [
                'path' => $terminal->official_pdf_path, 'original_filename' => 'DEMO-simulated-signed-terminal.pdf', 'mime_type' => 'application/pdf',
                'size' => $terminal->official_pdf_size, 'checksum' => $terminal->official_pdf_checksum,
                'uploaded_by' => $head->id, 'uploaded_at' => '2026-07-08T09:00:00+08:00',
            ]]]);
        }
    }

    /** @param list<TopicProposal> $projects */
    private function collegeReports(User $coordinator, User $head, array $projects): void
    {
        foreach (User::COLLEGES as $code => $college) {
            $owner = User::role('research_coordinator')->where('college', $college)->orderBy('id')->first();
            if (! $owner) {
                $owner = User::create(['name' => 'DEMO '.$code.' Coordinator', 'email' => 'monitoring-demo-'.strtolower($code).'@example.test',
                    'college' => $college, 'email_verified_at' => now(), 'password' => Str::password(40)]);
                $owner->assignRole('research_coordinator');
            }
            $isSource = $college === $coordinator->college;
            $report = CollegeQuarterlyReport::create(['college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 2, 'prepared_by' => $owner->id,
                'other_accomplishments' => 'DEMO ONLY: '.($isSource ? 'Proposal-to-archive examples and fictional dissemination outputs.' : 'No activities entered in this demonstration college.'),
                'presentations' => [], 'publications' => [], 'seminars' => [], 'manual_research' => [], 'ip_assets' => [], 'evidence' => [], 'submitted_at' => '2026-07-10 09:00:00']);
            $data = ['research' => [], 'presentations' => [], 'publications' => []];
            if ($isSource) {
                $data = app(CollegeQuarterlyReportData::class)->build($owner, 2026, 2);
                $titles = array_map(fn (TopicProposal $project): string => $project->title, $projects);
                $data['research'] = array_values(array_filter($data['research'], fn (array $row): bool => in_array($row[0], $titles, true)));
                $proofReport = $projects[0]->narrativeReports()->where('report_type', 'progress')->where('reporting_quarter', 2)->sole();
                $contents = Storage::disk('local')->get($proofReport->official_pdf_path);
                $document = $projects[0]->projectDocuments()->create(['uploaded_by' => $owner->id, 'category' => 'accomplishment_evidence',
                    'title' => 'DEMO supporting project report', 'note' => 'DEMO ONLY: Sample supporting paper; no actual presentation or publication is certified.',
                    'file_path' => $this->store($projects[0], 'DEMO-output-evidence.pdf', $contents), 'original_filename' => 'DEMO-output-evidence.pdf',
                    'mime_type' => 'application/pdf', 'file_size' => strlen($contents), 'checksum' => hash('sha256', $contents)]);
                $url = route('topics.documents.view', [$projects[0], $document]);
                $report->presentations = [['id' => 'demo-presentation', 'event' => 'DEMO Community Research Forum', 'research_title' => $projects[0]->title,
                    'date' => '2026-06-25', 'place' => 'DEMO campus venue', 'personnel' => $projects[0]->user->name, 'project_id' => $projects[0]->id]];
                $report->publications = [['id' => 'demo-publication', 'event' => 'DEMO Applied Research Journal', 'research_title' => $projects[0]->title,
                    'date' => '2026-06-28', 'personnel' => $projects[0]->user->name, 'project_id' => $projects[0]->id]];
                $report->ip_assets = [['id' => 'demo-ip', 'title' => '[DEMO] Water Quality Toolkit', 'type' => 'Copyright', 'inventors' => $projects[0]->user->name,
                    'date' => '2026-06-29', 'remarks' => 'DEMO: Fictional submission only.', 'evidence_url' => $url]];
                $proof = app(CollegeQuarterlyReportFacultyEvidence::class);
                $ids = array_map(fn (TopicProposal $project): int => $project->id, $projects);
                $facultyFiles = array_values(array_filter($proof->forQuarter($owner, 2026, 2), fn (array $file): bool => in_array($file['project_id'] ?? null, $ids, true)));
                $report->evidence = $proof->freeze($report, $facultyFiles, $this->storedPaths);
            }
            $report->snapshot = ['demo_seed' => self::MARKER, 'college' => $college, 'prepared_by_name' => $owner->name,
                'research' => $data['research'], 'presentations' => [], 'publications' => [],
                'manual_presentations' => $report->presentations, 'manual_publications' => $report->publications, 'manual_research' => [], 'seminars' => [],
                'ip_assets' => $report->ip_assets, 'evidence' => $report->evidence, 'other_accomplishments' => $report->other_accomplishments];
            $report->save();
            $report->submissions()->create(['version_number' => 1, 'submitted_by' => $owner->id, 'snapshot' => $report->snapshot, 'submitted_at' => $report->submitted_at]);
        }
        $rows = app(RmsQuarterlyReportData::class)->build(2026, 2);
        $details = [];
        foreach (['presentations', 'publications', 'ip_assets'] as $section) {
            foreach ($rows[$section] as $row) {
                $details[$row['key']] = [...$row['values'], 'evidence_url' => $url];
                if ($section !== 'ip_assets') {
                    $details[$row['key']] = [...$details[$row['key']], 'authors' => $projects[0]->user->name, 'source_of_fund' => 'Institutionally-funded',
                        'project_status' => 'On-going', 'sdgs' => '6, 9, 11', 'support_requested' => 'No',
                        ...($section === 'presentations' ? ['classification' => 'Institutional', 'organizer' => 'DEMO Research Office'] : ['publication_type' => 'Journal', 'indexing' => 'Others', 'publisher' => 'DEMO fictional publisher'])];
                }
            }
        }
        RmsQuarterlyReport::create(['reporting_year' => 2026, 'reporting_quarter' => 2, 'campus' => 'ARASOF-Nasugbu', 'updated_by' => $head->id,
            'row_details' => $details, 'signatories' => ['prepared' => ['name' => $head->name, 'position' => 'Research Head', 'office' => self::MARKER],
                'reviewed' => ['name' => 'DEMO Reviewer', 'position' => 'Simulated reviewer', 'office' => 'Demonstration only'],
                'approved' => ['name' => 'DEMO Approver', 'position' => 'Simulated approver', 'office' => 'Demonstration only']]]);
    }

    private function targets(User $head): void
    {
        $indicators = [];
        foreach (ResearchAnnualTarget::INDICATORS as $key => $indicator) {
            $indicators[$key] = ['total' => $indicator['unit'] === 'percent' ? 75 : 14,
                'allocations' => array_fill_keys(array_keys(User::COLLEGES), $indicator['unit'] === 'percent' ? 75 : 2)];
        }
        ResearchAnnualTarget::firstOrCreate(['academic_year' => 'DEMO 2025-2026'], [
            'starts_on' => '2025-11-01', 'ends_on' => '2026-10-31', 'projects_target' => 14, 'faculty_target' => 14,
            'publications_target' => 14, 'indicators' => $indicators, 'updated_by' => $head->id,
        ]);
    }

    private function reportPdf(ProjectProgressReport|ProjectNarrativeReport $report): void
    {
        $service = $report instanceof ProjectProgressReport ? MonitoringToolDocumentService::class : ProgressReportDocumentService::class;
        $pdf = app(DocumentPdfConverter::class)->convertDocx(app($service)->generate($report));
        $path = $this->store($report->topic, $report->tracking_number.'.pdf', $pdf);
        $report->update(['official_pdf_path' => $path, 'official_pdf_filename' => basename($path),
            'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf)]);
    }

    /** @return array<string, mixed> */
    private function evidence(TopicProposal $topic, string $date): array
    {
        $image = imagecreatetruecolor(1000, 280);
        imagefill($image, 0, 0, imagecolorallocate($image, 248, 250, 252));
        $ink = imagecolorallocate($image, 30, 41, 59);
        imagestring($image, 5, 30, 35, 'ATHENA - DEMONSTRATION ONLY', $ink);
        imagestring($image, 5, 30, 85, $topic->title, $ink);
        imagestring($image, 4, 30, 135, 'Fictional observation log | Period ending '.$date, $ink);
        imagestring($image, 4, 30, 185, 'Baseline and pilot observations recorded. No actual findings are certified.', $ink);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        $id = (string) Str::uuid();
        $path = 'progress-reports/'.$topic->id.'/evidence/'.$id.'.png';
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Unable to store the demonstration observation log.');
        }
        $this->storedPaths[] = $path;

        return ['id' => $id, 'path' => $path, 'name' => 'DEMO-observation-log-'.$date.'.png', 'mime_type' => 'image/png',
            'size' => strlen($contents), 'checksum' => hash('sha256', $contents)];
    }

    private function store(TopicProposal $topic, string $filename, string $contents): string
    {
        $path = self::MARKER.'/'.$topic->id.'/'.$filename;
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Unable to store the monitoring demonstration paper.');
        }
        $this->storedPaths[] = $path;

        return $path;
    }
}
