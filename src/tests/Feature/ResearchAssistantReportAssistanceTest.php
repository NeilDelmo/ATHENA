<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\RmsQuarterlyReportData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->researcher = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->researcher->assignRole(['faculty', 'faculty_researcher']);
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $call = ResearchCall::create(['title' => 'Reviewed reports', 'academic_year' => '2026-2027', 'opens_at' => now()->subMonth(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->topic = TopicProposal::create(['user_id' => $this->researcher->id, 'research_call_id' => $call->id, 'title' => 'Coastal monitoring',
        'estimated_budget' => 50000, 'estimated_duration_months' => 12, 'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_by' => $this->head->id, 'notice_to_proceed_issued_at' => now()->subMonths(3)]);
    $this->progress = ProjectNarrativeReportDraft::create(['user_id' => $this->researcher->id, 'topic_id' => $this->topic->id,
        'report_type' => 'progress', 'lock_version' => 2, 'source_data' => ['introduction' => 'Monitoring describes coastal conditions.',
            'results_discussion' => 'Interviewed 12 participants [1].', 'objectives' => 'Keep the approved objective.',
            'accomplishments' => [['objective' => 'Approved target', 'target' => '20 participants', 'actual' => 'Interviewed 12 participants.']]]]);
    $this->terminal = ProjectNarrativeReportDraft::create(['user_id' => $this->researcher->id, 'topic_id' => $this->topic->id,
        'report_type' => 'terminal', 'lock_version' => 4, 'source_data' => ['results_discussion' => 'Interviewed 12 participants [1].',
            'terminal_data' => ['conclusions' => 'Coastal conditions require further observation.', 'literature_review' => '<p>Existing findings describe coastal conditions (Santos, 2024).</p>']]]);
    $this->monitoring = ProjectMonitoringDraft::create(['user_id' => $this->researcher->id, 'topic_id' => $this->topic->id, 'source_key' => 'new',
        'lock_version' => 3, 'source_data' => ['work_plan' => [['activity' => 'Approved activity', 'percent_weight' => 50,
            'actual_accomplishment' => 'Interviewed 12 participants.', 'findings' => 'Further observation is needed.']], 'budget_utilization' => [['amount_requested' => 5000, 'remarks' => 'Documents await processing.']]]]);
    $this->asset = ['id' => (string) Str::uuid(), 'title' => 'Coastal sensor', 'type' => 'Utility Model', 'inventors' => 'Saved researcher',
        'date' => '2026-08-14', 'remarks' => 'Application documents await review.', 'evidence_url' => 'https://example.org/proof'];
    $this->college = CollegeQuarterlyReport::create(['college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'prepared_by' => $this->coordinator->id, 'other_accomplishments' => 'Provided support for coastal monitoring.', 'ip_assets' => [$this->asset],
        'evidence' => [['path' => 'college/proof.jpg', 'caption' => 'Research presentation documentation.']]]);
    $this->rms = RmsQuarterlyReport::create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'updated_by' => $this->head->id]);
    config(['cache.default' => 'array', 'services.openrouter.key' => '', 'services.gemini.key' => 'fixture-key',
        'services.gemini.model' => 'fixture-model', 'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    $this->fakeChanges = function (array $suggestions = []): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'Review the suggested wording against the saved records.', 'suggestions' => $suggestions,
        ], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']]])]);
    };
    $this->payload = fn (string $type, array $context = [], string $action = 'suggest_report_changes'): array => [
        'messages' => [['role' => 'user', 'content' => $action === 'review_report' ? 'Check this report for missing information.' : 'Improve this report wording.']],
        'action' => ['type' => $action], 'context' => ['report_type' => $type, ...$context],
    ];
    $this->selectWorkspace = function (User $user, string $workspace): void {
        $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    };
});

