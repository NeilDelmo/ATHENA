<?php

use App\Models\ProjectDocument;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\MonitoringQuarterService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mysql = config('database.connections.mysql');
$database = $mysql['database'];
$appUrl = parse_url((string) config('app.url'));
$root = str_replace('\\', '/', storage_path());
if (! $app->environment('testing') || config('database.default') !== 'mysql' || $mysql['url']
    || $mysql['host'] !== '127.0.0.1' || (int) $mysql['port'] < 1024 || (int) $mysql['port'] > 65535
    || ! preg_match('/^athena_jmeter_\d+$/D', $database)
    || ! preg_match('#^'.preg_quote(str_replace('\\', '/', base_path('test-results/jmeter-local')), '#').'/\d+/storage$#D', $root)
    || ! is_array($appUrl) || ($appUrl['scheme'] ?? null) !== 'http' || ($appUrl['host'] ?? null) !== '127.0.0.1'
    || (int) ($appUrl['port'] ?? 0) < 1024 || (int) ($appUrl['port'] ?? 0) > 65535
    || isset($appUrl['user']) || isset($appUrl['pass']) || isset($appUrl['query']) || isset($appUrl['fragment'])) {
    throw new RuntimeException('The benchmark fixtures require a separate, loopback-only JMeter database and storage.');
}

$directory = dirname($root);
if (($argv[1] ?? '') === 'verify') {
    $pool = json_decode(File::get($directory.'/pool.local.json'), true, flags: JSON_THROW_ON_ERROR);
    $expected = $pool['writeReports'];
    $reports = ProjectProgressReport::query()->whereIn('id', $expected)->get();
    if ($reports->count() !== count($expected) || $reports->contains(fn ($report): bool => ! $report->isSubmitted() || $report->review_stage !== 'head' || $report->review_status !== 'reviewed'
        || ! $report->secretary_reviewed_by || ! $report->reviewed_by)) {
        throw new RuntimeException('A synthetic report did not complete the researcher → secretary → head handoff.');
    }
    $drafts = ProposalDraft::query()->where('project_title', 'like', 'JMETER TEST %')->count();
    if ($drafts !== count($expected)) {
        throw new RuntimeException('The expected synthetic proposal drafts were not saved.');
    }
    echo "Verified $drafts synthetic proposal drafts were created.\n";
    echo 'Verified '.$reports->count()." monitoring reports were submitted, forwarded, and accepted.\n";
    exit;
}

$pdo = new PDO('mysql:host=127.0.0.1;port='.(int) $mysql['port'], $mysql['username'], $mysql['password']);
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}

