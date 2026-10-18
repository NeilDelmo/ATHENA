import assert from 'node:assert/strict';
import test from 'node:test';
import { rmsQuarterlyReportForm } from '../../resources/js/rms-quarterly-report-form.js';

test('section folders open only known categories and keep all report fields in the form', () => {
    const state = rmsQuarterlyReportForm();
    assert.equal(state.section, null);
    state.openSection('publications');
    assert.equal(state.section, 'publications');
    state.openSection('unknown');
    assert.equal(state.section, 'publications');
    state.openSection(null);
    assert.equal(state.section, null);
});

test('all college rows submit as one JSON value without exhausting PHP input variables', () => {
    const fields = ['presentation_title', 'presenter', 'project_title', 'authors', 'source_of_fund', 'project_status', 'sdgs', 'support_requested', 'event_title', 'theme', 'date', 'venue', 'classification', 'organizer', 'website', 'evidence_url'];
    const elements = Array.from({ length: 80 }, (_, row) => fields.map(field => ({
        name: `row_details[presentation-s1-${row}][${field}]`,
        value: field === 'authors' ? 'Ana Researcher\nBen Researcher' : `${field} ${row}`,
        disabled: false,
    }))).flat();
    const formData = new FormData();
    for (const input of elements) formData.append(input.name, input.value);
    for (const [name, value] of Object.entries({ _token: 'csrf', campus: 'ARASOF-Nasugbu', intent: 'download', form_complete: '1' })) formData.append(name, value);

    rmsQuarterlyReportForm().serialize({ currentTarget: { elements }, formData });

    assert.equal([...formData.keys()].length, 5);
    const rows = JSON.parse(formData.get('row_details_json'));
    assert.equal(Object.keys(rows).length, 80);
    assert.equal(rows['presentation-s1-79'].authors, 'Ana Researcher\nBen Researcher');
    assert.equal(rows['presentation-s1-79'].evidence_url, 'evidence_url 79');
    assert.equal(formData.get('intent'), 'download');
    assert.equal(formData.get('_token'), 'csrf');
    assert.equal(formData.get('form_complete'), '1');
    assert.equal(elements[0].name, 'row_details[presentation-s1-0][presentation_title]');
    assert.equal(elements[0].disabled, false);
});

test('empty rows and repeated downloads leave the editable form intact', () => {
    const state = rmsQuarterlyReportForm();
    const input = { name: 'row_details[ip-s1-1][remarks]', value: '', disabled: false };
    const formData = new FormData();
    state.serialize({ currentTarget: { elements: [input] }, formData });
    assert.deepEqual(JSON.parse(formData.get('row_details_json')), { 'ip-s1-1': { remarks: '' } });
    input.value = 'Revised after download';
    state.serialize({ currentTarget: { elements: [input] }, formData });
    assert.equal(JSON.parse(formData.get('row_details_json'))['ip-s1-1'].remarks, 'Revised after download');
    state.serialize({ currentTarget: { elements: [] }, formData });
    assert.deepEqual(JSON.parse(formData.get('row_details_json')), {});
});
