<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\AiChatCompletionService;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

if (getenv('ATHENA_TEST_LIVE_REPORT_AI') !== '1') {
    test('live report provider smoke requires explicit opt in')->skip('Set ATHENA_TEST_LIVE_REPORT_AI=1 to send fictional report text to the configured AI provider.');

    return;
}

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_DATABASE') !== 'athena_testing') {
    throw new RuntimeException('Live report tests require APP_ENV=testing and the isolated MySQL athena_testing database before database refresh.');
}

$liveResultsInitialized = false;

beforeEach(function () use (&$liveResultsInitialized) {
    expect(app()->environment('testing'))->toBeTrue();
    expect(config('database.default'))->toBe('mysql');
    expect(DB::connection()->getDatabaseName())->toBe('athena_testing');
    expect(DB::selectOne('SELECT DATABASE() AS database_name')->database_name)->toBe('athena_testing');
    $developmentValues = Dotenv::createArrayBacked(base_path())->safeLoad();
    foreach (['gemini' => 'GEMINI', 'openrouter' => 'OPENROUTER'] as $provider => $prefix) {
        foreach (['API_KEY' => 'key', 'MODEL' => 'model', 'BASE_URL' => 'base_url'] as $suffix => $setting) {
            if (array_key_exists($prefix.'_'.$suffix, $developmentValues)) {
                config(['services.'.$provider.'.'.$setting => $developmentValues[$prefix.'_'.$suffix]]);
            }
        }
    }
    unset($developmentValues);
    if (! app(AiChatCompletionService::class)->isConfigured()) {
        throw new RuntimeException('No live AI provider is configured. Configure the existing provider settings before opting in.');
    }
    $providerUrls = collect(['openrouter', 'gemini'])->filter(fn (string $provider): bool => filled(config('services.'.$provider.'.key')) && filled(config('services.'.$provider.'.model')))
        ->map(fn (string $provider): string => rtrim(config('services.'.$provider.'.base_url'), '/').'/chat/completions')->all();
    Http::preventStrayRequests();
    Http::allowStrayRequests($providerUrls);
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $researcher = User::factory()->create(['name' => 'Fictional Live Test Researcher', 'college' => User::COLLEGES['CICS']]);
    $researcher->assignRole(['faculty', 'faculty_researcher']);
    $head = User::factory()->create(['name' => 'Fictional Live Test Research Head']);
    $head->assignRole('research_head');
    $coordinator = User::factory()->create(['name' => 'Fictional Live Test Coordinator', 'college' => User::COLLEGES['CICS']]);
    $coordinator->assignRole('research_coordinator');
    $call = ResearchCall::create(['title' => 'Fictional Live Report Smoke', 'academic_year' => '2026-2027', 'opens_at' => now()->subMonth(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $topic = TopicProposal::create(['user_id' => $researcher->id, 'research_call_id' => $call->id, 'title' => 'Fictional Test of Coastal Records', 'estimated_budget' => 50000,
        'estimated_duration_months' => 12, 'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_by' => $head->id, 'notice_to_proceed_issued_at' => now()->subMonths(3)]);
    $before = 'The records was reviewed for consistency.';
    $literature = '<p>The findings was consistent across the reviewed records (Santos, 2024).</p>';
    $progress = ProjectNarrativeReportDraft::create(['user_id' => $researcher->id, 'topic_id' => $topic->id, 'report_type' => 'progress', 'lock_version' => 2,
        'source_data' => ['introduction' => $before]]);
    $terminal = ProjectNarrativeReportDraft::create(['user_id' => $researcher->id, 'topic_id' => $topic->id, 'report_type' => 'terminal', 'lock_version' => 3,
        'source_data' => ['terminal_data' => ['conclusions' => $before, 'literature_review' => $literature]]]);
    $monitoring = ProjectMonitoringDraft::create(['user_id' => $researcher->id, 'topic_id' => $topic->id, 'source_key' => 'new', 'lock_version' => 4,
        'source_data' => ['work_plan' => [['activity' => 'Review fictional records.', 'findings' => $before, 'percent_weight' => 100]]]]);
    $asset = ['id' => (string) Str::uuid(), 'title' => 'Fictional Test Sensor', 'type' => 'Utility Model', 'inventors' => 'Fictional Test Researcher', 'date' => '2026-08-14',
        'remarks' => $before, 'evidence_url' => 'https://example.org/fictional-test-proof'];
    $college = CollegeQuarterlyReport::create(['college' => $coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'prepared_by' => $coordinator->id,
        'other_accomplishments' => $before, 'ip_assets' => [$asset]]);
    $sourceCollege = CollegeQuarterlyReport::create(['college' => User::COLLEGES['CAS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'prepared_by' => $coordinator->id,
        'submitted_at' => now(), 'ip_assets' => [$asset], 'snapshot' => ['college' => User::COLLEGES['CAS'], 'ip_assets' => [$asset]]]);
    $rms = RmsQuarterlyReport::create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'updated_by' => $head->id]);
    $this->recordModels = [$topic, $progress, $terminal, $monitoring, $college, $sourceCollege, $rms];
    $this->liveCases = [
        'progress-report' => [$researcher, User::WORKSPACE_FACULTY_RESEARCHER, ['report_type' => 'progress-report', 'topic_id' => $topic->id, 'field' => 'introduction']],
        'monitoring-tool' => [$researcher, User::WORKSPACE_FACULTY_RESEARCHER, ['report_type' => 'monitoring-tool', 'topic_id' => $topic->id, 'source_key' => 'new', 'field' => 'work_plan.0.findings']],
        'terminal-report' => [$researcher, User::WORKSPACE_FACULTY_RESEARCHER, ['report_type' => 'terminal-report', 'topic_id' => $topic->id, 'field' => 'terminal_data.conclusions']],
        'terminal-rrl' => [$researcher, User::WORKSPACE_FACULTY_RESEARCHER, ['report_type' => 'terminal-report', 'topic_id' => $topic->id, 'field' => 'terminal_data.literature_review']],
        'college-quarterly' => [$coordinator, User::WORKSPACE_RESEARCH_OFFICE, ['report_type' => 'college-quarterly', 'report_id' => $college->id, 'field' => 'other_accomplishments']],
        'rms-quarterly' => [$head, User::WORKSPACE_RESEARCH_HEAD, ['report_type' => 'rms-quarterly', 'report_id' => $rms->id, 'field' => 'row_details.ip-r'.$sourceCollege->id.'-'.$asset['id'].'.remarks']],
    ];
    $this->liveTopic = $topic;
    $this->liveResearcher = $researcher;
    $this->liveHead = $head;
    $this->liveCall = $call;
    $this->snapshotRecords = fn (): array => collect($this->recordModels)->map(fn ($model): array => $model->fresh()->getAttributes())->all();
    $this->captureLiveResult = function (array $result) use (&$liveResultsInitialized): void {
        $path = storage_path('framework/testing/report-ai-live-results.json');
        File::ensureDirectoryExists(dirname($path));
        $results = $liveResultsInitialized && File::isFile($path) ? json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR) : [];
        $results[] = $result;
        File::put($path, json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $liveResultsInitialized = true;
    };
});

test('configured live provider produces a reviewed suggestion without writing the fictional report', function (string $case) {
    if ($case === 'comment-response') {
        $this->liveTopic->update(['status' => 'revision_requested']);
        $version = $this->liveTopic->versions()->create(['version_number' => 1, 'submission_type' => 'initial', 'title' => $this->liveTopic->title,
            'submitted_by' => $this->liveResearcher->id, 'estimated_budget' => 50000, 'estimated_duration_months' => 12,
            'file_path' => 'fictional-test/package.pdf', 'original_filename' => 'fictional-package.pdf']);
        $priorPaper = $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'fictional-test/proposal.pdf',
            'original_filename' => 'fictional-proposal.pdf', 'source_data' => ['rationale' => 'The rationale does not yet explain the purpose of checking fictional records.']]);
        $review = $this->liveTopic->reviews()->create(['reviewer_id' => $this->liveHead->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the fictional rationale.']);
        $revision = ProposalDraft::create(['user_id' => $this->liveResearcher->id, 'topic_id' => $this->liveTopic->id, 'research_call_id' => $this->liveCall->id,
            'project_title' => $this->liveTopic->title, 'lock_version' => 2]);
        $document = $revision->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'source_data' => ['rationale' => 'The rationale now explains the purpose of checking fictional records.'], 'lock_version' => 1]);
        array_push($this->recordModels, $version, $priorPaper, $review, $revision, $document);
        $this->liveCases[$case] = [$this->liveResearcher, User::WORKSPACE_FACULTY, ['report_type' => 'comment-response', 'topic_id' => $this->liveTopic->id,
            'field' => 'feedback_responses.overall.response', 'response_values' => ['overall' => 'We has clarified the rationale.']]];
    }
    [$user, $workspace, $context] = $this->liveCases[$case];
    $baseline = ($this->snapshotRecords)();
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $question = $case === 'terminal-rrl'
        ? "Fix subject-verb agreement in the existing literature review: change 'findings was' to 'findings were'. Preserve its citation, meaning, and every block. Show the change for my review."
        : "Improve this field by correcting subject-verb agreement only. Change 'records was' to 'records were' or 'We has' to 'We have', as applicable. Keep all existing facts and provide one proposed replacement for review.";
    $response = $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => $question]], 'action' => ['type' => 'suggest_report_changes'], 'context' => $context]);
    $response->assertSuccessful()->assertJsonPath('report_changes.report_type', $context['report_type']);
    $suggestion = collect($response->json('report_changes.suggestions'))->firstWhere('field', $context['field']);
    expect($suggestion)->not->toBeNull();
    $resolved = $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])
        ->assertSuccessful()->assertJsonPath('report_change.field', $context['field'])->assertJsonPath('report_change.before', $suggestion['before'])
        ->assertJsonPath('report_change.value', $suggestion['value'])->json('report_change');
    expect(($this->snapshotRecords)())->toBe($baseline);
    ($this->captureLiveResult)(['report_type' => $context['report_type'], 'field' => $context['field'], 'before' => $resolved['before'], 'value' => $resolved['value'], 'format' => $resolved['format']]);
})->with(['progress-report', 'monitoring-tool', 'terminal-report', 'terminal-rrl', 'comment-response', 'college-quarterly', 'rms-quarterly']);

test('configured live provider reviews fictional RMS records without preparing or applying changes', function () {
    [$user, $workspace, $context] = $this->liveCases['rms-quarterly'];
    unset($context['field']);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $baseline = ($this->snapshotRecords)();
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Check the supplied fictional RMS report for missing required information. Briefly identify missing entries; never invent funding or supporting evidence.']],
        'action' => ['type' => 'review_report'], 'context' => $context])->assertSuccessful()->assertJsonCount(0, 'report_changes.suggestions');
    expect(($this->snapshotRecords)())->toBe($baseline);
});
