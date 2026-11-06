<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use App\Models\ResearchCall;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\RmsQuarterlyReportData;
use App\Services\RmsQuarterlyReportStatus;
use App\Support\ProposalDraftReadiness;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function capacityWebAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function capacityWebGuard(): void
{
    capacityWebAssert((string) env('CAPACITY_ENABLED') === '1', 'Disposable benchmark fixtures are disabled.');
    capacityWebAssert(strlen((string) env('CAPACITY_TOKEN')) >= 32, 'A private benchmark token of at least 32 characters is required.');
}

function capacityWebSeedProjects(User $faculty, User $head, User $secretary, ResearchCall $call, int $count): int
{
    capacityWebGuard();
    capacityWebAssert($count >= 1 && $count <= 2000, 'Project count must be between 1 and 2000.');
    $firstId = 0;
    for ($index = 1; $index <= $count; $index++) {
        $topic = TopicProposal::firstOrCreate(['title' => sprintf('Capacity synthetic project %04d', $index)], [
            'user_id' => $faculty->id, 'research_secretary_id' => $secretary->id,
            'research_call_id' => $call->id, 'research_manual_id' => null,
            'description' => 'Synthetic benchmark project without external data.',
            'estimated_budget' => 3600, 'estimated_duration_months' => 12,
            'status' => 'approved', 'project_status' => $index % 5 === 0 ? 'delayed' : 'ongoing',
            'notice_to_proceed_issued_at' => '2026-07-01 09:00:00', 'notice_to_proceed_issued_by' => $head->id,
            'notice_to_proceed_data' => ['approved_duration_months' => 12, 'approved_start_date' => '2026-07-01', 'approved_end_date' => '2027-06-30'],
        ]);
        $firstId = $firstId ?: $topic->id;
        $topic->versions()->firstOrCreate(['version_number' => 1], [
            'submitted_by' => $faculty->id, 'submission_type' => 'initial',
            'file_path' => 'capacity/source-not-prepared.docx', 'original_filename' => 'synthetic-project.docx',
            'title' => $topic->title, 'description' => $topic->description,
            'estimated_budget' => 3600, 'estimated_duration_months' => 12,
        ]);
        $topic->progressReports()->firstOrCreate(['reporting_year' => 2026, 'reporting_quarter' => 3, 'version_number' => 1], [
            'submitted_by' => $faculty->id, 'reporting_date' => '2026-09-30',
            'period_start' => '2026-07-01', 'period_end' => '2026-09-30',
            'progress_percentage' => 25 + ($index % 60), 'accomplishments' => 'Synthetic progress record.',
            'work_plan' => [], 'budget_utilization' => [], 'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED,
            'submitted_at' => '2026-10-01 09:00:00', 'review_stage' => 'head',
            'review_status' => $index % 3 === 0 ? 'pending' : 'reviewed',
        ]);
    }

    return $firstId;
}

function capacityWebDraftSources(User $faculty): array
{
    // Source shapes mirror ProposalDraftWorkflowTest's completeDraft fixture; PDF conversion belongs to the measured job.
    return [
        'detailed_proposal' => [
            'research_agenda' => 'Environment and Climate Change', 'sdgs' => [13, 14, 17],
            'leader_email' => $faculty->email, 'leader_contact' => '09171234567', 'staff' => [],
            'proponent_department' => 'Research Department', 'proponent_college' => $faculty->college,
            'proponent_campus' => 'ARASOF-Nasugbu', 'cooperating_agency' => '',
            'executive_brief' => 'This project restores priority coastal habitats through evidence-based community action.',
            'rationale' => 'Coastal habitat degradation threatens biodiversity and local livelihoods.',
            'general_objective' => 'Document baseline conditions and validate a community restoration model.',
            'specific_objectives' => [['description' => 'Document baseline conditions and validate a community restoration model.']],
            'expected_outputs' => [
                'publication' => [['description' => 'One peer-reviewed publication']], 'patent' => [],
                'product' => [['description' => 'Validated restoration model']], 'people_service' => [['description' => 'Community training']],
                'place_partnership' => [['description' => 'University-LGU partnership']], 'policy' => [['description' => 'Restoration protocol']],
                'social_impact' => [['description' => 'Improved participation']], 'economic_impact' => [['description' => 'Protected livelihoods']],
            ],
            'introduction' => 'The project responds to coastal habitat degradation through community research.',
            'related_literature' => 'Recent literature supports participatory coastal habitat restoration.',
            'methodology' => [
                'research_design' => 'The project uses a mixed-method design.',
                'specific_methods' => 'The team will conduct surveys, transects, and stakeholder workshops.',
                'data_analysis' => 'Data will be analyzed with descriptive statistics and thematic analysis.',
            ],
            'responsibilities' => [['name' => $faculty->name, 'percentage' => 100, 'duties' => 'Leads implementation, quality assurance, and reporting.']],
            'references' => 'Synthetic Author, A. (2025). Coastal habitat restoration. Synthetic Journal, 1(1), 1-10.',
        ],
        'work_plan' => ['entries' => [[
            'objective' => 'Document the baseline habitat condition', 'expected_output' => 'Validated baseline habitat profile',
            'activity' => "Conduct field survey\nComplete community mapping", 'months' => [1, 2, 3],
        ]]],
        'line_item_budget' => ['amounts' => ['telephone_expenses' => 3600]],
        'expense_breakdown' => ['items' => [[
            'category' => 'mooe', 'account' => 'Communication Expenses', 'sub_account' => 'Telephone Expenses',
            'particulars' => 'Prepaid Card', 'details' => 'Prepaid Call Card', 'purpose' => 'For project communication',
            'unit' => 'pc', 'quantity' => 12, 'unit_cost' => 300,
        ]]],
        'curriculum_vitae' => ['people' => [[
            'last_name' => 'Owner', 'first_name' => 'Capacity', 'middle_name' => '', 'agency' => '', 'gender' => '',
            'birthday' => '', 'street' => '', 'barangay' => '', 'municipality' => '', 'province' => '', 'landline' => '',
            'cellphone' => '', 'email' => '',
            ...collect(array_keys(config('curriculum_vitae.sections')))->mapWithKeys(fn (string $key): array => [$key => []])->all(),
        ]]],
    ];
}

