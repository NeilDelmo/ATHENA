import assert from 'node:assert/strict';
import test from 'node:test';
import { quarterlyReportForm } from '../../resources/js/quarterly-report-form.js';

test('quarterly activity IDs keep uploaded proof attached after another row is removed', () => {
    const state = quarterlyReportForm();
    state.addActivity('presentations');
    state.addActivity('presentations');
    const proofRowId = state.presentations[1].id;
    state.rowProofs.push({ section: 'presentations', row_id: proofRowId });
    state.presentations.splice(0, 1);

    assert.match(proofRowId, /^[a-f0-9-]{36}$/);
    assert.equal(state.rowProofs[0].row_id, state.presentations[0].id);
    state.addActivity('publications');
    assert.notEqual(state.publications[0].id, proofRowId);
});

test('quarterly form keeps saved manual rows and existing writing controls', () => {
    const seminars = [{ id: 'saved-row', event: 'Training' }];
    const state = quarterlyReportForm({ seminars });

    assert.deepEqual(state.seminars, seminars);
    assert.equal(typeof state.formatWriting, 'function');
    state.addActivity('seminars');
    assert.equal(state.seminars.length, 2);
    assert.equal(state.seminars[0].event, 'Training');
});

test('unmonitored faculty research can be entered without a linked Athena project', () => {
    const state = quarterlyReportForm();
    state.addActivity('manual_research');
    const research = state.manual_research[0];
    research.title = 'Externally funded faculty research';
    research.duration = '12 months';
    research.personnel = 'Faculty Member (Project Leader)';
    research.status = 'Ongoing';

    assert.equal(research.title, 'Externally funded faculty research');
    assert.equal('project_id' in research, false);
    assert.equal('date' in research, false);
});

test('IP assets can be added without creating project evidence fields', () => {
    const state = quarterlyReportForm();
    state.addActivity('ip_assets');

    assert.match(state.ip_assets[0].id, /^[a-f0-9-]{36}$/);
    assert.equal(state.ip_assets[0].title, '');
    assert.equal(state.ip_assets[0].type, '');
    assert.equal(state.ip_assets[0].inventors, '');
    assert.equal(state.ip_assets[0].date, '');
    assert.equal(state.ip_assets[0].remarks, '');
    assert.equal(state.ip_assets[0].evidence_url, '');
    assert.equal('project_id' in state.ip_assets[0], false);
});
