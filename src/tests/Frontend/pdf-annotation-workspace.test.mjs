import assert from 'node:assert/strict';
import test from 'node:test';
import registerPdfAnnotationWorkspace, { consolidateTextRectangles, pdfScaleToFit } from '../../resources/js/pdf-annotation-workspace.js';

test('duplicate PDF text-layer rectangles keep the tighter highlight', () => {
    const rectangles = consolidateTextRectangles([
        { x: 0.1, y: 0.2, width: 0.45, height: 0.04 },
        { x: 0.1, y: 0.2, width: 0.18, height: 0.04 },
    ]);

    assert.deepEqual(rectangles, [
        { x: 0.1, y: 0.2, width: 0.18, height: 0.04 },
    ]);
});

test('adjacent fragments on the same line become one precise highlight', () => {
    const rectangles = consolidateTextRectangles([
        { x: 0.1, y: 0.2, width: 0.08, height: 0.03 },
        { x: 0.185, y: 0.201, width: 0.1, height: 0.029 },
    ]);

    assert.equal(rectangles.length, 1);
    assert.equal(rectangles[0].x, 0.1);
    assert.ok(Math.abs(rectangles[0].width - 0.185) < Number.EPSILON * 2);
});

test('fragments on separate lines stay separate', () => {
    const rectangles = consolidateTextRectangles([
        { x: 0.1, y: 0.2, width: 0.2, height: 0.03 },
        { x: 0.1, y: 0.25, width: 0.2, height: 0.03 },
    ]);

    assert.equal(rectangles.length, 2);
});


test('portrait and landscape PDF pages fit the available pane width at desktop and smaller sizes', () => {
    for (const pageWidth of [595, 842, 1224]) {
        for (const available of [352, 608, 928]) {
            assert.ok(Math.abs(pageWidth * pdfScaleToFit(pageWidth, available) - available) < 0.001);
        }
    }
});

test('PDF highlight selection notifies the editor while parent-driven selection avoids feedback loops', (t) => {
    const original = globalThis.window;
    t.after(() => { globalThis.window = original; });
    const notifications = [];
    globalThis.window = { athenaRevisionPdf: { onSelect: (id) => notifications.push(id) } };
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory();
    state.config = { fitWidth: true };
    const annotation = { id: 11, pageNumber: 2 };
    state.annotations = [annotation];
    state.jumpToAnnotation(annotation, false);
    assert.equal(state.selectedAnnotationId, 11);
    assert.deepEqual(notifications, []);
    state.selectAnnotation(annotation);
    assert.deepEqual(notifications, [11]);
});

function annotationWorkspace() {
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory();
    state.canAnnotate = true;
    state.$el = { querySelectorAll: () => [] };
    state.$refs = {};
    state.$nextTick = (callback) => callback();
    state.selectAnnotation = (annotation) => { state.selectedAnnotationId = annotation.id; };
    state.config = { storeUrl: '/annotations', updateUrlTemplate: '/annotations/__ANNOTATION__', fileId: 1 };
    return state;
}

test('co-evaluator highlighting requires confirmation and changing the name relocks it', () => {
    const state = annotationWorkspace();
    state.switchReviewer('co_evaluator');
    const selection = { type: 'area', pageNumber: 1, rectangles: [] };
    state.coEvaluatorName = ' Dr. Santos ';
    state.openCommentComposer(selection);
    assert.equal(state.draftSelection, null);
    state.confirmReviewer();
    assert.equal(state.reviewerReady, true);
    assert.equal(state.confirmedCoEvaluatorName, 'Dr. Santos');
    state.openCommentComposer(selection);
    assert.equal(state.draftCoEvaluatorName, 'Dr. Santos');
    state.cancelDraft();
    state.editReviewerName();
    assert.equal(state.reviewerReady, false);
    state.coEvaluatorName = '   ';
    state.confirmReviewer();
    assert.equal(state.reviewerReady, false);
    state.switchReviewer('research_head');
    assert.equal(state.reviewerReady, true);
});

test('pin placement stays inside the page even at its edges', async () => {
    const { pinRectangle } = await import('../../resources/js/pdf-annotation-workspace.js');
    const rectangle = pinRectangle(1500, -10, { left: 100, top: 100, width: 600, height: 900 });
    assert.equal(rectangle.x, 0.999);
    assert.equal(rectangle.y, 0);
    assert.ok(rectangle.x + rectangle.width <= 1);
    assert.ok(rectangle.y + rectangle.height <= 1);
});

test('comment composer stays inside desktop and phone viewports', async () => {
    const { commentComposerPosition } = await import('../../resources/js/pdf-annotation-workspace.js');
    for (const viewport of [{ width: 1366, height: 768 }, { width: 390, height: 300 }]) {
        const position = commentComposerPosition({ left: 900, right: 1000, top: 700, bottom: 740 }, viewport, 400);
        assert.ok(position.left >= 12);
        assert.ok(position.top >= 12);
        assert.ok(position.left + position.width <= viewport.width - 12);
        assert.ok(position.top + Math.min(400, position.maxHeight) <= viewport.height);
    }
});

test('a marked location can be saved with a comment and no editor field', async (t) => {
    const state = annotationWorkspace();
    state.editorTargets = [{ value: 'activity-1' }];
    state.draftSelection = { type: 'pin', pageNumber: 1, rectangles: [{ x: 0.2, y: 0.3, width: 0.001, height: 0.001 }] };
    state.draftComment = 'Explain the timing.';
    t.mock.method(globalThis, 'fetch', async (_url, options) => {
        const body = JSON.parse(options.body);
        assert.equal(options.method, 'POST');
        assert.equal(body.editor_target, null);
        assert.equal(body.annotation_type, 'pin');
        return Response.json({ id: 1, pageNumber: 1, type: 'pin', comment: body.comment });
    });
    await state.saveAnnotation();
    assert.equal(state.annotations.length, 1);
    assert.equal(state.revisionCandidates[0].annotationCount, 1);
    assert.equal(state.draftSelection, null);
});