test('project report suggestions resolve immutable saved wording without changing any report', function (string $type, string $field, string $before, string $value, int $version) {
    $baseline = [$this->progress->refresh()->source_data, $this->terminal->refresh()->source_data, $this->monitoring->refresh()->source_data];
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    ($this->fakeChanges)([['field' => $field, 'before' => $before, 'value' => $value, 'reason' => 'Clarifies the wording.']]);
    $response = $this->postJson(route('research-support.chat'), ($this->payload)($type, ['topic_id' => $this->topic->id, 'field' => $field]));
    $response->assertSuccessful()->assertJsonPath('report_changes.report_type', $type)->assertJsonPath('report_changes.suggestions.0.field', $field);
    $suggestion = $response->json('report_changes.suggestions.0');
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])
        ->assertSuccessful()->assertJsonPath('report_change.value', $value)->assertJsonPath('report_change.before', $before)->assertJsonPath('report_change.draft_version', $version);
    expect([$this->progress->fresh()->source_data, $this->terminal->fresh()->source_data, $this->monitoring->fresh()->source_data])->toBe($baseline);
})->with([
    ['progress-report', 'introduction', 'Monitoring describes coastal conditions.', 'Coastal conditions are described through monitoring.', 2],
    ['progress-report', 'accomplishments.0.actual', 'Interviewed 12 participants.', 'Completed interviews with 12 participants.', 2],
    ['monitoring-tool', 'work_plan.0.actual_accomplishment', 'Interviewed 12 participants.', 'Completed interviews with 12 participants.', 3],
    ['monitoring-tool', 'budget_utilization.0.remarks', 'Documents await processing.', 'Documents are awaiting processing.', 3],
    ['terminal-report', 'terminal_data.conclusions', 'Coastal conditions require further observation.', 'Further observation of coastal conditions is needed.', 4],
]);

test('report suggestions reject factual changes protected fields citations and oversized output', function (string $field, string $before, string $value) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    ($this->fakeChanges)([['field' => $field, 'before' => $before, 'value' => $value, 'reason' => 'Attempted replacement.']]);
    $this->postJson(route('research-support.chat'), ($this->payload)('progress-report', ['topic_id' => $this->topic->id]))
        ->assertSuccessful()->assertJsonCount(0, 'report_changes.suggestions');
})->with([
    ['results_discussion', 'Interviewed 12 participants [1].', 'Interviewed 20 participants [1].'],
    ['results_discussion', 'Interviewed 12 participants [1].', 'Interviewed 12 participants.'],
    ['objectives', 'Keep the approved objective.', 'Replace the approved objective.'],
    ['introduction', 'Monitoring describes coastal conditions.', '<script>overwrite()</script>'],
    ['introduction', 'Monitoring describes coastal conditions.', str_repeat('x', 30001)],
]);

test('unsupported report sections never fall through to unrelated narrative edits', function (string $question) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    $payload = ($this->payload)('progress-report', ['topic_id' => $this->topic->id]);
    $payload['messages'][0]['content'] = $question;
    $this->postJson(route('research-support.chat'), $payload)->assertUnprocessable();
    Http::assertNothingSent();
})->with(['Improve my objectives.', 'Revise the funding source.', 'Improve the actual accomplishment for row 99.', 'Revise my conclusions.',
    'Change the report names.', 'Update my approval.', 'Revise my signature.', 'Change the report dates.', 'Change the report amounts.']);

test('withheld report edits cannot leave a summary claiming a suggestion is available', function (array $suggestions) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'summary' => 'One minor editorial clarification is proposed for review.', 'suggestions' => $suggestions,
    ], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']]])]);

    $response = $this->postJson(route('research-support.chat'), ($this->payload)('progress-report', ['topic_id' => $this->topic->id]))
        ->assertSuccessful()->assertJsonCount(0, 'report_changes.suggestions');

    expect($response->json('reply'))->not->toContain('One minor editorial clarification is proposed')
        ->toContain('No wording suggestions are available to apply.', 'Your report is unchanged.', 'No external sources were consulted.');
})->with([
    'no candidates' => [[]],
    'changed fact' => [[['field' => 'results_discussion', 'before' => 'Interviewed 12 participants [1].',
        'value' => 'Interviewed 20 participants [1].', 'reason' => 'Clarifies wording.']]],
]);

test('natural requests for precise wording improvements produce reviewable suggestions', function () {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    ($this->fakeChanges)([['field' => 'introduction', 'before' => 'Monitoring describes coastal conditions.',
        'value' => 'Coastal conditions are described through monitoring.', 'reason' => 'Clarifies wording.']]);
    $payload = ($this->payload)('progress-report', ['topic_id' => $this->topic->id]);
    unset($payload['action']);
    $payload['messages'][0]['content'] = 'Read this report and suggest precise improvements to its wording and organization. Preserve recorded facts and citations. Show changes for my review.';

    $this->postJson(route('research-support.chat'), $payload)->assertSuccessful()
        ->assertJsonPath('report_changes.suggestions.0.field', 'introduction');
});

