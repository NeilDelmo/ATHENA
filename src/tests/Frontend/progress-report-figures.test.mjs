import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(app.indexOf("Alpine.data('narrativeProgressReportForm',"), app.indexOf("Alpine.data('noticeToProceedForm',"));
const previewSource = app.slice(app.indexOf('const documentPreviewForm ='), app.indexOf("Alpine.data('monitoringToolForm',"));
const paperPreviewSource = readFileSync(new URL('../../resources/js/proposal-preview-workspace.js', import.meta.url), 'utf8').replaceAll('export ', '')
    + readFileSync(new URL('../../resources/js/proposal-paper-workspace.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
function createState(config = {}) {
    const revoked = [];
    let factory;
    runInNewContext(paperPreviewSource + previewSource + source, {
        Alpine: { data: (name, callback) => { factory = callback; } },
        URL: { createObjectURL: () => 'blob:figure', revokeObjectURL: value => revoked.push(value) },
        window: { clearTimeout() {} },
        FormData: class extends FormData {
            constructor(entries) {
                super();
                for (const [name, value] of entries) this.append(name, value);
            }
        },
    });
    const state = factory(config);
    state.$nextTick = callback => callback();
    state.triggerNarrativeDraftAutoSave = () => {};
    return { state, revoked };
}

test('repeatable figures exceed the old ten-slot limit and preserve stable keys when moved or removed', () => {
    const { state } = createState();
    for (let index = 0; index < 15; index++) state.addFigure();
    assert.equal(state.figureRows.length, 15);
    const first = state.figureRows[0];
    first.caption = 'Context diagram';
    state.moveFigure(0, 1);
    assert.equal(state.figureRows[1], first);
    state.removeFigure(0);
    assert.equal(state.figureRows[0], first);
    assert.equal(first.caption, 'Context diagram');
    assert.equal(new Set(state.figureRows.map(row => row.id)).size, 14);
});

test('removing a selected figure revokes its image preview and keeps other captions', () => {
    const { state, revoked } = createState({ initialFigures: [{ caption: 'First' }, { caption: 'Second' }] });
    state.selectFigureFile(state.figureRows[0], { target: { files: [{}] } });
    assert.equal(state.figureRows[0].previewUrl, 'blob:figure');
    state.removeFigure(0);
    assert.deepEqual(revoked, ['blob:figure']);
    assert.equal(state.figureRows[0].caption, 'Second');
    assert.equal(state.figureRows[0].section, 'results_discussion');
});

test('accomplishment rows do not truncate approved objectives and retain at least one row', () => {
    const { state } = createState({ initialAccomplishments: Array.from({ length: 9 }, (_, index) => ({ objective: 'Objective ' + index })) });
    assert.equal(state.accomplishmentRows.length, 9);
    state.addAccomplishment();
    assert.equal(state.accomplishmentRows.length, 10);
    for (let index = 0; index < 12; index++) state.removeAccomplishment(0);
    assert.equal(state.accomplishmentRows.length, 1);
});

test('changing figure order invalidates the rendered preview before saving the draft', () => {
    const { state, revoked } = createState({ initialFigures: [{ caption: 'First' }, { caption: 'Second' }] });
    let saves = 0;
    state.triggerNarrativeDraftAutoSave = () => saves++;
    state.previewHtml = '<p>Old report</p>';
    state.previewReady = true;
    state.previewObjectUrls = ['blob:old-report'];
    state.moveFigure(0, 1);
    assert.equal(state.previewHtml, '<p>Old report</p>');
    assert.equal(state.previewReady, true);
    assert.equal(state.previewStale, true);
    assert.deepEqual(revoked, []);
    assert.equal(saves, 1);
});

test('printing uses the current report preview and waits for edits or an in-progress refresh', () => {
    const { state } = createState();
    let printed = 0;
    state.$refs = { previewFrame: { contentWindow: { focus() {}, print() { printed++; } } } };
    state.previewReady = true;
    state.printPreview();
    assert.equal(printed, 1);
    state.previewStale = true;
    state.printPreview();
    state.previewStale = false;
    state.previewLoading = true;
    state.printPreview();
    state.previewLoading = false;
    state.previewReady = false;
    state.printPreview();
    assert.equal(printed, 1);
});

test('draft autosave retains figure captions and placement without sending selected image files', () => {
    const { state } = createState();
    const form = [
        ['figures[12][image]', 'selected-image'],
        ['photo_1', 'legacy-image'],
        ['figures[12][caption]', 'Context diagram'],
        ['figures[12][section]', 'methodology'],
        ['figures[12][after_paragraph]', '2'],
    ];
    const data = state.narrativeDraftFormData(form);
    assert.equal(data.has('figures[12][image]'), false);
    assert.equal(data.has('photo_1'), false);
    assert.equal(data.get('figures[12][caption]'), 'Context diagram');
    assert.equal(data.get('figures[12][after_paragraph]'), '2');
});