test('editing a draft replaces its comment without adding a revision candidate', async (t) => {
    const state = annotationWorkspace();
    state.annotations = [{ id: 4, type: 'area', pageNumber: 1, comment: 'Before' }];
    state.revisionCandidates = [{ fileId: 1, annotationCount: 1 }];
    state.editingAnnotationId = 4;
    state.draftSelection = { type: 'area', pageNumber: 1, rectangles: [] };
    state.draftComment = 'After';
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(url, '/annotations/4');
        assert.equal(options.method, 'PATCH');
        return Response.json({ id: 4, pageNumber: 1, comment: 'After' });
    });
    await state.saveAnnotation();
    assert.equal(state.annotations.length, 1);
    assert.equal(state.annotations[0].comment, 'After');
    assert.equal(state.revisionCandidates[0].annotationCount, 1);
    assert.equal(state.editingAnnotationId, null);
});

test('failed saves keep the comment draft and allow retry', async (t) => {
    const state = annotationWorkspace();
    state.draftSelection = { type: 'area', pageNumber: 1, rectangles: [] };
    state.draftComment = 'Keep this feedback.';
    t.mock.method(globalThis, 'fetch', async () => Response.json({ message: 'Please retry.' }, { status: 503 }));
    await state.saveAnnotation();
    assert.equal(state.draftComment, 'Keep this feedback.');
    assert.notEqual(state.draftSelection, null);
    assert.equal(state.annotations.length, 0);
    assert.equal(state.saving, false);
    assert.equal(state.saveError, 'Please retry.');
});

test('a new selection cannot overwrite an unfinished comment', () => {
    const state = annotationWorkspace();
    state.draftSelection = { type: 'text', pageNumber: 1 };
    state.draftComment = 'Unsaved feedback';
    state.openCommentComposer({ type: 'pin', pageNumber: 2 });
    assert.equal(state.draftSelection.type, 'text');
    assert.equal(state.draftComment, 'Unsaved feedback');
});


test('section matching uses the greatest overlap on the selected page', async () => {
    const { matchRevisionSection } = await import('../../resources/js/pdf-annotation-workspace.js');
    const sections = [
        { id: 'section-sdgs', pageNumber: 1, x: .1, y: .1, width: .8, height: .2 },
        { id: 'section-project-team', pageNumber: 1, x: .1, y: .3, width: .8, height: .4 },
        { id: 'section-proponent', pageNumber: 2, x: .1, y: .1, width: .8, height: .6 },
    ];
    const selection = { pageNumber: 1, rectangles: [{ x: .6, y: .25, width: .2, height: .2 }] };
    assert.equal(matchRevisionSection(sections, selection).id, 'section-project-team');
    assert.equal(matchRevisionSection(sections, { ...selection, pageNumber: 2 }).id, 'section-proponent');
    assert.equal(matchRevisionSection(sections, { ...selection, pageNumber: 3 }), null);
});

test('switching reviewer keeps an unfinished comment attached to its original source', () => {
    const state = annotationWorkspace();
    state.switchReviewer('co_evaluator');
    assert.equal(state.activeReviewer, 'co_evaluator');
    state.coEvaluatorName = 'Dr. Maria Santos';
    state.confirmReviewer();
    state.openCommentComposer({type:'area', pageNumber:1, rectangles:[]});
    state.switchReviewer('research_head');
    assert.equal(state.activeReviewer, 'co_evaluator');
    assert.equal(state.draftFeedbackSource, 'co_evaluator');
    assert.equal(state.draftCoEvaluatorName, 'Dr. Maria Santos');
    state.cancelDraft();
    state.switchReviewer('research_head');
    assert.equal(state.activeReviewer, 'research_head');
});

test('co evaluator save transmits attribution and an unnamed evaluator cannot be saved', async (t) => {
    const state = annotationWorkspace();
    state.draftSelection = {type:'area',pageNumber:1,rectangles:[]};
    state.draftComment = 'Explain the sampling plan.';
    state.draftFeedbackSource = 'co_evaluator';
    let requests = 0;
    t.mock.method(globalThis, 'fetch', async (_url, options) => {
        requests++;
        const body = JSON.parse(options.body);
        assert.equal(body.feedback_source,'co_evaluator');
        assert.equal(body.co_evaluator_name,'Dr. Santos');
        return Response.json({id:1,pageNumber:1,feedbackSource:'co_evaluator',coEvaluatorName:'Dr. Santos'});
    });
    await state.saveAnnotation();
    assert.equal(requests,0);
    assert.match(state.saveError,/name/);
    state.draftCoEvaluatorName = 'Dr. Santos';
    await state.saveAnnotation();
    assert.equal(requests,1);
    assert.equal(state.coEvaluatorName,'Dr. Santos');
});

test('each profile counts only its feedback while legacy comments remain Research Head feedback', () => {
    const state = annotationWorkspace();
    state.annotations = [{id:1}, {id:2,feedbackSource:'research_head'}, {id:3,feedbackSource:'co_evaluator'}];
    assert.equal(state.reviewerCommentCount,2);
    state.switchReviewer('co_evaluator');
    assert.equal(state.reviewerCommentCount,1);
    assert.equal(state.reviewerInitials('Maria Santos'),'MS');
});