test('unchanged terminal literature cannot claim a wording suggestion is available', function () {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return Http::response(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'One minor editorial clarification is proposed for review.', 'blocks' => $payload['blocks'],
        ], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']]]);
    }]);

    $response = $this->postJson(route('research-support.chat'), ($this->payload)('terminal-report', ['topic_id' => $this->topic->id, 'field' => 'terminal_data.literature_review']))
        ->assertSuccessful()->assertJsonCount(0, 'report_changes.suggestions');

    expect($response->json('reply'))->not->toContain('One minor editorial clarification is proposed')
        ->toContain('No wording suggestions are available to apply.', 'Your report is unchanged.');
});

test('RMS review identifies saved report sources without presenting evidence links as consulted external sources', function () {
    $this->college->update(['submitted_at' => now(), 'snapshot' => ['college' => $this->college->college, 'ip_assets' => [$this->asset]]]);
    ($this->selectWorkspace)($this->head, User::WORKSPACE_RESEARCH_HEAD);
    ($this->fakeChanges)();

    $response = $this->postJson(route('research-support.chat'), ($this->payload)('rms-quarterly', ['report_id' => $this->rms->id], 'review_report'))
        ->assertSuccessful()->assertJsonPath('sources.0.title', 'RMS Quarterly Monitoring Report · Q3 2026')
        ->assertJsonPath('sources.0.url', route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertJsonPath('sources.1.title', $this->college->college.' · Quarterly Accomplishment Report · Q3 2026 · Version 1');

    expect($response->json('reply'))->toContain('No external sources were consulted.', 'uploaded evidence was not checked.')
        ->and($response->json('sources.1.url'))->toBe(route('research_head.quarterly-reports.index', ['year' => 2026, 'quarter' => 3, 'college' => $this->college->college]))
        ->and(json_encode($response->json('sources')))->not->toContain('ATHENA 1', 'example.org/proof');
});

test('report wording suggestions preserve facts inside narrative fields', function (string $before, string $value, int $count) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    $this->progress->update(['source_data' => ['results_discussion' => $before]]);
    ($this->fakeChanges)([['field' => 'results_discussion', 'before' => $before, 'value' => $value, 'reason' => 'Revises wording.']]);
    $payload = ($this->payload)('progress-report', ['topic_id' => $this->topic->id]);
    unset($payload['action']);
    $payload['messages'][0]['content'] = 'Update this report wording.';

    $this->postJson(route('research-support.chat'), $payload)->assertSuccessful()->assertJsonCount($count, 'report_changes.suggestions');
    expect($this->progress->fresh()->source_data['results_discussion'])->toBe($before);
})->with([
    'name' => ['Dr. Maria Santos recorded the observations.', 'Dr. Juan Cruz recorded the observations.', 0],
    'written date' => ['Observations were recorded in May.', 'Observations were recorded in June.', 0],
    'numeric date' => ['Observations were recorded on 10/11/2026.', 'Observations were recorded on 11/10/2026.', 0],
    'currency' => ['Materials cost PHP 500.', 'Materials cost USD 500.', 0],
    'approval' => ['The board has not approved the protocol.', 'The board has approved the protocol.', 0],
    'valid wording' => ['Dr. Maria Santos recorded observations in May.', 'In May, observations were recorded by Dr. Maria Santos.', 1],
]);

test('resolving rejects changed drafts withdrawn access other accounts and expired tokens', function (string $change) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    ($this->fakeChanges)([['field' => 'introduction', 'before' => 'Monitoring describes coastal conditions.', 'value' => 'Coastal conditions are described through monitoring.', 'reason' => 'Clarifies wording.']]);
    $suggestion = $this->postJson(route('research-support.chat'), ($this->payload)('progress-report', ['topic_id' => $this->topic->id]))->assertSuccessful()->json('report_changes.suggestions.0');
    if ($change === 'changed') {
        $this->progress->increment('lock_version');
    }
    if ($change === 'withdrawn') {
        $this->topic->update(['project_status' => 'completed']);
    }
    if ($change === 'other') {
        ($this->selectWorkspace)($this->head, User::WORKSPACE_RESEARCH_HEAD);
    }
    if ($change === 'expired') {
        $this->travel(31)->minutes();
    }
    $response = $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']]);
    match ($change) {
        'changed' => $response->assertConflict(), 'withdrawn' => $response->assertForbidden(), default => $response->assertUnprocessable()
    };
})->with(['changed', 'withdrawn', 'other', 'expired']);

