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
