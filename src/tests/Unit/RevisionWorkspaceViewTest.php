<?php

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\TopicReview;
use App\Models\TopicReviewFileRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class);

test('revision feedback and responses omit commenter names for every review stage', function (string $stage) {
    $html = view('components.proposal-revision-form', [
        'topic' => $this->topic, 'pendingFileRevisions' => collect([$this->revision]),
        'stagedRevisionFiles' => collect(), 'displayProjectCost' => 3000,
        'commentResponseRows' => [[
            'key' => 'annotation_11', 'reviewer' => 'Dr. Maria Santos', 'location' => 'Work Plan · Page 2',
            'comment' => 'Move fieldwork to June.', 'response' => 'Moved fieldwork to June.',
            'remarks' => '', 'stage' => $stage, 'form_source' => 'research_head',
        ]],
    ])->render();

    expect($html)->not->toContain('Dr. Maria Santos')
        ->and($html)->toContain('Move fieldwork to June.', 'Moved fieldwork to June.');
})->with(['research_head', 'gad', 'co_evaluator', 'lrec']);

beforeEach(function () {
    DB::connection()->beforeExecuting(function (): never {
        throw new RuntimeException('Revision view tests must not access the database.');
    });
    View::share('errors', new ViewErrorBag);

    $this->topic = (new TopicProposal)->forceFill([
        'id' => 3, 'user_id' => 7, 'research_call_id' => 2, 'title' => 'Fruit Drop Detection',
        'status' => 'revision_requested', 'estimated_budget' => 3000, 'estimated_duration_months' => 12,
    ]);
    $version = (new ProposalVersion)->forceFill(['id' => 4, 'topic_id' => 3, 'version_number' => 1]);
    $this->topic->setRelation('versions', collect([$version]));
    $this->topic->setRelation('reviews', collect());
    $this->topic->setRelation('revisionDraft', null);
    $this->topic->setRelation('researchCall', (new ResearchCall)->forceFill(['id' => 2, 'maximum_budget' => 100000]));

    $file = (new ProposalVersionFile)->forceFill([
        'id' => 5, 'proposal_version_id' => 4, 'document_type' => 'work_plan', 'position' => 0,
        'source_data' => ['entries' => [['objective' => 'Observe fruit drop']]],
    ]);
    $this->revision = (new TopicReviewFileRevision)->forceFill([
        'id' => 6, 'document_type' => 'work_plan', 'original_filename' => 'work-plan.pdf',
        'revision_note' => 'Update the fieldwork schedule.',
    ]);
    $this->revision->setRelation('file', $file);
    $this->revision->setRelation('annotations', collect([
        (new ProposalFileAnnotation)->forceFill([
            'id' => 11, 'page_number' => 2, 'editor_target' => 'activity-1',
            'selected_text' => 'May fieldwork', 'comment' => 'Move fieldwork to June.',
        ]),
        (new ProposalFileAnnotation)->forceFill([
            'id' => 12, 'page_number' => 3, 'editor_target' => null, 'comment' => 'Check the remaining schedule.',
        ]),
    ]));
});

