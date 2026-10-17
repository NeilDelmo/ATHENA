<?php

use App\Services\ResearchAssistantReportAssistanceService;
use App\Services\ResearchAssistantRrlRevisionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

test('report review controls render with their record context and explicit accept actions', function () {
    $this->blade('<x-assistant-report-editor :context="$context" :editable="false" />', ['context' => ['report_type' => 'college-quarterly', 'report_id' => 7]])
        ->assertSee('data-assistant-report-editor', false)
        ->assertSee('college-quarterly')
        ->assertSee('Check with Athena')
        ->assertSee('This submitted report is read only.');
    $this->blade('<x-research-assistant-report-changes />')
        ->assertSee('Apply change')->assertSee('Keep current')->assertSee('data-assistant-report-changes', false);
});

beforeEach(function () {
    config(['services.openrouter.key' => '', 'services.gemini.key' => 'fixture-key', 'services.gemini.model' => 'fixture-model',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    $this->assistance = app(ResearchAssistantReportAssistanceService::class);
    $this->historyRules = $this->assistance->historyRules('messages.*.report_changes');
});

test('report history rules accept ordinary chats with absent or null packets', function (array $message) {
    $validator = Validator::make(['messages' => [['role' => 'assistant', 'content' => 'Saved report guidance.', ...$message]]], $this->historyRules);

    expect($validator->passes())->toBeTrue();
})->with([[[]], [['report_changes' => null]]]);

test('report history rules accept bounded suggestions for each supported paper', function (string $type, string $field, array $identity) {
    $context = ['report_type' => $type, ...$identity];
    $key = isset($identity['report_id']) ? $type.':'.$identity['report_id'] : $type.':topic:'.$identity['topic_id'].(isset($identity['source_key']) ? ':'.$identity['source_key'] : '');
    $packet = ['report_key' => $key, 'report_type' => $type, 'title' => 'Saved report', 'editor_url' => null, 'context' => $context,
        'suggestions' => [['id' => 'suggestion-1', 'token' => str_repeat('a', 64), 'field' => $field, 'label' => 'Saved wording',
            'before' => 'Existing report wording.', 'value' => 'Existing wording from the report.', 'reason' => 'Clarifies wording.', 'format' => 'plain']]];
    $validator = Validator::make(['messages' => [['report_changes' => $packet]]], $this->historyRules);

    expect($validator->passes())->toBeTrue()
        ->and($this->assistance->historyPacket($packet)['suggestions'][0]['field'])->toBe($field);
})->with([
    ['progress-report', 'results_discussion', ['topic_id' => 1]],
    ['monitoring-tool', 'work_plan.0.findings', ['topic_id' => 1, 'source_key' => 'new']],
    ['terminal-report', 'terminal_data.conclusions', ['topic_id' => 1]],
    ['comment-response', 'feedback_responses.overall.response', ['topic_id' => 1]],
    ['college-quarterly', 'other_accomplishments', ['report_id' => 1]],
    ['rms-quarterly', 'row_details.ip-r1-asset1.remarks', ['report_id' => 1]],
]);

test('report history rules reject incomplete packets while preserving inert terminal citation previews', function () {
    expect(Validator::make(['messages' => [['report_changes' => ['suggestions' => []]]]], $this->historyRules)->fails())->toBeTrue();
    $html = '<p>Existing findings. <span data-proposal-citation="77" data-proposal-locator="p. 4">[1]</span></p>';
    $packet = ['report_key' => 'terminal-report:topic:1', 'report_type' => 'terminal-report', 'context' => ['report_type' => 'terminal-report', 'topic_id' => 1],
        'suggestions' => [['id' => 'suggestion-1', 'token' => str_repeat('a', 64), 'field' => 'terminal_data.literature_review', 'label' => 'Literature review',
            'before' => $html, 'value' => str_replace('Existing findings.', 'Existing observations.', $html), 'format' => 'html']]];

    expect(Validator::make(['messages' => [['report_changes' => $packet]]], $this->historyRules)->passes())->toBeTrue()
        ->and($this->assistance->historyPacket($packet)['suggestions'][0]['before'])->toBe($html)
        ->and($this->assistance->historyPacket($packet)['suggestions'][0]['format'])->toBe('html');
});

test('public literature revision rejects changed negative signs and percentage units', function (string $replacement) {
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) use ($replacement) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $blocks = $payload['blocks'];
        $blocks[0]['text'] = str_replace('-12.5%', $replacement, $blocks[0]['text']);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Attempted factual change.', 'blocks' => $blocks])], 'finish_reason' => 'stop']]]);
    }]);

    expect(fn () => app(ResearchAssistantRrlRevisionService::class)->revise('<p>The observed change was -12.5% (Santos, 2024).</p>', [], 'Improve the wording.'))
        ->toThrow(HttpException::class);
    Http::assertSentCount(1);
})->with(['12.5%', '+12.5%', '-12.5']);

test('public literature revision preserves signed percentages and citation identity during a valid wording edit', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $blocks = $payload['blocks'];
        $blocks[0]['text'] = str_replace('The observed change was', 'The recorded change was', $blocks[0]['text']);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Clarified the wording.', 'blocks' => $blocks])], 'finish_reason' => 'stop']]]);
    }]);
    $citation = '<span data-proposal-citation="77" data-proposal-locator="p. 4">[1]</span>';
    $html = '<p>The observed change was -12.5%. '.$citation.'</p>';
    $result = app(ResearchAssistantRrlRevisionService::class)->revise($html, [77], 'Improve the wording.');

    expect($result['value'])->toBe(str_replace('observed', 'recorded', $html));
    Http::assertSentCount(1);
});
