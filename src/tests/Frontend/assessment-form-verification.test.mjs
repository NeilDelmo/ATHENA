import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const template = readFileSync(new URL('../../resources/views/components/assessment-form-verification.blade.php', import.meta.url), 'utf8');
const expression = template.match(/x-data="([\s\S]*?)"/)[1];

test('changing the assessment clears the preview and manual confirmation for the previous file', () => {
    const state = runInNewContext(`(${expression})`, { URL });
    let reset;
    state.$watch = (name, callback) => { assert.equal(name, 'files'); reset = callback; };
    state.files = [new File(['%PDF-1.4'], 'gad.pdf', { type: 'application/pdf' })];
    state.init();
    state.preview();
    assert.match(state.previewUrl, /^blob:/);
    assert.equal(state.previewed, true);
    state.confirmed = true;
    state.files = [new File(['%PDF-1.4'], 'different.pdf', { type: 'application/pdf' })];
    reset();
    assert.equal(state.previewUrl, null);
    assert.equal(state.previewed, false);
    assert.equal(state.confirmed, false);
});

test('a selected DOCX must be opened for review before manual confirmation is enabled', () => {
    const state = runInNewContext(`(${expression})`, { URL });
    state.files = [new File(['docx'], 'screening.docx')];
    state.preview();
    assert.match(state.previewUrl, /^blob:/);
    assert.equal(state.previewed, false);
    state.destroy();
});