test('report suggestions preserve negative signs and percentage units', function (string $value) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    $this->progress->update(['source_data' => ['results_discussion' => 'The change was -12.5%.']]);
    ($this->fakeChanges)([['field' => 'results_discussion', 'before' => 'The change was -12.5%.', 'value' => $value, 'reason' => 'Attempted factual edit.']]);
    $this->postJson(route('research-support.chat'), ($this->payload)('progress-report', ['topic_id' => $this->topic->id]))->assertSuccessful()->assertJsonCount(0, 'report_changes.suggestions');
})->with(['The change was 12.5%.', 'The change was -12.5.', 'The change was +12.5%.']);

test('older cached report suggestions cannot bypass current factual protections', function () {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    ($this->fakeChanges)([['field' => 'introduction', 'before' => 'Monitoring describes coastal conditions.',
        'value' => 'Coastal conditions are described through monitoring.', 'reason' => 'Clarifies wording.']]);
    $suggestion = $this->postJson(route('research-support.chat'), ($this->payload)('progress-report', ['topic_id' => $this->topic->id]))->assertSuccessful()->json('report_changes.suggestions.0');
    $key = 'assistant-report-changes:'.hash('sha256', $suggestion['token']);
    Cache::put($key, [...Cache::get($key), 'value' => 'The report is approved.'], now()->addMinutes(30));

    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertUnprocessable();
    expect($this->progress->fresh()->source_data['introduction'])->toBe('Monitoring describes coastal conditions.');
});

test('terminal literature revision rejects reversing a negative percentage', function () {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    $this->terminal->update(['source_data' => ['terminal_data' => ['literature_review' => '<p>The change was -12.5% (Santos, 2024).</p>']]]);
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $blocks = $payload['blocks'];
        $blocks[0]['text'] = str_replace('-12.5%', '12.5%', $blocks[0]['text']);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Changed the meaning.', 'blocks' => $blocks])], 'finish_reason' => 'stop']]]);
    }]);
    $this->postJson(route('research-support.chat'), ($this->payload)('terminal-report', ['topic_id' => $this->topic->id, 'field' => 'terminal_data.literature_review']))->assertStatus(502);
});

test('terminal abstract suggestions respect the final document word-count rule', function (int $words, int $expected) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    $before = trim(str_repeat('Coastal monitoring observations support further research. ', 35));
    $value = trim(str_repeat('observation ', $words));
    $this->terminal->update(['source_data' => ['terminal_data' => ['abstract' => $before]]]);
    ($this->fakeChanges)([['field' => 'terminal_data.abstract', 'before' => $before, 'value' => $value, 'reason' => 'Rewords the existing abstract.']]);
    $this->postJson(route('research-support.chat'), ($this->payload)('terminal-report', ['topic_id' => $this->topic->id, 'field' => 'terminal_data.abstract']))
        ->assertSuccessful()->assertJsonCount($expected, 'report_changes.suggestions');
})->with([[199, 0], [200, 1], [250, 1], [251, 0]]);

test('college suggestions use saved report identity captions and owning workspace only', function () {
    ($this->selectWorkspace)($this->coordinator, User::WORKSPACE_RESEARCH_OFFICE);
    $field = 'saved_evidence_captions.'.hash('sha256', 'college/proof.jpg');
    ($this->fakeChanges)([['field' => $field, 'before' => 'Research presentation documentation.', 'value' => 'Documentation of the research presentation.', 'reason' => 'Clarifies caption wording.']]);
    $packet = $this->postJson(route('research-support.chat'), ($this->payload)('college-quarterly', ['report_id' => $this->college->id, 'field' => $field]))
        ->assertSuccessful()->assertJsonPath('report_changes.report_key', 'college-quarterly:'.$this->college->id)->json('report_changes');
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $packet['suggestions'][0]['token'], 'suggestion_id' => $packet['suggestions'][0]['id']])->assertSuccessful();
    $other = User::factory()->create(['college' => User::COLLEGES['CAS']]);
    $other->assignRole('research_coordinator');
    ($this->selectWorkspace)($other, User::WORKSPACE_RESEARCH_OFFICE);
    $this->postJson(route('research-support.chat'), ($this->payload)('college-quarterly', ['report_id' => $this->college->id]))->assertForbidden();
});

