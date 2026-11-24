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

    private const PORTFOLIO = [
        'pending' => ['Classroom Ventilation and Thermal Comfort Survey', 'classroom ventilation and thermal comfort'],
        'revision_requested' => ['Accessible Campus Navigation for Students With Low Vision', 'accessible campus navigation'],
        'resubmitted' => ['Inventory Tracking for Shared Laboratory Equipment', 'shared laboratory inventory tracking'],
        'gad_review' => ['Inclusive Participation in Campus Technology Workshops', 'participation in campus technology workshops'],
        'rejected' => ['Study Space Availability and Utilization Mapping', 'study space utilization'],
        'report_due' => ['Rainwater Harvesting for Campus Gardens', 'rainwater harvesting'],
        'secretary_pending' => ['Digital Queue Management for Student Services', 'student service queue management'],
        'faculty_corrections' => ['Campus Food Waste Measurement and Reduction', 'campus food waste reduction'],
        'head_pending' => ['Wireless Coverage Mapping in Academic Buildings', 'campus wireless coverage'],
        'head_concerns' => ['Assistive Reading Tools for Introductory Computing', 'assistive reading tools'],
        'terminal_pending' => ['Offline Disaster Preparedness Resource Portal', 'offline disaster preparedness resources'],
        'terminal_head' => ['Maintenance Request Tracking for Campus Facilities', 'facility maintenance request tracking'],
        'awaiting_signed_copy' => ['Digital Catalog for Campus Herbarium Specimens', 'campus herbarium cataloging'],
        'completed' => ['Open Data Guide for Municipal Planning Records', 'municipal planning data access'],
    ];

    private const MONITORING_PORTFOLIO = [
        'recently_issued' => ['Digital Incident Log for Campus Security', 'campus incident reporting', 'report_due', '2026-11-01'],
        'active_second_quarter' => ['Solar Lighting Performance on Campus Walkways', 'campus solar walkway lighting', 'first_quarter_only', '2026-07-01'],
        'overdue_second_quarter' => ['Smart Irrigation for the Campus Demonstration Garden', 'campus garden irrigation', 'first_quarter_only', '2026-04-01'],
        'monitoring_tool_only' => ['Cold Storage Temperature Logging for Food Laboratories', 'food laboratory temperature logging', 'tool_only', '2026-07-01'],
        'quarterly_secretary_queue' => ['Campus Computer Accessibility Audit', 'computer laboratory accessibility', 'secretary_pending', '2026-05-01'],
        'revised_quarterly_reports' => ['Equipment Checkout Records for Engineering Workshops', 'engineering workshop equipment checkout', 'revised_reports', '2026-05-01'],
        'delayed_budget_followup' => ['Water Refill Station Usage and Maintenance Study', 'water refill station maintenance', 'faculty_corrections', '2026-04-01'],
        'quarterly_head_queue' => ['Reusable Packaging Pilot for Campus Food Vendors', 'reusable packaging for campus vendors', 'head_pending', '2026-05-01'],
        'terminal_due' => ['Library Study Rooms Reservation and Utilization Study', 'library room reservations', 'terminal_due', '2026-04-01'],
        'terminal_secretary_queue' => ['Geotagged Inventory of Campus Emergency Equipment', 'emergency equipment inventory', 'terminal_pending', '2026-05-01'],
        'ready_to_close' => ['SMS Reminders for Community Extension Workshops', 'extension workshop attendance reminders', 'ready_to_close', '2026-04-01'],
        'completed_publication' => ['Open-Source Data Cleaning Workflows for Student Researchers', 'data cleaning for student researchers', 'completed', '2026-03-01', ['publication']],
        'completed_presentation' => ['Digital Map of Accessibility Features on Campus', 'mapping campus accessibility features', 'completed', '2026-04-01', ['presentation', 'utilization']],
    ];

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
        $secretary = User::role(User::ROLE_RESEARCH_OFFICE_SECRETARY)->where('college', $coordinator->college)->orderBy('id')->firstOrFail();
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

    /** @return array{review: TopicProposal, completed: TopicProposal} */
    public function seedWalkthrough(int $sourceId): array
    {
        $this->storedPaths = [];

        try {
            return DB::transaction(function () use ($sourceId): array {
                $source = TopicProposal::query()->with('user')->lockForUpdate()->findOrFail($sourceId);
                $faculty = $source->user;
                if (! $source->hasIssuedNoticeToProceed() || ! $faculty?->hasRole('faculty_researcher') || blank($faculty->college)) {
                    throw new RuntimeException('Choose an issued demo proposal owned by a Faculty Researcher with an assigned college.');
                }

                $existing = TopicProposal::query()->where('notice_to_proceed_data->walkthrough_source_topic_id', $sourceId)->get()
                    ->keyBy(fn (TopicProposal $project): string => $project->notice_to_proceed_data['walkthrough_case'] ?? 'review');
                $head = User::role('research_head')->orderBy('id')->firstOrFail();
                $secretary = User::role(User::ROLE_RESEARCH_OFFICE_SECRETARY)->where('college', $faculty->college)->orderBy('id')->firstOrFail();
                $review = $existing->get('review') ?? $this->project(null, $faculty, $head, $secretary, completed: false, walkthroughSourceId: $sourceId);
                $completed = $existing->get('completed') ?? $this->project(null, $faculty, $head, $secretary, completed: true, walkthroughSourceId: $sourceId);

                $coordinator = User::role('research_coordinator')->where('college', $faculty->college)->orderBy('id')->first();
                if ($coordinator !== null) {
                    CollegeQuarterlyReport::firstOrCreate(['college' => $coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 2], [
                        'prepared_by' => $coordinator->id,
                        'presentations' => [[
                            'id' => (string) Str::uuid(),
                            'event' => 'Community Water Quality Toolkit Orientation',
                            'research_title' => $completed->title,
                            'date' => '2026-06-25',
                            'place' => 'ARASOF-Nasugbu Campus',
                            'personnel' => $faculty->name,
                            'project_id' => $completed->id,
                        ]],
                        'other_accomplishments' => 'The research team demonstrated the recording and validation workflow and prepared a reference guide for community users.',
                    ]);
                }

                return ['review' => $review, 'completed' => $completed];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($this->storedPaths);

            throw $exception;
        }
    }

    /** @return array<string, TopicProposal> */
    public function seedPortfolio(int $sourceId, bool $monitoringOnly = false): array
    {
        $source = TopicProposal::query()->with('user')->findOrFail($sourceId);
        if (! $source->hasIssuedNoticeToProceed() || ! $source->user?->hasRole('faculty_researcher') || blank($source->projectCollege())
            || ! TopicProposal::query()->where('notice_to_proceed_data->walkthrough_source_topic_id', $sourceId)->exists()) {
            throw new RuntimeException('Select the source proposal of an existing monitoring walkthrough.');
        }
        $head = User::role('research_head')->oldest('id')->firstOrFail();
        $secretary = User::role(User::ROLE_RESEARCH_OFFICE_SECRETARY)
            ->whereIn('college', TopicProposal::collegeAliases($source->projectCollege()))->oldest('id')->firstOrFail();
        $projects = [];
        $cases = $monitoringOnly ? self::MONITORING_PORTFOLIO : self::PORTFOLIO;
        foreach ($cases as $key => $details) {
            [$title, $focus] = $details;
            $portfolio = ['key' => $key, 'state' => $details[2] ?? $key, 'title' => $title, 'focus' => $focus,
                'start' => $details[3] ?? '2026-01-01', 'labels' => $details[4] ?? ['presentation', 'utilization']];
            $this->storedPaths = [];
            try {
                $projects[$key] = DB::transaction(function () use ($source, $sourceId, $head, $secretary, $key, $title, $portfolio): TopicProposal {
                    TopicProposal::query()->whereKey($sourceId)->lockForUpdate()->firstOrFail();

                    return TopicProposal::query()->where('user_id', $source->user_id)
                        ->where(fn (Builder $query): Builder => $query->where('notice_to_proceed_data->portfolio_source', $sourceId)
                            ->where('notice_to_proceed_data->portfolio_case', $key))
                        ->orWhere(fn (Builder $query): Builder => $query->where('user_id', $source->user_id)->where('title', $title)
                            ->whereHas('versions', fn (Builder $versions): Builder => $versions->where('file_path', 'like', self::MARKER.'/%')))
                        ->first()
                        ?? $this->project(null, $source->user, $head, $secretary, $portfolio['state'] === 'completed', $sourceId, $portfolio);
                });
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($this->storedPaths);
                throw $exception;
            }
        }
        $coordinator = User::assignedToRole('research_coordinator')
            ->whereIn('college', TopicProposal::collegeAliases($source->projectCollege()))->oldest('id')->first();
        if (! $monitoringOnly && $coordinator !== null) {
            $this->portfolioCollegeReports($source, $coordinator, $head, $projects);
        }

        return $projects;
    }

    /** @param array<string, TopicProposal> $projects */
    private function portfolioCollegeReports(TopicProposal $source, User $coordinator, User $head, array $projects): void
    {
        $this->storedPaths = [];
        try {
            DB::transaction(function () use ($source, $coordinator, $head, $projects): void {
                TopicProposal::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                $projectIds = array_map(fn (TopicProposal $project): int => $project->id, $projects);
                $titles = array_map(fn (TopicProposal $project): string => $project->title, $projects);
                $evidence = app(CollegeQuarterlyReportFacultyEvidence::class);
                foreach ([1 => '2026-04-08 09:00:00', 3 => '2026-10-08 09:00:00'] as $quarter => $date) {
                    $period = ['college' => $coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => $quarter];
                    $report = CollegeQuarterlyReport::firstOrCreate($period, [
                        'prepared_by' => $coordinator->id,
                        'other_accomplishments' => $quarter === 1
                            ? 'The faculty teams completed baseline assessments and documented requirements for their respective pilots.'
                            : 'The research team prepared a municipal open data orientation for campus and community users.',
                    ]);
                    if (! $report->wasRecentlyCreated) {
                        continue;
                    }
                    $returned = $quarter === 3;
                    $data = app(CollegeQuarterlyReportData::class)->build($coordinator, 2026, $quarter);
                    $facultyFiles = array_values(array_filter($evidence->forQuarter($coordinator, 2026, $quarter),
                        fn (array $file): bool => in_array($file['project_id'] ?? null, $projectIds, true)));
                    $files = $evidence->freeze($report, $facultyFiles, $this->storedPaths);
                    $research = array_values(array_filter($data['research'], fn (array $row): bool => in_array($row[0], $titles, true)));
                    $snapshot = [
                        'portfolio_source' => $source->id, 'college' => $coordinator->college, 'prepared_by_name' => $coordinator->name,
                        'research' => $research, 'presentations' => [], 'publications' => [], 'evidence' => $files,
                        'other_accomplishments' => $report->other_accomplishments,
                    ];
                    $report->update(['submitted_at' => $returned ? null : $date,
                        'evidence' => $files, 'snapshot' => $returned ? ['research' => $research, 'presentations' => [], 'publications' => []] : $snapshot]);
                    $report->submissions()->create(['version_number' => 1, 'submitted_by' => $coordinator->id, 'submitted_at' => $date, 'snapshot' => $snapshot,
                        ...($returned ? ['returned_by' => $head->id, 'returned_at' => '2026-10-09 09:00:00',
                            'return_reason' => 'Add the orientation attendance record and a short description of how the guide was distributed.'] : [])]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($this->storedPaths);
            throw $exception;
        }
    }

    /** @param array{key: string, state: string, title: string, focus: string, start: string, labels: list<string>}|null $portfolio */
    private function project(?ResearchCall $call, User $faculty, User $head, User $secretary, bool $completed, ?int $walkthroughSourceId = null, ?array $portfolio = null): TopicProposal
    {
        $walkthrough = $walkthroughSourceId !== null;
        $energyWalkthrough = $walkthrough && ! $completed && $portfolio === null;
        $case = $portfolio['state'] ?? null;
        $focus = $portfolio['focus'] ?? null;
        $proposalStage = in_array($case, ['pending', 'revision_requested', 'resubmitted', 'gad_review', 'rejected'], true);
        $portfolioData = $portfolio === null ? [] : ['portfolio_source' => $walkthroughSourceId, 'portfolio_case' => $portfolio['key']];
        $months = $completed || $walkthrough ? 6 : 12;
        $startDate = CarbonImmutable::parse($portfolio['start'] ?? '2026-01-01');
        $applicationDate = $startDate->subMonth()->setTime(9, 0);
        $noticeDate = $startDate->subDay()->setTime(9, 0);
        $start = $startDate->toDateString();
        $end = $startDate->addMonthsNoOverflow($months)->subDay()->toDateString();
        $topic = TopicProposal::create([
            'research_call_id' => $call?->id, 'user_id' => $faculty->id,
            'title' => $portfolio['title'] ?? match (true) {
                $energyWalkthrough => 'Campus Laboratory Energy Monitoring Dashboard',
                $walkthrough => 'Community Water Quality Monitoring Toolkit',
                $completed => '[DEMO] Community Water Quality Toolkit',
                default => '[DEMO] Campus Energy Monitoring',
            },
            'description' => $focus !== null ? 'The project documents current practices for '.$focus.', develops a practical improvement, and evaluates it in a campus pilot.' : match (true) {
                $energyWalkthrough => 'The project develops a dashboard for tracking electricity use in campus computing laboratories. The reporting walkthrough uses simulated readings and review records.',
                $walkthrough => 'The project evaluates a toolkit for recording and sharing community water quality readings. The archive contains simulated readings, reviews, approvals, and outcome records.',
                default => '['.self::MARKER.'] Fictional end-to-end example. Proposal papers, reviews, approvals, signatures and results are simulated for demonstration only.',
            },
            ...($portfolio === null ? [] : ['notice_to_proceed_data' => $portfolioData]),
            'estimated_budget' => 75000, 'estimated_duration_months' => $months, 'status' => 'pending', 'created_at' => $applicationDate,
        ]);
        $common = ['project_title' => $topic->title, 'project_leader' => $faculty->name,
            'planned_start' => $start, 'planned_end' => $end, 'total_duration_months' => $months,
            'prepared_by' => $faculty->name, 'verified_by' => 'DEMO verifier', 'verified_role' => 'Demonstration only'];
        $entries = $energyWalkthrough ? [
            ['objective' => 'Establish an electricity use baseline for the computing laboratories.', 'activity' => 'Record meter readings, reconcile room schedules, and document peak demand.', 'expected_output' => 'Validated laboratory energy baseline', 'months' => range(1, 3)],
            ['objective' => 'Evaluate a dashboard that flags high consumption outside teaching hours.', 'activity' => 'Build the dashboard, verify readings, and pilot the alerts with laboratory staff.', 'expected_output' => 'Pilot dashboard, validation log, and operator guide', 'months' => range(4, 6)],
        ] : [
            ['objective' => 'DEMO: Establish baseline needs.', 'activity' => 'DEMO: Baseline assessment', 'expected_output' => 'DEMO baseline dataset', 'months' => range(1, $completed ? 3 : 6)],
            ['objective' => 'DEMO: Validate the prototype.', 'activity' => 'DEMO: Pilot and evaluation', 'expected_output' => 'DEMO validated toolkit and adoption guide', 'months' => range($completed ? 4 : 7, $months)],
        ];
        if ($focus !== null) {
            $entries = [
                ['objective' => 'Establish a baseline for '.$focus.'.', 'activity' => 'Interview users, document the existing workflow, and check baseline observations.', 'expected_output' => 'Baseline assessment and requirements', 'months' => range(1, 3)],
                ['objective' => 'Evaluate a practical improvement for '.$focus.'.', 'activity' => 'Build the pilot, compare outcomes with the baseline, and document the handover.', 'expected_output' => 'Pilot evaluation and operating guide', 'months' => range(4, 6)],
            ];
        } elseif ($walkthrough && $completed) {
            $entries = [
                ['objective' => 'Establish a baseline for community water sampling.', 'activity' => 'Map collection sites and validate the observation form with field users.', 'expected_output' => 'Sampling map and baseline observation log', 'months' => range(1, 3)],
                ['objective' => 'Pilot a toolkit for recording and sharing water quality readings.', 'activity' => 'Validate the recording workflow and orient community users.', 'expected_output' => 'Validated water quality toolkit and user guide', 'months' => range(4, 6)],
            ];
        }
        $supplies = match (true) {
            $portfolio !== null => 'Field documentation and pilot materials',
            $energyWalkthrough => 'Energy meter accessories and pilot supplies',
            $walkthrough => 'Water sampling and field validation supplies',
            default => 'DEMO prototype supplies',
        };
        $expense = ['project_title' => $topic->title, 'items' => [[
            'category' => 'mooe', 'account' => 'Supplies and Materials Expenses', 'sub_account' => 'Other Supplies and Materials Expenses',
            'particulars' => $supplies, 'details' => $walkthrough ? 'Materials for equipment setup and pilot evaluation' : 'Fictional supplies',
            'purpose' => 'Pilot evaluation', 'unit' => 'lot', 'quantity' => 1, 'unit_cost' => 75000,
        ]]];
        $budget = [...$common, 'leader_campus' => 'ARASOF-Nasugbu', 'leader_college' => $faculty->college,
            'custom_mooe_items' => [['particular' => $supplies, 'amount' => 75000]], 'certified_by' => 'DEMO certifier', 'certified_role' => 'Demonstration only'];
        $detail = [...$common, 'proponent_college' => $faculty->college, 'proponent_campus' => 'ARASOF-Nasugbu', 'sdgs' => [6, 9, 11],
            'general_objective' => 'DEMO: Develop and evaluate '.$topic->title.'.', 'specific_objectives' => array_column($entries, 'objective'),
            'executive_brief' => 'DEMO ONLY: A fictional pilot study demonstrating a complete research workflow.',
            'rationale' => 'DEMO ONLY: Establish the baseline and validate a practical prototype.',
            'methodology' => ['research_design' => 'DEMO: Developmental research with baseline and pilot evaluation.', 'specific_methods' => 'DEMO: Record baseline observations, build a prototype, and evaluate pilot results.', 'data_analysis' => 'DEMO: Compare baseline and pilot measurements.'],
            'references' => 'DEMO ONLY: No real sources or findings are claimed.'];
        if ($focus !== null) {
            $detail = [...$detail,
                'general_objective' => 'Develop and evaluate a practical improvement for '.$focus.'.',
                'executive_brief' => $topic->description,
                'rationale' => 'A baseline and a documented pilot give the campus a way to evaluate '.$focus.' before deciding whether to extend the work.',
                'methodology' => ['research_design' => 'Baseline assessment and pilot evaluation.',
                    'specific_methods' => 'Document the existing workflow, assemble the pilot, and review observations against agreed requirements.',
                    'data_analysis' => 'Compare baseline and pilot records and document practical limitations.'],
                'references' => 'Campus operating procedures and project observation records.',
            ];
        } elseif ($energyWalkthrough) {
            $detail = [...$detail,
                'general_objective' => 'Develop and evaluate a dashboard that tracks laboratory electricity use against teaching schedules.',
                'executive_brief' => 'The study establishes a baseline, pilots an energy dashboard, and evaluates its alerts against meter logs.',
                'rationale' => 'Laboratory staff need a way to distinguish scheduled teaching demand from avoidable after-hours use.',
                'methodology' => ['research_design' => 'Development and pilot validation.',
                    'specific_methods' => 'Collect meter logs and timetables, verify dashboard totals, and review alerts with laboratory staff.',
                    'data_analysis' => 'Compare recorded and displayed totals and document discrepancies and common false alerts.'],
            ];
        }
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
        foreach (in_array($case, ['pending', 'revision_requested'], true) ? [1] : [1, 2] as $number) {
            $version = $topic->versions()->create([
                'submitted_by' => $faculty->id, 'version_number' => $number, 'submission_type' => $number === 1 ? 'initial' : 'revision',
                'title' => $topic->title, 'estimated_budget' => 75000, 'estimated_duration_months' => $months,
                ...collect($primary)->only(['file_path', 'original_filename', 'mime_type', 'file_size', 'checksum'])->all(),
                'change_summary' => $number === 1 ? 'Initial proposal package.' : 'Revised after initial review. Supporting papers carried forward.',
                'created_at' => $applicationDate->addDays($number === 1 ? 0 : 7),
            ]);
            foreach ($files as $file) {
                $saved = $version->files()->create([...$file, 'uploaded_by' => $faculty->id, 'position' => 0, 'is_carried_forward' => $number === 2,
                    'source_version_file_id' => $number === 2 ? $initialFiles[$file['document_type']] : null]);
                if ($number === 1) {
                    $initialFiles[$file['document_type']] = $saved->id;
                }
            }
        }
        if ($proposalStage) {
            $stages = match ($case) {
                'revision_requested' => ['revision_requested'],
                'resubmitted' => ['revision_requested', 'resubmitted'],
                'gad_review' => ['revision_requested', 'resubmitted', 'gad_review'],
                'rejected' => ['revision_requested', 'resubmitted', 'rejected'],
                default => [],
            };
            foreach ($stages as $stage) {
                $topic->update(['status' => $stage, 'review_stage' => $stage === 'gad_review' ? 'gad' : 'initial']);
                if ($stage !== 'resubmitted') {
                    $topic->reviews()->create(['reviewer_id' => $head->id, 'review_stage' => $topic->review_stage, 'decision' => $stage,
                        'comment' => match ($stage) {
                            'revision_requested' => 'Clarify the pilot criteria and how observations will be recorded.',
                            'gad_review' => 'Initial review complete. Assess participation and access considerations.',
                            default => 'The revised sampling plan does not address the feasibility concerns raised in the initial review.',
                        }]);
                }
            }

            return $topic->fresh();
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
            $date = $applicationDate->startOfDay()->addDays(4 + $index * 3);
            $topic->stageTransitions()->create(['from_status' => $topic->status, 'to_status' => $status, 'previous_started_at' => $topic->status_started_at, 'changed_at' => $date]);
            $stage = $index >= 3 ? 'lrec' : ($status === 'gad_review' ? 'gad' : 'initial');
            $topic->forceFill(['status' => $status, 'review_stage' => $stage, 'status_started_at' => $date])->saveQuietly();
            if ($status !== 'resubmitted') {
                $topic->reviews()->create(['reviewer_id' => $head->id, 'review_stage' => $stage, 'decision' => $status,
                    'comment' => 'DEMO ONLY: Simulated '.str_replace('_', ' ', $status).' decision.', 'created_at' => $date]);
            }
        }
        $topic->update(['project_status' => 'ongoing', 'signed_approval_path' => $primary['file_path'],
            'notice_to_proceed_issued_by' => $walkthrough ? $secretary->id : $head->id, 'notice_to_proceed_issued_at' => $noticeDate,
            'notice_to_proceed_data' => ['notice_date' => $noticeDate->toDateString(), 'project_title' => $topic->title, 'researcher_names' => [$faculty->name],
                'approved_start_date' => $start, 'approved_end_date' => $end, 'approved_duration_months' => $months,
                'approved_budget' => '75000.00', 'resolution_number' => 'DEMO-SIMULATED', 'resolution_year' => $noticeDate->year,
                ...$portfolioData,
                ...($walkthrough && $portfolio === null ? ['walkthrough_source_topic_id' => $walkthroughSourceId, 'walkthrough_case' => $completed ? 'completed' : 'review'] : [])]]);
        $notice = app(NoticeToProceedDataService::class);
        $pdf = app(DocumentPdfConverter::class)->convertDocx(app(NoticeToProceedDocumentService::class)->generate($notice->documentValues($notice->defaults($topic->fresh()))));
        $path = $this->store($topic, 'DEMO-notice-to-proceed.pdf', $pdf);
        $topic->update(['notice_to_proceed_path' => $path, 'notice_to_proceed_original_filename' => basename($path)]);
        if ($case !== 'report_due') {
            $this->reports($topic->fresh(), $head, $secretary, $completed, $walkthrough, $case, $focus);
        }
        if ($case === 'faculty_corrections') {
            $topic->update(['project_status' => 'delayed']);
        }
        if ($completed) {
            $topic->update(['project_status' => 'completed']);
            $topic->researchOutcome()->create(['leader_id' => $faculty->id, 'funding_source' => 'school',
                'labels' => $portfolio['labels'] ?? ['presentation', 'utilization'], 'lock_version' => 1]);
        }

        return $topic->fresh();
    }

    private function reports(TopicProposal $topic, User $head, User $secretary, bool $completed, bool $walkthrough, ?string $case = null, ?string $focus = null): void
    {
        $schedule = app(MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $trackingPrefix = $walkthrough ? 'MON' : 'DEMO';
        $reportLimit = match ($case) {
            'first_quarter_only', 'tool_only' => 1,
            default => $completed || $walkthrough ? 2 : 3,
        };
        foreach ($schedule->projectPeriods($topic)->take($reportLimit) as $period) {
            $date = $period['end']->toDateString();
            $review = ['review_stage' => 'head', 'review_status' => 'reviewed', 'submission_status' => 'submitted',
                'submitted_at' => $period['end']->addDay(), 'secretary_reviewed_by' => $secretary->id, 'secretary_reviewed_at' => $period['end']->addDays(2),
                'forwarded_at' => $period['end']->addDays(2), 'reviewed_by' => $head->id, 'reviewed_at' => $period['end']->addDays(3),
                'research_head_remarks' => 'DEMO ONLY: Simulated report acceptance.', 'secretary_remarks' => 'DEMO ONLY: Simulated completeness checking.'];
            if ($case === 'tool_only' || ($walkthrough && ! $completed && $case === null || $case === 'secretary_pending') && $period['quarter'] === 2) {
                $review = ['review_stage' => 'secretary', 'review_status' => 'pending', 'submission_status' => 'submitted', 'submitted_at' => $review['submitted_at']];
            }
            if ($period['quarter'] === 2) {
                $review = match ($case) {
                    'faculty_corrections', 'revised_reports' => [...$review, 'review_stage' => 'secretary', 'review_status' => 'revision_requested', 'reviewed_by' => null, 'reviewed_at' => null,
                        'forwarded_at' => null, 'research_head_remarks' => null, 'secretary_remarks' => 'Attach the measurement sheet and reconcile the totals before resubmitting.'],
                    'head_pending' => [...$review, 'review_status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'research_head_remarks' => null],
                    'head_concerns' => [...$review, 'review_stage' => 'secretary', 'review_status' => 'pending',
                        'research_head_remarks' => 'Explain the difference between the planned and observed pilot participation.'],
                    default => $review,
                };
            }
            $walkthroughFinding = match (true) {
                $case === 'faculty_corrections' && $period['quarter'] === 2 => 'The pilot was delayed while the team checked inconsistent measurements.',
                $focus !== null && $period['quarter'] === 1 => 'Documented the current process for '.$focus.' and checked the baseline records with campus users.',
                $focus !== null => 'Compared the pilot for '.$focus.' with the baseline and recorded operational feedback.',
                $completed && $period['quarter'] === 1 => 'Mapped the collection sites and checked the baseline field log for missing or inconsistent readings.',
                $completed => 'Validated the recording workflow at the pilot sites and prepared the community user guide.',
                $period['quarter'] === 1 => 'Reconciled the laboratory meter logs with room timetables and identified the highest-demand teaching periods.',
                default => 'Verified hourly totals against source logs, adjusted the after-hours alert threshold, and prepared the operator guide.',
            };
            $proof = $this->evidence($topic, $date);
            $rows = array_map(function (array $row) use ($period, $proof, $walkthrough, $walkthroughFinding, $case): array {
                $elapsed = min(count($row['work_plan_months']), max(0, $period['quarter'] * 3 - min($row['work_plan_months']) + 1));
                $fraction = $elapsed / count($row['work_plan_months']);
                if ($case === 'faculty_corrections' && $period['quarter'] === 2 && min($row['work_plan_months']) > 3) {
                    $fraction /= 2;
                }

                return [...$row, 'actual_accomplishment' => $walkthrough ? $walkthroughFinding : 'DEMO: '.round($fraction * 100).'% of this activity completed in the fictional pilot.',
                    'accomplished_percentage' => round((float) $row['percent_weight'] * $fraction, 2),
                    'findings' => $walkthrough ? $walkthroughFinding : ($fraction >= 1 ? 'DEMO: Scheduled activity completed.' : 'DEMO: Activity continues next quarter.'), 'evidence' => [$proof]];
            }, app(ApprovedWorkPlanMonitoringService::class)->defaultsForDate($topic, $date));
            $tool = $topic->progressReports()->create([
                'submitted_by' => $topic->user_id, 'reporting_date' => $date, 'reporting_year' => $period['year'], 'reporting_quarter' => $period['quarter'],
                'period_start' => $period['start'], 'period_end' => $period['end'], 'version_number' => 1,
                'tracking_number' => $trackingPrefix.'-'.$topic->id.'-Q'.$period['quarter'].'-TOOL', 'progress_percentage' => $case === 'faculty_corrections' && $period['quarter'] === 2
                    ? 75 : min(100, $period['quarter'] * ($completed || $walkthrough ? 50 : 25)),
                'accomplishments' => implode(' ', array_column($rows, 'actual_accomplishment')), 'issues' => match (true) {
                    $case === 'faculty_corrections' => 'A supplier delivery was deferred. Only delivered and verified items were recorded against the budget.',
                    $walkthrough => 'No unresolved issues for this period.',
                    default => 'DEMO: No unresolved issues for this period.',
                },
                'work_plan' => $rows, 'budget_utilization' => [['type' => 'Purchase Request', 'details' => $walkthrough ? 'Field equipment and validation supplies' : 'DEMO supplies',
                    'amount_requested' => 10000, 'actual_amount' => $case === 'faculty_corrections' ? 3200 : 9000,
                    'remarks' => $case === 'faculty_corrections' ? 'Partial delivery recorded.' : ($walkthrough ? 'Delivered and reconciled against the purchase request.' : 'Fictional amounts.')]],
                'budget_prepared_by' => $topic->user_id, 'budget_prepared_at' => $period['end'], ...$review,
            ]);
            $this->reportPdf($tool);
            if ($case === 'tool_only') {
                continue;
            }
            $progress = $topic->narrativeReports()->create([
                'submitted_by' => $topic->user_id, 'report_type' => 'progress', 'submission_date' => $period['end']->addDay(),
                'reporting_date' => $date, 'reporting_quarter' => $period['quarter'], 'period_start' => $period['start'], 'period_end' => $period['end'],
                'tracking_number' => $trackingPrefix.'-'.$topic->id.'-Q'.$period['quarter'].'-PROGRESS', 'version_number' => 1,
                ...($walkthrough ? ['monitoring_report_id' => $tool->id] : []),
                'researchers' => $topic->user->name, 'implementation_start' => $window['start'], 'implementation_end' => $window['end'],
                'budget' => 75000, 'funding_agency' => $walkthrough ? 'Campus research allocation' : 'DEMO school-funded research', 'accomplishment_summary' => $tool->accomplishments,
                'accomplishments' => array_map(fn (array $row): array => ['objective' => $row['objective'], 'target' => $row['physical_target'], 'actual' => $row['actual_accomplishment']], $rows),
                'introduction' => $walkthrough ? 'This quarterly report covers the approved activities for '.$topic->title.'.' : 'DEMO ONLY: Fictional implementation of '.$topic->title,
                'rationale' => $walkthrough ? 'Documenting the baseline and pilot results gives operators a reliable reference for using the toolkit.' : 'DEMO: Demonstrate the approved plan.',
                'objectives' => implode("\n", array_column($rows, 'objective')),
                'methodology' => $walkthrough ? 'Review the source readings, cross-check the observation log, and verify the pilot outputs against the approved activities.' : 'DEMO: Baseline and pilot measurements.',
                'results_discussion' => $walkthrough ? $walkthroughFinding : 'DEMO: Scheduled targets were achieved; no real findings are claimed.', 'photos' => [], ...$review,
            ]);
            $this->reportPdf($progress);
            if ($case === 'revised_reports' && $period['quarter'] === 2) {
                $this->revisedReports($tool, $progress, $period['end']->addDays(4)->setTime(10, 0));
            }
        }
        if ($completed || in_array($case, ['terminal_pending', 'terminal_head', 'awaiting_signed_copy', 'ready_to_close'], true)) {
            $submissionDate = $window['end']->addDays(5)->setTime(9, 0);
            $signatureDate = $window['end']->addDays(8)->setTime(9, 0);
            $defaults = app(TerminalReportData::class)->defaults($topic->fresh());
            $abstract = 'DEMO ONLY: This fictional study demonstrates the complete development and evaluation of a community water quality toolkit. All observations, approvals, expenditure figures, and outcomes are simulated for a software presentation and must not be interpreted as actual research findings. The proposed work began with a baseline assessment of community monitoring needs and continued with prototype preparation, pilot observation, and evaluation. Two approved objectives linked the activities to a baseline dataset and a validated toolkit with an adoption guide. The first quarterly reporting period documented the baseline work. The second period documented the pilot and evaluation activities. Each period contains a Monitoring Tool, a Progress Report, a sample observation log, and a simulated review record. The terminal report consolidates both periods and preserves their relationship to the approved proposal and work plan. A fictional expenditure of eighteen thousand pesos illustrates reconciliation against the approved budget of seventy five thousand pesos. The demonstration treats completion, dissemination, and utilization as separate records so that changing an output label does not alter the archived reports. The example supports a walkthrough by the faculty researcher, college coordinator, and Research Head, including college accomplishment reporting and campus RMS export. It concludes that the fictional objectives have been documented and recommends using the connected records to demonstrate evidence review, archival retrieval, and subsequent utilization tracking.';
            $terminalData = [...$defaults['terminal_data'], 'abstract' => $abstract, 'total_expenditure' => 18000,
                'literature_review' => 'DEMO ONLY: The approved proposal describes baseline assessment and prototype evaluation. No external literature or real findings are claimed.',
                'conclusions' => 'DEMO ONLY: Both approved objectives were achieved in the fictional scenario.',
                'recommendations' => 'DEMO ONLY: Present the toolkit, demonstrate utilization tracking, and retain all quarterly evidence.',
                'bibliography' => 'DEMO ONLY: Internal demonstration proposal, approved Work Plan, Q1 and Q2 Monitoring Tools and Progress Reports.',
                'signatories' => collect(TerminalReportRules::SIGNATORY_ROLES)->map(fn (array $role): array => ['name' => 'DEMO '.$role[1],
                    'date_signed' => $completed || $case === 'ready_to_close' ? $signatureDate->toDateString() : null])->all()];
            if ($focus !== null) {
                $terminalData = [...$terminalData,
                    'abstract' => 'This simulated research project documents the baseline for '.$focus.' and evaluates a pilot carried out during the approved implementation period. Baseline observations established how the existing process worked and identified practical issues to address. The team documented its requirements, implemented the pilot, and collected feedback. The evaluation compared the pilot and baseline records against the approved Work Plan. The resulting guide records both the process and the conditions needed to repeat the work. Supporting quarterly records preserve the evidence behind each objective. Because the observation and expenditure figures represent a fictional case, the results are suitable for a workflow demonstration only.',
                    'literature_review' => 'The proposal and Work Plan describe baseline assessment and pilot validation.',
                    'conclusions' => 'The pilot met the documented objectives in the simulated evaluation.',
                    'recommendations' => 'Retain the baseline records and handover guide before extending the pilot.',
                ];
            }
            $terminal = $progress->replicate(['official_pdf_path', 'official_pdf_filename', 'official_pdf_checksum', 'official_pdf_size']);
            $terminal->fill(['report_type' => 'terminal', 'tracking_number' => $trackingPrefix.'-'.$topic->id.'-TERMINAL', 'submission_date' => $submissionDate,
                'reporting_date' => null, 'reporting_quarter' => null, 'period_start' => null, 'period_end' => null,
                'monitoring_report_id' => null,
                'accomplishments' => $defaults['accomplishments'], 'objectives' => $defaults['objectives'],
                'accomplishment_summary' => implode(' ', array_column($defaults['accomplishments'], 'actual')),
                'review_stage' => $walkthrough ? 'head' : null, 'secretary_reviewed_by' => $walkthrough ? $secretary->id : null,
                'secretary_reviewed_at' => $walkthrough ? $submissionDate->addDay() : null, 'secretary_remarks' => $walkthrough ? 'The attachments and required sections are complete.' : null,
                'forwarded_at' => $walkthrough ? $submissionDate->addDay() : null,
                'submitted_at' => $submissionDate, 'reviewed_at' => $submissionDate->addDays(2),
                ...($walkthrough ? ['photos' => $this->terminalPhotos($topic, $proof, $focus !== null)] : []),
                'terminal_data' => $terminalData])->save();
            if (in_array($case, ['terminal_pending', 'terminal_head'], true)) {
                $terminal->update(['review_status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'research_head_remarks' => null]);
            }
            if ($case === 'terminal_pending') {
                $terminal->update(['review_stage' => 'secretary', 'secretary_reviewed_by' => null, 'secretary_reviewed_at' => null, 'secretary_remarks' => null, 'forwarded_at' => null]);
            }
            $this->reportPdf($terminal);
            if ($completed || $case === 'ready_to_close') {
                $terminal->update(['terminal_data' => [...$terminal->terminal_data, 'signed_copy' => [
                    'path' => $terminal->official_pdf_path, 'original_filename' => 'DEMO-simulated-signed-terminal.pdf', 'mime_type' => 'application/pdf',
                    'size' => $terminal->official_pdf_size, 'checksum' => $terminal->official_pdf_checksum,
                    'uploaded_by' => $head->id, 'uploaded_at' => $signatureDate->toIso8601String(),
                ]]]);
            }
        }
    }

    private function revisedReports(ProjectProgressReport $tool, ProjectNarrativeReport $progress, CarbonImmutable $submittedAt): void
    {
        $officialFiles = ['official_pdf_path', 'official_pdf_filename', 'official_pdf_checksum', 'official_pdf_size'];
        $submission = ['version_number' => 2, 'submitted_at' => $submittedAt, 'review_status' => 'pending',
            'secretary_reviewed_by' => null, 'secretary_reviewed_at' => null, 'secretary_remarks' => null];
        $revisedTool = $tool->replicate($officialFiles);
        $revisedTool->fill([...$submission, 'supersedes_report_id' => $tool->id, 'tracking_number' => $tool->tracking_number.'-V2',
            'issues' => 'The team reconciled the observation log and checked the itemized totals against the source sheets.'])->save();
        $this->reportPdf($revisedTool);

        $revisedProgress = $progress->replicate($officialFiles);
        $revisedProgress->fill([...$submission, 'monitoring_report_id' => $revisedTool->id, 'tracking_number' => $progress->tracking_number.'-V2',
            'submission_date' => $submittedAt, 'results_discussion' => $progress->results_discussion."\n\nThe revised report includes a reconciliation of the observation log and the pilot totals."])->save();
        $this->reportPdf($revisedProgress);
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

    /** @param array<string, mixed> $proof
     * @return list<array<string, mixed>>
     */
    private function terminalPhotos(TopicProposal $topic, array $proof, bool $portfolio = false): array
    {
        $poster = imagecreatetruecolor(440, 240);
        $background = imagecolorallocate($poster, 240, 249, 250);
        $ink = imagecolorallocate($poster, 15, 37, 53);
        $accent = imagecolorallocate($poster, 0, 106, 116);
        $white = imagecolorallocate($poster, 255, 255, 255);
        imagefill($poster, 0, 0, $background);
        imagefilledrectangle($poster, 0, 0, 440, 29, $accent);
        imagestring($poster, 3, 18, 9, 'COMMUNITY RESEARCH | PROJECT POSTER', $white);
        imagestring($poster, 5, 18, 61, $topic->title, $ink);
        $window = app(MonitoringQuarterService::class)->reportingWindow($topic);
        imagestring($poster, 3, 18, 95, $window['start']->format('F Y').' - '.$window['end']->format('F Y'), $ink);
        foreach ($portfolio ? ['Record baseline', 'Pilot the method', 'Review findings'] : ['Map sites', 'Record readings', 'Share results'] as $index => $label) {
            $left = 18 + $index * 139;
            imagefilledrectangle($poster, $left, 136, $left + 124, 180, $accent);
            imagestring($poster, 3, $left + 9, 151, $label, $white);
        }
        imagestring($poster, 3, 18, 207, 'Illustrative field workflow', $ink);
        ob_start();
        imagepng(imagescale($poster, 880, 480));
        $contents = ob_get_clean();

        $filename = $portfolio ? 'project-poster.png' : 'water-quality-project-poster.png';

        return [
            ['path' => $this->store($topic, $filename, $contents), 'original_name' => $filename,
                'mime_type' => 'image/png', 'size' => strlen($contents), 'section' => 'cover', 'after_paragraph' => 0,
                'caption' => $portfolio ? $topic->title.' project poster' : 'Community water quality toolkit project poster'],
            ['path' => $proof['path'], 'original_name' => $proof['name'], 'mime_type' => $proof['mime_type'], 'size' => $proof['size'],
                'section' => 'results_discussion', 'after_paragraph' => 0, 'caption' => 'Pilot observation log reused from the second-quarter Monitoring Tool'],
        ];
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