$allocation = json_decode($argv[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
$users = [];
$accounts = [];
$roles = ['faculty', 'faculty_researcher', 'research_head', 'research_office', 'research_secretary', 'vcrdes'];
foreach ($roles as $workspace) {
    $role = $workspace === 'research_office' ? 'research_coordinator' : $workspace;
    Role::findOrCreate($role, 'web');
    for ($i = 1; $i <= $allocation[$workspace]; $i++) {
        $account = User::factory()->create([
            'name' => 'JMeter '.str_replace('_', ' ', ucfirst($workspace)).' '.$i,
            'email' => 'jmeter.'.$workspace.'.'.$i.'@example.test',
            'college' => 'CICS', 'department' => 'Synthetic research department',
        ]);
        $account->assignRole($role);
        $accounts[$workspace][] = $account;

        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        $session = app('session.store');
        $session->start();
        $guard = Auth::guard('web');
        $guard->login($account);
        if ($session->get($guard->getName()) !== $account->id) {
            throw new RuntimeException('The authenticated session was not written to the active store.');
        }
        $session->put(['active_role' => $role, User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
        $session->save();
        $cookie = config('session.cookie');
        $encrypter = app('encrypter');
        $value = $encrypter->encrypt(CookieValuePrefix::create($cookie, $encrypter->getKey()).$session->getId(), false);
        $sessionFile = 'sessions/'.$workspace.'-'.$i.'.json';
        File::ensureDirectoryExists($directory.'/sessions');
        File::put($directory.'/'.$sessionFile, json_encode(['cookies' => [[
            'name' => $cookie, 'value' => $value, 'domain' => '127.0.0.1', 'path' => '/', 'expires' => -1, 'secure' => false,
        ]]], JSON_THROW_ON_ERROR));
        $users[$workspace][] = ['workspace' => $workspace, 'actorId' => $account->id, 'sessionFile' => $sessionFile];
    }
}

$head = $accounts['research_head'][0];
$call = ResearchCall::create([
    'title' => 'JMeter synthetic research call', 'academic_year' => now()->year.'-'.(now()->year + 1), 'status' => 'open',
    'opens_at' => now()->subMonth(), 'closes_at' => now()->addMonth(), 'created_by' => $head->id,
    'max_active_research_per_faculty' => 5,
]);

$proposalRecords = [];
foreach ($accounts['faculty'] as $i => $faculty) {
    for ($j = 1; $j <= 2; $j++) {
        $title = 'JMeter proposal '.($i + 1).'.'.$j;
        $draft = ProposalDraft::create(['user_id' => $faculty->id, 'research_call_id' => $call->id,
            'project_title' => $title.' draft', 'project_leader' => $faculty->name]);
        $topic = TopicProposal::create(['user_id' => $faculty->id, 'research_call_id' => $call->id,
            'title' => $title, 'status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
        $record = ['topic_id' => $topic->id, 'draft_id' => $draft->id, 'call_id' => $call->id];
        $users['faculty'][$i]['recordSets'][] = $record;
        $proposalRecords[] = $record;
    }
}

$projectRecords = [];
$writeReports = [];
$writtenProjects = [];
$start = now()->subMonthsNoOverflow(4)->startOfMonth();
$end = $start->copy()->addYear()->subDay();
$pdf = "%PDF-1.4\n";
$xref = "0000000000 65535 f \n";
foreach ([
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << >> >>',
] as $i => $object) {
    $xref .= sprintf("%010d 00000 n \n", strlen($pdf));
    $pdf .= ($i + 1)." 0 obj\n$object\nendobj\n";
}
$pdf .= "xref\n0 4\n$xref"."trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n".strlen($pdf)."\n%%EOF\n";
$checksum = hash('sha256', $pdf);
foreach ($accounts['faculty_researcher'] as $i => $researcher) {
    for ($j = 1; $j <= ($i < 3 ? 3 : 2); $j++) {
        $topic = TopicProposal::create([
            'user_id' => $researcher->id, 'research_call_id' => $call->id, 'title' => 'JMeter project '.($i + 1).'.'.$j,
            'status' => 'approved', 'project_status' => 'ongoing', 'estimated_budget' => 30000, 'estimated_duration_months' => 12,
            'notice_to_proceed_issued_by' => $head->id, 'notice_to_proceed_issued_at' => $start,
            'notice_to_proceed_data' => ['approved_start_date' => $start->toDateString(), 'approved_end_date' => $end->toDateString()],
        ]);
        $path = 'jmeter/'.$topic->id.'.pdf';
        if (! Storage::disk('local')->put($path, $pdf)) {
            throw new RuntimeException('Unable to write the benchmark PDF.');
        }
        $document = ProjectDocument::factory()->for($topic, 'topic')->for($researcher, 'uploadedBy')->create([
            'file_path' => $path, 'checksum' => $checksum, 'file_size' => strlen($pdf), 'original_filename' => 'synthetic-project.pdf',
        ]);
        $period = app(MonitoringQuarterService::class)->forDate($start, $topic);
        $report = ProjectProgressReport::create([
            'topic_id' => $topic->id, 'submitted_by' => $researcher->id, 'reporting_date' => $period['end'],
            'reporting_year' => $period['year'], 'reporting_quarter' => $period['quarter'],
            'period_start' => $period['start'], 'period_end' => $period['end'], 'version_number' => 1,
            'progress_percentage' => 0, 'accomplishments' => 'Synthetic monitoring report', 'review_stage' => 'secretary',
            'submission_status' => $j === 3 ? 'prepared' : 'submitted', 'submitted_at' => $j === 3 ? null : now(),
            'work_plan' => [['activity' => 'Conduct benchmark survey', 'percent_weight' => 100, 'accomplished_percentage' => 0]],
            'budget_utilization' => [['type' => 'Purchase Request', 'amount_requested' => 1000, 'actual_amount' => 0]],
            'official_pdf_path' => $path, 'official_pdf_filename' => 'synthetic-monitoring.pdf',
            'official_pdf_checksum' => $checksum, 'official_pdf_size' => strlen($pdf),
        ]);
        $record = ['topic_id' => $topic->id, 'document_id' => $document->id, 'report_id' => $report->id];
        if ($j === 3) {
            $writeReports[] = $report->id;
            $writtenProjects[] = $record;
            $users['faculty_researcher'][$i]['writeRecord'] = $record;
        } else {
            $users['faculty_researcher'][$i]['recordSets'][] = $record;
            $projectRecords[] = $record;
        }
    }
}

foreach (['research_office' => $proposalRecords, 'research_head' => $projectRecords, 'research_secretary' => $projectRecords] as $role => $records) {
    foreach ($users[$role] as $i => &$user) {
        $first = ($i * 2) % count($records);
        $user['recordSets'] = [$records[$first], $records[$first + 1]];
    }
    unset($user);
}

if (Artisan::call('view:cache', ['--no-interaction' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}

File::put($directory.'/pool.local.json', json_encode([
    'baseUrl' => config('app.url'), 'isolatedTestEnvironment' => true, 'users' => array_merge(...array_values($users)),
    'writeReports' => $writeReports, 'writeRecords' => $writtenProjects,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Prepared '.count($proposalRecords).' proposals and '.(count($projectRecords) + count($writeReports))." projects.\n";