test('submitted college reports can be checked but not edited and RMS uses current submitted source identity', function () {
    $this->college->update(['submitted_at' => now(), 'snapshot' => ['college' => $this->college->college, 'ip_assets' => [$this->asset], 'other_accomplishments' => 'Frozen accomplishment text.']]);
    ($this->selectWorkspace)($this->head, User::WORKSPACE_RESEARCH_HEAD);
    $key = 'ip-r'.$this->college->id.'-'.$this->asset['id'];
    expect(app(RmsQuarterlyReportData::class)->build(2026, 3)['ip_assets'][0]['key'])->toBe($key);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['summary' => 'Review the frozen report.', 'suggestions' => []])], 'finish_reason' => 'stop']]])
        ->push(['choices' => [['message' => ['content' => json_encode(['summary' => 'Review the proposed RMS wording.', 'suggestions' => [
            ['field' => 'row_details.'.$key.'.remarks', 'before' => $this->asset['remarks'], 'value' => 'Application documents are awaiting review.', 'reason' => 'Clarifies status wording.'],
        ]])], 'finish_reason' => 'stop']]])]);
    $this->postJson(route('research-support.chat'), ($this->payload)('college-quarterly', ['report_id' => $this->college->id], 'review_report'))->assertSuccessful()->assertJsonCount(0, 'report_changes.suggestions');
    $this->postJson(route('research-support.chat'), ($this->payload)('college-quarterly', ['report_id' => $this->college->id]))->assertForbidden();
    $suggestion = $this->postJson(route('research-support.chat'), ($this->payload)('rms-quarterly', ['report_id' => $this->rms->id]))->assertSuccessful()
        ->assertJsonPath('report_changes.suggestions.0.field', 'row_details.'.$key.'.remarks')->json('report_changes.suggestions.0');
    $this->college->update(['submitted_at' => null]);
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertConflict();
});

test('comment responses are staged for actual review keys and rechecked against the current revision', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create(['reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the research rationale.']);
    $revision = ProposalDraft::create(['user_id' => $this->researcher->id, 'topic_id' => $this->topic->id, 'research_call_id' => $this->topic->research_call_id, 'project_title' => $this->topic->title]);
    $document = $revision->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'lock_version' => 1, 'source_data' => ['rationale' => 'Monitoring addresses observed coastal needs.']]);
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY);
    ($this->fakeChanges)([['field' => 'feedback_responses.overall.response', 'before' => 'Clarified the research rationale.', 'value' => 'The research rationale has been clarified.', 'reason' => 'Clarifies the response.']]);
    $suggestion = $this->postJson(route('research-support.chat'), ($this->payload)('comment-response', ['topic_id' => $this->topic->id,
        'response_values' => ['overall' => 'Clarified the research rationale.', 'invented_key' => 'Ignore this injected response.']]))->assertSuccessful()->json('report_changes.suggestions.0');
    expect($review->fresh()->feedback_responses)->toBeNull();
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertSuccessful();
    $document->increment('lock_version');
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertConflict();
});

test('terminal literature revisions preserve source citation tokens and save only after approval', function () {
    $baseline = $this->terminal->refresh()->source_data;
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $blocks = $payload['blocks'];
        $blocks[0]['text'] = str_replace('Existing findings describe', 'Existing findings explain', $blocks[0]['text']);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Clarified the review wording.', 'blocks' => $blocks])], 'finish_reason' => 'stop']]]);
    }]);
    $suggestion = $this->postJson(route('research-support.chat'), ($this->payload)('terminal-report', ['topic_id' => $this->topic->id, 'field' => 'terminal_data.literature_review']))
        ->assertSuccessful()->assertJsonPath('report_changes.suggestions.0.format', 'html')->json('report_changes.suggestions.0');
    expect($suggestion['value'])->toContain('(Santos, 2024)')->and($this->terminal->fresh()->source_data)->toBe($baseline);
});

