import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('fileDropzone',");
const source = app.slice(start, app.indexOf('Alpine.data(', start + 1));

test('upload initialization waits until Alpine registers the file input inside the revision dialog', () => {
    let factory;
    runInNewContext(source, { Alpine: { data: (_, callback) => { factory = callback; } } });
    const state = factory({ accept: '.pdf', maxBytes: 26214400 });
    const ticks = [];
    state.$refs = {};
    state.$nextTick = callback => ticks.push(callback);
    assert.doesNotThrow(() => state.init());
    const replacement = { name: 'revised.pdf', size: 1024 };
    state.$refs.input = { files: [replacement] };
    ticks.forEach(callback => callback());
    assert.equal(state.files.length, 1);
    assert.equal(state.files[0], replacement);
});