test('requested documents keep the editor between the paper reference and collapsible feedback panel', function () {
    $html = view('components.proposal-revision-form', [
        'topic' => $this->topic, 'pendingFileRevisions' => collect([$this->revision]),
        'stagedRevisionFiles' => collect(), 'displayProjectCost' => 3000,
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $dialog = '//dialog[@data-revision-dialog]';

    expect($xpath->query($dialog)->length)->toBe(1)
        ->and($xpath->query($dialog.'//section[contains(@class,"revision-feedback")]//iframe[@data-revision-pdf-frame]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-pdf-loading][@role="status"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//section[contains(@class,"revision-feedback")]/following-sibling::section[contains(@class,"revision-editor-panel")]//iframe[@data-revision-editor-frame]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-editor-loading][@role="status"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//iframe[@data-revision-editor-frame][@src]')->length)->toBe(0)
        ->and($xpath->query($dialog.'//iframe[@data-revision-editor-frame]')->item(0)->getAttribute('data-revision-editor-src'))
        ->toBe(route('faculty.proposal-drafts.revision', ['topic' => $this->topic, 'document_type' => 'work_plan', 'revision_embed' => 1]))
        ->and($xpath->query($dialog.'//select[@data-revision-comment]/option[@data-annotation-id="11"][contains(@data-pdf-url,"revision_embed=1")]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-comment-body="12"][@hidden]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-modification-status="11"][@data-modified="false"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-modification-status="11"][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//article//*[@data-revision-document-state][@hidden][@data-modified="false"][@data-addressed="false"]')->length)->toBe(1)
        ->and($xpath->query('//article[@data-revision-document="work_plan"]//*[@data-revision-resolved-cue][@hidden]/following-sibling::button[@data-revision-open]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="work_plan"]')->length)->toBe(0)
        ->and($xpath->query('//article[@data-revision-document="work_plan"][@x-data]')->length)->toBe(0)
        ->and($xpath->query('//form//button[@type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//form//*[@data-revision-submit-overlay][@role="status"][@aria-hidden="true"][contains(concat(" ", normalize-space(@class), " "), " hidden ")]')->length)->toBe(1)
        ->and($xpath->query('//form//*[@data-revision-submit-overlay][contains(concat(" ", normalize-space(@class), " "), " flex ")]')->length)->toBe(0)
        ->and($xpath->query('//form//*[@data-revision-submit-overlay]//*[@data-revision-submit-status]')->length)->toBe(1)
        ->and($xpath->query('//form[@novalidate]')->length)->toBe(1)
        ->and($xpath->query('//form//button[@data-revision-submit-button]//*[@data-revision-submit-button-spinner][@hidden]')->length)->toBe(1)
        ->and($html)->toContain('disabled:bg-slate-300', 'disabled:opacity-100', 'dark:disabled:bg-slate-700')
        ->and($xpath->query($dialog.'//*[@data-revision-dialog-submit-error][@role="alert"][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-other-revision-files]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="expense_breakdown"]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="gad_checklist"]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="curricula_vitae[]"]')->length)->toBe(0)
        ->and($xpath->query($dialog.'//button[@type="submit"]')->length)->toBe(0)
        ->and($html)->toContain('Move fieldwork to June.', 'May fieldwork', 'data-revision-open', 'Revise paper', 'Revision action recorded')
        ->and($html)->toContain('2. Revise and respond', 'Open a paper to edit it and reply to its reviewer comments in the same workspace.', 'Keep submitted paper', 'Saving your edits and generating the requested PDFs…')
        ->and($html)->not->toContain('Open for review', 'Changes detected', 'Upload a replacement instead')
        ->and($xpath->query($dialog.'//div[@data-revision-resolution-panel]//input[@name="revision_resolutions[work_plan][action]"][@value="no_change"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//div[@data-revision-resolution-panel]//div[@data-revision-no-change-details][@hidden]//textarea[@name="revision_resolutions[work_plan][explanation]"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//div[contains(@class,"revision-comment-strip")]//*[@data-revision-no-change]')->length)->toBe(0)
        ->and($xpath->query($dialog.'//section[contains(@class,"revision-feedback")]//*[@data-revision-resolution-panel]')->length)->toBe(0)
        ->and($xpath->query($dialog.'//section[contains(@class,"revision-editor-panel")]//div[@data-revision-resolution-panel]')->length)->toBe(0)
        ->and($xpath->query($dialog.'//section[contains(@class,"revision-editor-panel")]/following-sibling::aside[@data-revision-feedback-panel]//div[@data-revision-resolution-panel]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//button[@data-revision-feedback-toggle][@aria-controls="revision-feedback-work_plan"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-paper-open][@role="button"][@tabindex="0"]')->length)->toBe(2)
        ->and($xpath->query($dialog.'//*[@data-revision-reference-expand]')->length)->toBe(0)
        ->and($html)->not->toContain('Focus editor', 'Focus this field', 'View highlighted PDF', 'Replace another file')
        ->and($html)->toContain('Why keep this paper?', '(required)', 'Submitted', 'Revised', 'Click the paper to enlarge it.', 'Reviewer comment')
        ->and($html)->not->toContain('View full paper')
        ->and($xpath->query($dialog.'//input[@data-revision-no-change and @type="radio"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//input[@data-revision-edit-paper and @type="radio"]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//section[contains(@class,"revision-feedback")]//*[@data-revision-preview-panel]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-resolution-resize]')->length)->toBe(0);
});

test('upload-only revisions retain their PDF and feedback beside one required file input', function () {
    $this->revision->document_type = 'gad_checklist';
    $this->revision->file->document_type = 'gad_checklist';
    $html = view('components.proposal-revision-document', [
        'topic' => $this->topic, 'documentType' => 'gad_checklist',
        'fileRevisions' => collect([$this->revision]), 'required' => true,
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//dialog//iframe[@data-revision-pdf-frame]')->length)->toBe(1)
        ->and($xpath->query('//dialog//input[@name="gad_checklist"][@required]')->length)->toBe(1)
        ->and($xpath->query('//article[contains(@x-data,"fileDropzone")]')->length)->toBe(1)
        ->and($xpath->query('//dialog//input[@name="revision_resolutions[gad_checklist][action]"]')->length)->toBe(2)
        ->and($xpath->query('//iframe[@data-revision-editor-frame]')->length)->toBe(0);
});

test('generated paper revisions use the saved form without requiring a research call or replacement upload', function () {
    $this->topic->research_call_id = null;
    $this->topic->setRelation('researchCall', null);

    $html = view('components.proposal-revision-document', [
        'topic' => $this->topic, 'documentType' => 'work_plan',
        'fileRevisions' => collect([$this->revision]), 'required' => true,
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//dialog//iframe[@data-revision-editor-frame]')->length)->toBe(1)
        ->and($xpath->query('//dialog//input[@name="work_plan"][@required]')->length)->toBe(0)
        ->and($xpath->query('//dialog//input[@type="file"]')->length)->toBe(0)
        ->and($html)->not->toContain('Upload a replacement instead');
});

test('feedback without highlighted annotations still opens its submitted PDF', function () {
    $this->revision->setRelation('annotations', collect());
    $html = view('components.proposal-revision-document', [
        'topic' => $this->topic, 'documentType' => 'work_plan',
        'fileRevisions' => collect([$this->revision]), 'required' => true,
    ])->render();

    expect($html)->toContain('Update the fieldwork schedule.', 'data-revision-pdf-frame', 'data-pdf-url=')
        ->not->toContain('View highlighted PDF', 'Focus editor');
});

test('a single reviewer comment prioritizes the response and shares its keep-paper explanation', function () {
    $this->revision->setRelation('annotations', $this->revision->annotations->take(1));
    $html = view('components.proposal-revision-document', [
        'topic' => $this->topic, 'documentType' => 'work_plan', 'fileRevisions' => collect([$this->revision]), 'required' => true,
        'commentResponseRows' => [['key' => 'annotation_11', 'response' => '', 'remarks' => '']],
    ])->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//article[@data-revision-single-reply]')->length)->toBe(1)
        ->and($xpath->query('//select[@data-revision-comment][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-revision-feedback]/following-sibling::*[@data-revision-resolution-panel]')->length)->toBe(1)
        ->and($xpath->query('//input[@data-revision-no-change-explanation][@type="hidden"]')->length)->toBe(1)
        ->and($xpath->query('//textarea[@data-revision-no-change-explanation]')->length)->toBe(0)
        ->and($xpath->query('//textarea[@data-revision-response-document]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//input[@data-location-document][@type="hidden"][@value="work_plan"]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//input[@data-comment-response-page][@type="hidden"]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//input[@data-comment-response-paragraph][@type="hidden"]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//button[@data-location-retry][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//*[@data-location-confirm][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//*[@data-location-status][@aria-live="polite"]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-comment-response-location]//select | //fieldset[@data-comment-response-location]//details | //fieldset[@data-comment-response-location]//input[@type="number"]')->length)->toBe(0)
        ->and($html)->toContain('revision-reviewer-comment', 'will be added automatically')
        ->and($html)->not->toContain('Why keep this paper?', 'Response details', 'Changed this passage', 'Explanation only', 'Find page and paragraph', 'Choose a revised paper');
});

test('embedded PDF uses the same annotation renderer without nested sidebars or focus controls', function () {
    $html = view('components.proposal-revision-pdf', [
        'configuration' => ['pdfUrl' => '/submitted.pdf', 'annotations' => [], 'canAnnotate' => false],
    ])->render();

    expect($html)->toContain('pdfAnnotationWorkspace', 'revision-pdf-pages', '"fitWidth":true', 'x-ref="viewer"')
        ->not->toContain('<aside', 'Expand paper', 'Exit focus');
});

test('revision page uses the form sections without a duplicate sidebar', function () {
    $view = file_get_contents(resource_path('views/faculty/topics/revision.blade.php'));

    expect($view)
        ->toContain('data-revision-summary', '<main class="min-w-0">', 'max-w-6xl')
        ->not->toContain('data-revision-step-panel', 'aria-label="Revision steps"', 'Respond to feedback', 'bg-slate-950 text-white');
});

test('revision introduction uses a light surface instead of an unconditional black background', function () {
    $html = view('components.proposal-revision-form', [
        'topic' => $this->topic, 'pendingFileRevisions' => collect([$this->revision]),
        'stagedRevisionFiles' => collect(), 'displayProjectCost' => 3000,
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $introClasses = $xpath->query('//*[@data-revision-intro]')->item(0)->getAttribute('class');

    expect($introClasses)
        ->toContain('bg-white', 'border-slate-200', 'rounded-2xl')
        ->not->toContain('bg-slate-950');
});

test('revision workspace presents the guided workflow in the order faculty completes revisions', function () {
    $page = file_get_contents(resource_path('views/faculty/topics/revision.blade.php'));
    $form = file_get_contents(resource_path('views/components/proposal-revision-form.blade.php'));

    expect($page)->toContain('Research proposal', 'Revision required', 'max-w-6xl')
        ->and($page)->not->toContain('data-revision-step-panel')
        ->and($form)->toContain('1. Comment Response paper', '2. Revise and respond', '3. Proposal details', '4. Final review and submission', 'data-revision-details-confirmed', 'data-revision-step-continue', 'data-comment-response-paper', '<x-proposal-revision-response')
        ->and($form)->not->toContain('Revised page and paragraph', '[remarks]', 'id="revision-responses"');
});

test('the first revision step shows each official comment response paper once without feedback cards or preview buttons', function () {
    Gate::before(fn (?User $user): bool => true);
    $this->topic->setRelation('reviews', collect([
        (new TopicReview)->forceFill(['id' => 42, 'decision' => 'revision_requested']),
    ]));
    $rows = [
        ['key' => 'annotation_11', 'location' => 'Work Plan', 'comment' => 'Move fieldwork to June.', 'form_source' => 'research_head'],
        ['key' => 'annotation_12', 'location' => 'Work Plan', 'comment' => 'Check the schedule.', 'form_source' => 'co_evaluator'],
    ];
    $html = view('components.proposal-revision-form', [
        'topic' => $this->topic, 'pendingFileRevisions' => collect([$this->revision]),
        'stagedRevisionFiles' => collect(), 'displayProjectCost' => 3000, 'commentResponseRows' => $rows,
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    foreach (['research_head', 'co_evaluator'] as $source) {
        $group = '//section[@data-revision-step="1"]//section[@data-comment-response-source="'.$source.'"]';
        expect($xpath->query($group.'//dialog[@data-comment-response-paper][@open][@role="region"]')->length)->toBe(1)
            ->and($xpath->query($group.'//*[@data-comment-response-paper-open][@tabindex="0"][@aria-haspopup="dialog"]')->length)->toBe(1)
            ->and($xpath->query($group.'//*[@data-comment-response-preview]')->length)->toBe(1);
        expect($xpath->query($group.'//*[@data-comment-response-preview]')->item(0)->getAttribute('data-comment-response-preview-url'))
            ->toBe(route('faculty.topics.comment-response-form.preview', ['topic' => $this->topic, 'source' => $source, 'review' => 42, 'embedded' => 1]));
    }
    expect($xpath->query('//*[@data-comment-response-preview]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-pdf-annotation-config]')->length)->toBe(0)
        ->and($xpath->query('//section[@data-revision-step="1"]//blockquote | //section[@data-revision-step="1"]//textarea | //template[@x-if="show"]')->length)->toBe(0)
        ->and($html)->not->toContain('Preview Comment Response Paper', 'data-revision-feedback-item', 'data-faculty-comment-response-preview-modal');
});

test('revision progress renders four accessible markers and three connectors', function () {
    $html = view('components.proposal-revision-form', [
        'topic' => $this->topic, 'pendingFileRevisions' => collect([$this->revision]),
        'stagedRevisionFiles' => collect(), 'displayProjectCost' => 3000,
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@data-revision-progress-navigation and @aria-label="Revision workflow"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-revision-progress-step]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-revision-progress-connector]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-revision-progress-step and @aria-current="step" and @data-state="current"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-revision-progress-description]')->length)->toBe(4);
});

test('each response appears once next to its paper feedback while general comments remain on the same step', function () {
    $rows = collect([
        ['key' => 'file_6', 'location' => 'Work Plan', 'comment' => 'Update the fieldwork schedule.', 'form_source' => 'research_head', 'response' => '', 'remarks' => ''],
        ['key' => 'annotation_11', 'location' => 'Work Plan · Page 2', 'comment' => 'Move fieldwork to June.', 'form_source' => 'research_head', 'response' => 'Moved fieldwork to June.', 'page' => 2, 'paragraph' => 1, 'remarks' => ''],
        ['key' => 'annotation_12', 'location' => 'Work Plan · Page 3', 'comment' => 'Check the remaining schedule.', 'form_source' => 'research_head', 'response' => '', 'remarks' => ''],
        ['key' => 'overall', 'location' => 'Overall proposal', 'comment' => 'Clarify the scope.', 'form_source' => 'research_head', 'response' => '', 'remarks' => ''],
    ]);
    $html = view('components.proposal-revision-form', [
        'topic' => $this->topic, 'pendingFileRevisions' => collect([$this->revision]),
        'stagedRevisionFiles' => collect(), 'displayProjectCost' => 3000, 'commentResponseRows' => $rows->all(),
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    foreach ($rows as $row) {
        expect($xpath->query('//textarea[@name="feedback_responses['.$row['key'].'][response]"]')->length)->toBe(1);
    }
    expect($xpath->query('//dialog//*[@data-revision-response-key]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-revision-step="2"]//*[@data-revision-general-responses]//*[@data-revision-response-key="overall"]')->length)->toBe(1)
        ->and($xpath->query('//dialog//*[@data-revision-comment-body="11"]//textarea[contains(@name,"annotation_11")]')->length)->toBe(1)
        ->and($xpath->query('//dialog//*[@data-revision-comment-body="file-6"]//textarea[contains(@name,"file_6")]')->length)->toBe(1)
        ->and($xpath->query('//dialog//*[@data-comment-response-location]//input[@type="checkbox"][@data-comment-response-no-change][@hidden]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-revision-step]')->length)->toBe(4)
        ->and($html)->toContain('Moved fieldwork to June.')
        ->and($html)->not->toContain('Changed this passage', 'Explanation only');
});

test('automatic location fields retain the saved location and submit an explicit change action', function (bool $noChange) {
    $html = view('components.proposal-revision-response', [
        'documentType' => 'work_plan',
        'item' => ['key' => 'annotation_11', 'response' => 'Moved fieldwork to June.', 'no_change' => $noChange, 'page' => 4, 'paragraph' => 2],
    ])->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $field = '//fieldset[@data-comment-response-location]';

    expect($xpath->query($field.'//input[@type="hidden"][@name="feedback_responses[annotation_11][no_change]"][@value="0"]')->length)->toBe(1)
        ->and($xpath->query($field.'//input[@data-comment-response-no-change]')->item(0)->hasAttribute('checked'))->toBe($noChange)
        ->and($xpath->query($field.'//input[@data-comment-response-page]')->item(0)->getAttribute('value'))->toBe('4')
        ->and($xpath->query($field.'//input[@data-comment-response-paragraph]')->item(0)->getAttribute('value'))->toBe('2');

    foreach (['data-comment-response-page', 'data-comment-response-paragraph'] as $attribute) {
        expect($xpath->query($field.'//input[@'.$attribute.']')->item(0)->hasAttribute('disabled'))->toBe($noChange);
    }
})->with([false, true]);