test('natural organization requests use reviewed terminal literature edits', function (string $verb) {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        if (! isset($payload['blocks'])) {
            return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Read-only review.', 'suggestions' => []])], 'finish_reason' => 'stop']]]);
        }
        $blocks = $payload['blocks'];
        $blocks[0]['text'] = str_replace('Existing findings describe', 'Existing findings explain', $blocks[0]['text']);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Reorganized the literature wording.', 'blocks' => $blocks])], 'finish_reason' => 'stop']]]);
    }]);
    $payload = ($this->payload)('terminal-report', ['topic_id' => $this->topic->id]);
    unset($payload['action']);
    $payload['messages'][0]['content'] = $verb.' the literature review.';
    $this->postJson(route('research-support.chat'), $payload)->assertSuccessful()
        ->assertJsonPath('report_changes.suggestions.0.field', 'terminal_data.literature_review')
        ->assertJsonPath('report_changes.suggestions.0.format', 'html');
})->with(['Reorganize', 'Rearrange', 'Clean up']);

test('blank comment responses cannot claim a revision when saved papers only clone the reviewed version', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $sourceData = ['rationale' => 'Existing rationale from the reviewed proposal.'];
    $version = $this->topic->versions()->create(['version_number' => 1, 'submission_type' => 'initial', 'title' => $this->topic->title,
        'submitted_by' => $this->researcher->id, 'estimated_budget' => 50000, 'estimated_duration_months' => 12,
        'file_path' => 'fixture/reviewed-package.pdf', 'original_filename' => 'reviewed-package.pdf']);
    $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'fixture/reviewed-proposal.pdf',
        'original_filename' => 'reviewed-proposal.pdf', 'source_data' => $sourceData]);
    $this->topic->reviews()->create(['reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the research rationale.']);
    $revision = ProposalDraft::create(['user_id' => $this->researcher->id, 'topic_id' => $this->topic->id, 'research_call_id' => $this->topic->research_call_id,
        'project_title' => $this->topic->title]);
    $revision->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'lock_version' => 1, 'source_data' => $sourceData]);
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY);
    ($this->fakeChanges)([['field' => 'feedback_responses.overall.response', 'before' => '', 'value' => 'The rationale has been clarified.', 'reason' => 'Claims the requested revision was completed.']]);
    $this->postJson(route('research-support.chat'), ($this->payload)('comment-response', ['topic_id' => $this->topic->id,
        'response_values' => ['overall' => '']]))->assertUnprocessable();
    Http::assertNothingSent();
});

test('blank comment responses use changed saved narrative and become stale when reviewed source text changes', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $version = $this->topic->versions()->create(['version_number' => 1, 'submission_type' => 'initial', 'title' => $this->topic->title,
        'submitted_by' => $this->researcher->id, 'estimated_budget' => 50000, 'estimated_duration_months' => 12,
        'file_path' => 'fixture/reviewed-package.pdf', 'original_filename' => 'reviewed-package.pdf']);
    $paper = $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'fixture/reviewed-proposal.pdf',
        'original_filename' => 'reviewed-proposal.pdf', 'source_data' => ['rationale' => 'The reviewed rationale lacks a clear monitoring purpose.']]);
    $this->topic->reviews()->create(['reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the monitoring purpose.']);
    $revision = ProposalDraft::create(['user_id' => $this->researcher->id, 'topic_id' => $this->topic->id, 'research_call_id' => $this->topic->research_call_id,
        'project_title' => $this->topic->title]);
    $revision->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'lock_version' => 1,
        'source_data' => ['rationale' => 'The revised rationale explains monitoring as documenting coastal conditions.']]);
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY);
    ($this->fakeChanges)([['field' => 'feedback_responses.overall.response', 'before' => '', 'value' => 'The revised rationale explains the monitoring purpose.', 'reason' => 'Describes the saved wording change for review.']]);
    $suggestion = $this->postJson(route('research-support.chat'), ($this->payload)('comment-response', ['topic_id' => $this->topic->id,
        'response_values' => ['overall' => '']]))->assertSuccessful()->assertJsonPath('report_changes.suggestions.0.before', '')->json('report_changes.suggestions.0');
    Http::assertSent(function ($request): bool {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $context = implode(' ', $payload['saved_context']['values']);

        return str_contains($context, 'The reviewed rationale lacks a clear monitoring purpose.')
            && str_contains($context, 'The revised rationale explains monitoring as documenting coastal conditions.');
    });
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertSuccessful();
    $paper->update(['source_data' => ['rationale' => 'The reviewed source changed.']]);
    $this->postJson(route('research-support.report-changes.resolve'), ['token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertConflict();
});

