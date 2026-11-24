<?php

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'athena_playwright'
    || ! in_array(parse_url(getenv('APP_URL'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Refusing to prepare browser fixtures outside the isolated local test database.');
}

require __DIR__.'/lifecycle-clock.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'athena_playwright'
    || str_replace('\\', '/', storage_path()) !== str_replace('\\', '/', base_path('storage/framework/testing/playwright'))) {
    throw new RuntimeException('Browser fixtures require their own database and storage directory.');
}

/** @param list<string> $lines */
function lifecyclePdf(array $lines): string
{
    $stream = "BT /F1 11 Tf 45 790 Td 16 TL\n";
    foreach ($lines as $line) {
        $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
    }
    $stream .= 'ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}

$command = $argv[1] ?? 'prepare';
$manifestPath = base_path('playwright/.auth/local/lifecycle.json');

if ($command === 'clock') {
    $date = $argv[2] ?? '';
    if (! in_array($date, ['2026-01-01 10:00:00', '2026-04-02 10:00:00'], true)) {
        throw new InvalidArgumentException('Unknown browser-test reporting date.');
    }
    File::put(storage_path('clock.txt'), $date);
    exit(0);
}

if ($command === 'inspect') {
    $topic = TopicProposal::with(['latestVersion.files'])->findOrFail((int) $argv[2]);
    $uploads = $topic->latestVersion?->files->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->sortByDesc('id');
    $gad = $uploads?->first(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT);
    $evaluation = $uploads?->first(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION);
    echo json_encode([
        'status' => $topic->status, 'projectStatus' => $topic->project_status,
        'userId' => $topic->user_id, 'researchCallId' => $topic->research_call_id,
        'version' => $topic->latestVersion?->version_number,
        'versionCount' => $topic->versions()->count(),
        'files' => $topic->latestVersion?->files->count(),
        'detailedProposal' => $topic->latestVersion?->files->firstWhere('document_type', 'detailed_proposal')?->source_data['research_agenda'] ?? null,
        'noticeIssued' => $topic->notice_to_proceed_issued_at !== null,
        'gad' => $gad?->source_data,
        'evaluation' => $evaluation?->source_data,
        'monitoring' => ProjectProgressReport::where('topic_id', $topic->id)->get()->toArray(),
        'narratives' => ProjectNarrativeReport::where('topic_id', $topic->id)->get()->toArray(),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($command === 'reports') {
    $topic = TopicProposal::findOrFail((int) $argv[2]);
    $workPlan = app(ApprovedWorkPlanMonitoringService::class)->defaultsForDate($topic, '2026-03-31');
    foreach ($workPlan as &$activity) {
        $activity['actual_accomplishment'] = 'Completed the approved coastal habitat report.';
    }
    unset($activity);
    ProjectMonitoringDraft::create([
        'topic_id' => $topic->id, 'user_id' => $topic->user_id, 'source_key' => 'new', 'lock_version' => 1,
        'source_data' => [
            'reporting_date' => '2026-03-31', 'work_plan' => $workPlan,
            'budget_utilization' => array_map(fn (string $type): array => [
                'type' => $type, 'amount_requested' => 0, 'actual_amount' => 0,
            ], ['Purchase Request', 'Cash Advance', 'Request of Payment']),
        ],
    ]);
    foreach (['progress', 'terminal'] as $type) {
        $data = app(ProgressReportData::class)->defaults($topic, '2026-03-31');
        $data = [
            ...$data, 'report_type' => $type, 'reporting_date' => '2026-03-31',
            'submission_date' => '2026-04-02', 'funding_agency' => 'Batangas State University',
            'implementation_start' => '2026-01-01', 'implementation_end' => '2026-03-31',
            'monitoring_report_id' => null,
        ];
        foreach (['introduction', 'rationale', 'objectives', 'methodology', 'results_discussion'] as $section) {
            $data[$section] = 'The capstone test project completed its approved coastal habitat report using documented observations and validation.';
        }
        foreach ($data['accomplishments'] as &$accomplishment) {
            $accomplishment['actual'] = 'Completed and validated one coastal habitat report.';
        }
        unset($accomplishment);
        if ($type === 'terminal') {
            $terminal = app(TerminalReportData::class)->defaults($topic)['terminal_data'];
            $terminal['abstract'] = implode(' ', array_fill(0, 20, 'The project documented coastal habitats and validated the findings with participating stakeholders.'));
            foreach (['literature_review', 'conclusions', 'recommendations', 'bibliography'] as $section) {
                $terminal[$section] = 'Capstone test narrative: evidence from the approved project supports the reported coastal habitat findings.';
            }
            $terminal['total_expenditure'] = 0;
            $data['terminal_data'] = $terminal;
        }
        ProjectNarrativeReportDraft::create([
            'topic_id' => $topic->id, 'user_id' => $topic->user_id, 'report_type' => $type,
            'source_data' => $data, 'lock_version' => 1,
        ]);
    }
    exit(0);
}

if ($command === 'functional') {
    require __DIR__.'/functional-fixtures.php';
    exit(0);
}

if ($command !== 'prepare') {
    throw new InvalidArgumentException('Unknown browser-test fixture action.');
}

$mysql = config('database.connections.mysql');
$pdo = new PDO('mysql:host='.$mysql['host'].';port='.$mysql['port'], $mysql['username'], $mysql['password']);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `athena_playwright` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
app(PermissionRegistrar::class)->forgetCachedPermissions();

$users = [];
$workspaces = [];
foreach ([
    'faculty' => ['faculty', 'faculty', 'CICS'],
    'head' => ['research_head', 'research_head', 'CICS'],
    'secretary' => ['research_office_secretary', 'research_secretary', 'CICS'],
    'coordinator' => ['research_coordinator', 'research_office', 'CICS'],
    'vcrdes' => ['vcrdes', 'vcrdes', 'CICS'],
    'outsider' => ['faculty', 'faculty', 'CTE'],
] as $key => [$role, $workspace, $college]) {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create([
        'name' => 'Capstone Test '.ucfirst($key), 'email' => 'playwright.'.$key.'@g.batstate-u.edu.ph',
        'college' => $college, 'department' => 'Capstone test department',
    ]);
    $user->assignRole($role);
    $users[$key] = $user;
    $workspaces[$key] = $workspace;
}

$call = ResearchCall::create([
    'title' => 'Capstone browser test call', 'academic_year' => '2026-2027', 'status' => 'open',
    'opens_at' => '2025-12-01', 'closes_at' => '2026-12-31', 'max_active_research_per_faculty' => 10,
    'created_by' => $users['head']->id,
]);
$drafts = [];
foreach (['main' => 'Coastal Habitat Restoration', 'rejection' => 'Rejection Scenario', 'revision' => 'Revision Scenario'] as $key => $label) {
    $draft = ProposalDraft::create([
        'user_id' => $users['faculty']->id, 'research_call_id' => $call->id,
        'project_title' => 'Capstone E2E '.$label, 'duration_months' => 3,
        'planned_start' => '2026-01-01', 'planned_end' => '2026-03-31',
        'project_leader' => $users['faculty']->name,
        'signatory_selections' => [
            'comment_response_head' => ['name' => $users['head']->name],
            'comment_response_vice_chancellor' => ['name' => 'Capstone Test Vice Chancellor'],
        ],
    ]);
    $sources = [
        'detailed_proposal' => [
            'research_agenda' => 'Environment and Climate Change', 'sdgs' => [13, 14, 17],
            'leader_email' => $users['faculty']->email, 'leader_contact' => '09171234567', 'staff' => [],
            'proponent_department' => 'Capstone test department', 'proponent_college' => 'CICS',
            'proponent_campus' => 'ARASOF-Nasugbu', 'cooperating_agency' => '',
            'executive_brief' => 'Document and validate coastal habitats through evidence-based community research.',
            'rationale' => 'Coastal habitat degradation threatens biodiversity and livelihoods.',
            'general_objective' => 'Document and validate the baseline coastal habitat condition.',
            'specific_objectives' => [['description' => 'Document and validate the baseline coastal habitat condition.']],
            'expected_outputs' => [
                'publication' => [['description' => '1 report']], 'patent' => [],
                'product' => [['description' => 'Validated coastal habitat profile']],
                'people_service' => [['description' => 'Community training']],
                'place_partnership' => [['description' => 'University partnership']],
                'policy' => [['description' => 'Restoration protocol']],
                'social_impact' => [['description' => 'Improved participation']],
                'economic_impact' => [['description' => 'Protected livelihoods']],
            ],
            'introduction' => 'The project addresses coastal habitat degradation through community research.',
            'related_literature' => 'Published research supports participatory coastal habitat monitoring.',
            'methodology' => [
                'research_design' => 'A mixed-method descriptive study.',
                'specific_methods' => 'Conduct field observations and stakeholder validation.',
                'data_analysis' => 'Analyze results with descriptive statistics and thematic analysis.',
            ],
            'responsibilities' => [['name' => $users['faculty']->name, 'percentage' => 100, 'duties' => 'Lead implementation and reporting.']],
            'references' => 'Capstone Test Author. (2025). Coastal habitat restoration. Test Research Journal, 1(1), 1-10.',
        ],
        'work_plan' => ['entries' => [[
            'objective' => 'Document and validate the baseline coastal habitat condition.',
            'expected_output' => '1 report', 'activity' => 'Conduct field survey and validate the findings', 'months' => [1, 2, 3],
        ]]],
        'line_item_budget' => ['amounts' => ['telephone_expenses' => 3600]],
        'expense_breakdown' => ['items' => [[
            'category' => 'mooe', 'account' => 'Communication Expenses', 'sub_account' => 'Telephone Expenses',
            'particulars' => 'Prepaid Card', 'details' => 'Prepaid Call Card', 'purpose' => 'Project communication',
            'unit' => 'pc', 'quantity' => 12, 'unit_cost' => 300,
        ]]],
        'curriculum_vitae' => ['people' => [[
            'last_name' => 'Faculty', 'first_name' => 'Capstone Test', 'middle_name' => '',
            'agency' => 'Batangas State University', 'gender' => '', 'birthday' => '',
            'street' => '', 'barangay' => '', 'municipality' => '', 'province' => '',
            'landline' => '', 'cellphone' => '', 'email' => '',
            ...collect(array_keys(config('curriculum_vitae.sections')))->mapWithKeys(fn (string $section): array => [$section => []])->all(),
        ]]],
    ];
    foreach ($sources as $documentType => $sourceData) {
        $draft->documents()->create(['document_type' => $documentType, 'position' => 0, 'source_data' => $sourceData, 'completed_at' => now()]);
    }
    $drafts[$key] = ['id' => $draft->id, 'title' => $draft->project_title, 'callId' => $call->id];
}

$fixtureDirectory = base_path('test-results/lifecycle-fixtures');
File::ensureDirectoryExists($fixtureDirectory);
$title = $drafts['main']['title'];
$pdfs = [
    'gad.pdf' => ['Box 7a Generic Checklist', 'Assessment of Gender Responsiveness', 'Involvement of women and men', 'Sex-disaggregated data', 'Gender analysis', 'Project Title: '.$title, 'Assessment of Gender Responsiveness', 'TOTAL GAD SCORE FOR THE PROJECT IDENTIFICATION AND DESIGN STAGES: 12.50', 'Interpretation of GAD Scores', 'Checked and verified by: /s/ Capstone Test GAD Verifier'],
    'evaluation.pdf' => ['BatStateU-FO-RES-03 Initial Screening Form', 'Order of Submission', 'Checklist of Submitted Documents', 'Level of Call', 'Project Title: '.$title, 'Project Leader: '.$users['faculty']->name, 'Narrative Evaluation: The objectives and method are complete and suitable for endorsement.', 'Prepared by: /s/ Capstone Test Evaluator'],
    'signed-detailed.pdf' => ['BatStateU-FO-RES-02', 'DETAILED RESEARCH PROPOSAL', 'Research Agenda', 'Project Leader', 'Proponent Agency', 'Sustainable Development Goal', 'Project Title: '.$title, 'Prepared by: /s/ Capstone Test Faculty'],
    'signed-work-plan.pdf' => ['BatStateU-FO-RES-02', 'MAJOR ACTIVITIES/WORK PLAN', 'Total Duration: 3 months', 'Planned Start: January 1, 2026', 'Planned End: March 31, 2026', 'Expected Output: 1 report', 'Project Title: '.$title, 'Prepared by: /s/ Capstone Test Faculty'],
    'signed-budget.pdf' => ['BatStateU-FO-RES-02', 'LINE-ITEM BUDGET', 'Particulars', 'Amount', 'Maintenance and Other Operating Expenses', 'Capital Outlays', 'Project Title: '.$title, 'Prepared by: /s/ Capstone Test Faculty'],
    'evidence.pdf' => ['CAPSTONE TEST DATA ONLY', 'Completed coastal habitat report', 'The approved field survey and stakeholder validation were completed.', 'One report was produced and reviewed by the project team.'],
    'signed-notice.pdf' => ['CAPSTONE TEST DATA ONLY', 'Signed Notice to Proceed', 'Project Title: '.$title, 'Approved implementation: January 1 through March 31, 2026.', 'Approved budget: PHP 3600.00', 'Approved by: /s/ Capstone Test Approving Officer'],
    'signed-terminal.pdf' => ['CAPSTONE TEST DATA ONLY', 'Signed Terminal Report', 'Project Title: '.$title, 'The project delivered its approved coastal habitat report.', 'Prepared, reviewed, verified and approved for this synthetic capstone test.', 'Signed by: /s/ Capstone Test Officers'],
];
foreach ($pdfs as $filename => $lines) {
    File::put($fixtureDirectory.'/'.$filename, lifecyclePdf([...$lines, 'Synthetic document for automated lifecycle validation only. No actual institutional approval is represented.']));
}
File::ensureDirectoryExists(dirname($manifestPath));
File::put($manifestPath, json_encode([
    'key' => bin2hex(random_bytes(32)),
    'drafts' => $drafts,
    'users' => collect($users)->map(fn (User $user, string $actor): array => [...$user->only(['id', 'name', 'email']), 'workspace' => $workspaces[$actor]])->all(),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Isolated lifecycle fixtures prepared.\n";
