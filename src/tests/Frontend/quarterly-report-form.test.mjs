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

test('large quarterly reports serialize every manual row and evidence caption without exhausting PHP input variables', () => {
    const formData = new FormData();
    const sections = ['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets'];
    for (const section of sections) {
        for (let index = 0; index < 100; index++) {
            formData.append(`${section}[${index}][id]`, `${section}-${index}`);
            formData.append(`${section}[${index}][${section === 'manual_research' || section === 'ip_assets' ? 'title' : 'event'}]`, `Lasting ${section} ${index}`);
            formData.append(`${section}[${index}][personnel]`, 'Ana\nBen');
        }
    }
    for (let index = 0; index < 1500; index++) formData.append(`saved_evidence_captions[token-${index}]`, `Caption ${index}`);
    formData.append('remove_evidence[]', 'remove-first');
    formData.append('remove_evidence[]', 'remove-last');
    formData.append('evidence_captions[0]', 'New evidence caption');
    for (const [name, value] of Object.entries({ _token: 'csrf', reporting_year: '2026', reporting_quarter: '3', other_accomplishments: 'Keep narrative', reviewed_by: '', intent: 'save' })) formData.append(name, value);

    quarterlyReportForm().serialize({ formData });

    const details = JSON.parse(formData.get('report_details_json'));
    for (const section of sections) {
        assert.equal(details[section].length, 100);
        assert.equal(details[section][99].id, `${section}-99`);
        assert.equal(details[section][99].personnel, 'Ana\nBen');
    }
    assert.equal(details.saved_evidence_captions['token-1499'], 'Caption 1499');
    assert.deepEqual(details.remove_evidence, ['remove-first', 'remove-last']);
    assert.deepEqual(details.evidence_captions, ['New evidence caption']);
    assert.equal(formData.get('other_accomplishments'), 'Keep narrative');
    assert.equal(formData.get('intent'), 'save');
    assert.equal(formData.get('form_complete'), '1');
    assert.equal(formData.get('evidence_file_count'), '0');
    assert.ok([...formData.keys()].length < 20);
});

test('quarterly serialization keeps uploads native and counts files so PHP cannot silently drop excess uploads', () => {
    const formData = new FormData();
    formData.append('row_evidence[presentations][row-id][]', new File(['proof'], 'proof.png', { type: 'image/png' }));
    formData.append('evidence_images[]', new File(['photo'], 'photo.png', { type: 'image/png' }));
    formData.append('evidence_images[]', new File([], '', { type: 'application/octet-stream' }));

    quarterlyReportForm().serialize({ formData });

    assert.equal(formData.get('evidence_file_count'), '2');
    assert.equal(formData.get('row_evidence[presentations][row-id][]').name, 'proof.png');
    assert.equal(formData.getAll('evidence_images[]').length, 2);
    const details = JSON.parse(formData.get('report_details_json'));
    for (const section of ['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets', 'remove_evidence', 'evidence_captions']) assert.deepEqual(details[section], []);
    assert.deepEqual(details.saved_evidence_captions, {});
});