test('report history stores safe previews and remains unavailable after workspace switch', function () {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    ($this->fakeChanges)([['field' => 'introduction', 'before' => 'Monitoring describes coastal conditions.', 'value' => 'Coastal conditions are described through monitoring.', 'reason' => 'Clarifies wording.']]);
    $packet = $this->postJson(route('research-support.chat'), ($this->payload)('progress-report', ['topic_id' => $this->topic->id]))->assertSuccessful()->json('report_changes');
    $conversation = $this->postJson(route('research-support.history.save'), ['messages' => [['role' => 'user', 'content' => 'Improve the report.'],
        ['role' => 'assistant', 'content' => 'Review these edits.', 'report_changes' => $packet]], 'context' => $packet['context']])->assertSuccessful()
        ->assertJsonPath('conversation.messages.1.report_changes.suggestions.0.field', 'introduction')->json('conversation');
    $this->progress->delete();
    $this->getJson(route('research-support.history.show', $conversation['id']))->assertSuccessful();
    $this->topic->update(['project_status' => 'completed']);
    $this->getJson(route('research-support.history.show', $conversation['id']))->assertSuccessful();
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY);
    $this->getJson(route('research-support.history.show', $conversation['id']))->assertNotFound();
});

test('ordinary history remains savable with absent or null report suggestion packets', function () {
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    $this->postJson(route('research-support.history.save'), ['messages' => [['role' => 'user', 'content' => 'Explain the report.'],
        ['role' => 'assistant', 'content' => 'The report records saved accomplishments.', 'report_changes' => null]]])->assertSuccessful();
});

test('terminal writing revisions retain citations anchored in the approved proposal after the private draft is gone', function () {
    $citation = '<span data-proposal-citation="77" data-proposal-locator="p. 4">[1]</span>';
    $html = '<p>Existing findings describe coastal conditions. '.$citation.'</p>';
    $version = $this->topic->versions()->create(['version_number' => 1, 'title' => $this->topic->title, 'description' => 'Approved coastal study.', 'estimated_budget' => 50000,
        'estimated_duration_months' => 12, 'submitted_by' => $this->researcher->id, 'submission_type' => 'initial',
        'file_path' => 'approved/package.pdf', 'original_filename' => 'package.pdf']);
    $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'approved/proposal.pdf', 'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => str_repeat('a', 64), 'uploaded_by' => $this->researcher->id, 'source_data' => ['related_literature' => $html]]);
    $this->terminal->update(['source_data' => ['terminal_data' => ['literature_review' => $html]]]);
    ($this->selectWorkspace)($this->researcher, User::WORKSPACE_FACULTY_RESEARCHER);
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $blocks = $payload['blocks'];
        $blocks[0]['text'] = str_replace('Existing findings describe', 'Existing findings explain', $blocks[0]['text']);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Clarified the review.', 'blocks' => $blocks])], 'finish_reason' => 'stop']]]);
    }]);
    $this->postJson(route('research-support.chat'), ($this->payload)('terminal-report', ['topic_id' => $this->topic->id, 'field' => 'terminal_data.literature_review']))
        ->assertSuccessful()->assertJsonPath('report_changes.suggestions.0.format', 'html')->assertJsonPath('report_changes.suggestions.0.value', str_replace('findings describe', 'findings explain', $html));
    expect($this->topic->revisionDraft)->toBeNull();
});

test('saved college evidence captions persist through existing save and reject unrelated proof tokens', function () {
    ($this->selectWorkspace)($this->coordinator, User::WORKSPACE_RESEARCH_OFFICE);
    $input = ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'save', 'form_complete' => '1', 'saved_evidence_captions' => [hash('sha256', 'college/proof.jpg') => 'Reviewed presentation documentation.']];
    $input['draft_token'] = $this->college->fresh()->draftToken();
    $this->postJson(route('research_coordinator.reports.store'), $input)->assertRedirect()->assertSessionHasNoErrors();
    expect($this->college->fresh()->evidence[0]['caption'])->toBe('Reviewed presentation documentation.');
    $input['saved_evidence_captions'] = [str_repeat('a', 64) => 'Another proof.'];
    $input['draft_token'] = $this->college->fresh()->draftToken();
    $this->postJson(route('research_coordinator.reports.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('saved_evidence_captions');
});
