<?php

use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

uses(TestCase::class);

test('AI assistance actions display the chatbot icon', function (string $component, array $handlers, ?array $buttonSource = null) {
    $user = new User;
    $user->id = 1;
    auth()->setUser($user);
    $draft = new ProposalDraft(['user_id' => 1, 'project_title' => 'Coastal study']);
    $draft->id = 4;
    $topic = new TopicProposal;
    $topic->id = 12;
    if ($buttonSource !== null) {
        $source = file_get_contents(resource_path('views/'.$buttonSource[0].'.blade.php'));
        $start = strrpos(substr($source, 0, strpos($source, $buttonSource[1])), '<button');
        $component = substr($source, $start, strpos($source, '</button>', $start) + strlen('</button>') - $start);
    }
    $html = Blade::render('<x-research-assistant-drawer />'.$component, [
        'item' => ['label' => 'Get revision help', 'target_label' => 'Coastal study'],
        'drafts' => collect([$draft]),
        'topic' => $topic,
        'question' => 'What ethics review is required?',
        'reader' => ['guidance_questions' => ['What ethics review is required?']],
    ]);
    $document = new DOMDocument;
    @$document->loadHTML(str_replace('@click=', 'x-on:click=', $html));
    $xpath = new DOMXPath($document);
    $launcherPath = $xpath->query('//*[@id="research-assistant-launcher"]//*[local-name()="svg"][1]/*[local-name()="path"]')->item(0)->getAttribute('d');

    foreach ($handlers as $handler) {
        $buttons = [];
        foreach ($xpath->query('//button') as $button) {
            if (str_contains($button->getAttribute('x-on:click'), $handler)
                || str_contains($button->getAttribute('x-bind:disabled'), $handler) || $button->hasAttribute($handler)) {
                $buttons[] = $button;
            }
        }
        expect($buttons)->not->toBeEmpty();
        foreach ($buttons as $button) {
            $icons = $xpath->query('.//*[local-name()="svg"][@data-assistant-icon]', $button);
            expect($icons)->toHaveCount(1);
            $icon = $icons->item(0);
            expect($icon->getAttribute('aria-hidden'))->toBe('true')
                ->and($xpath->query('./*[local-name()="path"]', $icon)->item(0)->getAttribute('d'))->toBe($launcherPath)
                ->and($button->hasAttribute('x-text'))->toBeFalse();
        }
    }
})->with([
    'dashboard help' => ['<x-dashboard-assistant-action :item="$item" :help="true" />', ['openProposalHelp']],
    'dashboard reviews' => ['<x-dashboard-assistant-action :item="$item" />', ['startSuggestedReview']],
    'report assistance' => ['<x-assistant-report-editor :context="[]" />', ['reviewReport()', 'requestChanges()']],
    'proposal help' => ['<x-research-assistant-proposal-help />', ['startProposalHelp']],
    'starter suggestions' => ['<x-research-assistant-welcome />', ['sendPrompt']],
    'review menu' => ['<x-research-assistant-menu id="test" />', ['startSuggestedReview']],
    'journal comparison' => ['<x-journal-finder endpoint="/journals" />', ['askAthena()']],
    'literature drafting' => ['<x-rrl-finder :proposal-drafts="$drafts" />', ['askAthena()', 'prepareSynthesis(', 'generateSynthesis(']],
    'source assistance' => ['<x-proposal-source-workspace />', ['prepareSourceWorkspacePaperDraft(', 'prepareSourceWorkspaceAssistance(']],
    'proposal conversation' => ['', ['openWithContext('], ['topics/show', 'openWithContext(']],
    'poster image assistance' => ['', ['data-research-call-extract'], ['research_calls/partials/form', 'data-research-call-extract disabled']],
    'document analysis' => ['<x-research-assistant-document-action id="test" />', ['toggleDocumentPicker(', 'analyzeSelectedDocument(']],
    'proposal suggestions' => ['<x-research-assistant-proposal-changes />', ['findProposalStudies(', 'refreshProposalChanges(']],
    'report suggestions' => ['<x-research-assistant-report-changes />', ['refreshReportChanges(']],
    'chat literature assistance' => ['<x-research-assistant-literature />', ['draftSelectedLiterature(', 'confirmLiteratureDraft(', 'reviewLiteratureFlow(']],
    'paper revision assistance' => ['', ['requestRrlRevision('], ['faculty/proposal-drafts/detailed-proposal/edit', 'requestRrlRevision(']],
    'connected literature drafts' => ['', ['generateLiteratureReviewDraft('], ['faculty/proposal-drafts/detailed-proposal/partials/literature-workspace', "generateLiteratureReviewDraft('auto')"]],
    'standalone literature drafts' => ['', ['generateLiteratureReviewDraft('], ['faculty/proposal-drafts/detailed-proposal/partials/literature-workspace', "generateLiteratureReviewDraft('standalone')"]],
    'literature tool identity' => ['', ["window.location.hash = 'rrl-finder'"], ['faculty/research_support/index', "window.location.hash = 'rrl-finder'"]],
    'manual sample question' => ['', ['askGuidance('], ['components/research-manual-reader', 'askGuidance(@js($question))']],
    'manual suggested checks' => ['', ['runGuidanceChecks('], ['components/research-manual-reader', 'runGuidanceChecks()']],
    'manual guidance question' => ['', ['guidanceLoading || suiteRunning || !question.trim()'], ['components/research-manual-reader', "x-text=\"guidanceLoading ? 'Checking…'"]],
]);
