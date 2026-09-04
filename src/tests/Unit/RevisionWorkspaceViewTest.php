<?php

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\TopicReviewFileRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class);

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

test('requested documents open a single dialog with submitted PDF left and the existing editor right', function () {
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
        ->and($xpath->query($dialog.'//iframe[@data-revision-editor-frame]')->item(0)->getAttribute('src'))
        ->toBe(route('faculty.proposal-drafts.revision', ['topic' => $this->topic, 'document_type' => 'work_plan', 'revision_embed' => 1]))
        ->and($xpath->query($dialog.'//select[@data-revision-comment]/option[@data-annotation-id="11"][contains(@data-pdf-url,"revision_embed=1")]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-comment-body="12"][@hidden]')->length)->toBe(1)
        ->and($xpath->query($dialog.'//*[@data-revision-modification-status="11"][@data-modified="false"]')->length)->toBe(1)
        ->and($xpath->query('//article//*[@data-revision-document-state][@data-modified="false"][@data-addressed="false"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="work_plan"]')->length)->toBe(1)
        ->and($xpath->query('//form//button[@type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//form//*[@data-revision-submit-overlay][@role="status"][@aria-hidden="true"][contains(concat(" ", normalize-space(@class), " "), " hidden ")]')->length)->toBe(1)
        ->and($xpath->query('//form//*[@data-revision-submit-overlay][contains(concat(" ", normalize-space(@class), " "), " flex ")]')->length)->toBe(0)
        ->and($xpath->query('//form//*[@data-revision-submit-overlay]//*[@data-revision-submit-status]')->length)->toBe(1)
        ->and($xpath->query('//form[@novalidate]')->length)->toBe(1)
        ->and($xpath->query('//form//button[@data-revision-submit-button]//*[@data-revision-submit-button-spinner][@hidden]')->length)->toBe(1)
        ->and($html)->toContain('disabled:bg-gray-400', 'disabled:opacity-100', 'dark:disabled:bg-slate-700')
        ->and($xpath->query($dialog.'//*[@data-revision-dialog-submit-error][@role="alert"][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-other-revision-files]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="expense_breakdown"]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="gad_checklist"]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="curricula_vitae[]"]')->length)->toBe(0)
        ->and($xpath->query($dialog.'//button[@type="submit"]')->length)->toBe(0)
        ->and($html)->toContain('Move fieldwork to June.', 'May fieldwork', 'data-revision-open', 'Review required', 'Not reviewed yet')
        ->and($html)->toContain('Address every requested file', 'Files that were not requested carry forward automatically.', 'No file change needed', 'Saving your edits and generating the requested PDFs…')
        ->and($xpath->query('//input[@name="revision_resolutions[work_plan][action]"][@value="no_change"]')->length)->toBe(1)
        ->and($xpath->query('//textarea[@name="revision_resolutions[work_plan][explanation]"]')->length)->toBe(1)
        ->and($html)->not->toContain('Focus editor', 'Focus this field', 'View highlighted PDF', 'Replace another file')
        ->and($html)->toContain('It does not judge whether the feedback has been fully addressed.');
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
        ->and($xpath->query('//dialog//input[@name="revision_resolutions[gad_checklist][action]"]')->length)->toBe(1)
        ->and($xpath->query('//iframe[@data-revision-editor-frame]')->length)->toBe(0);
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

test('embedded PDF uses the same annotation renderer without nested sidebars or focus controls', function () {
    $html = view('components.proposal-revision-pdf', [
        'configuration' => ['pdfUrl' => '/submitted.pdf', 'annotations' => [], 'canAnnotate' => false],
    ])->render();

    expect($html)->toContain('pdfAnnotationWorkspace', 'revision-pdf-pages', '"fitWidth":true', 'x-ref="viewer"')
        ->not->toContain('<aside', 'Expand paper', 'Exit focus');
});
