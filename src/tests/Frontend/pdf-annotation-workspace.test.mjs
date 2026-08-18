import assert from 'node:assert/strict';
import test from 'node:test';
import { consolidateTextRectangles } from '../../resources/js/pdf-annotation-workspace.js';

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