function capacitySeedWeb(): array
{
    capacityWebGuard();
    capacityWebAssert(! User::where('email', 'capacity-head@example.test')->exists(), 'Seed once against a fresh disposable database.');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator', 'research_secretary', 'vcrdes'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $users = [];
    foreach (['head' => ['research_head'], 'faculty' => ['faculty', 'faculty_researcher'],
        'coordinator' => ['research_coordinator'], 'secretary' => ['research_secretary'], 'vcrdes' => ['vcrdes']] as $key => $roles) {
        $users[$key] = User::factory()->create([
            'name' => 'Capacity '.ucfirst($key), 'email' => 'capacity-'.$key.'@example.test',
            'college' => User::COLLEGES['CICS'],
        ]);
        $users[$key]->assignRole($roles);
    }
    $head = $users['head'];
    $faculty = $users['faculty'];
    $coordinator = $users['coordinator'];
    foreach (ProposalSignatory::roles() as $key => $label) {
        ProposalSignatory::create(['role_key' => $key, 'name' => 'Capacity Signatory', 'position' => $label, 'active' => true, 'is_default' => true]);
    }
    $call = ResearchCall::create([
        'title' => 'Capacity synthetic research call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(), 'closes_at' => now()->addYear(), 'status' => 'open',
        'max_active_research_per_faculty' => 2, 'maximum_budget' => 100000, 'created_by' => $head->id,
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id, 'research_call_id' => $call->id, 'research_manual_id' => null,
        'project_title' => 'Capacity Coastal Habitat Restoration', 'project_leader' => $faculty->name,
        'duration_months' => 12, 'planned_start' => '2026-08-01', 'planned_end' => '2027-07-31',
    ]);
    foreach (capacityWebDraftSources($faculty) as $type => $source) {
        $draft->documents()->create(['document_type' => $type, 'position' => 0, 'source_data' => $source, 'completed_at' => now(), 'lock_version' => 0]);
    }
    $draft = $draft->fresh(['documents', 'researchCall', 'owner']);
    $readiness = app(ProposalDraftReadiness::class);
    capacityWebAssert($draft->isEditable() && $draft->owner?->id === $faculty->id, 'PDF draft must be editable and faculty-owned.');
    capacityWebAssert($draft->documents->count() === 5, 'Exactly five source documents are required.');
    capacityWebAssert($readiness->errors($draft) === [], 'Invalid PDF queue fixture: '.json_encode($readiness->errors($draft), JSON_THROW_ON_ERROR));
    capacityWebAssert(! $readiness->submissionFilesArePrepared($draft), 'PDF fixture must require real preparation.');
    $projectCount = (int) env('CAPACITY_PROJECT_COUNT', 100);
    $topicId = capacityWebSeedProjects($faculty, $head, $users['secretary'], $call, $projectCount);

    $reportIds = [];
    foreach (User::COLLEGES as $college) {
        $presentations = $publications = $ipAssets = [];
        foreach (range(1, 12) as $index) {
            $presentations[] = [
                'id' => 'presentation-'.$index, 'event' => 'Synthetic Research Forum '.$index,
                'research_title' => 'Synthetic Research Presentation '.$index, 'date' => '2026-09-03',
                'place' => 'Synthetic Venue', 'personnel' => 'Capacity Researcher', 'authors' => 'Capacity Researcher',
                'source_of_fund' => 'Non-funded', 'sdgs' => '4, 9', 'support_requested' => 'N/A',
                'classification' => 'Institutional', 'evidence_url' => 'https://example.test/synthetic-evidence',
            ];
            $publications[] = [
                'id' => 'publication-'.$index, 'event' => 'Synthetic Journal '.$index,
                'research_title' => 'Synthetic Research Publication '.$index, 'date' => '2026-09-04',
                'personnel' => 'Capacity Researcher', 'authors' => 'Capacity Researcher',
                'source_of_fund' => 'Non-funded', 'sdgs' => '4, 9', 'support_requested' => 'No',
                'publication_type' => 'Journal', 'evidence_url' => 'https://example.test/synthetic-evidence',
            ];
            $ipAssets[] = ['id' => 'ip-'.$index, 'title' => 'Synthetic IP '.$index, 'type' => 'Copyright',
                'inventors' => 'Capacity Researcher', 'date' => '2026-09-05', 'remarks' => 'Synthetic benchmark row.',
                'evidence_url' => 'https://example.test/synthetic-evidence'];
        }
        $snapshot = ['college' => $college, 'prepared_by_name' => $coordinator->name,
            'manual_presentations' => $presentations, 'manual_publications' => $publications,
            'ip_assets' => $ipAssets, 'evidence' => [], 'undated_publications' => 0,
            'research' => array_map(fn (int $index): array => ['Synthetic Project '.$index, '12 months', 'Capacity Researcher', 'Ongoing'], range(1, 12)),
            'presentations' => array_map(fn (array $row): array => [$row['event'], $row['research_title'], $row['date'], $row['place'], $row['personnel']], $presentations),
            'publications' => array_map(fn (array $row): array => [$row['event'], $row['research_title'], $row['date'], $row['personnel']], $publications)];
        $report = CollegeQuarterlyReport::factory()->create([
            'college' => $college, 'prepared_by' => $coordinator->id,
            'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => '2026-10-01 09:00:00',
            'presentations' => $presentations, 'publications' => $publications, 'ip_assets' => $ipAssets,
            'seminars' => [], 'manual_research' => [], 'evidence' => [], 'snapshot' => $snapshot,
            'other_accomplishments' => 'Synthetic quarterly benchmark data.',
        ]);
        CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create([
            'submitted_by' => $coordinator->id, 'version_number' => 1, 'snapshot' => $snapshot,
            'submitted_at' => '2026-10-01 09:00:00',
        ]);
        $reportIds[] = $report->id;
    }
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'updated_by' => $head->id]);
    capacityWebAssert(app(RmsQuarterlyReportStatus::class)->forPeriod(2026, 3)['ready'], 'All six college submissions must unlock RMS downloads.');
    $rms = app(RmsQuarterlyReportData::class)->build(2026, 3);
    foreach (['presentations', 'publications', 'ip_assets'] as $section) {
        capacityWebAssert(count($rms[$section]) === count(User::COLLEGES) * 12, 'Unexpected RMS row count: '.$section);
    }
    foreach (['head' => 'research_head', 'faculty' => 'faculty', 'coordinator' => 'research_office'] as $key => $workspace) {
        foreach (range(1, 100) as $index) {
            $users[$key]->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => ProposalActivityNotification::class,
                'data' => ['title' => 'Synthetic project update '.$index, 'message' => 'Synthetic benchmark notification.',
                    'url' => '/topics/'.$topicId, 'level' => 'info', 'topic_id' => $topicId, 'action_data' => [],
                    'workspace' => $workspace, 'sidebar_area' => $key === 'faculty' ? 'my_projects' : 'project_monitoring'],
                'read_at' => $index % 3 === 0 ? now() : null,
            ]);
        }
    }
    $workspaces = ['head' => 'research_head', 'faculty' => 'faculty', 'coordinator' => 'research_office', 'secretary' => 'research_secretary', 'vcrdes' => 'vcrdes'];

    return [
        'faculty_id' => $faculty->id, 'head_id' => $head->id, 'coordinator_id' => $coordinator->id,
        'secretary_id' => $users['secretary']->id, 'draft_id' => $draft->id, 'topic_id' => $topicId,
        'call_id' => $call->id, 'report_ids' => $reportIds, 'project_count' => $projectCount,
        'users' => collect($users)->map(fn (User $user, string $key): array => ['id' => $user->id, 'workspace' => $workspaces[$key]])->all(),
        'paths' => [
            'faculty_dashboard' => '/faculty/dashboard', 'faculty_submissions' => '/faculty/submissions',
            'faculty_draft' => '/faculty/proposal-drafts/'.$draft->id, 'faculty_draft_review' => '/faculty/proposal-drafts/'.$draft->id.'/review',
            'draft_pdf' => '/faculty/proposal-drafts/'.$draft->id.'/submission-files/detailed-proposal',
            'head_dashboard' => '/research-head/dashboard', 'head_analytics' => '/research-head/analytics',
            'head_proposals' => '/research-head/proposal-submissions', 'head_projects' => '/research-head/projects',
            'head_report_reviews' => '/research-head/report-reviews', 'coordinator_dashboard' => '/research-coordinator/dashboard',
            'quarterly_docx' => '/research-coordinator/reports/download?year=2026&quarter=3',
            'quarterly_print' => '/research-coordinator/reports/print?year=2026&quarter=3',
            'rms_pdf' => '/research-head/rms-quarterly-reports/preview?year=2026&quarter=3',
            'rms_xlsx' => '/research-head/rms-quarterly-reports/download?year=2026&quarter=3',
            'notifications' => '/notifications', 'topic' => '/topics/'.$topicId,
        ],
        'pdf_content_ready' => true, 'pdf_files_prepared' => false,
    ];
}
